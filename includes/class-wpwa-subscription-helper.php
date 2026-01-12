<?php
if (!defined('ABSPATH')) exit;

class WPWA_Subscription_Helper {

    const DURATION_META_KEY = 'subscription_duration';
    const WHITELIST_META_KEY = '_wpwa_whitelist_product';

    /* -----------------------------
     * Product type detection
     * ----------------------------- */

    public static function is_whitelist_product($product_id) {
        $flag = get_post_meta($product_id, self::WHITELIST_META_KEY, true);
        return $flag === 'yes' || $flag === 1 || $flag === true;
    }

    /* -----------------------------
     * Duration handling
     * ----------------------------- */

    public static function normalize_duration($duration) {
        $duration = intval($duration);
        if ($duration <= 0) return 1;
        return $duration;
    }

    public static function get_duration_from_cart_item($cart_item) {
        if (!empty($cart_item[self::DURATION_META_KEY])) {
            return self::normalize_duration($cart_item[self::DURATION_META_KEY]);
        }
        return 1;
    }

    /* -----------------------------
     * Pricing logic (centralized)
     * ----------------------------- */

    public static function calculate_price($base_price, $duration, $discount_percent = 0) {
        $duration = self::normalize_duration($duration);

        $price = floatval($base_price) * $duration;

        if ($discount_percent > 0) {
            $price -= ($price * ($discount_percent / 100));
        }

        return round($price, wc_get_price_decimals());
    }

    /* -----------------------------
     * Cart protection
     * ----------------------------- */

    public static function price_already_applied(&$cart_item) {
        if (!empty($cart_item['_wpwa_price_applied'])) {
            return true;
        }

        $cart_item['_wpwa_price_applied'] = true;
        return false;
    }
}
