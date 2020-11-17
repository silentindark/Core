<?php
/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2020
 *
 */

namespace MikoPBX\Core\System;


use MikoPBX\Core\Workers\Cron\WorkerSafeScriptsCore;
use Phalcon\Di;

class Processes
{

    /**
     * Kills process/daemon by name
     *
     * @param $procName
     *
     * @return int|null
     */
    public static function killByName($procName): ?int
    {
        $killallPath = Util::which('killall');

        return self::mwExec($killallPath . ' ' . escapeshellarg($procName));
    }

    /**
     * Executes command exec().
     *
     * @param $command
     * @param $outArr
     * @param $retVal
     *
     * @return int
     */
    public static function mwExec($command, &$outArr = null, &$retVal = null): int
    {
        $retVal = 0;
        $outArr   = [];
        $di     = Di::getDefault();

        if ($di !== null && $di->getShared('config')->path('core.debugMode')) {
            echo "mwExec(): $command\n";
        } else {
            exec("$command 2>&1", $outArr, $retVal);
        }

        return $retVal;
    }

    /**
     * Executes command exec() as background process.
     *
     * @param $command
     * @param $out_file
     * @param $sleep_time
     */
    public static function mwExecBg($command, $out_file = '/dev/null', $sleep_time = 0): void
    {
        $nohupPath = Util::which('nohup');
        $shPath    = Util::which('sh');
        $rmPath    = Util::which('rm');
        $sleepPath = Util::which('sleep');
        if ($sleep_time > 0) {
            $filename = '/tmp/' . time() . '_noop.sh';
            file_put_contents($filename, "{$sleepPath} {$sleep_time}; {$command}; {$rmPath} -rf {$filename}");
            $noop_command = "{$nohupPath} {$shPath} {$filename} > {$out_file} 2>&1 &";
        } else {
            $noop_command = "{$nohupPath} {$command} > {$out_file} 2>&1 &";
        }
        exec($noop_command);
    }

    /**
     * Executes command exec() as background process with an execution timeout.
     *
     * @param        $command
     * @param int    $timeout
     * @param string $logname
     */
    public static function mwExecBgWithTimeout($command, $timeout = 4, $logname = '/dev/null'): void
    {
        $di = Di::getDefault();

        if ($di !== null && $di->getShared('config')->path('core.debugMode')) {
            echo "mwExecBg(): $command\n";

            return;
        }
        $nohupPath   = Util::which('nohup');
        $timeoutPath = Util::which('timeout');
        exec("{$nohupPath} {$timeoutPath} -t {$timeout} {$command} > {$logname} 2>&1 &");
    }

    /**
     * Executes multiple commands.
     *
     * @param        $arr_cmds
     * @param array  $out
     * @param string $logname
     */
    public static function mwExecCommands($arr_cmds, &$out = [], $logname = ''): void
    {
        $out = [];
        foreach ($arr_cmds as $cmd) {
            $out[]   = "$cmd;";
            $out_cmd = [];
            self::mwExec($cmd, $out_cmd);
            $out = array_merge($out, $out_cmd);
        }

        if ($logname !== '') {
            $result = implode("\n", $out);
            file_put_contents("/tmp/{$logname}_commands.log", $result);
        }
    }


    /**
     * Restart all workers in separate process,
     * we use this method after module install or delete
     */
    public static function restartAllWorkers(): void
    {
        $workerSafeScriptsPath = Util::getFilePathByClassName(WorkerSafeScriptsCore::class);
        $phpPath               = Util::which('php');
        $WorkerSafeScripts     = "{$phpPath} -f {$workerSafeScriptsPath} restart > /dev/null 2> /dev/null";
        self::mwExecBg($WorkerSafeScripts, '/dev/null', 1);
    }


    /**
     * Process PHP workers
     *
     * @param string $className
     * @param string $param
     * @param string $action
     */
    public static function processPHPWorker(string $className, string $param = 'start', string $action='restart'): void
    {
        $workerPath = Util::getFilePathByClassName($className);
        if ( ! empty($workerPath)) {
            $command = "php -f {$workerPath}";
            $path_kill  = Util::which('kill');
            $path_nohup = Util::which('nohup');
            $WorkerPID = self::getPidOfProcess($className);
            switch ($action) {
                case 'restart':
                    // Firstly start new process
                    self::mwExec("{$path_nohup} {$command} {$param}  > /dev/null 2>&1 &");
                    // Then kill the old one
                    if ($WorkerPID !== '') {
                        self::mwExec("{$path_kill} SIGTERM {$WorkerPID}  > /dev/null 2>&1 &");
                    }
                    break;
                case 'stop':
                    if ($WorkerPID !== '') {
                        self::mwExec("{$path_kill} SIGTERM {$WorkerPID}  > /dev/null 2>&1 &");
                    }
                    break;
                case 'start':
                    if ($WorkerPID === '') {
                        self::mwExec("{$path_nohup} {$command} {$param}  > /dev/null 2>&1 &");
                    }
                    break;
                case 'multiStart':
                    self::mwExec("{$path_nohup} {$command} {$param}  > /dev/null 2>&1 &");
                    break;
                default:
            }
        }
    }

    /**
     * Manages a daemon/worker process
     * Returns process statuses by name of it
     *
     * @param $cmd
     * @param $param
     * @param $proc_name
     * @param $action
     * @param $out_file
     *
     * @return array | bool
     */
    public static function processWorker($cmd, $param, $proc_name, $action, $out_file = '/dev/null')
    {
        $path_kill  = Util::which('kill');
        $path_nohup = Util::which('nohup');

        $WorkerPID = self::getPidOfProcess($proc_name);

        switch ($action) {
            case 'status':
                $status = ($WorkerPID !== '') ? 'Started' : 'Stoped';
                return ['status' => $status, 'app' => $proc_name, 'PID' => $WorkerPID];
            case 'restart':
                if ($WorkerPID !== '') {
                    self::mwExec("{$path_kill} -9 {$WorkerPID}  > /dev/null 2>&1 &");
                }
                self::mwExec("{$path_nohup} {$cmd} {$param}  > {$out_file} 2>&1 &");
                break;
            case 'stop':
                if ($WorkerPID !== '') {
                    self::mwExec("{$path_kill} -9 {$WorkerPID}  > /dev/null 2>&1 &");
                }
                break;
            case 'start':
                if ($WorkerPID === '') {
                    self::mwExec("{$path_nohup} {$cmd} {$param}  > {$out_file} 2>&1 &");
                }
                break;
            default:
        }

        return true;
    }

    /**
     * Возвращает PID процесса по его имени.
     *
     * @param        $name
     * @param string $exclude
     *
     * @return string
     */
    public static function getPidOfProcess($name, $exclude = ''): string
    {
        $path_ps   = Util::which('ps');
        $path_grep = Util::which('grep');
        $path_awk  = Util::which('awk');

        $name       = addslashes($name);
        $filter_cmd = '';
        if ( ! empty($exclude)) {
            $filter_cmd = "| $path_grep -v " . escapeshellarg($exclude);
        }
        $out = [];
        self::mwExec(
            "{$path_ps} -A -o 'pid,args' {$filter_cmd} | {$path_grep} '{$name}' | {$path_grep} -v grep | {$path_awk} ' {print $1} '",
            $out
        );

        return trim(implode(' ', $out));
    }

}