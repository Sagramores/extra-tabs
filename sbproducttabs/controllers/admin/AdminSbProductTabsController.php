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

require_once _PS_MODULE_DIR_ . 'sbproducttabs/classes/SbProductTab.php';

class AdminSbProductTabsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'sb_product_tab';
        $this->identifier = 'id_sb_product_tab';
        $this->className = 'SbProductTab';
        $this->lang = true;
        $this->position_identifier = 'id_sb_product_tab';
        $this->_defaultOrderBy = 'position';
        $this->_defaultOrderWay = 'ASC';
        $this->allow_export = false;

        parent::__construct();

        Shop::addTableAssociation($this->table, ['type' => 'shop']);

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->t('Delete selected'),
                'icon' => 'icon-trash',
                'confirm' => $this->t('Delete selected items?'),
            ],
        ];

        $this->fields_list = [
            'id_sb_product_tab' => [
                'title' => $this->t('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'internal_name' => [
                'title' => $this->t('Internal name'),
                'filter_key' => 'a!internal_name',
            ],
            'title' => [
                'title' => $this->t('Title'),
                'filter_key' => 'b!title',
            ],
            'display_rule' => [
                'title' => $this->t('Displayed on'),
                'type' => 'select',
                'list' => $this->getRuleNames(),
                'filter_key' => 'a!display_rule',
                'callback' => 'renderRuleName',
                'search' => true,
                'orderby' => false,
            ],
            'position' => [
                'title' => $this->t('Position'),
                'filter_key' => 'a!position',
                'position' => 'position',
                'align' => 'center',
                'class' => 'fixed-width-md',
            ],
            'active' => [
                'title' => $this->t('Status'),
                'active' => 'status',
                'type' => 'bool',
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'orderby' => false,
            ],
        ];

        $this->fields_options = [
            'sbpt_default_tabs' => [
                'title' => $this->t('Default product page tabs'),
                'icon' => 'icon-eye-slash',
                'description' => $this->t('Hide the default PrestaShop / theme tabs you do not need. The first visible tab (default or from this module) opens automatically.'),
                'fields' => [
                    'SBPT_HIDE_DESCRIPTION' => [
                        'title' => $this->t('Hide "Description"'),
                        'type' => 'bool',
                        'cast' => 'intval',
                        'validation' => 'isBool',
                    ],
                    'SBPT_HIDE_DETAILS' => [
                        'title' => $this->t('Hide "Product details"'),
                        'type' => 'bool',
                        'cast' => 'intval',
                        'validation' => 'isBool',
                    ],
                    'SBPT_HIDE_FEATURES' => [
                        'title' => $this->t('Hide "Data sheet" (Hummingbird theme)'),
                        'type' => 'bool',
                        'cast' => 'intval',
                        'validation' => 'isBool',
                    ],
                    'SBPT_HIDE_ATTACHMENTS' => [
                        'title' => $this->t('Hide "Attachments"'),
                        'type' => 'bool',
                        'cast' => 'intval',
                        'validation' => 'isBool',
                    ],
                    'SBPT_HIDE_CUSTOM' => [
                        'title' => $this->t('Other tabs to hide'),
                        'type' => 'text',
                        'validation' => 'isGenericName',
                        'class' => 'fixed-width-xxl',
                        'hint' => $this->t('Tabs added by your theme. Enter the id of the tab content, comma separated.'),
                        'desc' => $this->t('Tab ids, comma separated, e.g. shippingcmsblock'),
                    ],
                    'SBPT_FOCUS_FIRST' => [
                        'title' => $this->t('Open the first visible tab'),
                        'type' => 'bool',
                        'cast' => 'intval',
                        'validation' => 'isBool',
                        'desc' => $this->t('When the product page loads, the first visible tab is opened, whether it is a default tab or a tab from this module.'),
                    ],
                ],
                'submit' => ['title' => $this->t('Save')],
            ],
        ];

        if (Shop::isFeatureActive()) {
            $shopIds = array_map('intval', Shop::getContextListShopID());
            $this->_join = 'INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_shop` sa
                ON (sa.`id_sb_product_tab` = a.`id_sb_product_tab` AND sa.`id_shop` IN (' . implode(',', $shopIds ?: [0]) . '))';
            $this->_group = 'GROUP BY a.`id_sb_product_tab`';
        }
    }

    /**
     * Translates a string in the module's domain.
     */
    protected function t($string)
    {
        return $this->module->l($string, 'adminsbproducttabscontroller');
    }

    protected function getRuleNames()
    {
        return [
            SbProductTab::RULE_ALL => $this->t('All products'),
            SbProductTab::RULE_PRODUCTS => $this->t('Selected products'),
            SbProductTab::RULE_CATEGORIES => $this->t('Products of selected categories'),
            SbProductTab::RULE_MANUFACTURERS => $this->t('Products of selected brands'),
        ];
    }

    public function renderRuleName($value)
    {
        $names = $this->getRuleNames();

        return isset($names[(int) $value]) ? $names[(int) $value] : '--';
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');

        Media::addJsDef([
            'sbProductTabs' => [
                'ajaxUrl' => $this->context->link->getAdminLink('AdminSbProductTabs'),
                'texts' => [
                    'noResults' => $this->t('No results'),
                    'remove' => $this->t('Remove'),
                    'searching' => $this->t('Searching...'),
                ],
            ],
        ]);
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_tab'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => $this->t('Add new tab'),
                'icon' => 'process-icon-new',
            ];
        }

        parent::initPageHeaderToolbar();
    }

    public function renderForm()
    {
        /** @var SbProductTab|false $object */
        $object = $this->loadObject(true);
        if (!$object) {
            return '';
        }

        $idLang = (int) $this->context->language->id;

        $this->fields_form = [
            'legend' => [
                'title' => $this->t('Product tab'),
                'icon' => 'icon-folder-open',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->t('Internal name'),
                    'name' => 'internal_name',
                    'maxlength' => 255,
                    'hint' => $this->t('Only visible in the back office. Use it to tell apart tabs that share the same title.'),
                    'desc' => $this->t('Only visible in the back office, e.g. "Ingredients - Trahanas".'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->t('Title'),
                    'name' => 'title',
                    'lang' => true,
                    'required' => true,
                    'maxlength' => 255,
                    'hint' => $this->t('The tab title shown on the product page.'),
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->t('Content'),
                    'name' => 'content',
                    'lang' => true,
                    'autoload_rte' => true,
                    'rows' => 10,
                    'cols' => 100,
                ],
                [
                    'type' => 'select',
                    'label' => $this->t('Show this tab on'),
                    'name' => 'display_rule',
                    'class' => 'sbpt-rule-select',
                    'options' => [
                        'query' => $this->getRuleOptions(),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'sb_picker',
                    'label' => $this->t('Products'),
                    'name' => 'product_ids',
                    'entity' => 'product',
                    'rule' => SbProductTab::RULE_PRODUCTS,
                    'placeholder' => $this->t('Search by product name, reference or ID'),
                    'selected' => SbProductTab::getNamesForIds('product', $object->product_ids, $idLang),
                ],
                [
                    'type' => 'sb_picker',
                    'label' => $this->t('Categories'),
                    'name' => 'category_ids',
                    'entity' => 'category',
                    'rule' => SbProductTab::RULE_CATEGORIES,
                    'placeholder' => $this->t('Search by category name or ID'),
                    'selected' => SbProductTab::getNamesForIds('category', $object->category_ids, $idLang),
                ],
                [
                    'type' => 'sb_picker',
                    'label' => $this->t('Brands'),
                    'name' => 'manufacturer_ids',
                    'entity' => 'manufacturer',
                    'rule' => SbProductTab::RULE_MANUFACTURERS,
                    'placeholder' => $this->t('Search by brand name or ID'),
                    'selected' => SbProductTab::getNamesForIds('manufacturer', $object->manufacturer_ids, $idLang),
                ],
                [
                    'type' => 'sb_picker',
                    'label' => $this->t('Also show on these products'),
                    'name' => 'include_product_ids',
                    'entity' => 'product',
                    'rule' => SbProductTab::RULE_CATEGORIES . ',' . SbProductTab::RULE_MANUFACTURERS,
                    'placeholder' => $this->t('Search by product name, reference or ID'),
                    'desc' => $this->t('Products outside the selected categories / brands that should also show this tab.'),
                    'selected' => SbProductTab::getNamesForIds('product', $object->include_product_ids, $idLang),
                ],
                [
                    'type' => 'sb_picker',
                    'label' => $this->t('Hide from these products'),
                    'name' => 'exclude_product_ids',
                    'entity' => 'product',
                    'rule' => SbProductTab::RULE_ALL . ',' . SbProductTab::RULE_CATEGORIES . ',' . SbProductTab::RULE_MANUFACTURERS,
                    'placeholder' => $this->t('Search by product name, reference or ID'),
                    'desc' => $this->t('These products never show this tab, even if they match the rule above.'),
                    'selected' => SbProductTab::getNamesForIds('product', $object->exclude_product_ids, $idLang),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->t('Active'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->t('Yes')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->t('No')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->t('Save'),
            ],
            'buttons' => [
                'save-and-stay' => [
                    'title' => $this->t('Save and stay'),
                    'name' => 'submitAdd' . $this->table . 'AndStay',
                    'type' => 'submit',
                    'class' => 'btn btn-default pull-right',
                    'icon' => 'process-icon-save',
                ],
            ],
        ];

        if (Shop::isFeatureActive()) {
            $this->fields_form['input'][] = [
                'type' => 'shop',
                'label' => $this->t('Shop association'),
                'name' => 'checkBoxShopAsso',
            ];
        }

        foreach ($this->fields_form['input'] as &$input) {
            if ($input['type'] === 'sb_picker') {
                $input['selected_ids'] = implode(',', array_column($input['selected'], 'id'));
            }
        }
        unset($input);

        return parent::renderForm();
    }

    protected function getRuleOptions()
    {
        $options = [];
        foreach ($this->getRuleNames() as $id => $name) {
            $options[] = ['id' => $id, 'name' => $name];
        }

        return $options;
    }

    /**
     * AJAX: search products / categories / brands for the picker.
     */
    public function ajaxProcessSearch()
    {
        $entity = (string) Tools::getValue('entity');
        $results = [];
        if (in_array($entity, ['product', 'category', 'manufacturer'], true)) {
            $results = SbProductTab::search(
                $entity,
                Tools::getValue('q'),
                (int) $this->context->language->id,
                (int) Tools::getValue('limit', 20)
            );
        }

        $this->sendJson($results);
    }

    /**
     * AJAX: drag & drop reordering from the list.
     */
    public function ajaxProcessUpdatePositions()
    {
        if (!$this->access('edit')) {
            $this->sendJson(['hasError' => true, 'errors' => $this->t('You do not have permission to edit this.')]);
        }

        $positions = Tools::getValue($this->table);
        if (!is_array($positions)) {
            $positions = Tools::getValue('positions');
        }
        if (!is_array($positions)) {
            foreach ($_POST as $value) {
                if (is_array($value)) {
                    $positions = $value;
                    break;
                }
            }
        }

        $orderedIds = [];
        foreach ((array) $positions as $value) {
            // Row ids look like "tr_<group>_<id>_<position>"
            $parts = explode('_', (string) $value);
            if (isset($parts[2]) && (int) $parts[2] > 0) {
                $orderedIds[] = (int) $parts[2];
            }
        }

        if (!$orderedIds || !SbProductTab::savePositions($orderedIds)) {
            $this->sendJson(['hasError' => true, 'errors' => $this->t('Cannot update the position.')]);
        }

        $this->sendJson(['hasError' => false]);
    }

    protected function sendJson($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
