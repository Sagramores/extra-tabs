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
        $this->version = '2.2.0';
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

    public const HOOKS = [
        'displayProductExtraContent',
        'actionProductDelete',
        'displayAdminProductsExtra',
        'actionAfterUpdateProductFormHandler',
        'actionProductUpdate',
    ];

    /** @var bool[] Products already processed in this request */
    protected static $processedProducts = [];

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->installTab()
            && $this->registerHook(self::HOOKS);
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

    /**
     * Product page in the back office ("Modules" tab): edit / hide / replace / add tabs.
     *
     * @param array $params
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = isset($params['id_product']) ? (int) $params['id_product'] : (int) Tools::getValue('id_product');
        if (!$idProduct) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $languages = Language::getLanguages(false);
        $ruleNames = [
            SbProductTab::RULE_ALL => $this->l('All products'),
            SbProductTab::RULE_PRODUCTS => $this->l('Selected products'),
            SbProductTab::RULE_CATEGORIES => $this->l('Products of selected categories'),
            SbProductTab::RULE_MANUFACTURERS => $this->l('Products of selected brands'),
        ];

        $tabs = [];
        $shownIds = [];
        foreach (SbProductTab::getTabsForProductAdmin($idProduct, $idShop) as $tab) {
            $count = $tab->countProducts();
            $visible = SbProductTab::appliesToProduct($tab, $idProduct);
            $tabs[] = [
                'id' => (int) $tab->id,
                'internal_name' => (string) $tab->internal_name,
                'title' => (array) $tab->title,
                'content' => (array) $tab->content,
                'active' => (bool) $tab->active,
                'visible' => $visible,
                'rule' => isset($ruleNames[(int) $tab->display_rule]) ? $ruleNames[(int) $tab->display_rule] : '',
                'count' => $count,
                // Shown on other products too
                'shared' => $count > ($visible ? 1 : 0),
                'all_label' => sprintf($this->l('For all %d products'), $count),
                'edit_url' => $this->context->link->getAdminLink(self::ADMIN_CONTROLLER, true, [], [
                    'id_sb_product_tab' => (int) $tab->id,
                    'updatesb_product_tab' => 1,
                ]),
            ];
            $shownIds[] = (int) $tab->id;
        }

        $allTabs = SbProductTab::getAllForSelect($idLang, $idShop);
        $addableTabs = array_values(array_filter($allTabs, function ($tab) use ($shownIds) {
            return !in_array($tab['id'], $shownIds, true);
        }));

        $this->context->smarty->assign([
            'sbpt_id_product' => $idProduct,
            'sbpt_tabs' => $tabs,
            'sbpt_all_tabs' => $allTabs,
            'sbpt_addable_tabs' => $addableTabs,
            'sbpt_languages' => $languages,
            'sbpt_multilang' => count($languages) > 1,
            'sbpt_id_lang' => $idLang,
            'sbpt_module_url' => $this->context->link->getAdminLink(self::ADMIN_CONTROLLER),
            'sbpt_css' => $this->getPathUri() . 'views/css/product.css?v=' . $this->version,
            'sbpt_js' => $this->getPathUri() . 'views/js/product.js?v=' . $this->version,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_product_tabs.tpl');
    }

    /**
     * Product page (PrestaShop 8 / 9): saves the tab changes made in the "Modules" tab.
     *
     * @param array $params
     */
    public function hookActionAfterUpdateProductFormHandler($params)
    {
        if (!empty($params['id'])) {
            $this->processProductTabs((int) $params['id']);
        }
    }

    /**
     * Legacy product page (PrestaShop 1.7): same as above.
     *
     * @param array $params
     */
    public function hookActionProductUpdate($params)
    {
        // The PrestaShop 8 / 9 product page is handled by actionAfterUpdateProductFormHandler,
        // once all the product data has been saved.
        if (isset($_POST['product']) && is_array($_POST['product'])) {
            return;
        }
        if (!empty($params['id_product'])) {
            $this->processProductTabs((int) $params['id_product']);
        }
    }

    /**
     * Applies the changes posted from the product page.
     */
    protected function processProductTabs($idProduct)
    {
        $data = Tools::getValue('sbpt');
        if (!is_array($data) || empty($data['present']) || (int) $data['id_product'] !== (int) $idProduct) {
            return;
        }
        if (isset(self::$processedProducts[$idProduct])) {
            return;
        }
        self::$processedProducts[$idProduct] = true;

        $errors = [];
        $languages = Language::getLanguages(false);
        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
        $postedTabs = isset($data['tabs']) && is_array($data['tabs']) ? $data['tabs'] : [];

        foreach ($postedTabs as $idTab => $posted) {
            try {
                $tab = new SbProductTab((int) $idTab);
                if (!Validate::isLoadedObject($tab) || !is_array($posted)) {
                    continue;
                }
                $isShown = SbProductTab::appliesToProduct($tab, $idProduct);
                $isExcluded = in_array($idProduct, SbProductTab::parseIds($tab->exclude_product_ids), true);
                if (!$isShown && !$isExcluded) {
                    continue;
                }

                // Replace with another tab
                $idReplacement = isset($posted['replace']) ? (int) $posted['replace'] : 0;
                if ($idReplacement && $idReplacement !== (int) $tab->id) {
                    $replacement = new SbProductTab($idReplacement);
                    if (Validate::isLoadedObject($replacement)) {
                        $tab->detachProduct($idProduct);
                        $replacement->attachProduct($idProduct);
                    }
                    continue;
                }

                // Show / hide on this product
                $visible = !empty($posted['visible']);
                if (!$visible) {
                    if ($isShown) {
                        $tab->detachProduct($idProduct);
                    }
                    continue;
                }
                if (!$isShown) {
                    $tab->attachProduct($idProduct);
                }

                // Text changes
                if (empty($posted['dirty'])) {
                    continue;
                }
                $titles = [];
                $contents = [];
                $changed = false;
                foreach ($languages as $lang) {
                    $idLang = (int) $lang['id_lang'];
                    $title = isset($posted['title'][$idLang]) ? trim((string) $posted['title'][$idLang]) : null;
                    $content = isset($posted['content'][$idLang]) ? (string) $posted['content'][$idLang] : null;
                    $titles[$idLang] = ($title === null || $title === '') ? (string) $tab->title[$idLang] : $title;
                    $contents[$idLang] = $content === null ? (string) $tab->content[$idLang] : $content;
                    if ($titles[$idLang] !== (string) $tab->title[$idLang] || $contents[$idLang] !== (string) $tab->content[$idLang]) {
                        $changed = true;
                    }
                }
                if (!$changed) {
                    continue;
                }
                if ($titles[$idLangDefault] === '') {
                    $errors[] = sprintf($this->l('Tab #%d: the title is required.'), (int) $tab->id);
                    continue;
                }
                foreach ($titles as $title) {
                    if (!Validate::isGenericName($title)) {
                        $errors[] = sprintf($this->l('Tab #%d: the title contains invalid characters.'), (int) $tab->id);
                        continue 2;
                    }
                }

                $onlyThisProduct = isset($posted['scope']) && $posted['scope'] === 'product';
                if ($onlyThisProduct && $tab->countProducts() > 1) {
                    if (!$tab->splitForProduct($idProduct, $titles, $contents)) {
                        $errors[] = sprintf($this->l('Tab #%d: could not create the copy for this product.'), (int) $tab->id);
                    }
                } else {
                    $tab->title = $titles;
                    $tab->content = $contents;
                    $tab->update();
                }
            } catch (Throwable $e) {
                $errors[] = sprintf($this->l('Tab #%d: %s'), (int) $idTab, $e->getMessage());
            }
        }

        // Existing tabs added to this product
        foreach (SbProductTab::parseIds(isset($data['add']) ? $data['add'] : '') as $idTab) {
            try {
                $tab = new SbProductTab($idTab);
                if (Validate::isLoadedObject($tab) && !SbProductTab::appliesToProduct($tab, $idProduct)) {
                    $tab->attachProduct($idProduct);
                }
            } catch (Throwable $e) {
                $errors[] = sprintf($this->l('Tab #%d: %s'), (int) $idTab, $e->getMessage());
            }
        }

        foreach ($errors as $error) {
            PrestaShopLogger::addLog('[sbproducttabs] ' . $error, 2, null, 'Product', (int) $idProduct, true);
            $this->addFlashError($error);
        }
    }

    protected function addFlashError($message)
    {
        try {
            if (class_exists('PrestaShop\\PrestaShop\\Adapter\\SymfonyContainer')) {
                $container = PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
                if ($container && $container->has('request_stack')) {
                    $request = $container->get('request_stack')->getCurrentRequest();
                    if ($request && $request->hasSession()) {
                        $request->getSession()->getFlashBag()->add('error', $this->displayName . ': ' . $message);
                    }
                }
            }
        } catch (Exception $e) {
            // The error is still in the logs
        }
    }
}
