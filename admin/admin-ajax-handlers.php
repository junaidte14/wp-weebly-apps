<?php 

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Handle AJAX actions
add_action('wp_ajax_wpwa_handle_order_action', 'wpwa_handle_order_action_ajax');
add_action('wp_ajax_nopriv_wpwa_handle_order_action', 'wpwa_handle_order_action_ajax');

function wpwa_handle_order_action_ajax() {
    check_ajax_referer('wpwa_order_action', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
    
    if (!$payment_id || !$action) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }
    
    $result = wpwa_process_order_action($payment_id, $action, $_POST);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * Handle order action AJAX
 */
function wpwa_process_order_action($payment_id, $action, $params) {
    $result = ['success' => false, 'message' => ''];
    // Get order object (HPOS compatible)
    $order = wc_get_order($payment_id);
    if (!$order) {
        return ['success' => false, 'message' => 'Invalid order ID'];
    }
    switch ($action) {
        case 'notified':
            $result = wpwa_send_payment_notification($payment_id, $params);
            break;
        case 'completed':
        case 'for-testing':
        case 'refunded':
            // Use order meta instead of post meta (HPOS compatible)
            $order->update_meta_data('weebly_notification', sanitize_text_field($action));
            $order->save();
            $result = ['success' => true, 'message' => 'Status updated to ' . ucfirst($action)];
            break;
        case 'remove_access':
            $result = wpwa_remove_app_access($payment_id, $params);
            break;       
        case 'delete':
            $result = wpwa_delete_order($payment_id, $params);
            break;
    }
    // Log the action
    wpwa_log_order_action($payment_id, $action, $result['success']);   
    return $result;
}

/**
 * Send payment notification
 */
function wpwa_send_payment_notification($payment_id, $params) {
    $gross_amount = isset($params['gross_amount']) ? floatval($params['gross_amount']) : 0;
    $payable_amount = isset($params['payable_amount']) ? floatval($params['payable_amount']) : 0;
    $access_token = isset($params['access_token']) ? sanitize_text_field($params['access_token']) : '';
    $app_name = isset($params['app_name']) ? sanitize_text_field($params['app_name']) : '';
    if (!$gross_amount || !$payable_amount || !$access_token) {
        return ['success' => false, 'message' => 'Missing required parameters'];
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.weebly.com/v1/admin/app/payment_notifications",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $app_name . " Install Fee",
            'method' => 'purchase',
            'kind' => 'single',
            'term' => 'forever',
            'gross_amount' => $gross_amount,
            'payable_amount' => $payable_amount,
            'currency' => 'USD'
        ]),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "cache-control: no-cache",
            "x-weebly-access-token: " . $access_token
        ],
    ]);
    $response = curl_exec($curl);
    $responseInfo = curl_getinfo($curl);
    $httpResponseCode = $responseInfo['http_code'];
    curl_close($curl);
    // Get order object (HPOS compatible)
    $order = wc_get_order($payment_id);   
    if ($httpResponseCode == 200) {
        if ($order) {
            $order->update_meta_data('weebly_notification', 'notified');
            $order->update_meta_data('wpwa_order_not_status', 'submitted');
            $order->save();
        }
        return ['success' => true, 'message' => 'Payment notification sent successfully'];
    } elseif ($httpResponseCode == 403) {
        return ['success' => false, 'message' => 'Unknown API key or notification already submitted'];
    } else {
        return ['success' => false, 'message' => 'API Error: ' . $response];
    }
}

function wpwa_remove_app_access($payment_id, $params) {
    $site_id = isset($params['site_id']) ? sanitize_text_field($params['site_id']) : '';
    $app_id = isset($params['app_id']) ? sanitize_text_field($params['app_id']) : '';
    $access_token = isset($params['access_token']) ? sanitize_text_field($params['access_token']) : '';
    
    if (!$site_id || !$app_id || !$access_token) {
        return ['success' => false, 'message' => 'Missing required parameters'];
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.weebly.com/v1/user/sites/{$site_id}/apps/{$app_id}/deauthorize",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'site_id' => $site_id,
            'platform_app_id' => $app_id
        ]),
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "cache-control: no-cache",
            "x-weebly-access-token: " . $access_token
        ),
    ));
    
    $response = curl_exec($curl);
    $responseInfo = curl_getinfo($curl);
    $httpResponseCode = $responseInfo['http_code'];
    curl_close($curl);
    
    if ($httpResponseCode == 200) {
        update_post_meta($payment_id, 'weebly_notification', 'access_removed');
        return ['success' => true, 'message' => 'Access removed successfully'];
    } elseif ($httpResponseCode == 403) {
        return ['success' => false, 'message' => 'App already disconnected or invalid API key'];
    } else {
        return ['success' => false, 'message' => 'API Error: ' . $response];
    }
}

/**
 * Delete order handler
 */
function wpwa_delete_order($payment_id, $params) {
    // First remove access
    $result = wpwa_remove_app_access($payment_id, $params);
    if ($result['success'] || strpos($result['message'], 'already disconnected') !== false) {
        $order = wc_get_order($payment_id);
        if ($order) {
            // HPOS compatible delete
            $order->delete(true); // true = force delete
            return ['success' => true, 'message' => 'Order deleted successfully'];
        }
    }   
    return $result;
}

/**
 * Order action logging
 */
function wpwa_log_order_action($payment_id, $action, $success) {
    $order = wc_get_order($payment_id);
    if (!$order) {
        return;
    }
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'action'    => $action,
        'success'   => $success,
        'user'      => wp_get_current_user()->user_login
    ];
    // Get existing logs (HPOS compatible)
    $logs = $order->get_meta('wpwa_action_logs');
    if (!is_array($logs)) {
        $logs = [];
    }
    $logs[] = $log_entry;   
    // Update order meta (HPOS compatible)
    $order->update_meta_data('wpwa_action_logs', $logs);
    $order->save();
}

add_action('wp_ajax_wpwa_save_whitelist_product', 'wpwa_save_whitelist_product_ajax');
function wpwa_save_whitelist_product_ajax() {
    check_ajax_referer('wpwa_whitelist_settings', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    
    WPWA_Whitelist::save_whitelist_product_id($product_id);
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

// Add at the end of the file

add_action('wp_ajax_wpwa_save_email_settings', 'wpwa_save_email_settings_ajax');

function wpwa_save_email_settings_ajax() {
    check_ajax_referer('wpwa_email_settings', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    update_option('wpwa_email_activation_enabled', isset($_POST['activation_enabled']) && $_POST['activation_enabled'] == 1);
    update_option('wpwa_email_expiring_enabled', isset($_POST['expiring_enabled']) && $_POST['expiring_enabled'] == 1);
    update_option('wpwa_email_expiring_days', absint($_POST['expiring_days'] ?? 7));
    update_option('wpwa_email_expired_enabled', isset($_POST['expired_enabled']) && $_POST['expired_enabled'] == 1);
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}