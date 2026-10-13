<?php
/**
 * Product Extra Tabs
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sb_product_tab` (
    `id_sb_product_tab` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `internal_name` VARCHAR(255) NOT NULL DEFAULT \'\',
    `display_rule` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `product_ids` TEXT NULL,
    `category_ids` TEXT NULL,
    `manufacturer_ids` TEXT NULL,
    `include_product_ids` TEXT NULL,
    `exclude_product_ids` TEXT NULL,
    `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_sb_product_tab`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sb_product_tab_lang` (
    `id_sb_product_tab` INT(10) UNSIGNED NOT NULL,
    `id_lang` INT(10) UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL DEFAULT \'\',
    `content` LONGTEXT NULL,
    PRIMARY KEY (`id_sb_product_tab`, `id_lang`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sb_product_tab_shop` (
    `id_sb_product_tab` INT(10) UNSIGNED NOT NULL,
    `id_shop` INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`id_sb_product_tab`, `id_shop`),
    KEY `id_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
