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

class SbProductTab extends ObjectModel
{
    public const RULE_ALL = 1;
    public const RULE_PRODUCTS = 2;
    public const RULE_CATEGORIES = 3;
    public const RULE_MANUFACTURERS = 4;

    /** @var string Back office only name, never shown to customers */
    public $internal_name = '';

    /** @var int */
    public $display_rule = self::RULE_ALL;

    /** @var string Comma separated product ids */
    public $product_ids = '';

    /** @var string Comma separated category ids */
    public $category_ids = '';

    /** @var string Comma separated manufacturer ids */
    public $manufacturer_ids = '';

    /** @var string Comma separated product ids that also show the tab (category / brand rules) */
    public $include_product_ids = '';

    /** @var string Comma separated product ids that never show the tab */
    public $exclude_product_ids = '';

    /** @var int */
    public $position = 0;

    /** @var bool */
    public $active = true;

    /** @var string|array */
    public $title;

    /** @var string|array */
    public $content;

    public static $definition = [
        'table' => 'sb_product_tab',
        'primary' => 'id_sb_product_tab',
        'multilang' => true,
        'fields' => [
            'internal_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 255],
            'display_rule' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'product_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'category_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'manufacturer_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'include_product_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'exclude_product_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'required' => true],

            // Lang fields
            'title' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'content' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml'],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
        parent::__construct($id, $id_lang, $id_shop);
    }

    public function add($auto_date = true, $null_values = false)
    {
        $this->sanitizeIds();
        if (!$this->id) {
            $this->position = self::getNextPosition();
        }

        return parent::add($auto_date, $null_values);
    }

    public function update($null_values = false)
    {
        $this->sanitizeIds();

        return parent::update($null_values);
    }

    public function delete()
    {
        $result = parent::delete();
        if ($result) {
            self::cleanPositions();
        }

        return $result;
    }

    private function sanitizeIds()
    {
        $this->display_rule = (int) $this->display_rule;
        if (!in_array($this->display_rule, [self::RULE_ALL, self::RULE_PRODUCTS, self::RULE_CATEGORIES, self::RULE_MANUFACTURERS], true)) {
            $this->display_rule = self::RULE_ALL;
        }
        $this->product_ids = implode(',', self::parseIds($this->product_ids));
        $this->category_ids = implode(',', self::parseIds($this->category_ids));
        $this->manufacturer_ids = implode(',', self::parseIds($this->manufacturer_ids));
        $this->include_product_ids = implode(',', self::parseIds($this->include_product_ids));
        $this->exclude_product_ids = implode(',', self::parseIds($this->exclude_product_ids));
    }

    /**
     * Converts "1,2,3" (or any separator) into a list of unique positive integers.
     *
     * @param mixed $value
     *
     * @return int[]
     */
    public static function parseIds($value)
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }
        $ids = [];
        foreach (preg_split('/[^0-9]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public static function getNextPosition()
    {
        $max = Db::getInstance()->getValue('SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'sb_product_tab`');

        return $max === null || $max === false ? 0 : (int) $max + 1;
    }

    public static function cleanPositions()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_sb_product_tab` FROM `' . _DB_PREFIX_ . 'sb_product_tab` ORDER BY `position` ASC, `id_sb_product_tab` ASC'
        );

        return self::savePositions(array_column($rows ?: [], 'id_sb_product_tab'));
    }

    /**
     * Stores the given order of ids as positions 0..n.
     *
     * @param int[] $orderedIds
     */
    public static function savePositions(array $orderedIds)
    {
        $result = true;
        foreach (array_values($orderedIds) as $position => $id) {
            $result &= Db::getInstance()->update(
                'sb_product_tab',
                ['position' => (int) $position],
                '`id_sb_product_tab` = ' . (int) $id
            );
        }

        return (bool) $result;
    }

    /**
     * Returns the active tabs that should be displayed for a product.
     *
     * @return array[] Rows with id_sb_product_tab, title and content
     */
    public static function getTabsForProduct($idProduct, $idLang, $idShop)
    {
        $idProduct = (int) $idProduct;

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT t.*, tl.`title`, tl.`content`
            FROM `' . _DB_PREFIX_ . 'sb_product_tab` t
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_shop` ts
                ON (ts.`id_sb_product_tab` = t.`id_sb_product_tab` AND ts.`id_shop` = ' . (int) $idShop . ')
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_lang` tl
                ON (tl.`id_sb_product_tab` = t.`id_sb_product_tab` AND tl.`id_lang` = ' . (int) $idLang . ')
            WHERE t.`active` = 1
            ORDER BY t.`position` ASC'
        );

        $tabs = [];
        foreach ($rows ?: [] as $row) {
            if (self::appliesToProduct($row, $idProduct) && trim((string) $row['title']) !== '') {
                $tabs[] = [
                    'id_sb_product_tab' => (int) $row['id_sb_product_tab'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                ];
            }
        }

        return $tabs;
    }

    /**
     * Product data needed to evaluate the display rules, cached per request.
     *
     * @return array{categories: int[], manufacturer: int}
     */
    protected static function getProductRuleData($idProduct)
    {
        static $cache = [];
        $idProduct = (int) $idProduct;
        if (!isset($cache[$idProduct])) {
            $cache[$idProduct] = [
                'categories' => array_map('intval', Product::getProductCategories($idProduct)),
                'manufacturer' => (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                    'SELECT `id_manufacturer` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $idProduct
                ),
            ];
        }

        return $cache[$idProduct];
    }

    /**
     * Whether the display rule alone (categories / brands / list) matches the product,
     * ignoring the extra include / exclude lists.
     *
     * @param array|SbProductTab $tab
     */
    public static function ruleMatchesProduct($tab, $idProduct)
    {
        $tab = (array) $tab;
        $idProduct = (int) $idProduct;

        switch ((int) $tab['display_rule']) {
            case self::RULE_ALL:
                return true;
            case self::RULE_PRODUCTS:
                return in_array($idProduct, self::parseIds($tab['product_ids']), true);
            case self::RULE_CATEGORIES:
                $data = self::getProductRuleData($idProduct);

                return (bool) array_intersect($data['categories'], self::parseIds($tab['category_ids']));
            case self::RULE_MANUFACTURERS:
                $data = self::getProductRuleData($idProduct);

                return $data['manufacturer'] > 0
                    && in_array($data['manufacturer'], self::parseIds($tab['manufacturer_ids']), true);
        }

        return false;
    }

    /**
     * Whether the tab is shown on the product (rule + include list - exclude list).
     *
     * @param array|SbProductTab $tab
     */
    public static function appliesToProduct($tab, $idProduct)
    {
        $tab = (array) $tab;
        $idProduct = (int) $idProduct;

        if (in_array($idProduct, self::parseIds(isset($tab['exclude_product_ids']) ? $tab['exclude_product_ids'] : ''), true)) {
            return false;
        }
        if (self::ruleMatchesProduct($tab, $idProduct)) {
            return true;
        }

        return in_array((int) $tab['display_rule'], [self::RULE_CATEGORIES, self::RULE_MANUFACTURERS], true)
            && in_array($idProduct, self::parseIds(isset($tab['include_product_ids']) ? $tab['include_product_ids'] : ''), true);
    }

    /**
     * Number of products the tab is currently shown on (all shops).
     */
    public function countProducts()
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $exclude = self::parseIds($this->exclude_product_ids);
        $include = self::parseIds($this->include_product_ids);
        $notExcluded = $exclude ? ' AND p.`id_product` NOT IN (' . implode(',', $exclude) . ')' : '';

        switch ((int) $this->display_rule) {
            case self::RULE_ALL:
                return (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` p WHERE 1' . $notExcluded);
            case self::RULE_PRODUCTS:
                return count(self::parseIds($this->product_ids));
            case self::RULE_CATEGORIES:
                $ids = self::parseIds($this->category_ids);
                $condition = $ids ? 'p.`id_product` IN (SELECT `id_product` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_category` IN (' . implode(',', $ids) . '))' : '0';
                break;
            case self::RULE_MANUFACTURERS:
                $ids = self::parseIds($this->manufacturer_ids);
                $condition = $ids ? 'p.`id_manufacturer` IN (' . implode(',', $ids) . ')' : '0';
                break;
            default:
                return 0;
        }
        if ($include) {
            $condition = '(' . $condition . ' OR p.`id_product` IN (' . implode(',', $include) . '))';
        }

        return (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product` p WHERE ' . $condition . $notExcluded);
    }

    /**
     * Makes the tab show on the product.
     */
    public function attachProduct($idProduct)
    {
        $idProduct = (int) $idProduct;
        $this->exclude_product_ids = implode(',', array_diff(self::parseIds($this->exclude_product_ids), [$idProduct]));

        if ((int) $this->display_rule === self::RULE_PRODUCTS) {
            $this->product_ids = implode(',', array_merge(self::parseIds($this->product_ids), [$idProduct]));
        } elseif ((int) $this->display_rule !== self::RULE_ALL && !self::ruleMatchesProduct($this, $idProduct)) {
            $this->include_product_ids = implode(',', array_merge(self::parseIds($this->include_product_ids), [$idProduct]));
        }

        return $this->update();
    }

    /**
     * Stops the tab from showing on the product, without affecting other products.
     */
    public function detachProduct($idProduct)
    {
        $idProduct = (int) $idProduct;
        $this->include_product_ids = implode(',', array_diff(self::parseIds($this->include_product_ids), [$idProduct]));

        if ((int) $this->display_rule === self::RULE_PRODUCTS) {
            $this->product_ids = implode(',', array_diff(self::parseIds($this->product_ids), [$idProduct]));
        } elseif (self::ruleMatchesProduct($this, $idProduct)) {
            $this->exclude_product_ids = implode(',', array_merge(self::parseIds($this->exclude_product_ids), [$idProduct]));
        }

        return $this->update();
    }

    /**
     * Creates a copy of the tab shown only on the given product, placed right after the original,
     * and stops the original from showing on that product.
     *
     * @param array $titles id_lang => title
     * @param array $contents id_lang => content
     *
     * @return SbProductTab|false
     */
    public function splitForProduct($idProduct, array $titles, array $contents)
    {
        $idProduct = (int) $idProduct;
        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');

        $baseName = trim((string) $this->internal_name);
        if ($baseName === '') {
            $baseName = is_array($this->title) && isset($this->title[$idLangDefault]) ? $this->title[$idLangDefault] : '';
        }
        $productName = Product::getProductName($idProduct, null, $idLangDefault);
        $internalName = Tools::substr(trim($baseName . ' - ' . $productName, ' -'), 0, 255);

        $copy = new self();
        $copy->internal_name = $internalName;
        $copy->display_rule = self::RULE_PRODUCTS;
        $copy->product_ids = (string) $idProduct;
        $copy->active = true;
        $copy->title = [];
        $copy->content = [];
        foreach (Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $copy->title[$idLang] = isset($titles[$idLang]) ? $titles[$idLang] : (isset($this->title[$idLang]) ? $this->title[$idLang] : '');
            $copy->content[$idLang] = isset($contents[$idLang]) ? $contents[$idLang] : (isset($this->content[$idLang]) ? $this->content[$idLang] : '');
        }
        $copy->id_shop_list = $this->getAssociatedShops();

        if (!$copy->add()) {
            return false;
        }

        // Place the copy right after the original
        $ordered = array_map('intval', array_column(Db::getInstance()->executeS(
            'SELECT `id_sb_product_tab` FROM `' . _DB_PREFIX_ . 'sb_product_tab`
            WHERE `id_sb_product_tab` != ' . (int) $copy->id . '
            ORDER BY `position` ASC, `id_sb_product_tab` ASC'
        ) ?: [], 'id_sb_product_tab'));
        $index = array_search((int) $this->id, $ordered, true);
        array_splice($ordered, $index === false ? count($ordered) : $index + 1, 0, [(int) $copy->id]);
        self::savePositions($ordered);

        $this->detachProduct($idProduct);

        return $copy;
    }

    /**
     * Tabs for the product page in the back office: the ones shown on the product
     * plus the ones hidden from it (so they can be shown again).
     *
     * @return SbProductTab[]
     */
    public static function getTabsForProductAdmin($idProduct, $idShop)
    {
        $idProduct = (int) $idProduct;
        $rows = Db::getInstance()->executeS(
            'SELECT t.`id_sb_product_tab`
            FROM `' . _DB_PREFIX_ . 'sb_product_tab` t
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_shop` ts
                ON (ts.`id_sb_product_tab` = t.`id_sb_product_tab` AND ts.`id_shop` = ' . (int) $idShop . ')
            ORDER BY t.`position` ASC'
        );

        $tabs = [];
        foreach ($rows ?: [] as $row) {
            $tab = new self((int) $row['id_sb_product_tab']);
            if (self::appliesToProduct($tab, $idProduct)
                || in_array($idProduct, self::parseIds($tab->exclude_product_ids), true)
            ) {
                $tabs[] = $tab;
            }
        }

        return $tabs;
    }

    /**
     * All tabs as [id, label] for selection lists.
     */
    public static function getAllForSelect($idLang, $idShop)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT t.`id_sb_product_tab`, t.`internal_name`, t.`active`, tl.`title`
            FROM `' . _DB_PREFIX_ . 'sb_product_tab` t
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_shop` ts
                ON (ts.`id_sb_product_tab` = t.`id_sb_product_tab` AND ts.`id_shop` = ' . (int) $idShop . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'sb_product_tab_lang` tl
                ON (tl.`id_sb_product_tab` = t.`id_sb_product_tab` AND tl.`id_lang` = ' . (int) $idLang . ')
            ORDER BY t.`position` ASC'
        );

        $result = [];
        foreach ($rows ?: [] as $row) {
            $label = trim((string) $row['internal_name']) !== ''
                ? $row['internal_name'] . ' (' . $row['title'] . ')'
                : (string) $row['title'];
            $result[] = [
                'id' => (int) $row['id_sb_product_tab'],
                'label' => '#' . (int) $row['id_sb_product_tab'] . ' ' . $label,
                'active' => (bool) $row['active'],
            ];
        }

        return $result;
    }

    public static function removeProductFromTabs($idProduct)
    {
        $idProduct = (int) $idProduct;
        $rows = Db::getInstance()->executeS(
            'SELECT `id_sb_product_tab`, `product_ids`, `include_product_ids`, `exclude_product_ids`
            FROM `' . _DB_PREFIX_ . 'sb_product_tab`'
        );

        foreach ($rows ?: [] as $row) {
            $data = [];
            foreach (['product_ids', 'include_product_ids', 'exclude_product_ids'] as $field) {
                $ids = self::parseIds($row[$field]);
                if (in_array($idProduct, $ids, true)) {
                    $data[$field] = pSQL(implode(',', array_diff($ids, [$idProduct])));
                }
            }
            if ($data) {
                Db::getInstance()->update('sb_product_tab', $data, '`id_sb_product_tab` = ' . (int) $row['id_sb_product_tab']);
            }
        }
    }

    /**
     * Resolves ids to [id, name] pairs for the admin picker.
     *
     * @param string $entity product|category|manufacturer
     * @param mixed $ids
     *
     * @return array[]
     */
    public static function getNamesForIds($entity, $ids, $idLang)
    {
        $ids = self::parseIds($ids);
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $idLang = (int) $idLang;

        switch ($entity) {
            case 'product':
                $sql = 'SELECT p.`id_product` AS id, pl.`name`, p.`reference`
                    FROM `' . _DB_PREFIX_ . 'product` p
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                        ON (pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . Shop::addSqlRestrictionOnLang('pl') . ')
                    WHERE p.`id_product` IN (' . $in . ')
                    GROUP BY p.`id_product`';
                break;
            case 'category':
                $sql = 'SELECT c.`id_category` AS id, cl.`name`, \'\' AS reference
                    FROM `' . _DB_PREFIX_ . 'category` c
                    LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                        ON (cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . Shop::addSqlRestrictionOnLang('cl') . ')
                    WHERE c.`id_category` IN (' . $in . ')
                    GROUP BY c.`id_category`';
                break;
            case 'manufacturer':
                $sql = 'SELECT m.`id_manufacturer` AS id, m.`name`, \'\' AS reference
                    FROM `' . _DB_PREFIX_ . 'manufacturer` m
                    WHERE m.`id_manufacturer` IN (' . $in . ')';
                break;
            default:
                return [];
        }

        $rows = Db::getInstance()->executeS($sql) ?: [];
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
            ];
        }

        // Keep the stored order
        $result = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $result[] = $byId[$id];
            }
        }

        return $result;
    }

    /**
     * Searches products, categories or manufacturers by name (or product reference / id).
     *
     * @return array[]
     */
    public static function search($entity, $query, $idLang, $limit = 20)
    {
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }
        $like = '\'%' . pSQL(addcslashes($query, '%_')) . '%\'';
        $idLang = (int) $idLang;
        $limit = max(1, min(50, (int) $limit));
        $byId = ctype_digit($query) ? (int) $query : 0;

        switch ($entity) {
            case 'product':
                $sql = 'SELECT p.`id_product` AS id, pl.`name`, p.`reference`
                    FROM `' . _DB_PREFIX_ . 'product` p
                    ' . Shop::addSqlAssociation('product', 'p') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                        ON (pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $idLang . Shop::addSqlRestrictionOnLang('pl') . ')
                    WHERE (pl.`name` LIKE ' . $like . ' OR p.`reference` LIKE ' . $like . ($byId ? ' OR p.`id_product` = ' . $byId : '') . ')
                    GROUP BY p.`id_product`
                    ORDER BY pl.`name` ASC
                    LIMIT ' . $limit;
                break;
            case 'category':
                $sql = 'SELECT c.`id_category` AS id, cl.`name`, \'\' AS reference
                    FROM `' . _DB_PREFIX_ . 'category` c
                    ' . Shop::addSqlAssociation('category', 'c') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                        ON (cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . $idLang . Shop::addSqlRestrictionOnLang('cl') . ')
                    WHERE c.`is_root_category` = 0 AND c.`id_parent` > 0
                        AND (cl.`name` LIKE ' . $like . ($byId ? ' OR c.`id_category` = ' . $byId : '') . ')
                    GROUP BY c.`id_category`
                    ORDER BY cl.`name` ASC
                    LIMIT ' . $limit;
                break;
            case 'manufacturer':
                $sql = 'SELECT m.`id_manufacturer` AS id, m.`name`, \'\' AS reference
                    FROM `' . _DB_PREFIX_ . 'manufacturer` m
                    WHERE (m.`name` LIKE ' . $like . ($byId ? ' OR m.`id_manufacturer` = ' . $byId : '') . ')
                    ORDER BY m.`name` ASC
                    LIMIT ' . $limit;
                break;
            default:
                return [];
        }

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'reference' => (string) $row['reference'],
            ];
        }

        return $result;
    }
}
