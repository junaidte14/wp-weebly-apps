<?php
/**
 * WooCommerce Data Migration Dashboard
 * File: admin/wc-migration-page.php
 */

if (!defined('ABSPATH')) exit;

function wpwa_render_migration_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Insufficient permissions', 'wpwa'));
    }

    global $wpdb;

    // Get migration status
    $total_wc_orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('completed', 'processing'),
        'return' => 'ids'
    ));
    $total_wc_count = count($total_wc_orders);

    $migrated_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_orders"
    );

    $migration_percent = $total_wc_count > 0 
        ? round(($migrated_count / $total_wc_count) * 100, 2) 
        : 0;

    $last_batch = $wpdb->get_var(
        "SELECT MAX(batch_number) FROM {$wpdb->prefix}wpwa_migration_log"
    ) ?: 0;

    // Handle actions
    if (isset($_POST['wpwa_migrate_batch'])) {
        check_admin_referer('wpwa_migration_batch');
        $batch_size = absint($_POST['batch_size']);
        $result = wpwa_migrate_wc_orders_batch($batch_size, $last_batch + 1);
        
        echo '<div class="notice notice-success"><p>';
        printf(
            __('Migrated %d orders in batch #%d', 'wpwa'),
            $result['migrated'],
            $last_batch + 1
        );
        echo '</p></div>';
        
        // Refresh counts
        $migrated_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_orders"
        );
        $migration_percent = $total_wc_count > 0 
            ? round(($migrated_count / $total_wc_count) * 100, 2) 
            : 0;
        $last_batch++;
    }

    if (isset($_POST['wpwa_migrate_all'])) {
        check_admin_referer('wpwa_migration_all');
        set_time_limit(600); // 10 minutes
        
        $total_migrated = 0;
        $batch_num = $last_batch + 1;
        
        while (true) {
            $result = wpwa_migrate_wc_orders_batch(100, $batch_num);
            $total_migrated += $result['migrated'];
            
            if ($result['migrated'] === 0) {
                break; // No more orders to migrate
            }
            
            $batch_num++;
            
            if ($batch_num > $last_batch + 50) {
                break; // Safety limit: max 50 batches per click
            }
        }
        
        echo '<div class="notice notice-success"><p>';
        printf(__('Migration complete! Migrated %d orders total.', 'wpwa'), $total_migrated);
        echo '</p></div>';
        
        // Refresh counts
        $migrated_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpwa_archived_orders"
        );
        $migration_percent = 100;
    }

    ?>
    <div class="wrap wpwa-migration-wrap">
        <h1>
            <span class="dashicons dashicons-database-export"></span>
            WooCommerce Data Migration
        </h1>

        <div class="wpwa-migration-stats">
            <div class="wpwa-stat-card">
                <h3>Total WooCommerce Orders</h3>
                <div class="wpwa-stat-number"><?php echo number_format($total_wc_count); ?></div>
            </div>

            <div class="wpwa-stat-card wpwa-stat-success">
                <h3>Migrated to Archive</h3>
                <div class="wpwa-stat-number"><?php echo number_format($migrated_count); ?></div>
            </div>

            <div class="wpwa-stat-card wpwa-stat-info">
                <h3>Migration Progress</h3>
                <div class="wpwa-stat-number"><?php echo $migration_percent; ?>%</div>
                <div class="wpwa-progress-bar">
                    <div class="wpwa-progress-fill" style="width: <?php echo $migration_percent; ?>%;"></div>
                </div>
            </div>

            <div class="wpwa-stat-card">
                <h3>Last Batch Number</h3>
                <div class="wpwa-stat-number"><?php echo $last_batch; ?></div>
            </div>
        </div>

        <?php if ($migration_percent < 100): ?>
            <div class="wpwa-migration-actions">
                <h2>Migration Options</h2>

                <div class="wpwa-action-card">
                    <h3>🔵 Batch Migration (Recommended)</h3>
                    <p>Migrate orders in small batches to avoid timeouts. Run multiple times until complete.</p>

                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('wpwa_migration_batch'); ?>
                        <label>
                            Batch Size: 
                            <input type="number" name="batch_size" value="100" min="10" max="500" style="width: 80px;">
                        </label>
                        <button type="submit" name="wpwa_migrate_batch" class="button button-primary button-large">
                            <span class="dashicons dashicons-controls-play"></span>
                            Run Next Batch
                        </button>
                    </form>
                </div>

                <div class="wpwa-action-card wpwa-warning-card">
                    <h3>🟠 Full Migration (Use with caution)</h3>
                    <p><strong>Warning:</strong> This will attempt to migrate ALL remaining orders at once. May take 5-10 minutes.</p>

                    <form method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure? This may take several minutes.');">
                        <?php wp_nonce_field('wpwa_migration_all'); ?>
                        <button type="submit" name="wpwa_migrate_all" class="button button-secondary button-large">
                            <span class="dashicons dashicons-database-export"></span>
                            Migrate All Remaining
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="notice notice-success notice-large">
                <h2>✅ Migration Complete!</h2>
                <p>All WooCommerce orders have been successfully migrated to the archive tables.</p>
                <p><strong>Next steps:</strong></p>
                <ol>
                    <li>Verify data integrity (check archived orders page)</li>
                    <li>Enable Stripe payment system</li>
                    <li>Test new checkout flow</li>
                    <li>Once confirmed working, you can safely disable WooCommerce plugin</li>
                </ol>
            </div>
        <?php endif; ?>

        <hr>

        <h2>Recent Migration Log</h2>
        <?php wpwa_render_migration_log_table(); ?>

        <hr>

        <h2>Verification Tools</h2>
        <div class="wpwa-verification-tools">
            <button type="button" class="button" onclick="wpwaVerifyMigration()">
                <span class="dashicons dashicons-yes-alt"></span>
                Verify Data Integrity
            </button>
            <button type="button" class="button" onclick="wpwaExportReport()">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                Export Migration Report
            </button>
        </div>

        <hr>

        <h2>Subscription Detection Analysis</h2>
        <button type="button" class="button" onclick="wpwaAnalyzeSubscriptions()">
            <span class="dashicons dashicons-search"></span>
            Analyze Subscription Detection
        </button>

        <div id="wpwa-subscription-analysis"></div>

        <script>
        function wpwaAnalyzeSubscriptions() {
            document.getElementById('wpwa-subscription-analysis').innerHTML = '<p>Analyzing...</p>';
            
            fetch(ajaxurl + '?action=wpwa_analyze_subscriptions')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="notice notice-info" style="padding: 20px;"><h3>Subscription Detection Analysis</h3>';
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                        html += '<th>Order ID</th><th>Product</th><th>Has Expiry</th><th>Licence Status</th><th>Detected as</th></tr></thead><tbody>';
                        
                        data.data.analysis.forEach(function(item) {
                            html += '<tr>';
                            html += '<td>#' + item.order_id + '</td>';
                            html += '<td>' + item.product_name + '</td>';
                            html += '<td>' + (item.has_expiry ? '✅ Yes' : '❌ No') + '</td>';
                            html += '<td>' + item.licence_status + '</td>';
                            html += '<td><strong>' + (item.is_subscription ? '🔄 Subscription' : '💵 One-time') + '</strong></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                        document.getElementById('wpwa-subscription-analysis').innerHTML = html;
                    }
                });
        }
        </script>

        <div id="wpwa-verification-result"></div>
    </div>

    <style>
    .wpwa-migration-wrap { background: #f5f5f5; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-migration-wrap h1 { display: flex; align-items: center; gap: 10px; }
    .wpwa-migration-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
    .wpwa-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .wpwa-stat-card h3 { margin: 0 0 10px; font-size: 14px; color: #666; }
    .wpwa-stat-number { font-size: 36px; font-weight: 700; color: #1d2327; }
    .wpwa-stat-success .wpwa-stat-number { color: #2ecc71; }
    .wpwa-stat-info .wpwa-stat-number { color: #3498db; }
    .wpwa-progress-bar { background: #e0e0e0; height: 10px; border-radius: 5px; margin-top: 10px; overflow: hidden; }
    .wpwa-progress-fill { background: #3498db; height: 100%; transition: width 0.3s; }
    .wpwa-migration-actions { margin: 25px 0; }
    .wpwa-action-card { background: #fff; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #3498db; }
    .wpwa-warning-card { border-left-color: #f39c12; }
    .wpwa-action-card h3 { margin-top: 0; }
    .notice-large { padding: 20px; }
    .notice-large h2 { margin-top: 0; }
    .wpwa-verification-tools { margin: 20px 0; }
    #wpwa-verification-result { margin-top: 20px; }
    </style>

    <script>
    function wpwaVerifyMigration() {
        document.getElementById('wpwa-verification-result').innerHTML = '<p>Verifying data integrity...</p>';
        
        fetch(ajaxurl + '?action=wpwa_verify_migration')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let html = '<div class="notice notice-success"><h3>✅ Verification Passed</h3><ul>';
                    for (let key in data.data.checks) {
                        html += '<li><strong>' + key + ':</strong> ' + data.data.checks[key] + '</li>';
                    }
                    html += '</ul></div>';
                    document.getElementById('wpwa-verification-result').innerHTML = html;
                } else {
                    document.getElementById('wpwa-verification-result').innerHTML = 
                        '<div class="notice notice-error"><p>' + data.data.message + '</p></div>';
                }
            });
    }

    function wpwaExportReport() {
        window.location.href = ajaxurl + '?action=wpwa_export_migration_report&_wpnonce=' + 
            '<?php echo wp_create_nonce('wpwa_export_report'); ?>';
    }
    </script>
    <?php
}

function wpwa_render_migration_log_table() {
    global $wpdb;

    $logs = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}wpwa_migration_log 
         ORDER BY migrated_at DESC 
         LIMIT 50",
        ARRAY_A
    );

    if (empty($logs)) {
        echo '<p>No migration activity yet.</p>';
        return;
    }

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Batch</th>
                <th>Order ID</th>
                <th>Type</th>
                <th>Status</th>
                <th>Error</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>#<?php echo $log['batch_number']; ?></td>
                    <td><?php echo $log['order_id']; ?></td>
                    <td><?php echo esc_html($log['migration_type']); ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color: #2ecc71;">✅ Success</span>
                        <?php elseif ($log['status'] === 'failed'): ?>
                            <span style="color: #e74c3c;">❌ Failed</span>
                        <?php else: ?>
                            <span style="color: #f39c12;">⚠️ Skipped</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $log['error_message'] ? esc_html($log['error_message']) : '—'; ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['migrated_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}