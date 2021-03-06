<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 7 2020
 */

namespace MikoPBX\Core\System\Upgrade;

use MikoPBX\Common\Models\PbxExtensionModules;
use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Core\System\Configs\IptablesConf;
use MikoPBX\Core\System\MikoPBXConfig;
use MikoPBX\Core\System\Storage;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\PbxExtensionUtils;
use Phalcon\Di;

use function MikoPBX\Common\Config\appPath;

class UpdateSystemConfig extends Di\Injectable
{

    private MikoPBXConfig $mikoPBXConfig;

    /**
     * System constructor.
     */
    public function __construct()
    {
        $this->mikoPBXConfig = new MikoPBXConfig();
    }

    /**
     * Updates settings after every new release
     */
    public function updateConfigs(): bool
    {
        $this->deleteLostModules();
        // Clear all caches on any changed models
        PbxSettings::clearCache(PbxSettings::class, true);
        $previous_version = str_ireplace('-dev', '', $this->mikoPBXConfig->getGeneralSettings('PBXVersion'));
        $current_version  = str_ireplace('-dev', '', trim(file_get_contents('/etc/version')));
        if ($previous_version !== $current_version) {
            $upgradeClasses      = [];
            $upgradeClassesDir   = appPath('src/Core/System/Upgrade/Releases');
            $upgradeClassesFiles = glob($upgradeClassesDir . '/*.php', GLOB_NOSORT);
            foreach ($upgradeClassesFiles as $file) {
                $className        = pathinfo($file)['filename'];
                $moduleModelClass = "\\MikoPBX\\Core\\System\\Upgrade\\Releases\\{$className}";
                if (class_exists($moduleModelClass)) {
                    $upgradeClasses[$moduleModelClass::PBX_VERSION] = $moduleModelClass;
                }
            }
            uksort($upgradeClasses, [__CLASS__, "sortArrayByReleaseNumber"]);

            foreach ($upgradeClasses as $releaseNumber => $upgradeClass) {
                if (version_compare($previous_version, $releaseNumber, '<')) {
                    $processor = new $upgradeClass();
                    $processor->processUpdate();
                    Util::echoWithSyslog(' - UpdateConfigs: Upgrade system up to ' . $releaseNumber . ' ');
                    Util::echoGreenDone();
                }
            }

            $this->updateConfigEveryNewRelease();
            $this->mikoPBXConfig->setGeneralSettings('PBXVersion', trim(file_get_contents('/etc/version')));
        }

        return true;
    }

    /**
     * Deletes modules, not installed on the system
     */
    private function deleteLostModules(): void
    {
        /** @var \MikoPBX\Common\Models\PbxExtensionModules $modules */
        $modules = PbxExtensionModules::find();
        $modulesDir = $this->getDI()->getShared('config')->path('core.modulesDir');
        foreach ($modules as $module) {
            if ( ! is_dir("{$modulesDir}/{$module->uniqid}")) {
                $module->delete();
            }
        }
    }

    /**
     * Every new release routines
     */
    private function updateConfigEveryNewRelease(): void
    {
        PbxExtensionUtils::disableOldModules();
        Storage::clearSessionsFiles();
        IptablesConf::updateFirewallRules();
    }

    /**
     * Sorts array of upgrade classes by release numbers
     *
     * @param $a
     * @param $b
     *
     * @return int|bool
     */
    private function sortArrayByReleaseNumber($a, $b)
    {
        return version_compare($a, $b);
    }



}