<?php
/**
 * HPOS Compatibility Test Script
 * Add this as a temporary admin page to test HPOS compatibility
 * 
 * Usage: Add to your plugin and visit wp-admin/admin.php?page=wpwa_hpos_test
 */

add_action('admin_menu', function() {
    add_submenu_page(
        'wpwa',
        'HPOS Test',
        'HPOS Test',
        'manage_options',
        'wpwa_hpos_test',
        'wpwa_render_hpos_test_page'
    );
});

function wpwa_render_hpos_test_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    echo '<div class="wrap">';
    echo '<h1>HPOS Compatibility Test</h1>';
    
    // Test 1: Check HPOS status
    echo '<h2>1. HPOS Status</h2>';
    if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
        $using_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        echo '<p>HPOS Enabled: <strong>' . ($using_hpos ? 'YES ✅' : 'NO ❌') . '</strong></p>';
    } else {
        echo '<p>OrderUtil class not found - WooCommerce may be outdated</p>';
    }
    
    // Test 3: Query orders using WC_Order_Query
    echo '<h2>3. Order Query Test</h2>';
    $start_time = microtime(true);
    
    $args = [
        'limit'  => 5,
        'status' => 'wc-completed',
        'return' => 'objects'
    ];
    
    $orders = wc_get_orders($args);
    $query_time = (microtime(true) - $start_time) * 1000;
    
    echo '<p>Query completed in: <strong>' . number_format($query_time, 2) . 'ms</strong></p>';
    echo '<p>Orders found: <strong>' . count($orders) . '</strong></p>';
    
    if (!empty($orders)) {
        echo '<table class="widefat">';
        echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Total</th><th>Site ID</th><th>User ID</th><th>Status Meta</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($orders as $order) {
            if (!is_a($order, 'WC_Order')) {
                echo '<tr><td colspan="6" style="color:red;">⚠️ Not a WC_Order object!</td></tr>';
                continue;
            }
            
            $site_id = '';
            $user_id = '';
            
            foreach ($order->get_items() as $item) {
                $site_id = $item->get_meta('site_id');
                $user_id = $item->get_meta('user_id');
                break;
            }
            
            $status_meta = $order->get_meta('weebly_notification');
            
            echo '<tr>';
            echo '<td>' . $order->get_id() . '</td>';
            echo '<td>' . esc_html($order->get_billing_email()) . '</td>';
            echo '<td>' . $order->get_formatted_order_total() . '</td>';
            echo '<td>' . esc_html($site_id ?: '—') . '</td>';
            echo '<td>' . esc_html($user_id ?: '—') . '</td>';
            echo '<td>' . esc_html($status_meta ?: 'pending') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Test 4: Check for problematic code patterns
    echo '<h2>4. Code Pattern Scan</h2>';
    
    $problematic_files = [];
    $plugin_dir = WPWA_BASE_DIR;
    
    // Files to check
    $files_to_check = [
        'woocommerce/woo-integration.php',
        'admin/admin-ajax-handlers.php',
        'admin/wpwa-analytics.php',
        'includes/class-wpwa-recurring.php'
    ];
    
    foreach ($files_to_check as $file) {
        $full_path = $plugin_dir . '/' . $file;
        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);
            
            $issues = [];
            
            // Check for direct post meta access on orders
            if (preg_match('/get_post_meta\s*\(\s*\$.*order.*\$/', $content)) {
                $issues[] = 'Uses get_post_meta() on orders';
            }
            if (preg_match('/update_post_meta\s*\(\s*\$.*order.*\$/', $content)) {
                $issues[] = 'Uses update_post_meta() on orders';
            }
            
            // Check for direct DB queries
            if (preg_match('/\$wpdb->get_results.*wp_posts.*shop_order/', $content)) {
                $issues[] = 'Direct database query on wp_posts for orders';
            }
            
            // Check for WP_Query on orders
            if (preg_match('/new\s+WP_Query.*post_type.*shop_order/', $content)) {
                $issues[] = 'Uses WP_Query for orders';
            }
            
            if (!empty($issues)) {
                $problematic_files[$file] = $issues;
            }
        }
    }
    
    if (empty($problematic_files)) {
        echo '<p style="color:green;"><strong>✅ No problematic code patterns detected!</strong></p>';
    } else {
        echo '<p style="color:red;"><strong>⚠️ Found potential issues:</strong></p>';
        echo '<ul>';
        foreach ($problematic_files as $file => $issues) {
            echo '<li><strong>' . esc_html($file) . '</strong><ul>';
            foreach ($issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul></li>';
        }
        echo '</ul>';
    }
    
    // Test 5: Meta data storage test
    echo '<h2>5. Meta Data Storage Test</h2>';
    
    if (!empty($orders)) {
        $test_order = $orders[0];
        $original_value = $test_order->get_meta('_wpwa_test_meta');
        
        // Write test
        $test_value = 'test_' . time();
        $test_order->update_meta_data('_wpwa_test_meta', $test_value);
        $test_order->save();
        
        // Read test
        $order_refreshed = wc_get_order($test_order->get_id());
        $read_value = $order_refreshed->get_meta('_wpwa_test_meta');
        
        if ($read_value === $test_value) {
            echo '<p style="color:green;"><strong>✅ Meta data write/read test passed!</strong></p>';
        } else {
            echo '<p style="color:red;"><strong>❌ Meta data write/read test failed!</strong></p>';
            echo '<p>Expected: ' . esc_html($test_value) . '</p>';
            echo '<p>Got: ' . esc_html($read_value) . '</p>';
        }
        
        // Cleanup (restore original or delete)
        if ($original_value) {
            $test_order->update_meta_data('_wpwa_test_meta', $original_value);
        } else {
            $test_order->delete_meta_data('_wpwa_test_meta');
        }
        $test_order->save();
    }
    
    // Test 6: Analytics function test
    echo '<h2>6. Analytics Functions Test</h2>';
    
    if (function_exists('wpwa_calculate_dashboard_metrics')) {
        $start_time = microtime(true);
        $metrics = wpwa_calculate_dashboard_metrics('this_month');
        $calc_time = (microtime(true) - $start_time) * 1000;
        
        echo '<p>Calculation time: <strong>' . number_format($calc_time, 2) . 'ms</strong></p>';
        
        if (isset($metrics['current'])) {
            echo '<table class="widefat">';
            echo '<tr><th>Metric</th><th>Value</th></tr>';
            echo '<tr><td>Total Revenue</td><td>$' . number_format($metrics['current']['total_revenue'], 2) . '</td></tr>';
            echo '<tr><td>Total Orders</td><td>' . $metrics['current']['total_orders'] . '</td></tr>';
            echo '<tr><td>Net Profit</td><td>$' . number_format($metrics['current']['net_profit'], 2) . '</td></tr>';
            echo '</table>';
        }
    } else {
        echo '<p style="color:orange;">⚠️ Analytics function not found</p>';
    }
    
    // Summary
    echo '<h2>Summary</h2>';
    echo '<p>Review the results above. For full HPOS compatibility:</p>';
    echo '<ul>';
    echo '<li>HPOS should be enabled ✅</li>';
    echo '<li>Plugin should declare compatibility ✅</li>';
    echo '<li>No problematic code patterns ✅</li>';
    echo '<li>Meta data storage working ✅</li>';
    echo '<li>Query performance acceptable (&lt;100ms) ✅</li>';
    echo '</ul>';
    
    echo '</div>';
}