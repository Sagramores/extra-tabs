<?php
/**
 * Product Extra Tabs
 *
 * Adds custom tabs to the product page for all products, selected products,
 * products of selected categories or products of selected brands.
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/SbProductTab.php';

class SbProductTabs extends Module
{
    public const ADMIN_CONTROLLER = 'AdminSbProductTabs';

    public function __construct()
    {
        $this->name = 'sbproducttabs';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
        $this->author = 'Sotiris Betsos';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = [
            'min' => '1.7.7.0',
            'max' => '9.99.99',
        ];

        parent::__construct();

        $this->displayName = $this->l('Product Extra Tabs');
        $this->description = $this->l('Add custom tabs to the product page for all products or for selected products, categories and brands.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All tabs will be deleted.');
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTab()
            && $this->registerHook('displayProductExtraContent')
            && $this->registerHook('actionProductDelete');
    }

    public function uninstall()
    {
        return $this->uninstallTab()
            && $this->uninstallDb()
            && parent::uninstall();
    }

    /**
     * Redirects the module "Configure" button to the tabs manager.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::ADMIN_CONTROLLER));
    }

    private function installDb()
    {
        $sql = [];
        include __DIR__ . '/sql/install.php';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallDb()
    {
        $sql = [];
        include __DIR__ . '/sql/uninstall.php';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function installTab()
    {
        if (Tab::getIdFromClassName(self::ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Product Extra Tabs';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if (!$idTab) {
            return true;
        }

        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    /**
     * Renders the tabs on the product page.
     *
     * @param array $params
     *
     * @return PrestaShop\PrestaShop\Core\Product\ProductExtraContent[]
     */
    public function hookDisplayProductExtraContent($params)
    {
        $idProduct = 0;
        if (isset($params['product'])) {
            $product = $params['product'];
            if (is_object($product) && isset($product->id)) {
                $idProduct = (int) $product->id;
            } elseif (is_array($product) && isset($product['id_product'])) {
                $idProduct = (int) $product['id_product'];
            }
        }
        if (!$idProduct) {
            $idProduct = (int) Tools::getValue('id_product');
        }
        if (!$idProduct) {
            return [];
        }

        $tabs = SbProductTab::getTabsForProduct(
            $idProduct,
            (int) $this->context->language->id,
            (int) $this->context->shop->id
        );

        $extraContents = [];
        foreach ($tabs as $tab) {
            $extraContent = new PrestaShop\PrestaShop\Core\Product\ProductExtraContent();
            $extraContent->setTitle($tab['title']);
            $extraContent->setContent((string) $tab['content']);
            $extraContent->addAttr(['id' => 'sb-product-tab-' . (int) $tab['id_sb_product_tab']]);
            $extraContents[] = $extraContent;
        }

        return $extraContents;
    }

    /**
     * Removes a deleted product from the tabs that target it.
     *
     * @param array $params
     */
    public function hookActionProductDelete($params)
    {
        $idProduct = isset($params['id_product']) ? (int) $params['id_product'] : 0;
        if ($idProduct) {
            SbProductTab::removeProductFromTabs($idProduct);
        }
    }
}
