<?php

$sql = [];

$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'vg_postnord_cart_data`;';
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'vg_postnord_booking`;';

return $sql;
