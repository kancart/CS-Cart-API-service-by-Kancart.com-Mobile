<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_store_information_get_action extends BaseAction {

    public function execute() {
        $storeService = ServiceFactory::factory('Store');
        $this->setSuccess($storeService->getStoreInfo());
    }

}

?>
