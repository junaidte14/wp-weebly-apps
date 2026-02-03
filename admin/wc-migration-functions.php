<?php
/**
 * WooCommerce Migration Core Functions
 * File: admin/wc-migration-functions.php
 */

if (!defined('ABSPATH')) exit;

/**
 * Migrate batch of WC orders
 */
function wpwa_migrate_wc_orders_batch($batch_size = 100, $batch_number = 1) {
    global $wpdb;

    // Get orders not yet migrated
    $already_migrated = $wpdb->get_col(
        "SELECT wc_order_id FROM {$wpdb->prefix}wpwa_archived_orders"
    );

    $args = array(
        'limit' => $batch_size,
        'status' => array('completed', 'processing'),
        'orderby' => 'date',
        'order' => 'ASC',
        'return' => 'objects',
        'exclude' => $already_migrated
    );

    $orders = wc_get_orders($args);

    $migrated = 0;
    $failed = 0;

    foreach ($orders as $order) {
        $result = wpwa_migrate_single_order($order, $batch_number);
        
        if ($result['success']) {
            $migrated++;
        } else {
            $failed++;
        }
    }

    return array(
        'migrated' => $migrated,
        'failed' => $failed,
        'batch_number' => $batch_number
    );
}

/**
 * Migrate single order
 * UPDATED: Smart subscription detection based on item meta
 */
function wpwa_migrate_single_order($order, $batch_number) {
    global $wpdb;

    try {
        $order_id = $order->get_id();
        $items = $order->get_items();

        if (empty($items)) {
            wpwa_log_migration($batch_number, $order_id, 'order', 'skipped', 'No items in order');
            return array('success' => false);
        }

        // Process each item (in case there are multiple products)
        foreach ($items as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);

            if (!$product) {
                wpwa_log_migration($batch_number, $order_id, 'order', 'failed', "Product not found for item #{$item_id}");
                continue;
            }

            // Extract metadata
            $weebly_user_id = wpwa_get_item_meta_safe($item, 'user_id');
            $weebly_site_id = wpwa_get_item_meta_safe($item, 'site_id');
            $access_token = wpwa_get_item_meta_safe($item, 'access_token');
            $final_url = wpwa_get_item_meta_safe($item, 'final_url');

            // Fallback to order meta if item meta is empty
            if (empty($weebly_user_id)) {
                $weebly_user_id = $order->get_meta('weebly_user_id');
            }
            if (empty($weebly_site_id)) {
                $weebly_site_id = $order->get_meta('weebly_site_id');
            }
            if (empty($weebly_user_id)) {
                $weebly_user_id = $order->get_meta('user_id');
            }

            // Build metadata JSON
            $metadata = array(
                'item_id' => $item_id,
                'billing_address' => array(
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'company' => $order->get_billing_company(),
                    'address_1' => $order->get_billing_address_1(),
                    'address_2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'postcode' => $order->get_billing_postcode(),
                    'country' => $order->get_billing_country(),
                    'phone' => $order->get_billing_phone()
                ),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title(),
                'customer_ip' => $order->get_customer_ip_address(),
                'customer_user_agent' => $order->get_customer_user_agent(),
                'order_key' => $order->get_order_key()
            );

            // Get order notes (only once per order, not per item)
            $notes_text = '';
            if (empty($already_got_notes)) {
                $notes = wc_get_order_notes(array('order_id' => $order_id));
                foreach ($notes as $note) {
                    $notes_text .= '[' . $note->date_created->format('Y-m-d H:i:s') . '] ' . $note->content . "\n";
                }
                $already_got_notes = true;
            }

            // Insert into archive
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'wpwa_archived_orders',
                array(
                    'wc_order_id' => $order_id,
                    'order_number' => $order->get_order_number(),
                    'customer_email' => $order->get_billing_email(),
                    'customer_name' => $order->get_formatted_billing_full_name(),
                    'weebly_user_id' => $weebly_user_id ?: null,
                    'weebly_site_id' => $weebly_site_id ?: null,
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'amount' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'status' => $order->get_status(),
                    'weebly_notification_status' => $order->get_meta('weebly_notification'),
                    'access_token' => $access_token ?: null,
                    'final_url' => $final_url ?: null,
                    'payment_method' => $order->get_payment_method(),
                    'transaction_id' => $order->get_transaction_id(),
                    'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                    'completed_date' => $order->get_date_completed() ? $order->get_date_completed()->format('Y-m-d H:i:s') : null,
                    'order_metadata' => json_encode($metadata),
                    'order_notes' => $notes_text
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if (!$inserted) {
                wpwa_log_migration($batch_number, $order_id, 'order', 'failed', 'Database insert failed: ' . $wpdb->last_error);
                continue;
            }

            // ========================================
            // SMART SUBSCRIPTION DETECTION
            // ========================================
            $is_subscription = wpwa_detect_subscription_from_item($item);

            if ($is_subscription) {
                wpwa_migrate_single_subscription($order, $item, $batch_number);
            }
        }

        wpwa_log_migration($batch_number, $order_id, 'order', 'success', null);
        return array('success' => true);

    } catch (Exception $e) {
        wpwa_log_migration($batch_number, $order_id, 'order', 'failed', $e->getMessage());
        return array('success' => false);
    }
}

/**
 * Smart subscription detection based on item meta
 * 
 * @param WC_Order_Item_Product $item
 * @return bool
 */
function wpwa_detect_subscription_from_item($item) {
    // Check for subscription-specific meta keys
    $subscription_indicators = array(
        '_wpwa_expiry',           // Expiry timestamp
        '_wpwa_licence_status',    // Licence status (active, grace, expired, revoked)
        '_wpwa_grace_until',       // Grace period end timestamp
        '_wpwa_renewal_count',     // Number of renewals
        '_wpwa_token_revoked',     // Token revoked flag
        '_wpwa_prepaid_cycles',    // Prepaid cycles
        '_wpwa_subscription_duration' // Subscription duration
    );

    foreach ($subscription_indicators as $meta_key) {
        $value = $item->get_meta($meta_key);
        
        // If any subscription meta exists and has a non-empty value
        if (!empty($value)) {
            // Special case: _wpwa_expiry must be a valid future/past timestamp
            if ($meta_key === '_wpwa_expiry') {
                $expiry_ts = intval($value);
                if ($expiry_ts > 0 && $expiry_ts > strtotime('2020-01-01')) {
                    return true;
                }
            } else {
                return true;
            }
        }
    }

    return false;
}

/**
 * Helper to safely get item meta
 */
function wpwa_get_item_meta_safe($item, $key) {
    $value = $item->get_meta($key);
    return $value ?: '';
}

/**
 * Migrate recurring subscription (UPDATED)
 * Now called only when subscription is detected via item meta
 */
function wpwa_migrate_single_subscription($order, $item, $batch_number) {
    global $wpdb;

    $order_id = $order->get_id();
    $product_id = $item->get_product_id();
    $product = wc_get_product($product_id);

    if (!$product) {
        wpwa_log_migration($batch_number, $order_id, 'subscription', 'failed', 'Product not found');
        return;
    }

    // Get cycle info from product meta (current state)
    $cycle_length = get_post_meta($product_id, '_wpwa_cycle_length', true);
    $cycle_unit = get_post_meta($product_id, '_wpwa_cycle_unit', true);
    $cycle_price = get_post_meta($product_id, '_wpwa_cycle_price', true);

    // If not available (product was converted), try to infer from item meta or use defaults
    if (empty($cycle_length)) {
        $cycle_length = 1;
    }
    if (empty($cycle_unit)) {
        $cycle_unit = 'month';
    }
    if (empty($cycle_price)) {
        $cycle_price = $product->get_price();
    }

    // Get subscription-specific item meta
    $expiry = $item->get_meta('_wpwa_expiry');
    $grace_until = $item->get_meta('_wpwa_grace_until');
    $renewal_count = $item->get_meta('_wpwa_renewal_count') ?: 0;
    $licence_status = $item->get_meta('_wpwa_licence_status') ?: 'active';
    $token_revoked = $item->get_meta('_wpwa_token_revoked');

    // Determine status
    if ($token_revoked === 'yes') {
        $licence_status = 'revoked';
    } elseif ($expiry && intval($expiry) < time()) {
        $licence_status = 'expired';
    } elseif (empty($licence_status)) {
        $licence_status = 'active';
    }

    // Build subscription metadata
    $metadata = array(
        'grace_period_days' => get_post_meta($product_id, '_wpwa_grace_period', true),
        'auto_renew_enabled' => get_post_meta($product_id, '_wpwa_auto_renew_enabled', true),
        'token_revoked' => $token_revoked,
        'prepaid_cycles' => $item->get_meta('_wpwa_prepaid_cycles'),
        'subscription_duration' => $item->get_meta('_wpwa_subscription_duration'),
        'item_id' => $item->get_id()
    );

    $inserted = $wpdb->insert(
        $wpdb->prefix . 'wpwa_archived_subscriptions',
        array(
            'wc_order_id' => $order_id,
            'customer_email' => $order->get_billing_email(),
            'weebly_user_id' => $item->get_meta('user_id'),
            'weebly_site_id' => $item->get_meta('site_id'),
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'cycle_length' => $cycle_length,
            'cycle_unit' => $cycle_unit,
            'cycle_price' => $cycle_price,
            'status' => $licence_status,
            'access_token' => $item->get_meta('access_token'),
            'expiry_date' => $expiry ? date('Y-m-d H:i:s', intval($expiry)) : null,
            'grace_until' => $grace_until ? date('Y-m-d H:i:s', intval($grace_until)) : null,
            'renewal_count' => $renewal_count,
            'subscription_metadata' => json_encode($metadata),
            'created_at' => $order->get_date_created()->format('Y-m-d H:i:s')
        ),
        array('%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
    );

    if ($inserted) {
        wpwa_log_migration($batch_number, $order_id, 'subscription', 'success', null);
    } else {
        wpwa_log_migration($batch_number, $order_id, 'subscription', 'failed', 'Database insert failed: ' . $wpdb->last_error);
    }
}

/**
 * Log migration activity
 */
function wpwa_log_migration($batch_number, $order_id, $type, $status, $error = null) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'wpwa_migration_log',
        array(
            'batch_number' => $batch_number,
            'order_id' => $order_id,
            'migration_type' => $type,
            'status' => $status,
            'error_message' => $error
        )
    );
}

/**
 * AJAX: Verify migration data integrity (ENHANCED)
 */
add_action('wp_ajax_wpwa_verify_migration', 'wpwa_verify_migration_ajax');
function wpwa_verify_migration_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    global $wpdb;

    $checks = array();

    // Check 1: Count match
    $wc_count = count(wc_get_orders(array('limit' => -1, 'status' => array('completed', 'processing'), 'return' => 'ids')));
    $archived_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_orders");
    $checks['Order Count Match'] = ($wc_count === (int)$archived_count) 
        ? "✅ Matched ($wc_count orders)" 
        : "⚠️ Mismatch (WC: $wc_count, Archived: $archived_count)";

    // Check 2: Revenue match
    $wc_revenue = 0;
    $wc_orders = wc_get_orders(array('limit' => -1, 'status' => array('completed', 'processing')));
    foreach ($wc_orders as $order) {
        $wc_revenue += $order->get_total();
    }

    $archived_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}wpwa_archived_orders");
    $revenue_match = abs($wc_revenue - floatval($archived_revenue)) < 0.01;
    $checks['Revenue Match'] = $revenue_match 
        ? "✅ Matched ($" . number_format($wc_revenue, 2) . ")" 
        : "⚠️ Mismatch (WC: $" . number_format($wc_revenue, 2) . ", Archived: $" . number_format($archived_revenue, 2) . ")";

    // Check 3: No duplicates
    $duplicates = $wpdb->get_var(
        "SELECT COUNT(*) FROM (
            SELECT wc_order_id, COUNT(*) as cnt 
            FROM {$wpdb->prefix}wpwa_archived_orders 
            GROUP BY wc_order_id 
            HAVING cnt > 1
        ) as dups"
    );
    $checks['No Duplicates'] = ($duplicates == 0) 
        ? "✅ No duplicates found" 
        : "❌ Found $duplicates duplicate orders";

    // Check 4: All emails present
    $missing_emails = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_orders WHERE customer_email IS NULL OR customer_email = ''"
    );
    $checks['Customer Emails'] = ($missing_emails == 0) 
        ? "✅ All orders have emails" 
        : "⚠️ $missing_emails orders missing emails";

    // Check 5: Failed migrations
    $failed = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_migration_log WHERE status = 'failed'"
    );
    $checks['Failed Migrations'] = ($failed == 0) 
        ? "✅ No failures" 
        : "⚠️ $failed failed migrations (check log)";

    // ========================================
    // NEW CHECKS FOR SUBSCRIPTION DETECTION
    // ========================================

    // Check 6: Subscription detection accuracy
    $total_subscriptions = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_subscriptions"
    );

    // Count WC orders with subscription meta
    $wc_sub_count = 0;
    foreach ($wc_orders as $order) {
        foreach ($order->get_items() as $item) {
            if (wpwa_detect_subscription_from_item($item)) {
                $wc_sub_count++;
                break; // Count order once even if multiple items
            }
        }
    }

    $checks['Subscription Detection'] = "📊 Found $total_subscriptions subscriptions (WC had ~$wc_sub_count items with subscription meta)";

    // Check 7: Active vs Expired subscriptions
    $active_subs = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_subscriptions WHERE status = 'active'"
    );
    $expired_subs = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_subscriptions WHERE status = 'expired'"
    );
    $revoked_subs = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_subscriptions WHERE status = 'revoked'"
    );

    $checks['Subscription Status'] = "✅ Active: $active_subs | Expired: $expired_subs | Revoked: $revoked_subs";

    // Check 8: Subscriptions with valid expiry dates
    $subs_with_expiry = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_subscriptions WHERE expiry_date IS NOT NULL"
    );
    $checks['Subscriptions with Expiry'] = "📅 $subs_with_expiry subscriptions have expiry dates";

    // Check 9: Recurring products current state vs actual subscriptions
    $recurring_products = get_posts(array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_wpwa_is_recurring',
                'value' => 'yes'
            )
        ),
        'fields' => 'ids'
    ));

    $checks['Recurring Products'] = count($recurring_products) . " products currently marked as recurring (independent of order history)";

    wp_send_json_success(array('checks' => $checks));
}

/**
 * AJAX: Analyze subscription detection (for debugging)
 */
add_action('wp_ajax_wpwa_analyze_subscriptions', 'wpwa_analyze_subscriptions_ajax');
function wpwa_analyze_subscriptions_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Get sample of orders
    $orders = wc_get_orders(array(
        'limit' => 50, // First 50 orders
        'status' => array('completed', 'processing'),
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    $analysis = array();

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = wc_get_product($item->get_product_id());
            if (!$product) continue;

            $is_subscription = wpwa_detect_subscription_from_item($item);
            $expiry = $item->get_meta('_wpwa_expiry');
            $licence_status = $item->get_meta('_wpwa_licence_status');

            $analysis[] = array(
                'order_id' => $order->get_id(),
                'product_name' => $product->get_name(),
                'has_expiry' => !empty($expiry),
                'licence_status' => $licence_status ?: 'none',
                'is_subscription' => $is_subscription
            );

            break; // Only first item per order for sample
        }
    }

    wp_send_json_success(array('analysis' => $analysis));
}

/**
 * AJAX: Export migration report
 */
add_action('wp_ajax_wpwa_export_migration_report', 'wpwa_export_migration_report_ajax');
function wpwa_export_migration_report_ajax() {
    check_ajax_referer('wpwa_export_report');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    global $wpdb;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wpwa-migration-report-' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, array(
        'WC Order ID',
        'Order Number',
        'Customer Email',
        'Customer Name',
        'Weebly User ID',
        'Weebly Site ID',
        'Product Name',
        'Amount',
        'Status',
        'Order Date',
        'Migration Date'
    ));

    // Data
    $orders = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}wpwa_archived_orders ORDER BY order_date DESC",
        ARRAY_A
    );

    foreach ($orders as $order) {
        fputcsv($output, array(
            $order['wc_order_id'],
            $order['order_number'],
            $order['customer_email'],
            $order['customer_name'],
            $order['weebly_user_id'],
            $order['weebly_site_id'],
            $order['product_name'],
            '$' . number_format($order['amount'], 2),
            $order['status'],
            $order['order_date'],
            $order['migrated_at']
        ));
    }

    fclose($output);
    exit;
}