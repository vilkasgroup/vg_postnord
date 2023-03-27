<?php
/**
 * 2022 Vilkas Group Oy
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License 3.0 (OSL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 *  @author    Vilkas Group Oy <techsupport@vilkas.fi>
 *  @copyright 2022 Vilkas Group Oy
 *  @license   https://opensource.org/licenses/OSL-3.0  Open Software License 3.0 (OSL-3.0)
 *  International Registered Trademark & Property of Vilkas Group Oy
 */

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
