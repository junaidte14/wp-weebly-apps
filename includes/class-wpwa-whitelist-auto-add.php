<?php
/**
 * WPWA Whitelist - Auto-Add from Orders
 * File: includes/class-wpwa-whitelist-auto-add.php
 * 
 * Automatically creates whitelist entries when customers purchase the whitelist product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Whitelist_Auto_Add {

	/* ───────── Bootstrap ───────── */
	public static function init() {
		// Hook into order completion
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'process_completed_order' ], 20 );
		
		// Hook into recurring renewal (when subscription renews)
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'process_renewal_order' ], 25 );
		
		// Admin notices for manual user input
		add_action( 'admin_notices', [ __CLASS__, 'show_pending_whitelist_notice' ] );
		
		// AJAX handler for completing whitelist entry
		add_action( 'wp_ajax_wpwa_complete_whitelist_entry', [ __CLASS__, 'ajax_complete_entry' ] );
		
		// Meta box on order edit page
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_order_meta_box' ] );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  ORDER PROCESSING
	 * ═══════════════════════════════════════════════════════════════ */

	/**
	 * Process completed order - check if it contains whitelist product
	 */
	public static function process_completed_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
		if ( ! $whitelist_product_id ) {
			return; // No whitelist product configured
		}

		$has_whitelist_product = false;
		$whitelist_item_id = null;

		foreach ( $order->get_items() as $item_id => $item ) {
			if ( (int) $item->get_product_id() === (int) $whitelist_product_id ) {
				$has_whitelist_product = true;
				$whitelist_item_id = $item_id;
				break;
			}
		}

		if ( ! $has_whitelist_product ) {
			return; // Order doesn't contain whitelist product
		}

		// Check if whitelist entry already exists for this order
		if ( self::entry_exists_for_order( $order_id ) ) {
			error_log( "WPWA Whitelist: Entry already exists for order #{$order_id}" );
			return;
		}

		// Try to auto-create entry if we have Weebly user/site IDs
		$auto_created = self::try_auto_create_entry( $order, $order_id, $whitelist_item_id );

		if ( ! $auto_created ) {
			// Mark order for manual completion
			update_post_meta( $order_id, '_wpwa_whitelist_pending', 'yes' );
			
			// Add order note
			$order->add_order_note( 
				__( 'Whitelist product purchased. Admin needs to add Weebly User ID and Site ID to activate whitelist access.', 'wpwa' )
			);
		}
	}

	/**
	 * Try to automatically create whitelist entry from order data
	 */
	private static function try_auto_create_entry( $order, $order_id, $item_id ) {
		// Try to get Weebly IDs from order item meta (if they exist from previous app purchases)
		$item = $order->get_item( $item_id );
		$user_id = $item->get_meta( 'user_id' );
		$site_id = $item->get_meta( 'site_id' );

		// Also check order meta
		if ( ! $user_id ) {
			$user_id = get_post_meta( $order_id, 'weebly_user_id', true );
		}
		if ( ! $site_id ) {
			$site_id = get_post_meta( $order_id, 'weebly_site_id', true );
		}

		// If we have at least user_id, create entry
		if ( $user_id ) {
			$whitelist_type = $site_id ? 'site_user' : 'user_id';
			
			$entry_id = self::create_whitelist_entry( [
				'whitelist_type'        => $whitelist_type,
				'user_id'               => $user_id,
				'site_id'               => $site_id ?: null,
				'email'                 => $order->get_billing_email(),
				'customer_name'         => $order->get_formatted_billing_full_name(),
				'subscription_order_id' => $order_id,
				'notes'                 => sprintf( 
					__( 'Auto-created from order #%d', 'wpwa' ), 
					$order_id 
				),
			] );

			if ( $entry_id ) {
				update_post_meta( $order_id, '_wpwa_whitelist_entry_id', $entry_id );
				update_post_meta( $order_id, '_wpwa_whitelist_pending', 'no' );
				
				$order->add_order_note( sprintf(
					__( 'Whitelist entry #%d created automatically. Type: %s', 'wpwa' ),
					$entry_id,
					$whitelist_type
				) );

				return true;
			}
		}

		return false;
	}

	/**
	 * Create whitelist entry in database
	 */
	private static function create_whitelist_entry( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;

		// Calculate expiry from subscription
		if ( ! empty( $data['subscription_order_id'] ) ) {
			$expiry = self::calculate_expiry_from_order( $data['subscription_order_id'] );
			if ( $expiry ) {
				$data['expiry_date'] = $expiry;
			}
		}

		$inserted = $wpdb->insert( $table, $data );

		if ( $inserted ) {
			do_action( 'wpwa_whitelist_entry_created', $wpdb->insert_id, $data );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Calculate expiry date from order's recurring product
	 */
	private static function calculate_expiry_from_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		foreach ( $order->get_items() as $item ) {
			$expiry = (int) $item->get_meta( '_wpwa_expiry' );
			if ( $expiry ) {
				return date( 'Y-m-d H:i:s', $expiry );
			}
		}

		return null;
	}

	/**
	 * Check if whitelist entry already exists for this order
	 */
	private static function entry_exists_for_order( $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE subscription_order_id = %d",
			$order_id
		) );

		return $count > 0;
	}

	/**
	 * Process renewal - extend existing whitelist entry
	 */
	public static function process_renewal_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if this is a renewal (has parent order or subscription meta)
		$parent_order_id = $order->get_meta( '_subscription_renewal' );
		if ( ! $parent_order_id ) {
			return; // Not a renewal
		}

		// Find whitelist entry from parent order
		$entry_id = get_post_meta( $parent_order_id, '_wpwa_whitelist_entry_id', true );
		if ( ! $entry_id ) {
			return;
		}

		// Extend the entry
		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;

		$new_expiry = self::calculate_expiry_from_order( $order_id );
		if ( $new_expiry ) {
			$wpdb->update(
				$table,
				[
					'expiry_date'           => $new_expiry,
					'subscription_order_id' => $order_id, // Update to new order
				],
				[ 'id' => $entry_id ]
			);

			$order->add_order_note( sprintf(
				__( 'Whitelist entry #%d extended to %s', 'wpwa' ),
				$entry_id,
				$new_expiry
			) );

			do_action( 'wpwa_whitelist_entry_renewed', $entry_id, $order_id );
		}
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  ADMIN NOTICES & META BOX
	 * ═══════════════════════════════════════════════════════════════ */

	/**
	 * Show admin notice for pending whitelist entries
	 */
	public static function show_pending_whitelist_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'shop_order' ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$pending = get_post_meta( $post->ID, '_wpwa_whitelist_pending', true );
		if ( $pending === 'yes' ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php _e( 'Action Required:', 'wpwa' ); ?></strong>
					<?php _e( 'This order contains a Whitelist product. Please add Weebly User ID and Site ID below to activate whitelist access.', 'wpwa' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add meta box to order edit page
	 */
	public static function add_order_meta_box() {
		add_meta_box(
			'wpwa_whitelist_order_meta',
			__( 'Whitelist Information', 'wpwa' ),
			[ __CLASS__, 'render_order_meta_box' ],
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Render meta box content
	 */
	public static function render_order_meta_box( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}

		$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
		$has_whitelist = false;

		foreach ( $order->get_items() as $item ) {
			if ( (int) $item->get_product_id() === (int) $whitelist_product_id ) {
				$has_whitelist = true;
				break;
			}
		}

		if ( ! $has_whitelist ) {
			echo '<p>' . __( 'This order does not contain a whitelist product.', 'wpwa' ) . '</p>';
			return;
		}

		$entry_id = get_post_meta( $post->ID, '_wpwa_whitelist_entry_id', true );
		$pending = get_post_meta( $post->ID, '_wpwa_whitelist_pending', true );

		if ( $entry_id ) {
			global $wpdb;
			$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;
			$entry = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d",
				$entry_id
			), ARRAY_A );

			if ( $entry ) {
				?>
				<div class="wpwa-whitelist-status">
					<p><strong><?php _e( 'Status:', 'wpwa' ); ?></strong> 
						<span class="wpwa-status-active"><?php _e( 'Active', 'wpwa' ); ?></span>
					</p>
					<p><strong><?php _e( 'Entry ID:', 'wpwa' ); ?></strong> #<?php echo $entry['id']; ?></p>
					<p><strong><?php _e( 'Type:', 'wpwa' ); ?></strong> <?php echo esc_html( $entry['whitelist_type'] ); ?></p>
					<p><strong><?php _e( 'User ID:', 'wpwa' ); ?></strong> <?php echo esc_html( $entry['user_id'] ); ?></p>
					<?php if ( $entry['site_id'] ) : ?>
						<p><strong><?php _e( 'Site ID:', 'wpwa' ); ?></strong> <?php echo esc_html( $entry['site_id'] ); ?></p>
					<?php endif; ?>
					<p><strong><?php _e( 'Expires:', 'wpwa' ); ?></strong> 
						<?php echo $entry['expiry_date'] ? date_i18n( get_option( 'date_format' ), strtotime( $entry['expiry_date'] ) ) : __( 'Never', 'wpwa' ); ?>
					</p>
					<p>
						<a href="<?php echo admin_url( 'admin.php?page=wpwa_whitelist' ); ?>" class="button button-small">
							<?php _e( 'View All Entries', 'wpwa' ); ?>
						</a>
					</p>
				</div>
				<?php
			}
		} elseif ( $pending === 'yes' ) {
			?>
			<div class="wpwa-whitelist-pending">
				<p class="wpwa-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php _e( 'Whitelist entry pending', 'wpwa' ); ?>
				</p>
				
				<?php wp_nonce_field( 'wpwa_complete_whitelist', 'wpwa_whitelist_nonce' ); ?>
				
				<p>
					<label for="wpwa_whitelist_type"><strong><?php _e( 'Type:', 'wpwa' ); ?></strong></label>
					<select id="wpwa_whitelist_type" style="width: 100%;">
						<option value="user_id"><?php _e( 'User ID (any site)', 'wpwa' ); ?></option>
						<option value="site_user"><?php _e( 'Site + User', 'wpwa' ); ?></option>
						<option value="global_user"><?php _e( 'Global User', 'wpwa' ); ?></option>
					</select>
				</p>

				<p>
					<label for="wpwa_weebly_user_id"><strong><?php _e( 'Weebly User ID:', 'wpwa' ); ?></strong></label>
					<input type="text" id="wpwa_weebly_user_id" style="width: 100%;" 
					       value="<?php echo esc_attr( get_post_meta( $post->ID, 'weebly_user_id', true ) ); ?>">
				</p>

				<p id="wpwa_site_id_field">
					<label for="wpwa_weebly_site_id"><strong><?php _e( 'Weebly Site ID:', 'wpwa' ); ?></strong></label>
					<input type="text" id="wpwa_weebly_site_id" style="width: 100%;"
					       value="<?php echo esc_attr( get_post_meta( $post->ID, 'weebly_site_id', true ) ); ?>">
				</p>

				<p>
					<button type="button" class="button button-primary" id="wpwa_complete_whitelist_btn">
						<?php _e( 'Create Whitelist Entry', 'wpwa' ); ?>
					</button>
				</p>

				<div id="wpwa_whitelist_message"></div>
			</div>

			<script>
			jQuery(document).ready(function($) {
				// Toggle site ID field
				function toggleSiteField() {
					const type = $('#wpwa_whitelist_type').val();
					if (type === 'site_user') {
						$('#wpwa_site_id_field').show();
					} else {
						$('#wpwa_site_id_field').hide();
					}
				}
				
				$('#wpwa_whitelist_type').on('change', toggleSiteField);
				toggleSiteField();

				// Complete whitelist entry
				$('#wpwa_complete_whitelist_btn').on('click', function() {
					const $btn = $(this);
					const userId = $('#wpwa_weebly_user_id').val().trim();
					const siteId = $('#wpwa_weebly_site_id').val().trim();
					const type = $('#wpwa_whitelist_type').val();

					if (!userId) {
						alert('<?php _e( 'Please enter Weebly User ID', 'wpwa' ); ?>');
						return;
					}

					if (type === 'site_user' && !siteId) {
						alert('<?php _e( 'Please enter Weebly Site ID for Site+User type', 'wpwa' ); ?>');
						return;
					}

					$btn.prop('disabled', true).text('<?php _e( 'Creating...', 'wpwa' ); ?>');

					$.post(ajaxurl, {
						action: 'wpwa_complete_whitelist_entry',
						nonce: $('#wpwa_whitelist_nonce').val(),
						order_id: <?php echo $post->ID; ?>,
						whitelist_type: type,
						user_id: userId,
						site_id: siteId
					}, function(response) {
						if (response.success) {
							$('#wpwa_whitelist_message').html(
								'<p style="color: green;"><strong><?php _e( 'Success!', 'wpwa' ); ?></strong> ' + 
								response.data.message + '</p>'
							);
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							$('#wpwa_whitelist_message').html(
								'<p style="color: red;"><strong><?php _e( 'Error:', 'wpwa' ); ?></strong> ' + 
								response.data.message + '</p>'
							);
							$btn.prop('disabled', false).text('<?php _e( 'Create Whitelist Entry', 'wpwa' ); ?>');
						}
					});
				});
			});
			</script>

			<style>
			.wpwa-whitelist-pending { padding: 10px; }
			.wpwa-warning { color: #856404; background: #fff3cd; padding: 8px; border-radius: 4px; }
			.wpwa-warning .dashicons { vertical-align: middle; }
			.wpwa-status-active { color: #155724; background: #d4edda; padding: 4px 8px; border-radius: 4px; }
			</style>
			<?php
		}
	}

	/**
	 * AJAX handler for completing whitelist entry from order page
	 */
	public static function ajax_complete_entry() {
		check_ajax_referer( 'wpwa_complete_whitelist', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
		}

		$order_id       = absint( $_POST['order_id'] ?? 0 );
		$whitelist_type = sanitize_text_field( $_POST['whitelist_type'] ?? 'user_id' );
		$user_id        = sanitize_text_field( $_POST['user_id'] ?? '' );
		$site_id        = sanitize_text_field( $_POST['site_id'] ?? '' );

		if ( ! $order_id || ! $user_id ) {
			wp_send_json_error( [ 'message' => 'Missing required fields' ] );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => 'Invalid order' ] );
		}

		// Create entry
		$entry_id = self::create_whitelist_entry( [
			'whitelist_type'        => $whitelist_type,
			'user_id'               => $user_id,
			'site_id'               => $site_id ?: null,
			'email'                 => $order->get_billing_email(),
			'customer_name'         => $order->get_formatted_billing_full_name(),
			'subscription_order_id' => $order_id,
			'notes'                 => sprintf(
				__( 'Created manually from order #%d', 'wpwa' ),
				$order_id
			),
		] );

		if ( $entry_id ) {
			update_post_meta( $order_id, '_wpwa_whitelist_entry_id', $entry_id );
			update_post_meta( $order_id, '_wpwa_whitelist_pending', 'no' );
			update_post_meta( $order_id, 'weebly_user_id', $user_id );
			if ( $site_id ) {
				update_post_meta( $order_id, 'weebly_site_id', $site_id );
			}

			$order->add_order_note( sprintf(
				__( 'Whitelist entry #%d created. Type: %s', 'wpwa' ),
				$entry_id,
				$whitelist_type
			) );

			wp_send_json_success( [
				'message'  => sprintf( __( 'Whitelist entry #%d created successfully!', 'wpwa' ), $entry_id ),
				'entry_id' => $entry_id,
			] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to create whitelist entry' ] );
		}
	}
}

// Bootstrap
WPWA_Whitelist_Auto_Add::init();