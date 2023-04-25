<?php

if (!defined("_PS_VERSION_")) {
    exit;
}

/**
 * @noinspection PhpUnused
 */
function upgrade_module_1_1_2(Vg_postnord $module): bool
{
    $success = true;

    // convert additional_service_codes from JSON string to array in carrier config
    $carrier_config = $module->getCarrierConfigurations();
    foreach ($carrier_config as &$config) {
        if (is_string($config["additional_service_codes"])) {
            $asc = json_decode($config["additional_service_codes"]) ?? [];
            $config["additional_service_codes"] = $asc;
        }
    }

    // move shop_party_id from address settings to basic settings for visibility
    $shop_address = json_decode(Configuration::get("VG_POSTNORD_SHOP_ADDRESS", true), true);
    if (array_key_exists("shop_party_id", $shop_address)) {
        $shop_party_id = $shop_address["shop_party_id"];
        unset($shop_address["shop_party_id"]);
        $success &= Configuration::updateValue("VG_POSTNORD_SHOP_ADDRESS", json_encode($shop_address));
        $success &= Configuration::updateValue("VG_POSTNORD_PARTY_ID", $shop_party_id);
    }

    return $success
        && Configuration::updateValue("VG_POSTNORD_CARRIER_SETTINGS", json_encode($carrier_config))
        && $module->unregisterHook("header")
        && $module->registerHook("displayHeader");
}
