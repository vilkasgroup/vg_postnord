<?php

if (!defined("_PS_VERSION_")) {
    exit;
}

/**
 * @noinspection PhpUnused
 */
function upgrade_module_0_9_4(Vg_postnord $module): bool
{
    return
        Configuration::updateValue('VG_POSTNORD_LABEL_PAPER_SIZE', 'A5')
        && $module->registerHook('displayOrderDetail')
        && $module->registerHook('displayOrderConfirmation1')
        ;

}
