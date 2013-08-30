<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class ProductService {

    private function setDefaultFilterIfNeed(&$filter, &$page_size) {
        if (!$page_size) {
            $page_size = Registry::get('settings.Appearance.products_per_page');
        }
        if (!$filter['page_no']) {
            $filter['page_no'] = 1;
        }
        if (!$filter['sort_by']) {
            $filter['sort_by'] = Registry::get('settings.Appearance.default_products_sorting');
        }
        if (!$filter['sort_order']) {
            $filter['sort_order'] = 'desc';
        }
    }

    /**
     * Get the products,filter is specified by the $filter parameter
     * 
     * @param array $filter array
     * @return array
     * @author hujs
     */
    public function getProducts($filter, $page_size) {
        $this->setDefaultFilterIfNeed($filter, $page_size);
        $products = array('total_results' => 0, 'items' => array());
        if (isset($filter['item_ids'])) {
            // get by item ids
            $products = $this->getSpecifiedProducts($filter, $page_size);
        } else if (isset($filter['is_specials']) && intval($filter['is_specials'])) {
            // get Special Products
            $products = $this->getSpecialProducts($filter, $page_size, defined('INCLUDE_PROMOTION') && INCLUDE_PROMOTION);
        } else if (isset($filter['q'])) {
            // get by query
            $products = $this->getProductsByQuery($filter, $page_size);
        } else {
            // get by category
            $products = $this->getProductsByCategory($filter, $page_size);
        }

        return $products;
    }

    /**
     * get product by name
     * @global type $languages_id
     * @param type $filter
     * @return int
     * @author hujs
     */
    public function getProductsByQuery($filter, $page_size) {
        if (is_null($filter['query'])) {
            return array('total_results' => 0, 'items' => array());
        }

        $params = array(
            'q' => $filter['q'],
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'cid' => 0,
            'type' => 'extended',
            'match' => '',
            'status' => 'A',
            'objects' => array_keys(fn_search_get_customer_objects())
        );

        $search = Registry::get('search_object');
        foreach ($search['conditions']['functions'] as $object => $function) {
            if ($search['default'] == $object) {
                continue;
            }

            if (!in_array($object, $params['objects'])) {
                unset($search['conditions']['functions'][$object]);
            }
        }
        $_params = fn_array_merge($params, $search['default_params']['products']);

        list($search_results, $param, $total) = fn_get_products($_params, $page_size);
        fn_gather_additional_products_data($search_results, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($search_results as $item) {
            $productTranslator->setProduct($item);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }
        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);
    }

    public function getSpecifiedProducts($filter, $page_size) {
        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'type' => 'extended', //apply for 2.1.2 no price
            'pid' => $filter['item_ids'],
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);
    }

    public function getSpecialProducts($filter, $page_size, $include_promotion = false) {
        $sql = 'SELECT products.product_id, MIN(prices.price) AS price 
               FROM ?:products AS products 
               LEFT JOIN ?:product_prices AS prices ON prices.product_id = products.product_id AND prices.lower_limit = 1
               WHERE products.status IN (\'A\') AND products.list_price > price
               group by products.product_id';
        $result = db_get_array($sql);
        $ids = array();
        foreach ($result as $value) {
            $ids[] = $value['product_id'];
        }

        if ($include_promotion) {
            $cids = array();
            $condition = array(
                'active' => true,
                'expand' => true,
                'zone' => 'catalog');
            list($promotions) = fn_get_promotions($condition);
            if ($promotions) {
                foreach ($promotions as $promotion) {
                    $yes = false;
                    foreach ($promotion['bonuses'] as $bonus) {
                        if (fn_promotions_calculate_discount($bonus['discount_bonus'], 100, $bonus['discount_value']) > 0) {
                            $yes = true;
                            break;
                        }
                    }
                    if ($yes && strpos($promotion['conditions_hash'], 'categories') !== false) {
                        $idstr = substr($promotion['conditions_hash'], strpos($promotion['conditions_hash'], '=') + 1);
                        $cids = array_merge($cids, explode(',', $idstr));
                    }
                }
                if (sizeof($cids)) {
                    $result = db_get_array('SELECT pc.product_id FROM ?:products_categories AS pc LEFT JOIN ?:categories AS ca ON ca.category_id = pc.category_id WHERE ca.category_id in(?n) AND ca.status IN (\'A\')', $cids);
                    foreach ($result as $value) {
                        $ids[] = $value['product_id'];
                    }
                }
            }
        }

        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'type' => 'extended', //apply for 2.1.2 no price
            'pid' => $ids,
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        return array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);
    }

    /**
     * get products
     * 
     * @param array $filter
     * @return array
     */
    public function getProductsByCategory($filter, $page_size) {
        if ($filter['cid']) {
            $_statuses = array('A', 'H');
            $_condition = ' AND (' . fn_find_array_in_set($_SESSION['auth']['usergroup_ids'], 'usergroup_ids', true) . ')';
            $_condition .= fn_get_localizations_condition('localization', true);

            if ($_SESSION['auth']['area'] != 'A') {
                $_condition .= db_quote(' AND status IN (?a)', $_statuses);
            }

            $is_avail = db_get_field("SELECT category_id FROM ?:categories WHERE category_id = ?i ?p", $filter['cid'], $_condition);
            if (empty($is_avail)) {
                return array('items' => array(), 'total_results' => 0);
            } else {
                // Save current category id to session
                $_SESSION['current_category_id'] = $_REQUEST['category_id'];
            }
        }

        $params = array(
            'page' => $filter['page'],
            'sort_by' => $filter['sort_by'],
            'sort_order' => $filter['sort_order'],
            'cid' => (int) $filter['cid'],
            'type' => 'extended', //apply for 2.1.2 no price
            'subcats' => Registry::get('settings.General.show_products_from_subcategories') || $filter['cid'] < 1,
            'extend' => array('category_ids', 'description')
        );

        list($products, $param, $total) = fn_get_products($params, $page_size);
        fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => true, 'get_discounts' => true, 'get_features' => false));
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $items = array();

        foreach ($products as $product) {
            $productTranslator->setProduct($product);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
            $productTranslator->clear();
        }

        $returnResult = array('items' => $items, 'total_results' => is_null($total) ? $param['total_items'] : $total);
        return $returnResult;
    }

    /**
     * get product by id
     * @param integer $product_id
     * @return array
     */
    public function getProduct($product_id) {
        $row = fn_get_product_data($product_id, $_SESSION['auth'], CART_LANGUAGE, '', true, true, true, true);
        fn_gather_additional_product_data($row, true, true, true, true, true);
        if ($row != false) {
            $productTranslator = ServiceFactory::factory('ProductTranslator');
            $productTranslator->setProduct($row);
            return $productTranslator->getFullItemInfo();
        } else {
            return false;
        }
    }

}

?>