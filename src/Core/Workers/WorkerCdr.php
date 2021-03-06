<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */

namespace MikoPBX\Core\Workers;

require_once 'Globals.php';

use MikoPBX\Common\Models\{CallDetailRecordsTmp, Extensions, Users};
use MikoPBX\Core\System\{BeanstalkClient, Processes, Util};
use Throwable;

/**
 * Class WorkerCdr
 * Обработка записей CDR. Заполение длительности звонков.
 */
class WorkerCdr extends WorkerBase
{

    public const SELECT_CDR_TUBE = 'select_cdr_tube';
    public const UPDATE_CDR_TUBE = 'update_cdr_tube';

    private BeanstalkClient $client_queue;
    private array $internal_numbers  = [];
    private array $no_answered_calls = [];


    /**
     * Entry point
     *
     * @param $argv
     *
     */
    public function start($argv): void
    {
        $filter = [
            'work_completed<>1 AND endtime<>""',
            'columns'=> 'start,answer,src_num,dst_num,dst_chan,endtime,linkedid,recordingfile,dialstatus,UNIQUEID'
        ];

        $this->client_queue = new BeanstalkClient(self::SELECT_CDR_TUBE);
        $this->client_queue->subscribe($this->makePingTubeName(self::class), [$this, 'pingCallBack']);

        $this->initSettings();

        while ($this->needRestart === false) {
            $result = CallDetailRecordsTmp::find($filter)->toArray();
            if (!empty($result)) {
                $this->updateCdr($result);
            }
            $this->client_queue->wait(5); // instead of sleep
        }
    }

    /**
     * Fills settings
     */
    private function initSettings(): void
    {
        $this->internal_numbers  = [];
        $this->no_answered_calls = [];

        $usersClass = Users::class;
        $parameters = [
            'columns'=>[
                'email'=>'email',
                'language'=>'language',
                'number'=>'Extensions.number'
            ],
            'joins'      => [
                'Extensions' => [
                    0 => Extensions::class,
                    1 => "Extensions.userid={$usersClass}.id",
                    2 => 'Extensions',
                    3 => 'INNER',
                ],
            ],
            'cache' => [
                'key'=>'Users-WorkerCdr',
                'lifetime' => 3600,
            ]
        ];

        $results   = Users::find($parameters);
        foreach ($results as $record) {
            if (empty($record->email)) {
                continue;
            }
            $this->internal_numbers[$record->number] = [
                'email'    => $record->email,
                'language' => $record->language,
            ];
        }
    }

    /**
     * Обработчик результата запроса.
     * @param $result
     */
    private function updateCdr($result): void
    {
        $this->initSettings();
        $arr_update_cdr = [];
        // Получаем идентификаторы активных каналов.
        $channels_id = $this->getActiveIdChannels();
        foreach ($result as $row) {
            if (array_key_exists($row['linkedid'], $channels_id)) {
                // Цепочка вызовов еще не завершена.
                continue;
            }

            $start      = strtotime($row['start']);
            $answer     = strtotime($row['answer']);
            $end        = strtotime($row['endtime']);
            $dialstatus = trim($row['dialstatus']);

            $duration = max(($end - $start), 0);
            $billsec  = ($end && $answer) ? ($end - $answer) : 0;

            [$disposition, $row] = $this->setDisposition($billsec, $dialstatus, $row);
            [$row, $billsec]     = $this->checkBillsecMakeRecFile($billsec, $row);

            $data = [
                'work_completed' => 1,
                'duration'       => $duration,
                'billsec'        => $billsec,
                'disposition'    => $disposition,
                'UNIQUEID'       => $row['UNIQUEID'],
                'recordingfile'  => ($disposition === 'ANSWERED') ? $row['recordingfile'] : '',
                'tmp_linked_id'  => $row['linkedid'],
            ];

            $arr_update_cdr[] = $data;
            $this->checkNoAnswerCall(array_merge($row, $data));
        }

        $this->setStatusAndPublish($arr_update_cdr);
        $this->notifyByEmail();
    }

    /**
     * Функция позволяет получить активные каналы.
     * Возвращает ассоциативный массив. Ключ - Linkedid, значение - массив каналов.
     *
     * @return array
     */
    private function getActiveIdChannels(): array
    {
        $am           = Util::getAstManager('off');
        return $am->GetChannels(true);
    }

    /**
     * Анализируем не отвеченные вызовы. Наполняем временный массив для дальнейшей обработки.
     *
     * @param $row
     */
    private function checkNoAnswerCall($row): void
    {
        if ($row['disposition'] === 'ANSWERED') {
            $this->no_answered_calls[$row['linkedid']]['NOANSWER'] = false;
            return;
        }
        if ( ! array_key_exists($row['dst_num'], $this->internal_numbers)) {
            // dst_num - не является номером сотрудника. Это исходящий.
            return;
        }
        $is_internal = false;
        if ((array_key_exists($row['src_num'], $this->internal_numbers))) {
            // Это внутренний вызов.
            $is_internal = true;
        }

        $this->no_answered_calls[$row['linkedid']][] = [
            'from_number' => $row['src_num'],
            'to_number'   => $row['dst_num'],
            'start'       => $row['start'],
            'answer'      => $row['answer'],
            'endtime'     => $row['endtime'],
            'email'       => $this->internal_numbers[$row['dst_num']]['email'],
            'language'    => $this->internal_numbers[$row['dst_num']]['language'],
            'is_internal' => $is_internal,
            'duration'    => $row['duration'],
        ];
    }


    /**
     * Постановка задачи в очередь на оповещение по email.
     */
    private function notifyByEmail(): void
    {
        foreach ($this->no_answered_calls as $call) {
            $this->client_queue->publish(json_encode($call), WorkerNotifyByEmail::class);
        }
        $this->no_answered_calls = [];
    }

    /**
     * @param array $arr_update_cdr
     */
    private function setStatusAndPublish(array $arr_update_cdr): void{
        foreach ($arr_update_cdr as $data) {
            $linkedid = $data['tmp_linked_id'];
            $data['GLOBAL_STATUS'] = $data['disposition'];
            if (isset($this->no_answered_calls[$linkedid]['NOANSWER']) && $this->no_answered_calls[$linkedid]['NOANSWER'] === false) {
                $data['GLOBAL_STATUS'] = 'ANSWERED';
                // Это отвеченный вызов (на очередь). Удаляем из списка.
                unset($this->no_answered_calls[$linkedid]);
            }
            unset($data['tmp_linked_id']);
            $this->client_queue->publish(json_encode($data), self::UPDATE_CDR_TUBE);
        }
    }

    /**
     * @param int $billsec
     * @param     $row
     * @return array
     */
    private function checkBillsecMakeRecFile(int $billsec, $row): array{
        if ($billsec <= 0) {
            $row['answer'] = '';
            $billsec = 0;

            if (!empty($row['recordingfile'])) {
                // Удаляем файлы
                $p_info = pathinfo($row['recordingfile']);
                $fileName = $p_info['dirname'] . '/' . $p_info['filename'];
                $file_list = [$fileName . '.mp3', $fileName . '.wav', $fileName . '_in.wav', $fileName . '_out.wav',];
                foreach ($file_list as $file) {
                    if (!file_exists($file) || is_dir($file)) {
                        continue;
                    }
                    Processes::mwExec("rm -rf '{$file}'");
                }
            }
        } elseif (trim($row['recordingfile']) !== '') {
            // Если каналов не существует с ID, то можно удалить временные файлы.
            $p_info = pathinfo($row['recordingfile']);
            // Запускаем процесс конвертации в mp3
            $wav2mp3Path = Util::which('wav2mp3.sh');
            $nicePath = Util::which('nice');
            Processes::mwExecBg("{$nicePath} -n 19 {$wav2mp3Path} '{$p_info['dirname']}/{$p_info['filename']}'");
            // В последствии конвертации (успешной) исходные файлы будут удалены.
        }
        return array($row, $billsec);
    }

    /**
     * @param int    $billsec
     * @param string $dialstatus
     * @param        $row
     * @return array
     */
    private function setDisposition(int $billsec, string $dialstatus, $row): array{
        $disposition = 'NOANSWER';
        if ($billsec > 0) {
            $disposition = 'ANSWERED';
        } elseif ('' !== $dialstatus) {
            $disposition = ($dialstatus === 'ANSWERED') ? $disposition : $dialstatus;
        }

        if ($disposition !== 'ANSWERED') {
            if (file_exists($row['recordingfile']) && !is_dir($row['recordingfile'])) {
                Processes::mwExec("rm -rf {$row['recordingfile']}");
            }
        } elseif (!empty($row['recordingfile']) &&
            !file_exists(Util::trimExtensionForFile($row['recordingfile']) . '.wav') &&
            !file_exists($row['recordingfile'])) {
            /** @var CallDetailRecordsTmp $rec_data */
            $rec_data = CallDetailRecordsTmp::findFirst("linkedid='{$row['linkedid']}' AND dst_chan='{$row['dst_chan']}'");
            if ($rec_data !== null) {
                $row['recordingfile'] = $rec_data->recordingfile;
            }
        }
        return array($disposition, $row);
    }

}

// Start worker process
$workerClassname = WorkerCdr::class;
if (isset($argv) && count($argv) > 1 && $argv[1] === 'start') {
    cli_set_process_title($workerClassname);
    try {
        $worker = new $workerClassname();
        $worker->start($argv);
    } catch (Throwable $e) {
        global $errorLogger;
        $errorLogger->captureException($e);
        Util::sysLogMsg("{$workerClassname}_EXCEPTION", $e->getMessage());
    }
}