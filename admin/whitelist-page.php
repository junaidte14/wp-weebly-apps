<?php
/**
 * Whitelist Management Admin Page
 * File: admin/whitelist-page.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap wpwa-whitelist-wrap">
	<h1 class="wpwa-page-title">
		<span class="dashicons dashicons-shield"></span>
		<?php _e( 'Whitelist Management', 'wpwa' ); ?>
	</h1>

	<div id="wpwa-notice-container"></div>

	<!-- Settings Section -->
	<div class="wpwa-settings-card">
		<h2><?php _e( 'Whitelist Product Configuration', 'wpwa' ); ?></h2>
		<p class="description">
			<?php _e( 'Select the recurring WooCommerce product that grants whitelist access. Customers who purchase this product can be added to the whitelist.', 'wpwa' ); ?>
		</p>
		
		<form id="wpwa-whitelist-settings-form" method="post">
			<?php wp_nonce_field( 'wpwa_whitelist_settings', 'wpwa_settings_nonce' ); ?>
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="wpwa_whitelist_product_id"><?php _e( 'Whitelist Product', 'wpwa' ); ?></label>
					</th>
					<td>
						<select name="wpwa_whitelist_product_id" id="wpwa_whitelist_product_id" class="regular-text">
							<option value="0"><?php _e( '-- Select Product --', 'wpwa' ); ?></option>
							<?php foreach ( $products as $product ) : ?>
								<option value="<?php echo esc_attr( $product['id'] ); ?>" 
									<?php selected( $whitelist_product_id, $product['id'] ); ?>>
									<?php echo esc_html( $product['name'] ); ?> (ID: <?php echo $product['id']; ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php _e( 'Only recurring products are shown. Create a recurring product first if none are available.', 'wpwa' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php _e( 'Save Settings', 'wpwa' ); ?>
				</button>
			</p>
		</form>
	</div>

    <!-- Email Notification Settings -->
    <div class="wpwa-settings-card" style="margin-top: 25px;">
        <h2><?php _e( 'Email Notification Settings', 'wpwa' ); ?></h2>
        <p class="description">
            <?php _e( 'Configure automatic email notifications for whitelist events.', 'wpwa' ); ?>
        </p>
        
        <form id="wpwa-email-settings-form" method="post">
            <?php wp_nonce_field( 'wpwa_email_settings', 'wpwa_email_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpwa_email_activation_enabled">
                            <?php _e( 'Activation Emails', 'wpwa' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpwa_email_activation_enabled" 
                                id="wpwa_email_activation_enabled" value="1"
                                <?php checked( get_option( 'wpwa_email_activation_enabled', true ) ); ?>>
                            <?php _e( 'Send email when whitelist is activated', 'wpwa' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpwa_email_expiring_enabled">
                            <?php _e( 'Expiring Soon Emails', 'wpwa' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpwa_email_expiring_enabled" 
                                id="wpwa_email_expiring_enabled" value="1"
                                <?php checked( get_option( 'wpwa_email_expiring_enabled', true ) ); ?>>
                            <?php _e( 'Send warning email before expiration', 'wpwa' ); ?>
                        </label>
                        <br>
                        <label style="margin-top: 10px; display: inline-block;">
                            <?php _e( 'Days before expiration:', 'wpwa' ); ?>
                            <input type="number" name="wpwa_email_expiring_days" 
                                value="<?php echo esc_attr( get_option( 'wpwa_email_expiring_days', 7 ) ); ?>"
                                min="1" max="30" style="width: 60px;">
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpwa_email_expired_enabled">
                            <?php _e( 'Expired Emails', 'wpwa' ); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpwa_email_expired_enabled" 
                                id="wpwa_email_expired_enabled" value="1"
                                <?php checked( get_option( 'wpwa_email_expired_enabled', true ) ); ?>>
                            <?php _e( 'Send email when whitelist expires', 'wpwa' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e( 'Save Email Settings', 'wpwa' ); ?>
                </button>
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#wpwa-email-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: 'wpwa_save_email_settings',
                nonce: $('#wpwa_email_nonce').val(),
                activation_enabled: $('#wpwa_email_activation_enabled').is(':checked') ? 1 : 0,
                expiring_enabled: $('#wpwa_email_expiring_enabled').is(':checked') ? 1 : 0,
                expiring_days: $('input[name="wpwa_email_expiring_days"]').val(),
                expired_enabled: $('#wpwa_email_expired_enabled').is(':checked') ? 1 : 0
            };
            
            $.post(ajaxurl, formData, function(response) {
                if (response.success) {
                    $('#wpwa-notice-container').html(
                        '<div class="notice notice-success is-dismissible"><p>Email settings saved successfully!</p></div>'
                    );
                }
            });
        });
    });
    </script>

	<!-- Whitelist Entries Section -->
	<div class="wpwa-whitelist-entries">
		<div class="wpwa-entries-header">
			<h2><?php _e( 'Whitelist Entries', 'wpwa' ); ?></h2>
			<button class="button button-primary" id="wpwa-add-entry-btn">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php _e( 'Add New Entry', 'wpwa' ); ?>
			</button>
		</div>

		<!-- Filters -->
		<div class="wpwa-filters">
			<select id="wpwa-filter-type">
				<option value=""><?php _e( 'All Types', 'wpwa' ); ?></option>
				<option value="global_user"><?php _e( 'Global User', 'wpwa' ); ?></option>
				<option value="user_id"><?php _e( 'User ID', 'wpwa' ); ?></option>
				<option value="site_user"><?php _e( 'Site + User', 'wpwa' ); ?></option>
			</select>

			<select id="wpwa-filter-status">
				<option value=""><?php _e( 'All Statuses', 'wpwa' ); ?></option>
				<option value="active"><?php _e( 'Active', 'wpwa' ); ?></option>
				<option value="expired"><?php _e( 'Expired', 'wpwa' ); ?></option>
			</select>

			<input type="search" id="wpwa-search" placeholder="<?php esc_attr_e( 'Search...', 'wpwa' ); ?>">
		</div>

		<!-- Table -->
		<div class="wpwa-table-container">
			<table class="wp-list-table widefat fixed striped wpwa-whitelist-table">
				<thead>
					<tr>
						<th class="column-type"><?php _e( 'Type', 'wpwa' ); ?></th>
						<th class="column-user-id"><?php _e( 'User ID', 'wpwa' ); ?></th>
						<th class="column-site-id"><?php _e( 'Site ID', 'wpwa' ); ?></th>
						<th class="column-customer"><?php _e( 'Customer', 'wpwa' ); ?></th>
						<th class="column-subscription"><?php _e( 'Subscription', 'wpwa' ); ?></th>
						<th class="column-expiry"><?php _e( 'Expiry', 'wpwa' ); ?></th>
						<th class="column-status"><?php _e( 'Status', 'wpwa' ); ?></th>
						<th class="column-actions"><?php _e( 'Actions', 'wpwa' ); ?></th>
					</tr>
				</thead>
				<tbody id="wpwa-whitelist-tbody">
					<tr class="wpwa-loading-row">
						<td colspan="8" class="wpwa-loading">
							<span class="spinner is-active"></span>
							<?php _e( 'Loading entries...', 'wpwa' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add/Edit Entry Modal -->
<div id="wpwa-entry-modal" class="wpwa-modal" style="display:none;">
	<div class="wpwa-modal-content">
		<span class="wpwa-modal-close">&times;</span>
		<h2 id="wpwa-modal-title"><?php _e( 'Add Whitelist Entry', 'wpwa' ); ?></h2>
		
		<form id="wpwa-entry-form">
			<input type="hidden" name="entry_id" id="entry_id" value="">

			<div class="wpwa-form-group">
				<label for="whitelist_type">
					<?php _e( 'Whitelist Type', 'wpwa' ); ?>
					<span class="required">*</span>
				</label>
				<select name="whitelist_type" id="whitelist_type" required>
					<option value="global_user"><?php _e( 'Global User (any app, any site)', 'wpwa' ); ?></option>
					<option value="user_id" selected><?php _e( 'User ID (any app for this user)', 'wpwa' ); ?></option>
					<option value="site_user"><?php _e( 'Site + User (specific site and user)', 'wpwa' ); ?></option>
				</select>
				<p class="description">
					<?php _e( 'Choose the scope of whitelist access for this entry.', 'wpwa' ); ?>
				</p>
			</div>

			<div class="wpwa-form-group">
				<label for="user_id">
					<?php _e( 'Weebly User ID', 'wpwa' ); ?>
					<span class="required" id="user_id_required">*</span>
				</label>
				<input type="text" name="user_id" id="user_id" class="regular-text">
				<p class="description">
					<?php _e( 'The Weebly user ID to whitelist.', 'wpwa' ); ?>
				</p>
			</div>

			<div class="wpwa-form-group" id="site_id_group">
				<label for="site_id">
					<?php _e( 'Weebly Site ID', 'wpwa' ); ?>
					<span class="required" id="site_id_required" style="display:none;">*</span>
				</label>
				<input type="text" name="site_id" id="site_id" class="regular-text">
				<p class="description">
					<?php _e( 'Required only for Site + User type.', 'wpwa' ); ?>
				</p>
			</div>

			<div class="wpwa-form-group">
				<label for="email"><?php _e( 'Email', 'wpwa' ); ?></label>
				<input type="email" name="email" id="email" class="regular-text">
			</div>

			<div class="wpwa-form-group">
				<label for="customer_name"><?php _e( 'Customer Name', 'wpwa' ); ?></label>
				<input type="text" name="customer_name" id="customer_name" class="regular-text">
			</div>

			<div class="wpwa-form-group">
				<label for="subscription_order_id"><?php _e( 'Subscription Order ID', 'wpwa' ); ?></label>
				<input type="number" name="subscription_order_id" id="subscription_order_id" class="regular-text">
				<p class="description">
					<?php _e( 'Link to a WooCommerce order containing the whitelist subscription product.', 'wpwa' ); ?>
				</p>
				<div id="subscription_status_display"></div>
			</div>

			<div class="wpwa-form-group">
				<label for="notes"><?php _e( 'Notes', 'wpwa' ); ?></label>
				<textarea name="notes" id="notes" rows="3" class="large-text"></textarea>
			</div>

			<div class="wpwa-modal-actions">
				<button type="submit" class="button button-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php _e( 'Save Entry', 'wpwa' ); ?>
				</button>
				<button type="button" class="button wpwa-modal-cancel">
					<?php _e( 'Cancel', 'wpwa' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<!-- Loading Overlay -->
<div id="wpwa-loading-overlay" style="display:none;">
	<div class="wpwa-spinner"></div>
</div>

<script>
// Settings form handler (inline for simplicity)
jQuery(document).ready(function($) {
	$('#wpwa-whitelist-settings-form').on('submit', function(e) {
		e.preventDefault();
		
		const productId = $('#wpwa_whitelist_product_id').val();
		
		$.post(ajaxurl, {
			action: 'wpwa_save_whitelist_product',
			nonce: '<?php echo wp_create_nonce('wpwa_whitelist_settings'); ?>',
			product_id: productId
		}, function(response) {
			if (response.success) {
				$('#wpwa-notice-container').html(
					'<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>'
				);
			}
		});
	});
});
</script>

<style>
.wpwa-whitelist-wrap { background: #f5f5f5; padding: 20px; margin: 20px 20px 20px 0; }
.wpwa-page-title { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.wpwa-page-title .dashicons { font-size: 32px; width: 32px; height: 32px; }

.wpwa-settings-card { background: #fff; padding: 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.wpwa-settings-card h2 { margin-top: 0; }

.wpwa-whitelist-entries { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.wpwa-entries-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.wpwa-entries-header h2 { margin: 0; }

.wpwa-filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.wpwa-filters select, .wpwa-filters input { padding: 6px 12px; }

.wpwa-table-container { overflow-x: auto; }
.wpwa-whitelist-table { background: #fff; }
.wpwa-whitelist-table thead th { background: #f9f9f9; font-weight: 600; padding: 12px; }
.wpwa-whitelist-table tbody td { padding: 12px; vertical-align: middle; }

.wpwa-type-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.wpwa-type-global_user { background: #e3f2fd; color: #1976d2; }
.wpwa-type-user_id { background: #f3e5f5; color: #7b1fa2; }
.wpwa-type-site_user { background: #fff3e0; color: #e65100; }

.wpwa-status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.wpwa-status-active { background: #d4edda; color: #155724; }
.wpwa-status-expired { background: #f8d7da; color: #721c24; }
.wpwa-status-revoked { background: #e2e3e5; color: #383d41; }

.wpwa-loading { text-align: center; padding: 40px; color: #666; }

/* Modal */
.wpwa-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
.wpwa-modal-content { background: #fff; margin: 5% auto; padding: 30px; border-radius: 8px; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.wpwa-modal-close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; color: #aaa; }
.wpwa-modal-close:hover { color: #000; }

.wpwa-form-group { margin-bottom: 20px; }
.wpwa-form-group label { display: block; margin-bottom: 6px; font-weight: 600; }
.wpwa-form-group .required { color: #dc3545; }
.wpwa-form-group .description { font-size: 13px; color: #666; margin-top: 4px; }

.wpwa-modal-actions { margin-top: 25px; display: flex; gap: 10px; }

#wpwa-loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.wpwa-spinner { width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

.subscription-status { margin-top: 8px; padding: 8px; border-radius: 4px; font-size: 13px; }
.subscription-status.active { background: #d4edda; color: #155724; }
.subscription-status.expired { background: #f8d7da; color: #721c24; }
.subscription-status.invalid { background: #fff3cd; color: #856404; }
</style>