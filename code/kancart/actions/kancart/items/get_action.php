<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_items_get_action extends BaseAction {

    public function execute() {

        $item_ids = is_null($this->getParam('item_ids')) ? '' : $this->getParam('item_ids');
        $filter = array();
        $filter['cid'] = is_null($this->getParam('cid')) ? 0 : intval($this->getParam('cid'));
        $filter['cid'] = ($filter['cid'] == -1) ? 0 : $filter['cid'];
        $filter['is_specials'] = isset($_POST['is_specials']) && $_POST['is_specials'] == 'true' ? true : false;
        $filter['q'] = empty($_POST['query']) ? '' : trim($_POST['query']);
        $order_option = explode(":", $_POST['order_by']);
        $filter['sort_by'] = empty($order_option[0]) ? 'position' : $order_option[0];
        $filter['sort_order'] = empty($order_option[1]) ? 'desc' : $order_option[1];

        $filter['page'] = empty($_POST['page_no']) && is_numeric($_POST['page_no']) ? 1 : max(intval($_POST['page_no']), 1);
        $page_size = empty($_POST['page_size']) ? 20 : min(intval($_POST['page_size']), 200);

        if (!empty($item_ids)) {
            $filter['item_ids'] = explode(',', $item_ids);
        }
        $productService = ServiceFactory::factory('Product');
        $this->setSuccess($productService->getProducts($filter, $page_size));
    }

}

?>
