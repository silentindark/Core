<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 7 2020
 *
 */

namespace MikoPBX\PBXCoreREST\Lib;


use MikoPBX\Core\System\Network;
use MikoPBX\Core\System\System;
use MikoPBX\Core\System\Util;
use Phalcon\Di\Injectable;

class LogsManagementProcessor extends Injectable
{
    /**
     * Стартует запись логов.
     *
     * @param int $timeout
     *
     * @return \MikoPBX\PBXCoreREST\Lib\PBXApiResult
     */
    public static function startLog($timeout = 300): PBXApiResult
    {
        $res = new PBXApiResult();
        $res->processor = __METHOD__;
        self::stopLog();
        $dir_all_log = System::getLogDir();
        $findPath    = Util::which('find');
        Util::mwExec("{$findPath} {$dir_all_log}" . '/ -name *_start_all_log* | xargs rm -rf');
        // Получим каталог с логами.
        $dirlog = $dir_all_log . '/dir_start_all_log';
        Util::mwMkdir($dirlog);

        $pingPath = Util::which('ping');
        Util::mwExecBg("{$pingPath} 8.8.8.8 -w 2", "{$dirlog}/ping_8888.log");
        Util::mwExecBg("{$pingPath} ya.ru -w 2", "{$dirlog}/ping_8888.log");

        $opensslPath = Util::which('openssl');
        Util::mwExecBgWithTimeout(
            "{$opensslPath} s_client -connect lm.miko.ru:443 > {$dirlog}/openssl_lm_miko_ru.log",
            1
        );
        Util::mwExecBgWithTimeout(
            "{$opensslPath} s_client -connect lic.miko.ru:443 > {$dirlog}/openssl_lic_miko_ru.log",
            1
        );
        $routePath = Util::which('route');
        Util::mwExecBg("{$routePath} -n ", " {$dirlog}/rout_n.log");

        $asteriskPath = Util::which('asterisk');
        Util::mwExecBg("{$asteriskPath} -rx 'pjsip show registrations' ", " {$dirlog}/pjsip_show_registrations.log");
        Util::mwExecBg("{$asteriskPath} -rx 'pjsip show endpoints' ", " {$dirlog}/pjsip_show_endpoints.log");
        Util::mwExecBg("{$asteriskPath} -rx 'pjsip show contacts' ", " {$dirlog}/pjsip_show_contacts.log");

        $php_log = '/var/log/php_error.log';
        if (file_exists($php_log)) {
            $cpPath = Util::which('cp');
            Util::mwExec("{$cpPath} {$php_log} {$dirlog}");
        }

        $network     = new Network();
        $arr_eth     = $network->getInterfacesNames();
        $tcpdumpPath = Util::which('tcpdump');
        foreach ($arr_eth as $eth) {
            Util::mwExecBgWithTimeout(
                "{$tcpdumpPath} -i {$eth} -n -s 0 -vvv -w {$dirlog}/{$eth}.pcap",
                $timeout,
                "{$dirlog}/{$eth}_out.log"
            );
        }
        $res->success=true;
        return $res;
    }

    /**
     * Завершает запись логов.
     *
     * @return \MikoPBX\PBXCoreREST\Lib\PBXApiResult
     */
    public static function stopLog(): PBXApiResult
    {
        $res = new PBXApiResult();
        $res->processor = __METHOD__;
        $dir_all_log = System::getLogDir();

        Util::killByName('timeout');
        Util::killByName('tcpdump');

        $rmPath   = Util::which('rm');
        $findPath = Util::which('find');
        $za7Path  = Util::which('7za');
        $cpPath   = Util::which('cp');

        $dirlog = $dir_all_log . '/dir_start_all_log';
        Util::mwMkdir($dirlog);

        $log_dir = System::getLogDir();
        Util::mwExec("{$cpPath} -R {$log_dir} {$dirlog}");

        $result = $dir_all_log . '/arhive_start_all_log.zip';
        if (file_exists($result)) {
            Util::mwExec("{$rmPath} -rf {$result}");
        }
        // Пакуем логи.
        Util::mwExec("{$za7Path} a -tzip -mx0 -spf '{$result}' '{$dirlog}'");
        // Удаляем логи. Оставляем только архив.
        Util::mwExec("{$findPath} {$dir_all_log}" . '/ -name *_start_all_log | xargs rm -rf');

        if (file_exists($dirlog)) {
            Util::mwExec("{$findPath} {$dirlog}" . '/ -name license.key | xargs rm -rf');
        }
        // Удаляем каталог логов.
        Util::mwExecBg("{$rmPath} -rf {$dirlog}");

        $res->success=true;
        $res->data['filename'] = $result; // TODO::Переделать на download link
        return $res;
    }
}