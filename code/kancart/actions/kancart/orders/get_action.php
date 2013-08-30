<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_orders_get_action extends UserAuthorizedAction {

    public function execute() {
        $pageNo = $this->getParam('page_no');
        $pageSize = $this->getParam('page_size');
        $pageNo = max(intval($pageNo) , 1);
        $pageSize = isset($pageSize)? intval($pageSize) : 5;
        $orderService = ServiceFactory::factory('Order');
        $result = $orderService->getOrderInfos($_SESSION['auth']['user_id'], $pageNo, $pageSize);
        $this->setSuccess($result);
    }

}

?>
