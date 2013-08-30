<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_addresses_get_action extends UserAuthorizedAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $addresses = $userService->getAllAddresses();
        $this->setSuccess(array("addresses" => $addresses));
    }

}

?>
