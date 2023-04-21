<?php

if (!defined("_PS_VERSION_")) {
    exit;
}

/**
 * @noinspection PhpUnused
 */
function upgrade_module_1_1_2(Vg_postnord $module): bool
{
    // convert additional_service_codes from JSON string to array in carrier config
    $carrier_config = $module->getCarrierConfigurations();
    foreach ($carrier_config as &$config) {
        if (is_string($config["additional_service_codes"])) {
            $asc = json_decode($config["additional_service_codes"]) ?? [];
            $config["additional_service_codes"] = $asc;
        }
    }

    return Configuration::updateValue('VG_POSTNORD_CARRIER_SETTINGS', json_encode($carrier_config));
}
