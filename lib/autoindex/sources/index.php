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
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

header('Location: ../');
exit;
