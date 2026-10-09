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

    /** @var int */
    public $display_rule = self::RULE_ALL;

    /** @var string Comma separated product ids */
    public $product_ids = '';

    /** @var string Comma separated category ids */
    public $category_ids = '';

    /** @var string Comma separated manufacturer ids */
    public $manufacturer_ids = '';

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
            'display_rule' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'product_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'category_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'manufacturer_ids' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
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
            'SELECT t.`id_sb_product_tab`, t.`display_rule`, t.`product_ids`, t.`category_ids`, t.`manufacturer_ids`,
                    tl.`title`, tl.`content`
            FROM `' . _DB_PREFIX_ . 'sb_product_tab` t
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_shop` ts
                ON (ts.`id_sb_product_tab` = t.`id_sb_product_tab` AND ts.`id_shop` = ' . (int) $idShop . ')
            INNER JOIN `' . _DB_PREFIX_ . 'sb_product_tab_lang` tl
                ON (tl.`id_sb_product_tab` = t.`id_sb_product_tab` AND tl.`id_lang` = ' . (int) $idLang . ')
            WHERE t.`active` = 1
            ORDER BY t.`position` ASC'
        );

        if (empty($rows)) {
            return [];
        }

        $productCategories = null;
        $productManufacturer = null;
        $tabs = [];

        foreach ($rows as $row) {
            $show = false;
            switch ((int) $row['display_rule']) {
                case self::RULE_ALL:
                    $show = true;
                    break;
                case self::RULE_PRODUCTS:
                    $show = in_array($idProduct, self::parseIds($row['product_ids']), true);
                    break;
                case self::RULE_CATEGORIES:
                    if ($productCategories === null) {
                        $productCategories = array_map('intval', Product::getProductCategories($idProduct));
                    }
                    $show = (bool) array_intersect($productCategories, self::parseIds($row['category_ids']));
                    break;
                case self::RULE_MANUFACTURERS:
                    if ($productManufacturer === null) {
                        $productManufacturer = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                            'SELECT `id_manufacturer` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $idProduct
                        );
                    }
                    $show = $productManufacturer > 0
                        && in_array($productManufacturer, self::parseIds($row['manufacturer_ids']), true);
                    break;
            }

            if ($show && trim((string) $row['title']) !== '') {
                $tabs[] = [
                    'id_sb_product_tab' => (int) $row['id_sb_product_tab'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                ];
            }
        }

        return $tabs;
    }

    public static function removeProductFromTabs($idProduct)
    {
        $idProduct = (int) $idProduct;
        $rows = Db::getInstance()->executeS(
            'SELECT `id_sb_product_tab`, `product_ids` FROM `' . _DB_PREFIX_ . 'sb_product_tab`
            WHERE `product_ids` IS NOT NULL AND `product_ids` != \'\''
        );

        foreach ($rows ?: [] as $row) {
            $ids = self::parseIds($row['product_ids']);
            if (in_array($idProduct, $ids, true)) {
                Db::getInstance()->update(
                    'sb_product_tab',
                    ['product_ids' => pSQL(implode(',', array_diff($ids, [$idProduct])))],
                    '`id_sb_product_tab` = ' . (int) $row['id_sb_product_tab']
                );
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
