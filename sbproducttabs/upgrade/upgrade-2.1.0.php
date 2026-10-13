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

/**
 * Adds the back office only "internal name" column.
 *
 * @param SbProductTabs $module
 *
 * @return bool
 */
function upgrade_module_2_1_0($module)
{
    $exists = Db::getInstance()->executeS(
        'SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'sb_product_tab` LIKE \'internal_name\''
    );
    if ($exists) {
        return true;
    }

    return (bool) Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'sb_product_tab`
        ADD `internal_name` VARCHAR(255) NOT NULL DEFAULT \'\' AFTER `id_sb_product_tab`'
    );
}
