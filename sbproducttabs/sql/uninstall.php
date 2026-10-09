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

$sql[] = 'DROP TABLE IF EXISTS
    `' . _DB_PREFIX_ . 'sb_product_tab`,
    `' . _DB_PREFIX_ . 'sb_product_tab_lang`,
    `' . _DB_PREFIX_ . 'sb_product_tab_shop`';
