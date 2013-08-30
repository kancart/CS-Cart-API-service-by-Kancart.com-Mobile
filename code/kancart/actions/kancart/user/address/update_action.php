<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_address_update_action extends UserAuthorizedAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $address = array_merge(prepare_address($_REQUEST, 'shipping'), prepare_address($_REQUEST, 'billing'));
        if ($userService->updateAddress($address)) {
            $this->setSuccess();
            return;
        }
        $this->setError();
    }

}

?>
