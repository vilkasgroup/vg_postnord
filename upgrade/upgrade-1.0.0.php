<?php

if (!defined("_PS_VERSION_")) {
    exit;
}

/**
 * @noinspection PhpUnused
 */
function upgrade_module_1_0_0(Vg_postnord $module): bool
{
    return $module->registerHook('displayAdminEndContent');
}
