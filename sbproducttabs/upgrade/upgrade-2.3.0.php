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
 * Adds the options to hide the default product page tabs.
 *
 * @param SbProductTabs $module
 *
 * @return bool
 */
function upgrade_module_2_3_0($module)
{
    return $module->installConfiguration()
        && (bool) $module->registerHook(SbProductTabs::HOOKS);
}
