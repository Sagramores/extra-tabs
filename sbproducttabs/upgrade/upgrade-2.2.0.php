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
 * Adds the per product include / exclude lists and the product page hooks.
 *
 * @param SbProductTabs $module
 *
 * @return bool
 */
function upgrade_module_2_2_0($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'sb_product_tab';

    foreach (['include_product_ids', 'exclude_product_ids'] as $column) {
        if (!$db->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE \'' . $column . '\'')) {
            if (!$db->execute('ALTER TABLE `' . $table . '` ADD `' . $column . '` TEXT NULL AFTER `manufacturer_ids`')) {
                return false;
            }
        }
    }

    return (bool) $module->registerHook(SbProductTabs::HOOKS);
}
