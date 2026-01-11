<?php
/**
 * WPWA Analytics Dashboard - Optimized & Clean Version
 * Complete analytics dashboard with real-time metrics
 */

// AJAX handler for dashboard data
add_action('wp_ajax_wpwa_get_dashboard_data', 'wpwa_get_dashboard_data_ajax');
add_action('wp_ajax_nopriv_wpwa_get_dashboard_data', 'wpwa_get_dashboard_data_ajax');

function wpwa_get_dashboard_data_ajax() {
	$input = json_decode(file_get_contents('php://input'), true);
    //check_ajax_referer('wpwa_dashboard_nonce', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    $date_range = isset($input['date_range']) ? sanitize_text_field($input['date_range']) : 'this_month';
    $start_date = isset($input['start_date']) ? sanitize_text_field($input['start_date']) : '';
    $end_date = isset($input['end_date']) ? sanitize_text_field($input['end_date']) : '';
    
    $data = wpwa_calculate_dashboard_metrics($date_range, $start_date, $end_date);
    
    wp_send_json_success($data);
}

function wpwa_calculate_dashboard_metrics($date_range = 'this_month', $start_date = '', $end_date = '') {
    $dates = wpwa_get_date_range($date_range, $start_date, $end_date);
    
    $current_orders = wpwa_get_orders_by_date_range($dates['start'], $dates['end']);
    $previous_orders = wpwa_get_orders_by_date_range($dates['prev_start'], $dates['prev_end']);
    
    $current_metrics = wpwa_calculate_metrics($current_orders);
    $previous_metrics = wpwa_calculate_metrics($previous_orders);
    
    $growth = array(
        'revenue' => wpwa_calculate_growth($current_metrics['total_revenue'], $previous_metrics['total_revenue']),
        'orders' => wpwa_calculate_growth($current_metrics['total_orders'], $previous_metrics['total_orders']),
        'profit' => wpwa_calculate_growth($current_metrics['net_profit'], $previous_metrics['net_profit']),
        'avg_order' => wpwa_calculate_growth($current_metrics['avg_order_value'], $previous_metrics['avg_order_value'])
    );
    
    return array(
        'current' => $current_metrics,
        'growth' => $growth,
        'chart_data' => wpwa_get_chart_data($current_orders, $dates['start'], $dates['end']),
        'top_products' => wpwa_get_top_products($current_orders),
        'top_customers' => wpwa_get_top_customers($current_orders),
        'status_distribution' => wpwa_get_status_distribution($current_orders),
        'recent_orders' => wpwa_get_recent_orders(5)
    );
}

function wpwa_get_date_range($range, $custom_start = '', $custom_end = '') {
    $now = current_time('timestamp');
    
    switch ($range) {
        case 'today':
            $start = date('Y-m-d 00:00:00', $now);
            $end = date('Y-m-d 23:59:59', $now);
            $prev_start = date('Y-m-d 00:00:00', strtotime('-1 day', $now));
            $prev_end = date('Y-m-d 23:59:59', strtotime('-1 day', $now));
            break;
        case 'last_7_days':
            $start = date('Y-m-d 00:00:00', strtotime('-7 days', $now));
            $end = date('Y-m-d 23:59:59', $now);
            $prev_start = date('Y-m-d 00:00:00', strtotime('-14 days', $now));
            $prev_end = date('Y-m-d 23:59:59', strtotime('-7 days', $now));
            break;
        case 'this_month':
            $start = date('Y-m-01 00:00:00', $now);
            $end = date('Y-m-t 23:59:59', $now);
            $prev_start = date('Y-m-01 00:00:00', strtotime('first day of last month', $now));
            $prev_end = date('Y-m-t 23:59:59', strtotime('last day of last month', $now));
            break;
        case 'last_month':
            $start = date('Y-m-01 00:00:00', strtotime('first day of last month', $now));
            $end = date('Y-m-t 23:59:59', strtotime('last day of last month', $now));
            $prev_start = date('Y-m-01 00:00:00', strtotime('first day of -2 months', $now));
            $prev_end = date('Y-m-t 23:59:59', strtotime('last day of -2 months', $now));
            break;
        case 'custom':
            $start = $custom_start ? $custom_start . ' 00:00:00' : date('Y-m-01 00:00:00', $now);
            $end = $custom_end ? $custom_end . ' 23:59:59' : date('Y-m-t 23:59:59', $now);
            $days_diff = (strtotime($end) - strtotime($start)) / 86400;
            $prev_start = date('Y-m-d 00:00:00', strtotime("-{$days_diff} days", strtotime($start)));
            $prev_end = date('Y-m-d 23:59:59', strtotime("-1 day", strtotime($start)));
            break;
        default:
            $start = date('Y-m-01 00:00:00', $now);
            $end = date('Y-m-t 23:59:59', $now);
            $prev_start = date('Y-m-01 00:00:00', strtotime('first day of last month', $now));
            $prev_end = date('Y-m-t 23:59:59', strtotime('last day of last month', $now));
    }
    
    return compact('start', 'end', 'prev_start', 'prev_end');
}

/**
 * Get orders by date range (HPOS compatible)
 */
function wpwa_get_orders_by_date_range($start, $end) {
    $query = new WC_Order_Query([
        'limit'        => -1,
        'type'         => 'shop_order',
        'status'       => 'wc-completed',
        'date_created' => $start . '...' . $end,
        'orderby'      => 'date',
        'order'        => 'DESC',
        'return'       => 'objects' // Ensure we get order objects
    ]);   
    return $query->get_orders();
}

/**
 * Calculate metrics (HPOS compatible)
 */
function wpwa_calculate_metrics($orders) {
    $metrics = [
        'total_revenue'         => 0,
        'total_fees'            => 0,
        'total_weebly_payout'   => 0,
        'net_profit'            => 0,
        'total_orders'          => count($orders),
        'avg_order_value'       => 0
    ];
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        $gross = $order->get_total() - $order->get_total_tax();
        $fee = ((2.9/100) * $gross) + 0.52;
        $net = $gross - $fee;
        $weebly = (30/100) * $net;   
        $metrics['total_revenue'] += $gross;
        $metrics['total_fees'] += $fee;
        $metrics['total_weebly_payout'] += $weebly;
        $metrics['net_profit'] += ($gross - $fee - $weebly);
    }
    $metrics['avg_order_value'] = $metrics['total_orders'] > 0 
        ? $metrics['total_revenue'] / $metrics['total_orders'] 
        : 0;   
    return $metrics;
}

function wpwa_calculate_growth($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 2);
}

/**
 * Get chart data (HPOS compatible)
 */
function wpwa_get_chart_data($orders, $start, $end) {
    $data = [];
    $current = strtotime($start);
    $end_time = strtotime($end);
    // Initialize all dates
    while ($current <= $end_time) {
        $date = date('Y-m-d', $current);
        $data[$date] = ['revenue' => 0, 'profit' => 0];
        $current = strtotime('+1 day', $current);
    }
    // Fill with order data
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        $date = $order->get_date_created()->format('Y-m-d');
        if (isset($data[$date])) {
            $gross = $order->get_total() - $order->get_total_tax();
            $fee = ((2.9/100) * $gross) + 0.52;
            $net = $gross - $fee;
            $weebly = (30/100) * $net;       
            $data[$date]['revenue'] += $gross;
            $data[$date]['profit'] += ($gross - $fee - $weebly);
        }
    }   
    return $data;
}

/**
 * Get top products (HPOS compatible)
 */
function wpwa_get_top_products($orders, $limit = 5) {
    $products = [];
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        $items = $order->get_items();
        foreach ($items as $item) {
            $name = $item->get_name();
            $gross = $order->get_total() - $order->get_total_tax();
            if (!isset($products[$name])) {
                $products[$name] = [
                    'name'    => $name,
                    'revenue' => 0,
                    'orders'  => 0
                ];
            }       
            $products[$name]['revenue'] += $gross;
            $products[$name]['orders']++;
        }
    }
    usort($products, function($a, $b) {
        return $b['revenue'] - $a['revenue'];
    });   
    return array_slice($products, 0, $limit);
}

/**
 * Get top customers (HPOS compatible)
 */
function wpwa_get_top_customers($orders, $limit = 5) {
    $customers = [];
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        $email = $order->get_billing_email();
        $gross = $order->get_total() - $order->get_total_tax();
        if (!isset($customers[$email])) {
            $customers[$email] = [
                'email'   => $email,
                'revenue' => 0,
                'orders'  => 0
            ];
        }   
        $customers[$email]['revenue'] += $gross;
        $customers[$email]['orders']++;
    }
    usort($customers, function($a, $b) {
        return $b['revenue'] - $a['revenue'];
    });   
    return array_slice($customers, 0, $limit);
}

/**
 * Get status distribution (HPOS compatible)
 */
function wpwa_get_status_distribution($orders) {
    $statuses = [
        'notified'    => 0,
        'completed'   => 0,
        'for-testing' => 0,
        'refunded'    => 0,
        'pending'     => 0
    ];
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        // Get meta using order object (HPOS compatible)
        $status = $order->get_meta('weebly_notification');
        if (empty($status)) {
            $status = 'pending';
        }   
        if (isset($statuses[$status])) {
            $statuses[$status]++;
        }
    }   
    return $statuses;
}

/**
 * Get recent orders (HPOS compatible)
 */
function wpwa_get_recent_orders($limit = 5) {
    $query = new WC_Order_Query([
        'limit'   => $limit,
        'type'    => 'shop_order',
        'status'  => 'wc-completed',
        'orderby' => 'date',
        'order'   => 'DESC',
        'return'  => 'objects'
    ]);
    $orders = $query->get_orders();
    $recent = [];
    foreach ($orders as $order) {
        // Ensure we have a valid order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            continue;
        }
        $items = $order->get_items();
        $product = '';
        foreach ($items as $item) {
            $product = $item->get_name();
            break;
        }   
        $recent[] = [
            'id'       => $order->get_id(),
            'product'  => $product,
            'customer' => $order->get_billing_email(),
            'amount'   => $order->get_total() - $order->get_total_tax(),
            'date'     => $order->get_date_created()->format('Y-m-d H:i:s'),
            'status'   => $order->get_meta('weebly_notification')
        ];
    }   
    return $recent;
}

function wpwa_growth_badge($growth) {
    $class = $growth >= 0 ? 'positive' : 'negative';
    $icon = $growth >= 0 ? 'arrow-up-alt' : 'arrow-down-alt';
    return sprintf('<span class="wpwa-badge %s"><span class="dashicons dashicons-%s"></span>%+.1f%%</span>', $class, $icon, $growth);
}
