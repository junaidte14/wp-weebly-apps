<?php
/**
 * WPWA Recurring - Professional Production-Ready Version
 * ------------------------------------------------------
 * Comprehensive licence/subscription system for WooCommerce
 * with enhanced security, grace periods, email notifications,
 * and proper HPOS compatibility.
 *
 * @package WPWA
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Recurring {

	/* =================================================================
	 *   CONSTANTS & META KEYS
	 * ================================================================= */
	
	const VERSION              = '2.0.0';
	
	// Product meta
	const META_KEY_FLAG        = '_wpwa_is_recurring';
	const META_CYCLE_LENGTH    = '_wpwa_cycle_length';
	const META_CYCLE_UNIT      = '_wpwa_cycle_unit';
	const META_CYCLE_PRICE     = '_wpwa_cycle_price';
	const META_GRACE_PERIOD    = '_wpwa_grace_period';
	const META_AUTO_RENEW      = '_wpwa_auto_renew_enabled';
	
	// Order item meta
	const META_KEY_TOKEN       = 'access_token';
	const META_KEY_EXPIRE      = '_wpwa_expiry';
	const META_TOKEN_REVOKED   = '_wpwa_token_revoked';
	const META_LICENCE_STATUS  = '_wpwa_licence_status';
	const META_GRACE_UNTIL     = '_wpwa_grace_until';
	const META_LAST_NOTICE     = '_wpwa_last_expiry_notice';
	const META_RENEWAL_COUNT   = '_wpwa_renewal_count';
	
	// Order meta
	const META_ORDER_TYPE      = '_wpwa_order_type';
	const META_PARENT_ORDER    = '_wpwa_parent_order_id';
	
	// Action Scheduler hooks
	const ACTION_EXPIRE_SCAN   = 'wpwa_daily_check_expired';
	const ACTION_GRACE_WARN    = 'wpwa_grace_period_warning';
	const ACTION_FINAL_REVOKE  = 'wpwa_final_revoke';
	
	// Status constants
	const STATUS_ACTIVE        = 'active';
	const STATUS_GRACE         = 'grace';
	const STATUS_EXPIRED       = 'expired';
	const STATUS_REVOKED       = 'revoked';
	const STATUS_CANCELLED     = 'cancelled';
	
	private static $instance = null;

	/* =================================================================
	 *   SINGLETON & INITIALIZATION
	 * ================================================================= */
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		// Admin UI
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_admin_fields' ] );
		add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_admin_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// Pricing
		add_filter( 'woocommerce_product_get_price', [ $this, 'filter_product_price' ], 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', [ $this, 'filter_product_price' ], 20, 2 );

		// Order workflow
		add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_meta' ], 10, 4 );

		// Scheduled tasks
		add_action( self::ACTION_EXPIRE_SCAN, [ $this, 'process_expiring_licences' ] );
		add_action( self::ACTION_GRACE_WARN, [ $this, 'send_grace_period_warnings' ] );
		add_action( self::ACTION_FINAL_REVOKE, [ $this, 'revoke_expired_tokens' ] );

		// Front-end UX
		add_action( 'woocommerce_single_product_summary', [ $this, 'print_recurring_badge' ], 25 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'maybe_hide_add_to_cart' ], 1 );
		add_filter( 'woocommerce_is_purchasable', [ $this, 'maybe_block_repurchase' ], 20, 2 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'show_licence_status' ], 10 );
		
		// My Account integration
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_my_licences_menu' ] );
		add_action( 'init', [ $this, 'add_my_licences_endpoint' ] );
		add_action( 'woocommerce_account_my-licences_endpoint', [ $this, 'my_licences_content' ] );
		
		// Admin columns
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_order_column' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_order_column' ], 10, 2 );
		
		// HPOS compatibility
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_order_column' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_order_column_hpos' ], 10, 2 );
	}

	public static function activate() {
		// Schedule daily expiry check (3 AM)
		if ( ! as_next_scheduled_action( self::ACTION_EXPIRE_SCAN ) ) {
			as_schedule_recurring_action( 
				strtotime( 'tomorrow 3:00 AM' ), 
				DAY_IN_SECONDS, 
				self::ACTION_EXPIRE_SCAN,
				[],
				'wpwa'
			);
		}
		
		// Schedule grace period warnings (every 6 hours)
		if ( ! as_next_scheduled_action( self::ACTION_GRACE_WARN ) ) {
			as_schedule_recurring_action( 
				time() + HOUR_IN_SECONDS, 
				6 * HOUR_IN_SECONDS, 
				self::ACTION_GRACE_WARN,
				[],
				'wpwa'
			);
		}
		
		// Add rewrite endpoints
		add_rewrite_endpoint( 'my-licences', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		as_unschedule_all_actions( self::ACTION_EXPIRE_SCAN, [], 'wpwa' );
		as_unschedule_all_actions( self::ACTION_GRACE_WARN, [], 'wpwa' );
		as_unschedule_all_actions( self::ACTION_FINAL_REVOKE, [], 'wpwa' );
		
		flush_rewrite_rules();
	}

	/* =================================================================
	 *   ADMIN: PRODUCT DATA PANEL
	 * ================================================================= */
	
	public function add_admin_fields() {
		echo '<div class="options_group wpwa-recurring-section">';
		
		woocommerce_wp_checkbox( [
			'id'          => self::META_KEY_FLAG,
			'label'       => __( 'Recurring Subscription', 'wpwa' ),
			'description' => __( 'Enable recurring billing for this product', 'wpwa' ),
			'desc_tip'    => true,
		] );

		echo '<div class="wpwa-recurring-fields" style="display:none;">';

		// Cycle configuration
		echo '<div style="display:flex;gap:10px;"><div style="flex:1;">';
		woocommerce_wp_text_input( [
			'id'                => self::META_CYCLE_LENGTH,
			'label'             => __( 'Billing Cycle', 'wpwa' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => 1, 'step' => 1 ],
			'desc_tip'          => true,
			'description'       => __( 'Length of each billing cycle', 'wpwa' ),
		] );
		echo '</div><div style="flex:1;">';
		woocommerce_wp_select( [
			'id'      => self::META_CYCLE_UNIT,
			'label'   => __( 'Period', 'wpwa' ),
			'options' => [
				'day'   => __( 'Day(s)', 'wpwa' ),
				'week'  => __( 'Week(s)', 'wpwa' ),
				'month' => __( 'Month(s)', 'wpwa' ),
				'year'  => __( 'Year(s)', 'wpwa' ),
			],
		] );
		echo '</div></div>';

		woocommerce_wp_text_input( [
			'id'          => self::META_CYCLE_PRICE,
			'label'       => __( 'Recurring Price', 'wpwa' ) . ' (' . get_woocommerce_currency_symbol() . ')',
			'type'        => 'text',
			'data_type'   => 'price',
			'desc_tip'    => true,
			'description' => __( 'Leave empty to use regular product price', 'wpwa' ),
		] );

		// Grace period
		woocommerce_wp_text_input( [
			'id'                => self::META_GRACE_PERIOD,
			'label'             => __( 'Grace Period (days)', 'wpwa' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => 0, 'step' => 1 ],
			'desc_tip'          => true,
			'description'       => __( 'Days after expiry before access is revoked (0 = immediate)', 'wpwa' ),
			'value'             => get_post_meta( get_the_ID(), self::META_GRACE_PERIOD, true ) ?: '7',
		] );

		// Auto-renew toggle (for future use)
		woocommerce_wp_checkbox( [
			'id'          => self::META_AUTO_RENEW,
			'label'       => __( 'Allow Auto-Renewal', 'wpwa' ),
			'description' => __( 'Enable automatic renewal (requires payment gateway support)', 'wpwa' ),
			'desc_tip'    => true,
		] );
		echo '<div class="wpwa-duration-options-section1" style="padding-left: 12px; border-left: 3px solid #48bb78; margin-top: 15px;">';

		echo '<h4 style="margin: 0 0 12px; color: #2d3748;">' . __( 'Prepaid Duration Options', 'wpwa' ) . '</h4>';
		echo '<p class="description" style="margin-bottom: 15px;">' . 
			__( 'Allow customers to prepay for multiple billing cycles at checkout. This is ideal for whitelist subscriptions.', 'wpwa' ) . 
			'</p>';

		// Enable duration selector
		woocommerce_wp_checkbox( [
			'id'          => '_wpwa_enable_duration_selector',
			'label'       => __( 'Enable Duration Options', 'wpwa' ),
			'description' => __( 'Show duration selector at checkout', 'wpwa' ),
			'desc_tip'    => true,
		] );
		echo '<div class="wpwa-duration-options-section">';
		// Available durations
		woocommerce_wp_text_input( [
			'id'          => '_wpwa_available_durations',
			'label'       => __( 'Available Durations', 'wpwa' ),
			'desc_tip'    => true,
			'description' => __( 'Comma-separated numbers (e.g., 1,3,6,12). Represents number of billing cycles.', 'wpwa' ),
			'placeholder' => '1,3,6,12',
			'value'       => get_post_meta( get_the_ID(), '_wpwa_available_durations', true ) ?: '1,3,6,12',
		] );

		// Default duration
		woocommerce_wp_select( [
			'id'      => '_wpwa_default_duration',
			'label'   => __( 'Default Duration', 'wpwa' ),
			'options' => [
				'1'  => '1 cycle',
				'3'  => '3 cycles',
				'6'  => '6 cycles',
				'12' => '12 cycles',
			],
			'desc_tip'    => true,
			'description' => __( 'Pre-selected duration option', 'wpwa' ),
		] );

		// Discount for longer durations
		woocommerce_wp_text_input( [
			'id'          => '_wpwa_duration_discount',
			'label'       => __( 'Multi-Cycle Discount (%)', 'wpwa' ),
			'type'        => 'number',
			'custom_attributes' => [ 'min' => 0, 'max' => 50, 'step' => 1 ],
			'desc_tip'    => true,
			'description' => __( 'Optional: Percentage discount for customers who prepay 6+ cycles', 'wpwa' ),
			'placeholder' => '0',
		] );
		echo '</div>';

		echo '</div>'; // .wpwa-duration-options-section

		// Add inline script for conditional display
		wp_add_inline_script( 'wpwa-recurring-admin', '
		jQuery(document).ready(function($) {
			function toggleDurationFields() {
				const $checkbox = $("#_wpwa_enable_duration_selector");
				const $section = $(".wpwa-duration-options-section").find("p, .form-field");
				
				if ($checkbox.is(":checked")) {
					$section.slideDown(200);
				} else {
					$section.slideUp(200);
				}
			}
			
			toggleDurationFields();
			$("#_wpwa_enable_duration_selector").on("change", toggleDurationFields);
		});
		' );
		echo '</div>'; // .wpwa-recurring-fields
		echo '</div>'; // .options_group
	}

	public function save_admin_fields( $product ) {
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$is_recurring = isset( $_POST[ self::META_KEY_FLAG ] ) ? 'yes' : 'no';
		$product->update_meta_data( self::META_KEY_FLAG, $is_recurring );

		if ( 'yes' === $is_recurring ) {
			$fields = [
				self::META_CYCLE_LENGTH  => 'absint',
				self::META_CYCLE_UNIT    => 'sanitize_text_field',
				self::META_CYCLE_PRICE   => 'wc_format_decimal',
				self::META_GRACE_PERIOD  => 'absint',
			];

			foreach ( $fields as $key => $sanitize ) {
				if ( isset( $_POST[ $key ] ) ) {
					$value = call_user_func( $sanitize, $_POST[ $key ] );
					$product->update_meta_data( $key, $value );
				}
			}
			
			$auto_renew = isset( $_POST[ self::META_AUTO_RENEW ] ) ? 'yes' : 'no';
			$product->update_meta_data( self::META_AUTO_RENEW, $auto_renew );

			// Duration options
			$enable_duration = isset( $_POST['_wpwa_enable_duration_selector'] ) ? 'yes' : 'no';
			$product->update_meta_data( '_wpwa_enable_duration_selector', $enable_duration );
			
			if ( isset( $_POST['_wpwa_available_durations'] ) ) {
				$durations = sanitize_text_field( $_POST['_wpwa_available_durations'] );
				$product->update_meta_data( '_wpwa_available_durations', $durations );
			}
			
			if ( isset( $_POST['_wpwa_default_duration'] ) ) {
				$default = absint( $_POST['_wpwa_default_duration'] );
				$product->update_meta_data( '_wpwa_default_duration', $default );
			}
			
			if ( isset( $_POST['_wpwa_duration_discount'] ) ) {
				$discount = absint( $_POST['_wpwa_duration_discount'] );
				$product->update_meta_data( '_wpwa_duration_discount', min( $discount, 50 ) );
			}
		}
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'wpwa-recurring-admin',
			WPWA_PLUGIN_URL . 'js/wpwa-admin-recurring.js',
			[ 'jquery' ],
			self::VERSION,
			true
		);

		wp_add_inline_style( 'woocommerce_admin_styles', '
			.wpwa-recurring-section { border-top: 1px solid #eee; padding-top: 12px; margin-top: 12px; }
			.wpwa-recurring-fields { padding-left: 12px; border-left: 3px solid #2271b1; margin-top: 12px; }
		' );
	}

	/* =================================================================
	 *   PRICING FILTERS
	 * ================================================================= */
	
	public function filter_product_price( $price, $product ) {
		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return $price;
		}

		$cycle_price = $product->get_meta( self::META_CYCLE_PRICE );
		return $cycle_price !== '' ? wc_format_decimal( $cycle_price ) : $price;
	}

	/* =================================================================
	 *   ORDER PROCESSING
	 * ================================================================= */
	
	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		$product = $values['data'];
		
		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return;
		}

		// Mark this as initial or renewal
		$customer_id = $order->get_customer_id();
		$product_id  = $product->get_id();
		
		$existing = $this->get_customer_licence( $customer_id, $product_id );
		
		if ( $existing ) {
			$item->add_meta_data( self::META_ORDER_TYPE, 'renewal', true );
			$item->add_meta_data( self::META_PARENT_ORDER, $existing['order_id'], true );
		} else {
			$item->add_meta_data( self::META_ORDER_TYPE, 'initial', true );
		}
	}

	public function handle_order_completed( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		
		if ( ! $order ) {
			return;
		}

		$logger = wc_get_logger();

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( ! $product || 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
				continue;
			}

			// Calculate expiry
			$length     = max( 1, (int) $product->get_meta( self::META_CYCLE_LENGTH ) );
			$unit       = $product->get_meta( self::META_CYCLE_UNIT ) ?: 'month';
			$grace_days = max( 0, (int) $product->get_meta( self::META_GRACE_PERIOD ) );
			
			$expiry_ts = apply_filters( 'wpwa_recurring_calculate_expiry', 
			    strtotime( "+{$length} {$unit}" ), 
			    $item, 
			    $product 
			);
			$grace_ts   = $grace_days > 0 ? strtotime( "+{$grace_days} day", $expiry_ts ) : $expiry_ts;

			// Check if this is a renewal
			$order_type = $item->get_meta( self::META_ORDER_TYPE );
			
			if ( 'renewal' === $order_type ) {
				// RENEWAL: extend existing licence
				$this->extend_licence( $order->get_customer_id(), $product_id, $expiry_ts, $grace_ts, $order_id );
				
				$logger->info( "Renewed licence for product {$product_id} (order {$order_id})", [ 'source' => 'wpwa-recurring' ] );
			} else {
				// NEW: create licence
				$token = $this->encrypt_token( wp_generate_password( 32, false ) );
				
				$item->add_meta_data( self::META_KEY_EXPIRE, $expiry_ts, true );
				$item->add_meta_data( self::META_GRACE_UNTIL, $grace_ts, true );
				$item->add_meta_data( self::META_KEY_TOKEN, $token, true );
				$item->add_meta_data( self::META_LICENCE_STATUS, self::STATUS_ACTIVE, true );
				$item->add_meta_data( self::META_RENEWAL_COUNT, 0, true );
				$item->save();
				
				$logger->info( "Created new licence for product {$product_id} (order {$order_id})", [ 'source' => 'wpwa-recurring' ] );
			}

			// Send welcome/renewal email
			$this->send_licence_email( $order, $item, $order_type );
		}
	}

	/* =================================================================
	 *   LICENCE MANAGEMENT
	 * ================================================================= */
	
	private function get_customer_licence( $customer_id, $product_id ) {
		if ( ! $customer_id ) {
			return null;
		}

		$orders = wc_get_orders( [
			'customer_id' => $customer_id,
			'status'      => [ 'completed', 'processing' ],
			'limit'       => -1,
			'return'      => 'objects',
		] );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === (int) $product_id ) {
					$status = $item->get_meta( self::META_LICENCE_STATUS );
					
					if ( in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_GRACE ], true ) ) {
						return [
							'order_id' => $order->get_id(),
							'item_id'  => $item->get_id(),
							'item'     => $item,
							'status'   => $status,
							'expiry'   => (int) $item->get_meta( self::META_KEY_EXPIRE ),
						];
					}
				}
			}
		}

		return null;
	}

	private function extend_licence( $customer_id, $product_id, $new_expiry, $new_grace, $renewal_order_id ) {
		$licence = $this->get_customer_licence( $customer_id, $product_id );
		
		if ( ! $licence ) {
			return false;
		}

		$item = $licence['item'];
		$item->update_meta_data( self::META_KEY_EXPIRE, $new_expiry );
		$item->update_meta_data( self::META_GRACE_UNTIL, $new_grace );
		$item->update_meta_data( self::META_LICENCE_STATUS, self::STATUS_ACTIVE );
		$item->update_meta_data( self::META_TOKEN_REVOKED, 'no' );
		
		$renewal_count = (int) $item->get_meta( self::META_RENEWAL_COUNT ) ?: 0;
		$item->update_meta_data( self::META_RENEWAL_COUNT, $renewal_count + 1 );
		
		$item->save();

		return true;
	}

	/* =================================================================
	 *   SCHEDULED TASKS
	 * ================================================================= */
	
	public function process_expiring_licences() {
		$logger = wc_get_logger();
		$now    = time();
		$count  = 0;

		$orders = wc_get_orders( [
			'status' => [ 'completed', 'processing' ],
			'limit'  => -1,
			'return' => 'ids',
		] );

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			
			if ( ! $order ) {
				continue;
			}
			
			foreach ( $order->get_items() as $item ) {
				$status = $item->get_meta( self::META_LICENCE_STATUS );
				$expiry = (int) $item->get_meta( self::META_KEY_EXPIRE );
				$grace  = (int) $item->get_meta( self::META_GRACE_UNTIL );

				if ( ! $expiry || self::STATUS_REVOKED === $status ) {
					continue;
				}

				// Check if expired but within grace
				if ( $expiry < $now && $grace >= $now && self::STATUS_GRACE !== $status ) {
					$item->update_meta_data( self::META_LICENCE_STATUS, self::STATUS_GRACE );
					$item->save();
					
					$this->send_expiry_warning( $order, $item );
					$count++;
					
					$logger->info( "Licence #{$item->get_id()} entered grace period", [ 'source' => 'wpwa-recurring' ] );
				}

				// Check if grace period ended
				if ( $grace < $now && self::STATUS_REVOKED !== $status ) {
					$this->revoke_single_licence( $item );
					$count++;
					
					$logger->info( "Licence #{$item->get_id()} revoked after grace period", [ 'source' => 'wpwa-recurring' ] );
				}
			}
		}

		$logger->info( "Processed {$count} expiring licences", [ 'source' => 'wpwa-recurring' ] );
	}

	public function send_grace_period_warnings() {
		$now = time();
		
		$orders = wc_get_orders( [
			'status' => [ 'completed', 'processing' ],
			'limit'  => -1,
		] );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( self::STATUS_GRACE !== $item->get_meta( self::META_LICENCE_STATUS ) ) {
					continue;
				}

				$last_notice = (int) $item->get_meta( self::META_LAST_NOTICE );
				
				if ( $now - $last_notice < DAY_IN_SECONDS ) {
					continue;
				}

				$this->send_expiry_warning( $order, $item );
				$item->update_meta_data( self::META_LAST_NOTICE, $now );
				$item->save();
			}
		}
	}

	public function revoke_expired_tokens() {
		$this->process_expiring_licences();
	}

	private function revoke_single_licence( $item ) {
		$item->update_meta_data( self::META_LICENCE_STATUS, self::STATUS_REVOKED );
		$item->update_meta_data( self::META_TOKEN_REVOKED, 'yes' );
		$item->save();

		$this->call_weebly_revoke( $item );
		
		$order = $item->get_order();
		if ( $order ) {
			$this->send_revocation_email( $order, $item );
		}
	}

	private function call_weebly_revoke( $item ) {
		$site_id = $item->get_meta( 'site_id' );
		$app_id  = $item->get_product_id();
		$token   = $item->get_meta( self::META_KEY_TOKEN );
		
		if ( $token ) {
			$token = $this->decrypt_token( $token );
		}

		if ( ! $site_id || ! $token ) {
			return;
		}

		$response = wp_remote_post( "https://api.weebly.com/v1/user/sites/{$site_id}/apps/{$app_id}/deauthorize", [
			'headers' => [
				'Content-Type'          => 'application/json',
				'x-weebly-access-token' => $token,
			],
			'body' => wp_json_encode( [
				'site_id'         => $site_id,
				'platform_app_id' => $app_id,
			] ),
			'timeout' => 15,
		] );
		
		if ( is_wp_error( $response ) ) {
			$logger = wc_get_logger();
			$logger->error( 'Weebly revoke failed: ' . $response->get_error_message(), [ 'source' => 'wpwa-recurring' ] );
		}
	}

	/* =================================================================
	 *   EMAIL NOTIFICATIONS
	 * ================================================================= */
	
	private function send_licence_email( $order, $item, $type ) {
		$customer_email = $order->get_billing_email();
		$product        = wc_get_product( $item->get_product_id() );
		$expiry         = date_i18n( get_option( 'date_format' ), (int) $item->get_meta( self::META_KEY_EXPIRE ) );
		$customer_name  = $order->get_formatted_billing_full_name();

		if ( 'renewal' === $type ) {
			$subject = sprintf( __( 'Your %s subscription has been renewed', 'wpwa' ), $product->get_name() );
			$message = sprintf(
				__( 'Hi %1$s,

Your subscription to %2$s has been successfully renewed!

Your subscription is now active until %3$s.

Thank you for your continued support!

Best regards,
%4$s', 'wpwa' ),
				$customer_name,
				$product->get_name(),
				$expiry,
				get_bloginfo( 'name' )
			);
		} else {
			$subject = sprintf( __( 'Welcome to %s', 'wpwa' ), $product->get_name() );
			$message = sprintf(
				__( 'Hi %1$s,

Thank you for subscribing to %2$s!

Your subscription is active until %3$s.

We hope you enjoy using our service!

Best regards,
%4$s', 'wpwa' ),
				$customer_name,
				$product->get_name(),
				$expiry,
				get_bloginfo( 'name' )
			);
		}

		wp_mail( $customer_email, $subject, $message );
	}

	private function send_expiry_warning( $order, $item ) {
		$customer_email = $order->get_billing_email();
		$product        = wc_get_product( $item->get_product_id() );
		$grace_end      = date_i18n( get_option( 'date_format' ), (int) $item->get_meta( self::META_GRACE_UNTIL ) );
		$renewal_url    = add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() );
		$customer_name  = $order->get_formatted_billing_full_name();

		$subject = sprintf( __( 'Your %s subscription has expired', 'wpwa' ), $product->get_name() );
		$message = sprintf(
			__( 'Hi %1$s,

Your subscription to %2$s has expired.

You have until %3$s to renew before your access is completely revoked.

Click here to renew now: %4$s

If you have any questions, please contact us.

Best regards,
%5$s', 'wpwa' ),
			$customer_name,
			$product->get_name(),
			$grace_end,
			$renewal_url,
			get_bloginfo( 'name' )
		);

		wp_mail( $customer_email, $subject, $message );
	}

	private function send_revocation_email( $order, $item ) {
		$customer_email = $order->get_billing_email();
		$product        = wc_get_product( $item->get_product_id() );
		$renewal_url    = add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() );
		$customer_name  = $order->get_formatted_billing_full_name();

		$subject = sprintf( __( 'Access to %s has been revoked', 'wpwa' ), $product->get_name() );
		$message = sprintf(
			__( 'Hi %1$s,

Your subscription to %2$s has ended and your access has been removed.

You can re-subscribe anytime by clicking here: %3$s

We hope to see you back soon!

Best regards,
%4$s', 'wpwa' ),
			$customer_name,
			$product->get_name(),
			$renewal_url,
			get_bloginfo( 'name' )
		);

		wp_mail( $customer_email, $subject, $message );
	}

	/* =================================================================
	 *   FRONT-END UX
	 * ================================================================= */
	
	public function print_recurring_badge() {
		global $product;
		
		if ( ! $product || 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return;
		}

		$length = absint( $product->get_meta( self::META_CYCLE_LENGTH ) );
		$unit   = esc_html( $product->get_meta( self::META_CYCLE_UNIT ) );
		$price  = $product->get_meta( self::META_CYCLE_PRICE );
		
		if ( $price === '' ) {
			$price = $product->get_price();
		}
		
		$price_html = wc_price( $price );

		echo '<div class="wpwa-recurring-info" style="background:#f0f8ff;border-left:4px solid #2271b1;padding:15px;margin:15px 0;border-radius:4px;">';
		echo '<strong style="color:#2271b1;font-size:16px;">🔄 ' . esc_html__( 'Recurring Subscription', 'wpwa' ) . '</strong><br>';
		echo '<span style="color:#666;font-size:14px;">';
		printf( 
			esc_html__( '%1$s every %2$d %3$s', 'wpwa' ),
			$price_html,
			$length,
			$unit
		);
		echo '</span></div>';
	}

	public function maybe_hide_add_to_cart() {
		global $product;
		if ( $product && ! $product->is_purchasable() ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		}
	}

	public function maybe_block_repurchase( $purchasable, $product ) {
		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) || ! is_user_logged_in() ) {
			return $purchasable;
		}

		$licence = $this->get_customer_licence( get_current_user_id(), $product->get_id() );
		
		// Block if active licence exists
		if ( $licence && self::STATUS_ACTIVE === $licence['status'] ) {
			return false;
		}

		return $purchasable;
	}

	public function show_licence_status() {
		global $product;
		
		if ( ! $product || 'yes' !== $product->get_meta( self::META_KEY_FLAG ) || ! is_user_logged_in() ) {
			return;
		}

		$licence = $this->get_customer_licence( get_current_user_id(), $product->get_id() );
		
		if ( ! $licence ) {
			return;
		}

		$expiry = date_i18n( get_option( 'date_format' ), $licence['expiry'] );
		
		switch ( $licence['status'] ) {
			case self::STATUS_ACTIVE:
				wc_print_notice(
					sprintf( 
						__( '✅ You have an active subscription. Valid until %s.', 'wpwa' ),
						$expiry 
					),
					'success'
				);
				break;
				
			case self::STATUS_GRACE:
				$grace_end = date_i18n( 
					get_option( 'date_format' ), 
					(int) $licence['item']->get_meta( self::META_GRACE_UNTIL ) 
				);
				wc_print_notice(
					sprintf( 
						__( '⚠️ Your subscription expired on %1$s. Grace period ends %2$s. Renew now to maintain access!', 'wpwa' ),
						$expiry,
						$grace_end
					),
					'notice'
				);
				break;
		}
	}

	/* ═══════════════════════════════════════════════════════════
	 *   MY ACCOUNT INTEGRATION
	 * ═══════════════════════════════════════════════════════════ */
	
	public function add_my_licences_menu( $items ) {
		// Insert "My Licences" after "Orders"
		$new_items = [];
		
		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;
			
			if ( 'orders' === $key ) {
				$new_items['my-licences'] = __( 'My Subscriptions', 'wpwa' );
			}
		}
		
		return $new_items;
	}

	public function add_my_licences_endpoint() {
		add_rewrite_endpoint( 'my-licences', EP_ROOT | EP_PAGES );
	}

	public function my_licences_content() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$customer_id = get_current_user_id();
		$licences    = $this->get_all_customer_licences( $customer_id );

		if ( empty( $licences ) ) {
			echo '<p>' . esc_html__( 'You have no active subscriptions.', 'wpwa' ) . '</p>';
			return;
		}

		echo '<h2>' . esc_html__( 'My Subscriptions', 'wpwa' ) . '</h2>';
		echo '<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'wpwa' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wpwa' ) . '</th>';
		echo '<th>' . esc_html__( 'Expires', 'wpwa' ) . '</th>';
		echo '<th>' . esc_html__( 'Renewals', 'wpwa' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wpwa' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $licences as $lic ) {
			$product     = wc_get_product( $lic['product_id'] );
			$expiry_date = date_i18n( get_option( 'date_format' ), $lic['expiry'] );
			$status_text = $this->get_status_badge( $lic['status'] );
			$renewal_url = add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() );

			echo '<tr>';
			echo '<td data-title="' . esc_attr__( 'Product', 'wpwa' ) . '">';
			echo '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a>';
			echo '</td>';
			echo '<td data-title="' . esc_attr__( 'Status', 'wpwa' ) . '">' . $status_text . '</td>';
			echo '<td data-title="' . esc_attr__( 'Expires', 'wpwa' ) . '">' . esc_html( $expiry_date ) . '</td>';
			echo '<td data-title="' . esc_attr__( 'Renewals', 'wpwa' ) . '">' . esc_html( $lic['renewal_count'] ) . '</td>';
			echo '<td data-title="' . esc_attr__( 'Actions', 'wpwa' ) . '">';
			
			if ( in_array( $lic['status'], [ self::STATUS_GRACE, self::STATUS_EXPIRED ], true ) ) {
				echo '<a href="' . esc_url( $renewal_url ) . '" class="button">' . esc_html__( 'Renew Now', 'wpwa' ) . '</a>';
			} elseif ( self::STATUS_ACTIVE === $lic['status'] ) {
				echo '<a href="' . esc_url( $product->get_permalink() ) . '" class="button">' . esc_html__( 'View Product', 'wpwa' ) . '</a>';
			}
			
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function get_all_customer_licences( $customer_id ) {
		$licences = [];
		
		$orders = wc_get_orders( [
			'customer_id' => $customer_id,
			'status'      => [ 'completed', 'processing' ],
			'limit'       => -1,
		] );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = wc_get_product( $item->get_product_id() );
				
				if ( ! $product || 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
					continue;
				}

				$status = $item->get_meta( self::META_LICENCE_STATUS );
				
				// Only show active, grace, or recently expired
				if ( ! in_array( $status, [ self::STATUS_ACTIVE, self::STATUS_GRACE, self::STATUS_EXPIRED ], true ) ) {
					continue;
				}

				$licences[] = [
					'order_id'      => $order->get_id(),
					'item_id'       => $item->get_id(),
					'product_id'    => $item->get_product_id(),
					'product_name'  => $product->get_name(),
					'status'        => $status,
					'expiry'        => (int) $item->get_meta( self::META_KEY_EXPIRE ),
					'renewal_count' => (int) $item->get_meta( self::META_RENEWAL_COUNT ),
				];
			}
		}

		// Sort by expiry (newest first)
		usort( $licences, function( $a, $b ) {
			return $b['expiry'] - $a['expiry'];
		} );

		return $licences;
	}

	private function get_status_badge( $status ) {
		$badges = [
			self::STATUS_ACTIVE    => '<span style="color:#2e7d32;font-weight:600;">✅ ' . __( 'Active', 'wpwa' ) . '</span>',
			self::STATUS_GRACE     => '<span style="color:#f57c00;font-weight:600;">⚠️ ' . __( 'Grace Period', 'wpwa' ) . '</span>',
			self::STATUS_EXPIRED   => '<span style="color:#c62828;font-weight:600;">❌ ' . __( 'Expired', 'wpwa' ) . '</span>',
			self::STATUS_REVOKED   => '<span style="color:#757575;font-weight:600;">🚫 ' . __( 'Revoked', 'wpwa' ) . '</span>',
			self::STATUS_CANCELLED => '<span style="color:#616161;font-weight:600;">⛔ ' . __( 'Cancelled', 'wpwa' ) . '</span>',
		];

		return $badges[ $status ] ?? $status;
	}

	/* ═══════════════════════════════════════════════════════════
	 *   ADMIN COLUMNS
	 * ═══════════════════════════════════════════════════════════ */
	
	public function add_order_column( $columns ) {
		$new_columns = [];
		
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			
			// Add after "Status" column
			if ( 'order_status' === $key ) {
				$new_columns['wpwa_subscription'] = __( 'Subscription', 'wpwa' );
			}
		}
		
		return $new_columns;
	}

	public function render_order_column( $column, $post_id ) {
		if ( 'wpwa_subscription' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		
		if ( ! $order ) {
			return;
		}

		$has_recurring = false;
		$statuses      = [];

		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item->get_product_id() );
			
			if ( $product && 'yes' === $product->get_meta( self::META_KEY_FLAG ) ) {
				$has_recurring = true;
				$status = $item->get_meta( self::META_LICENCE_STATUS );
				
				if ( $status ) {
					$statuses[] = $this->get_status_badge( $status );
				}
			}
		}

		if ( $has_recurring ) {
			echo implode( '<br>', $statuses );
		} else {
			echo '—';
		}
	}

	public function render_order_column_hpos( $column, $order ) {
		if ( 'wpwa_subscription' !== $column ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		$this->render_order_column( $column, $order->get_id() );
	}

	/* ═══════════════════════════════════════════════════════════
	 *   UTILITY FUNCTIONS
	 * ═══════════════════════════════════════════════════════════ */
	
	private function encrypt_token( $plain ) {
		if ( empty( $plain ) ) {
			return '';
		}

		$key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$iv     = substr( hash( 'sha256', SECURE_AUTH_SALT ), 0, 16 );
		$cipher = 'aes-256-ctr';

		$encrypted = openssl_encrypt( $plain, $cipher, $key, 0, $iv );
		
		return $encrypted ? base64_encode( $encrypted ) : '';
	}

	private function decrypt_token( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$iv     = substr( hash( 'sha256', SECURE_AUTH_SALT ), 0, 16 );
		$cipher = 'aes-256-ctr';

		$decrypted = openssl_decrypt( base64_decode( $encrypted ), $cipher, $key, 0, $iv );
		
		return $decrypted ?: '';
	}

	/* ═══════════════════════════════════════════════════════════
	 *   PUBLIC API METHODS
	 * ═══════════════════════════════════════════════════════════ */
	
	/**
	 * Check if a customer has an active licence for a product
	 *
	 * @param int $customer_id Customer ID
	 * @param int $product_id  Product ID
	 * @return bool
	 */
	public function customer_has_active_licence( $customer_id, $product_id ) {
		$licence = $this->get_customer_licence( $customer_id, $product_id );
		return $licence && self::STATUS_ACTIVE === $licence['status'];
	}

	/**
	 * Get licence expiry timestamp
	 *
	 * @param int $customer_id Customer ID
	 * @param int $product_id  Product ID
	 * @return int|false Timestamp or false
	 */
	public function get_licence_expiry( $customer_id, $product_id ) {
		$licence = $this->get_customer_licence( $customer_id, $product_id );
		return $licence ? $licence['expiry'] : false;
	}

	/**
	 * Manually revoke a customer's licence
	 *
	 * @param int $customer_id Customer ID
	 * @param int $product_id  Product ID
	 * @return bool Success
	 */
	public function revoke_licence( $customer_id, $product_id ) {
		$licence = $this->get_customer_licence( $customer_id, $product_id );
		
		if ( ! $licence ) {
			return false;
		}

		$this->revoke_single_licence( $licence['item'] );
		
		return true;
	}

	/**
	 * Get all licences for display (admin/debugging)
	 *
	 * @return array
	 */
	public function get_all_licences() {
		$licences = [];
		
		$orders = wc_get_orders( [
			'status' => [ 'completed', 'processing' ],
			'limit'  => -1,
		] );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product = wc_get_product( $item->get_product_id() );
				
				if ( ! $product || 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
					continue;
				}

				$licences[] = [
					'order_id'      => $order->get_id(),
					'customer_id'   => $order->get_customer_id(),
					'customer_name' => $order->get_formatted_billing_full_name(),
					'product_id'    => $item->get_product_id(),
					'product_name'  => $product->get_name(),
					'status'        => $item->get_meta( self::META_LICENCE_STATUS ),
					'expiry'        => (int) $item->get_meta( self::META_KEY_EXPIRE ),
					'grace_until'   => (int) $item->get_meta( self::META_GRACE_UNTIL ),
				];
			}
		}

		return $licences;
	}
}

/* ═══════════════════════════════════════════════════════════
 *   BOOTSTRAP
 * ═══════════════════════════════════════════════════════════ */

// Initialize singleton
WPWA_Recurring::instance();