<?php
/**
 * WPWA Whitelisting System - FIXED VERSION
 * Issue: String comparison failures due to data type inconsistencies
 * Fix: Normalize all user_id and site_id to strings and trim whitespace
 *
 * @package WPWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Whitelist {

	const TABLE_NAME = 'wpwa_whitelist';
	const META_WHITELIST_PRODUCT_ID = '_wpwa_whitelist_product_id';

	/* ───────── Bootstrap ───────── */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 25 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		
		// AJAX handlers
		add_action( 'wp_ajax_wpwa_whitelist_get_entries', [ __CLASS__, 'ajax_get_entries' ] );
		add_action( 'wp_ajax_wpwa_whitelist_save_entry', [ __CLASS__, 'ajax_save_entry' ] );
		add_action( 'wp_ajax_wpwa_whitelist_delete_entry', [ __CLASS__, 'ajax_delete_entry' ] );
		add_action( 'wp_ajax_wpwa_whitelist_check_subscription', [ __CLASS__, 'ajax_check_subscription' ] );
	}

	/* ───────── Activation: Create Database Table ───────── */
	public static function activate() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			whitelist_type varchar(20) NOT NULL DEFAULT 'user_id',
			user_id varchar(255) DEFAULT NULL,
			site_id varchar(255) DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			customer_name varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			subscription_order_id bigint(20) UNSIGNED DEFAULT NULL,
			expiry_date datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY whitelist_type (whitelist_type),
			KEY user_id (user_id),
			KEY site_id (site_id),
			KEY expiry_date (expiry_date)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/* ───────── Deactivation ───────── */
	public static function deactivate() {
		// Optionally keep data on deactivation
	}

	/* ───────── Admin Menu ───────── */
	public static function add_admin_menu() {
		global $wpwa_plugin_name;
		add_submenu_page(
			$wpwa_plugin_name,
			__( 'Whitelist Management', 'wpwa' ),
			__( 'Whitelist', 'wpwa' ),
			'manage_woocommerce',
			'wpwa_whitelist',
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	/* ───────── Enqueue Assets ───────── */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'wp-weebly-apps_page_wpwa_whitelist' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wpwa-whitelist-js',
			WPWA_PLUGIN_URL . 'admin/js/wpwa-whitelist.js',
			[ 'jquery' ],
			WPWA_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'wpwa-whitelist-js', 'wpwaWhitelist', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpwa_whitelist_nonce' ),
			'i18n'    => [
				'confirm_delete' => __( 'Are you sure you want to delete this whitelist entry?', 'wpwa' ),
				'error_generic'  => __( 'An error occurred. Please try again.', 'wpwa' ),
				'success_saved'  => __( 'Whitelist entry saved successfully!', 'wpwa' ),
				'success_deleted'=> __( 'Whitelist entry deleted successfully!', 'wpwa' ),
			],
		] );

	}

	/* ───────── Admin Page Render ───────── */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions.', 'wpwa' ) );
		}

		$whitelist_product_id = get_option( self::META_WHITELIST_PRODUCT_ID, 0 );
		$products = self::get_recurring_products();

		include WPWA_BASE_DIR . '/admin/whitelist-page.php';
	}

	/* ───────── Get Recurring Products (for dropdown) ───────── */
	private static function get_recurring_products() {
		$args = [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'   => WPWA_Recurring::META_KEY_FLAG,
					'value' => 'yes',
				],
			],
		];

		$query = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$products[] = [
					'id'   => get_the_ID(),
					'name' => get_the_title(),
				];
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  AJAX HANDLERS
	 * ═══════════════════════════════════════════════════════════════ */

	public static function ajax_get_entries() {
		check_ajax_referer( 'wpwa_whitelist_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$entries = $wpdb->get_results(
			"SELECT * FROM `{$table}` ORDER BY created_at DESC",
			ARRAY_A
		);

		// Enrich with subscription status
		foreach ( $entries as &$entry ) {
			$entry['subscription_status'] = self::get_subscription_status( $entry['subscription_order_id'] );
			$entry['is_active'] = self::is_entry_active( $entry );
		}

		wp_send_json_success( [ 'entries' => $entries ] );
	}

	public static function ajax_save_entry() {
		check_ajax_referer( 'wpwa_whitelist_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
		}

		$id                     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$whitelist_type         = sanitize_text_field( $_POST['whitelist_type'] ?? 'user_id' );
		
		// 🔥 CRITICAL FIX: Normalize IDs to strings and trim whitespace
		$user_id                = sanitize_text_field( $_POST['user_id'] ?? '' );
		$site_id                = sanitize_text_field( $_POST['site_id'] ?? '' );
		
		$email                  = sanitize_email( $_POST['email'] ?? '' );
		$customer_name          = sanitize_text_field( $_POST['customer_name'] ?? '' );
		$notes                  = sanitize_textarea_field( $_POST['notes'] ?? '' );
		$subscription_order_id  = absint( $_POST['subscription_order_id'] ?? 0 );

		// Enhanced logging
		error_log( "WPWA Whitelist Save: type={$whitelist_type}, user_id='{$user_id}', site_id='{$site_id}'" );

		// Validate type-specific requirements
		$validation = self::validate_entry( $whitelist_type, $user_id, $site_id );
		if ( ! $validation['valid'] ) {
			wp_send_json_error( [ 'message' => $validation['message'] ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$data = [
			'whitelist_type'        => $whitelist_type,
			'user_id'               => $user_id ?: null,
			'site_id'               => $site_id ?: null,
			'email'                 => $email ?: null,
			'customer_name'         => $customer_name ?: null,
			'notes'                 => $notes ?: null,
			'subscription_order_id' => $subscription_order_id ?: null,
		];

		// Calculate expiry from subscription if provided
		if ( $subscription_order_id ) {
			$expiry = self::calculate_expiry_from_subscription( $subscription_order_id );
			if ( $expiry ) {
				$data['expiry_date'] = $expiry;
			}
		}

		if ( $id ) {
			// Update
			$wpdb->update( $table, $data, [ 'id' => $id ] );
			$entry_id = $id;
			error_log( "WPWA Whitelist: Updated entry #{$entry_id}" );
		} else {
			// Insert
			$wpdb->insert( $table, $data );
			$entry_id = $wpdb->insert_id;
			error_log( "WPWA Whitelist: Created entry #{$entry_id}" );
		}

		wp_send_json_success( [
			'message'  => __( 'Whitelist entry saved successfully!', 'wpwa' ),
			'entry_id' => $entry_id,
		] );
	}

	public static function ajax_delete_entry() {
		check_ajax_referer( 'wpwa_whitelist_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => 'Invalid entry ID' ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->delete( $table, [ 'id' => $id ] );

		error_log( "WPWA Whitelist: Deleted entry #{$id}" );

		wp_send_json_success( [ 'message' => __( 'Entry deleted successfully!', 'wpwa' ) ] );
	}

	public static function ajax_check_subscription() {
		check_ajax_referer( 'wpwa_whitelist_nonce', 'nonce' );

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$status = self::get_subscription_status( $order_id );
		$expiry = self::calculate_expiry_from_subscription( $order_id );

		wp_send_json_success( [
			'status' => $status,
			'expiry' => $expiry,
		] );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  CORE WHITELIST LOGIC
	 * ═══════════════════════════════════════════════════════════════ */

	/**
	 * Check if user/site combination is whitelisted
	 * 🔥 FIXED: Proper string normalization and comparison
	 *
	 * @param string $user_id  Weebly user ID
	 * @param string $site_id  Weebly site ID
	 * @return bool
	 */
	public static function is_whitelisted( $user_id, $site_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$now   = current_time( 'mysql' );

		error_log( "🔍 WPWA Whitelist Check: user_id='{$user_id}', site_id='{$site_id}'" );

		// Type 2: User-specific (any app for this user_id regardless of site)
		$user_specific = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` 
			 WHERE whitelist_type = 'user_id' 
			   AND TRIM(user_id) = %s 
			   AND (expiry_date IS NULL OR expiry_date > %s)",
			$user_id,
			$now
		) );

		if ( $user_specific > 0 ) {
			error_log( "✅ WPWA Whitelist: MATCHED on user_id" );
			return true;
		}

		error_log( "❌ WPWA Whitelist: NO MATCH found" );
		return false;
	}

	/* ───────── Validation ───────── */
	private static function validate_entry( $type, $user_id, $site_id ) {
		switch ( $type ) {
			case 'user_id':
				if ( empty( $user_id ) ) {
					return [ 'valid' => false, 'message' => 'User ID is required for User ID type' ];
				}
				break;

			default:
				return [ 'valid' => false, 'message' => 'Invalid whitelist type' ];
		}

		return [ 'valid' => true ];
	}

	/* ───────── Subscription Helpers ───────── */
	private static function get_subscription_status( $order_id ) {
		if ( ! $order_id ) {
			return 'none';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 'invalid';
		}

		// Check if order contains recurring product
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			if ( 'yes' === get_post_meta( $product_id, WPWA_Recurring::META_KEY_FLAG, true ) ) {
				$expiry  = (int) $item->get_meta( '_wpwa_expiry' );
				$revoked = $item->get_meta( '_wpwa_token_revoked' );

				if ( 'yes' === $revoked ) {
					return 'revoked';
				}
				if ( $expiry && $expiry < time() ) {
					return 'expired';
				}
				return 'active';
			}
		}

		return 'not_recurring';
	}

	private static function calculate_expiry_from_subscription( $order_id ) {
		if ( ! $order_id ) {
			return null;
		}

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

	private static function is_entry_active( $entry ) {
		if ( ! empty( $entry['expiry_date'] ) ) {
			return strtotime( $entry['expiry_date'] ) > time();
		}
		return true; // No expiry = always active
	}

	/* ───────── Settings: Save Whitelist Product ID ───────── */
	public static function save_whitelist_product_id( $product_id ) {
		update_option( self::META_WHITELIST_PRODUCT_ID, absint( $product_id ) );
	}

	public static function get_whitelist_product_id() {
		return absint( get_option( self::META_WHITELIST_PRODUCT_ID, 0 ) );
	}
}

// Bootstrap
WPWA_Whitelist::init();