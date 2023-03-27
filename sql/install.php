<?php

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'vg_postnord_cart_data` (
    `id_cart_data`        INT          NOT NULL AUTO_INCREMENT,
    `id_cart`             INT          NOT NULL,
    `id_order`            INT          DEFAULT NULL,
    `servicepointid`      VARCHAR(255) DEFAULT NULL,
    `service_point_data`  MEDIUMTEXT   DEFAULT NULL,
    PRIMARY KEY (`id_cart_data`),
    INDEX idx__id_cart (id_cart)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'vg_postnord_booking` (
    `id_booking`               INT          NOT NULL AUTO_INCREMENT,
    `id_order`                 INT          NOT NULL,
    `id_cart_data`             INT          DEFAULT NULL,
    `id_booking_external`      VARCHAR(255) DEFAULT NULL,
    `tracking_url`             VARCHAR(255) DEFAULT NULL,
    `label_data`               LONGTEXT     DEFAULT NULL,
    `return_label_data`        LONGTEXT     DEFAULT NULL,
    `servicepointid`           VARCHAR(255) DEFAULT NULL,
    `service_point_data`       MEDIUMTEXT   DEFAULT NULL,
    `additional_services`      VARCHAR(255) DEFAULT NULL,
    `id_label_external`        VARCHAR(255) DEFAULT NULL,
    `finalized`                DATETIME     DEFAULT NULL,
    `parcel_data`              TEXT         NOT NULL,
    `customs_declaration`      BOOLEAN      DEFAULT NULL,
    `customs_declaration_data` TEXT         DEFAULT NULL,
    `detailed_description`     TEXT         DEFAULT NULL,
    PRIMARY KEY (`id_booking`),
    INDEX idx__id_cart_data (id_cart_data)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

return $sql;
