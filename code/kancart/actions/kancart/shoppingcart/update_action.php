<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_update_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $validateInfo = array();
        if (!isset($cartItemId)) {
            $validateInfo[] = 'Cart item id is not specified .';
        }
        if (!isset($qty) || !is_numeric($qty) || $qty < 0) {
            $validateInfo[] = 'Qty is not valid.';
        }
        if ($validateInfo) {
            $this->setError(KancartResult::CART_INPUT_PARAMETER_ERROR, $validateInfo);
            return false;
        }
        return true;
    }

    /**
     * Update shopping cart
     * 2012-09-04
     */
    public function execute() {
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $skus = $this->getParam('attributes');
        $product_options = array();
        if ($skus) {
            $skus = json_decode(stripslashes(urldecode($skus)));
            foreach ($skus as $sku) {
                foreach (explode(',', $sku->value) as $attr) {
                    $product_options[] = $attr;
                };
            }
        }
        $shoppingCartService = ServiceFactory::factory('ShoppingCart');
        $updateInputParam = array('cart_item_id' => $cartItemId, 'product_options' => $product_options, 'qty' => $qty);
        $updateResult = $shoppingCartService->update($updateInputParam);
        if ($updateResult['result']) {
            $this->setSuccess($shoppingCartService->get());
            return;
        }
        $this->setError('', $updateResult['err_msg']);
    }

}

?>
