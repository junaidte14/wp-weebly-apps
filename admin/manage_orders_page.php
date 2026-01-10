<?php
/**
 * Enhanced Weebly Orders Management Page
 * Improved security, UX, UI, and features
 */

// Security: Check user capabilities
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get pagination and filter parameters
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
$view = isset($_GET['sub_page']) && $_GET['sub_page'] == 'action_needed' ? 'action_needed' : 'all';

// Build query args
$query_args = array(
    'limit' => $per_page,
    'type' => 'shop_order',
    'orderby' => 'date',
    'order' => 'DESC',
    'status' => 'wc-completed',
    'paginate' => true,
    'page' => $paged,
);

if ($search) {
    $query_args['s'] = $search;
}

$query = new WC_Order_Query($query_args);
$results = $query->get_orders();
$orders = $results->orders;
$total_orders = $results->total;
$max_pages = $results->max_num_pages;

?>

<div class="wrap wpwa-orders-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-cart"></span> Weebly App Orders Management
    </h1>
    
    <!-- Admin Notice Container -->
    <div id="wpwa-notice-container"></div>
    
    <!-- Filters and Search -->
    <div class="wpwa-filters-bar">
        <div class="wpwa-view-tabs">
            <a href="?page=wpwa_manage_orders" class="wpwa-tab <?php echo $view === 'all' ? 'active' : ''; ?>">
                All Orders (<?php echo $total_orders; ?>)
            </a>
            <a href="?page=wpwa_manage_orders&sub_page=action_needed" class="wpwa-tab <?php echo $view === 'action_needed' ? 'active' : ''; ?>">
                <span class="dashicons dashicons-warning"></span> Action Needed
            </a>
        </div>
        
        <div class="wpwa-toolbar">
            <form method="get" class="wpwa-search-form">
                <input type="hidden" name="page" value="wpwa_manage_orders">
                <?php if ($view === 'action_needed') : ?>
                    <input type="hidden" name="sub_page" value="action_needed">
                <?php endif; ?>
                
                <select name="status_filter" class="wpwa-filter-select">
                    <option value="">All Statuses</option>
                    <option value="notified" <?php selected($status_filter, 'notified'); ?>>Notified</option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                    <option value="for-testing" <?php selected($status_filter, 'for-testing'); ?>>For Testing</option>
                    <option value="refunded" <?php selected($status_filter, 'refunded'); ?>>Refunded</option>
                    <option value="access_removed" <?php selected($status_filter, 'access_removed'); ?>>Access Removed</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by Order ID or Email..." class="wpwa-search-input">
                <button type="submit" class="button">
                    <span class="dashicons dashicons-search"></span> Search
                </button>
                
                <button type="button" class="button wpwa-export-btn">
                    <span class="dashicons dashicons-download"></span> Export CSV
                </button>
            </form>
        </div>
    </div>
    
    <!-- Orders Table -->
    <div class="wpwa-table-container">
        <table class="wp-list-table widefat fixed striped wpwa-orders-table">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="wpwa-select-all"></th>
                    <th class="column-order-id">Order ID</th>
                    <th class="column-product">Product</th>
                    <th class="column-customer">Customer</th>
                    <th class="column-amount">Amount</th>
                    <th class="column-fees">Fees & Payouts</th>
                    <th class="column-status">Status</th>
                    <th class="column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)) : ?>
                <tr>
                    <td colspan="8" class="wpwa-no-orders">
                        <span class="dashicons dashicons-info"></span>
                        No orders found matching your criteria.
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($orders as $order) : 
                    $order_id = $order->get_id();
                    $items = $order->get_items();
                    $product_name = '';
                    $product_id = '';
                    $access_token = '';
                    $site_id = '';
                    $user_id = '';
                    
                    foreach ($items as $item) {
                        $product_name = $item->get_name();
                        $product_id = $item->get_product_id();
                        $access_token = $item->get_meta('access_token') ?: '';
                        $site_id = $item->get_meta('site_id') ?: '';
                        $user_id = $item->get_meta('user_id') ?: '';
                    }
                    
                    $gross_amount = $order->get_total() - $order->get_total_tax();
                    $fee = ((2.9/100) * $gross_amount) + 0.52;
                    $net_amount = $gross_amount - $fee;
                    $weebly_amount = (30/100) * $net_amount;
                    
                    $status = $order->get_meta('weebly_notification');
                    
                    // Skip if action_needed view and status is completed or for-testing
                    if ($view === 'action_needed' && ($status === 'completed' || $status === 'for-testing')) {
                        continue;
                    }
                    
                    // Apply status filter
                    if ($status_filter && $status !== $status_filter) {
                        continue;
                    }
                ?>
                <tr data-order-id="<?php echo esc_attr($order_id); ?>" class="wpwa-order-row">
                    <td class="check-column">
                        <input type="checkbox" class="wpwa-order-checkbox" value="<?php echo esc_attr($order_id); ?>">
                    </td>
                    <td class="column-order-id">
                        <strong>#<?php echo $order_id; ?></strong>
                        <div class="row-actions">
                            <span class="view">
                                <a href="<?php echo get_edit_post_link($order_id); ?>" target="_blank">View in WooCommerce</a>
                            </span>
                        </div>
                    </td>
                    <td class="column-product">
                        <strong><?php echo esc_html($product_name); ?></strong>
                        <?php if ($access_token) : ?>
                            <br><small class="wpwa-meta">Token: <?php echo esc_html(substr($access_token, 0, 20)) . '...'; ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="column-customer">
                        <?php echo esc_html($order->get_billing_email()); ?>
                        <?php if ($user_id) : ?>
                            <br><small class="wpwa-meta">User ID: <?php echo esc_html($user_id); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="column-amount">
                        <strong><?php echo $order->get_formatted_order_total(); ?></strong>
                    </td>
                    <td class="column-fees">
                        <div class="wpwa-fee-breakdown">
                            <small>
                                Gross: $<?php echo number_format($gross_amount, 2); ?><br>
                                Fee: $<?php echo number_format($fee, 2); ?><br>
                                <strong>Weebly: $<?php echo number_format($weebly_amount, 2); ?></strong>
                            </small>
                        </div>
                    </td>
                    <td class="column-status">
                        <?php echo wpwa_get_status_badge($status); ?>
                    </td>
                    <td class="column-actions">
                        <div class="wpwa-action-buttons">
                            <?php if ($status !== 'notified' && $access_token) : ?>
                                <button class="button button-small wpwa-action-btn wpwa-notify-btn" 
                                        data-action="notified"
                                        data-order-id="<?php echo esc_attr($order_id); ?>"
                                        data-gross="<?php echo esc_attr($gross_amount); ?>"
                                        data-net="<?php echo esc_attr($net_amount); ?>"
                                        data-payable="<?php echo esc_attr($weebly_amount); ?>"
                                        data-token="<?php echo esc_attr($access_token); ?>"
                                        data-app-name="<?php echo esc_attr($product_name); ?>">
                                    <span class="dashicons dashicons-email"></span> Notify
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($status !== 'completed') : ?>
                                <button class="button button-small wpwa-action-btn" 
                                        data-action="completed"
                                        data-order-id="<?php echo esc_attr($order_id); ?>">
                                    <span class="dashicons dashicons-yes"></span> Complete
                                </button>
                            <?php endif; ?>
                            
                            <div class="wpwa-dropdown">
                                <button class="button button-small wpwa-dropdown-toggle">
                                    <span class="dashicons dashicons-menu"></span> More
                                </button>
                                <div class="wpwa-dropdown-menu">
                                    <a href="#" class="wpwa-action-btn" data-action="for-testing" data-order-id="<?php echo esc_attr($order_id); ?>">
                                        <span class="dashicons dashicons-admin-tools"></span> Mark for Testing
                                    </a>
                                    <a href="#" class="wpwa-action-btn" data-action="refunded" data-order-id="<?php echo esc_attr($order_id); ?>">
                                        <span class="dashicons dashicons-undo"></span> Mark Refunded
                                    </a>
                                    <?php if ($access_token && $site_id && $product_id) : ?>
                                        <a href="#" class="wpwa-action-btn wpwa-danger" 
                                           data-action="remove_access" 
                                           data-order-id="<?php echo esc_attr($order_id); ?>"
                                           data-site-id="<?php echo esc_attr($site_id); ?>"
                                           data-user-id="<?php echo esc_attr($user_id); ?>"
                                           data-app-id="<?php echo esc_attr($product_id); ?>"
                                           data-token="<?php echo esc_attr($access_token); ?>">
                                            <span class="dashicons dashicons-lock"></span> Remove Access
                                        </a>
                                        <a href="#" class="wpwa-action-btn wpwa-danger" 
                                           data-action="delete" 
                                           data-order-id="<?php echo esc_attr($order_id); ?>"
                                           data-site-id="<?php echo esc_attr($site_id); ?>"
                                           data-user-id="<?php echo esc_attr($user_id); ?>"
                                           data-app-id="<?php echo esc_attr($product_id); ?>"
                                           data-token="<?php echo esc_attr($access_token); ?>">
                                            <span class="dashicons dashicons-trash"></span> Delete Order
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($max_pages > 1) : ?>
    <div class="wpwa-pagination">
        <?php
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'total' => $max_pages,
            'current' => $paged
        ));
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div id="wpwa-loading-overlay" style="display:none;">
    <div class="wpwa-spinner"></div>
</div>

<!-- Confirmation Modal -->
<div id="wpwa-confirm-modal" class="wpwa-modal" style="display:none;">
    <div class="wpwa-modal-content">
        <span class="wpwa-modal-close">&times;</span>
        <h2 id="wpwa-modal-title">Confirm Action</h2>
        <p id="wpwa-modal-message">Are you sure you want to proceed?</p>
        <div class="wpwa-modal-actions">
            <button class="button button-primary" id="wpwa-modal-confirm">Confirm</button>
            <button class="button" id="wpwa-modal-cancel">Cancel</button>
        </div>
    </div>
</div>

<?php
function wpwa_get_status_badge($status) {
    $badges = array(
        'notified' => '<span class="wpwa-badge wpwa-badge-success"><span class="dashicons dashicons-yes"></span> Notified</span>',
        'completed' => '<span class="wpwa-badge wpwa-badge-info"><span class="dashicons dashicons-saved"></span> Completed</span>',
        'for-testing' => '<span class="wpwa-badge wpwa-badge-warning"><span class="dashicons dashicons-admin-tools"></span> Testing</span>',
        'refunded' => '<span class="wpwa-badge wpwa-badge-danger"><span class="dashicons dashicons-undo"></span> Refunded</span>',
        'access_removed' => '<span class="wpwa-badge wpwa-badge-default"><span class="dashicons dashicons-lock"></span> Access Removed</span>',
    );
    
    return isset($badges[$status]) ? $badges[$status] : '<span class="wpwa-badge wpwa-badge-default">Pending</span>';
}
?>

<style>
/* Enhanced Styling */
.wpwa-orders-wrap {
    background: #fff;
    padding: 20px;
    margin: 20px 20px 20px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.wp-heading-inline {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.wpwa-filters-bar {
    background: #f9f9f9;
    padding: 15px;
    margin: 20px 0;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

.wpwa-view-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.wpwa-tab {
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    background: #fff;
    border: 1px solid #ddd;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.wpwa-tab:hover {
    background: #f0f0f0;
}

.wpwa-tab.active {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.wpwa-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wpwa-search-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.wpwa-search-input {
    min-width: 250px;
    padding: 6px 12px;
}

.wpwa-filter-select {
    padding: 6px 12px;
    min-width: 150px;
}

.wpwa-table-container {
    overflow-x: auto;
    margin: 20px 0;
}

.wpwa-orders-table {
    background: #fff;
}

.wpwa-orders-table thead th {
    background: #f9f9f9;
    font-weight: 600;
    padding: 12px;
}

.wpwa-orders-table tbody td {
    padding: 12px;
    vertical-align: top;
}

.wpwa-order-row:hover {
    background: #f5f5f5;
}

.wpwa-meta {
    color: #666;
    font-size: 12px;
}

.wpwa-fee-breakdown {
    font-size: 12px;
    line-height: 1.6;
}

.wpwa-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.wpwa-badge .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.wpwa-badge-success {
    background: #d4edda;
    color: #155724;
}

.wpwa-badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.wpwa-badge-warning {
    background: #fff3cd;
    color: #856404;
}

.wpwa-badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.wpwa-badge-default {
    background: #e2e3e5;
    color: #383d41;
}

.wpwa-action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.wpwa-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    font-size: 12px;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}

.wpwa-action-btn .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
}

.wpwa-notify-btn {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.wpwa-notify-btn:hover {
    background: #135e96;
}

.wpwa-danger {
    color: #d63638;
}

.wpwa-danger:hover {
    color: #a00;
    background: #fef1f1;
}

.wpwa-dropdown {
    position: relative;
    display: inline-block;
}

.wpwa-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    background: #fff;
    min-width: 200px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    border: 1px solid #ddd;
    border-radius: 4px;
    z-index: 1000;
    margin-top: 4px;
}

.wpwa-dropdown.active .wpwa-dropdown-menu {
    display: block;
}

.wpwa-dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    text-decoration: none;
    color: #333;
    border-bottom: 1px solid #f0f0f0;
}

.wpwa-dropdown-menu a:last-child {
    border-bottom: none;
}

.wpwa-dropdown-menu a:hover {
    background: #f5f5f5;
}

.wpwa-no-orders {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.wpwa-no-orders .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    margin-right: 8px;
}

.wpwa-pagination {
    text-align: center;
    padding: 20px 0;
}

.wpwa-pagination .page-numbers {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.2s;
}

.wpwa-pagination .page-numbers:hover,
.wpwa-pagination .page-numbers.current {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

#wpwa-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.wpwa-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2271b1;
    border-radius: 50%;
    animation: wpwa-spin 1s linear infinite;
}

@keyframes wpwa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.wpwa-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    animation: wpwa-fadeIn 0.3s;
}

.wpwa-modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    width: 90%;
    max-width: 500px;
    animation: wpwa-slideIn 0.3s;
}

@keyframes wpwa-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes wpwa-slideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.wpwa-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.wpwa-modal-close:hover {
    color: #000;
}

.wpwa-modal-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

#wpwa-notice-container {
    margin: 15px 0;
}

.wpwa-notice {
    padding: 12px 20px;
    margin: 10px 0;
    border-left: 4px solid;
    border-radius: 4px;
    animation: wpwa-slideDown 0.3s;
}

@keyframes wpwa-slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.wpwa-notice-success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.wpwa-notice-error {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .wpwa-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .wpwa-search-form {
        flex-direction: column;
    }
    
    .wpwa-search-input {
        width: 100%;
    }
    
    .wpwa-action-buttons {
        flex-direction: column;
    }
}
</style>