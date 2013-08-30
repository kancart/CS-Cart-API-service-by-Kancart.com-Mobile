<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class ProductTranslatorService {

    private $product;
    private $item = array();

    const OPTION_TYPE_SELECT = 'select';
    const OPTION_TYPE_CHECKBOX = 'select';
    const OPTION_TYPE_MULTIPLE_SELECT = 'multiselect';
    const OPTION_TYPE_TEXT = 'text';
    const OPTION_TYPE_DATE = 'date';
    const OPTION_TYPE_TIME = 'time';
    const OPTION_TYPE_DATE_TIME = 'datetime';

    public function getTranslatedItem() {
        return $this->item;
    }

    public function getItemBaseInfo() {
        $this->item['item_id'] = $this->product['product_id'];
        $this->item['display_id'] = $this->product['product_code'];
        $this->item['cid'] = $this->product['category_ids'];
        $this->item['item_title'] = $this->product['product'];
        $this->item['thumbnail_pic_url'] = get_product_image_url($this->product);
        $this->item['post_free'] = (boolean) ($this->product['free_shipping'] == 'Y');
        $this->item['shipping_fee'] = $this->product['shipping_freight'];
        $this->item['price'] = convert_price((double) $this->product['price']);
        $this->item['item_url'] = fn_url('products.view?product_id=' . $this->product['product_id'], AREA, 'current');
        $this->item['allow_add_to_cart'] = !$this->product['has_options'] && $this->item['price'] > 0;
        $this->item['qty'] = $this->product['amount'];

        if ($this->item['qty'] <= 0 && Registry::get('settings.General.inventory_tracking') == 'Y' && Registry::get('settings.General.allow_negative_amount') != 'Y') {
            $this->item['item_status'] = 'outofstock';
        } else {
            $this->item['item_status'] = 'onsale';
        }

        if (is_addon_enabled('discussion') && $this->product['discussion_type'] != 'D') {
            $reviewService = ServiceFactory::factory('Review');
            $this->item['rating_count'] = $reviewService->getReviewsCount($this->product['product_id']);
            $this->item['rating_score'] = $reviewService->getAvgRatingScore($this->product['product_id']);
        }

        $this->item['stuff_status'] = 'new';
        $this->item['virtual_flag'] = false;
        $this->item['currency'] = CART_SECONDARY_CURRENCY;
        $this->item['attributes'] = array();
        return $this->item;
    }

    public function getProductFeature() {
        !empty($this->product['product_features']) || $this->product['product_features'] = fn_get_product_features_list($this->product['product_id']);
        $features = array();
        foreach ($this->product['product_features'] as $feature) {
            if ($feature['feature_type'] == 'G') {
                foreach ($feature['subfeatures'] as $subfeature) {
                    $value = $this->getFeatureValue($subfeature);
                    $features[] = array(
                        'name' => $subfeature['description'],
                        'value' => $value
                    );
                }
            } else {
                $value = $this->getFeatureValue($feature);
                $features[] = array(
                    'name' => $feature['description'],
                    'value' => $value
                );
            }
        }

        return $features;
    }

    private function getFeatureValue($feature) {
        $feature_type = $feature['feature_type'];
        $hide_prefix = $feature_type == 'M';
        $value = ($feature['prefix'] && !$hide_prefix) ? $feature['prefix'] : '';
        switch (true) {
            case $feature_type == 'C':
                $value.=$feature['value'];
                break;
            case $feature_type == 'D':
                $value.=fn_date_format($feature['value_int'], Registry::get('settings.Appearance.date_format'));
                break;
            case $feature_type == 'M':
                foreach ($feature['variants'] as $variant) {
                    if ($variant['selected']) {
                        $value.=$variant['variant'] . ' ';
                    }
                }
                break;
            case $feature_type == 'S' || $feature_type == 'E':
                foreach ($feature['variants'] as $variant) {
                    if ($variant['selected']) {
                        $value.=$variant['variant'];
                    }
                }
                break;
            case $feature_type == 'N' || $feature_type == 'O':
                $value.=(empty($feature['value_int']) ? '-' : $feature['value_int']);
                break;
            default :
                $value.=(empty($feature['value']) ? '-' : $feature['value']);
                break;
        }

        if ($feature['suffix'] && !$hide_prefix) {
            $value.=$feature['suffix'];
        }

        return $value;
    }

    public function getItemPrices() {
        $prices = array();
        $prices['base_price']['price'] = convert_price((double) $this->product['base_price']);
        $prices['display_prices'] = array();
        if (!(floatval($this->product['price']))) { //Handling the product without price
            $prices['display_prices'][] = array(
                'title' => fn_get_lang_var('contact_us_for_price'),
                'price' => convert_price((double) $this->product['price']),
                'style' => 'free'
            );
        } else {
            $prices['display_prices'][] = array(
                'title' => fn_get_lang_var('price'),
                'price' => convert_price((double) $this->product['price']),
                'style' => 'normal'
            );
        }
        // if product has discounts,we need display the original price
        if ($this->product['original_price'] > $this->product['price']) {
            $prices['display_prices'][] = array(
                'title' => fn_get_lang_var('old_price'),
                'price' => convert_price($this->product['original_price']),
                'style' => 'line-through'
            );
            $this->item['discount'] = !empty($this->product['discount_prc']) ? (int) $this->product['discount_prc'] : round(100 - ($this->product['price'] * 100) / $this->product['original_price']);
        } elseif (isset($this->product['list_price']) && $this->product['list_price']) {
            if ($this->product['list_price'] > $this->product['price']) {
                $prices['display_prices'][] = array(
                    'title' => fn_get_lang_var('list_price'),
                    'price' => convert_price($this->product['list_price']),
                    'style' => 'line-through'
                );
                $this->item['discount'] = !empty($this->product['discount_prc']) ? (int) $this->product['discount_prc'] : round(100 - ($this->product['price'] * 100) / $this->product['list_price']);
            }
        }
        $prices['tier_prices'] = $this->getTierPrices();
        $prices['currency'] = CART_SECONDARY_CURRENCY;
        $this->item['prices'] = $prices;
        return $prices;
    }

    public function getTierPrices() {
        $info = array();
        if (isset($this->product['prices']) && is_array($this->product['prices'])) {
            foreach ($this->product['prices'] as $discount) {
                $info[] = array(
                    'min_qty' => (int) $discount['lower_limit'],
                    'price' => convert_price($discount['price']) + 0.0
                );
            }
        }

        return $info;
    }

    public function getItemAttributes() {
        $product_options = fn_get_product_options($this->product['product_id']);
        if (is_array($product_options)) {
            foreach ($product_options as $u => $product_option) {
                $attribute = array(
                    'attribute_id' => $product_option['option_id'],
                    'title' => $product_option['option_name'],
                    'custom_name' => $product_option['option_name'],
                    'custom_text' => $product_option['option_name'],
                    'required' => $product_option['required'] != 'N',
                    'input' => $this->getOptionType($product_option['option_type'])
                );
                foreach ($product_option['variants'] as $u_id => $variant) {
                    $attribute_value = array(
                        'option_id' => $variant['variant_id'],
                        'attribute_id' => $attribute['attribute_id'],
                        'title' => $variant['variant_name'],
                        'price' => convert_price((double) $variant['modifier'])
                    );
                    $attribute['options'][] = $attribute_value;
                }
                $this->item['attributes'][] = $attribute;
            }
        }
    }

    private function getOptionType($type) {
        switch ($type) {
            case 'S':
                return self::OPTION_TYPE_SELECT;
                break;
            case 'R':
                return self::OPTION_TYPE_SELECT;
                break;
            case 'C':
                return self::OPTION_TYPE_MULTIPLE_SELECT;
                break;
            case 'I':
                return self::OPTION_TYPE_TEXT;
                break;
            case 'T':
                return self::OPTION_TYPE_TEXT;
                break;
            case 'F':
                return self::OPTION_TYPE_TEXT;
                break;
            default:
                return self::OPTION_TYPE_TEXT;
                break;
        }
    }

    public function getRecommededItems() {
        $this->item['recommended_items'] = array();
    }

    public function getRelatedItems() {
        $this->item['related_items'] = array();
    }

    public function getItemImgs() {
        $this->item['short_description'] = empty($this->product['short_description']) ? $this->product['meta_description'] : $this->product['short_description'];
        $this->item['detail_description'] = (!preg_match('/(<br|<p|<div|<dd|<li|<span)/i', $this->product['full_description']) ? nl2br($this->product['full_description']) : $this->product['full_description']);
        $this->item['detail_description'] = preg_replace('/(\<img[^\<^\>]+src\s*=\s*[\"\'])([^(http)]+\/)/i', '$1' . Registry::get('config.current_location') . '/$2', $this->item['detail_description']);
        $this->item['specifications'] = $this->getProductFeature();

        $main_pair = $this->product['main_pair'];
        $image_pairs = $this->product['image_pairs'];
        $i = 1;
        $this->item['item_imgs'][] = array(
            'img_id' => $main_pair['pair_id'],
            'img_url' => $this->item['thumbnail_pic_url'],
            'position' => $i++
        );
        if (is_array($image_pairs)) {
            foreach ($image_pairs as $u => $image) {
                $this->item['item_imgs'][] = array(
                    'img_id' => $u,
                    'img_url' => get_product_image_url($image),
                    'position' => $i++
                );
            }
        }
    }

    public function clear() {
        $this->product = array();
        $this->item = array();
    }

    public function setProduct($product) {
        $this->product = $product;
    }

    public function getFullItemInfo() {
        $this->getItemBaseInfo();
        $this->getItemPrices();
        $this->getItemAttributes();
        $this->getItemImgs();
        $this->getRecommededItems();
        $this->getRelatedItems();
        return $this->getTranslatedItem();
    }

}

?>
