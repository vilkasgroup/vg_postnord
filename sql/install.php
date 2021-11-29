<?php

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'vg_postnord` (
    `id_vg_postnord_carrier` int(11) NOT NULL AUTO_INCREMENT,
    -- prestashop carrier:
    `id_carrier_reference` int(10) DEFAULT NULL,
    -- service point types for fetching service points in FO. if left empty no service points will be asked
    -- a list of service types separated by comma 14,15,16:
    `service_point_types` text DEFAULT NULL,
    -- postnord service code, used when fetching the label:
    `postnord_servicecode` varchar(30) DEFAULT NULL,
    PRIMARY KEY  (`id_vg_postnord_carrier`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';


return $sql;
