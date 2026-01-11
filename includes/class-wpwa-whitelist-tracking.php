<?php
/**
 * WPWA Whitelist - Usage Tracking
 * File: includes/class-wpwa-whitelist-tracking.php
 * 
 * Tracks when whitelisted users install/access apps
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Whitelist_Tracking {

	const TABLE_NAME = 'wpwa_whitelist_usage';

	/* ───────── Bootstrap ───────── */
	public static function init() {
		// Admin page for viewing usage
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ], 26 );
		
		// AJAX handlers
		add_action( 'wp_ajax_wpwa_get_usage_stats', [ __CLASS__, 'ajax_get_usage_stats' ] );
		add_action( 'wp_ajax_wpwa_export_usage', [ __CLASS__, 'ajax_export_usage' ] );
	}

	/* ───────── Activation: Create Database Table ───────── */
	public static function activate() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			whitelist_entry_id bigint(20) UNSIGNED NOT NULL,
			user_id varchar(255) NOT NULL,
			site_id varchar(255) DEFAULT NULL,
			product_id bigint(20) UNSIGNED DEFAULT NULL,
			product_name varchar(255) DEFAULT NULL,
			app_client_id varchar(255) DEFAULT NULL,
			action_type varchar(50) NOT NULL DEFAULT 'install',
			ip_address varchar(100) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY whitelist_entry_id (whitelist_entry_id),
			KEY user_id (user_id),
			KEY site_id (site_id),
			KEY product_id (product_id),
			KEY action_type (action_type),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  TRACKING METHODS
	 * ═══════════════════════════════════════════════════════════════ */

	/**
	 * Log whitelist usage (called from phase_one.php when whitelist grants access)
	 */
	public static function log_usage( $user_id, $site_id, $product_id, $action_type = 'install' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		// Find the whitelist entry that granted this access
		$entry_id = self::find_matching_entry( $user_id, $site_id );
		if ( ! $entry_id ) {
			return false;
		}

		// Get product details
		$product = wc_get_product( $product_id );
		$product_name = $product ? $product->get_name() : '';
		$app_client_id = $product ? get_post_meta( $product_id, 'woowa_product_client_id', true ) : '';

		$data = [
			'whitelist_entry_id' => $entry_id,
			'user_id'            => $user_id,
			'site_id'            => $site_id,
			'product_id'         => $product_id,
			'product_name'       => $product_name,
			'app_client_id'      => $app_client_id,
			'action_type'        => $action_type,
			'ip_address'         => self::get_client_ip(),
			'user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
		];

		$inserted = $wpdb->insert( $table, $data );

		if ( $inserted ) {
			do_action( 'wpwa_whitelist_usage_logged', $wpdb->insert_id, $data );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Find which whitelist entry granted access
	 */
	private static function find_matching_entry( $user_id, $site_id ) {
		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;
		$now = current_time( 'mysql' );

		// Check global_user first
		$entry = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$table}` 
			 WHERE whitelist_type = 'global_user' 
			   AND user_id = %s 
			   AND (expiry_date IS NULL OR expiry_date > %s)
			 LIMIT 1",
			$user_id,
			$now
		) );

		if ( $entry ) {
			return $entry;
		}

		// Check user_id
		$entry = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$table}` 
			 WHERE whitelist_type = 'user_id' 
			   AND user_id = %s 
			   AND (expiry_date IS NULL OR expiry_date > %s)
			 LIMIT 1",
			$user_id,
			$now
		) );

		if ( $entry ) {
			return $entry;
		}

		// Check site_user
		$entry = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$table}` 
			 WHERE whitelist_type = 'site_user' 
			   AND user_id = %s 
			   AND site_id = %s 
			   AND (expiry_date IS NULL OR expiry_date > %s)
			 LIMIT 1",
			$user_id,
			$site_id,
			$now
		) );

		return $entry ?: null;
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  ADMIN INTERFACE
	 * ═══════════════════════════════════════════════════════════════ */

	public static function add_admin_menu() {
		global $wpwa_plugin_name;
		add_submenu_page(
			$wpwa_plugin_name,
			__( 'Whitelist Usage', 'wpwa' ),
			__( 'Usage Tracking', 'wpwa' ),
			'manage_woocommerce',
			'wpwa_whitelist_usage',
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions.', 'wpwa' ) );
		}

		$stats = self::get_overall_stats();
		$recent_usage = self::get_recent_usage( 20 );

		include WPWA_BASE_DIR . '/admin/whitelist-usage-page.php';
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  STATISTICS
	 * ═══════════════════════════════════════════════════════════════ */

	private static function get_overall_stats() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$stats = [
			'total_installs'       => 0,
			'unique_users'         => 0,
			'unique_sites'         => 0,
			'total_today'          => 0,
			'total_this_week'      => 0,
			'total_this_month'     => 0,
			'most_popular_apps'    => [],
			'most_active_users'    => [],
		];

		// Total installs
		$stats['total_installs'] = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		// Unique users and sites
		$stats['unique_users'] = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM `{$table}`" );
		$stats['unique_sites'] = $wpdb->get_var( "SELECT COUNT(DISTINCT site_id) FROM `{$table}` WHERE site_id IS NOT NULL" );

		// Time-based stats
		$stats['total_today'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE DATE(created_at) = %s",
			current_time( 'Y-m-d' )
		) );

		$stats['total_this_week'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE YEARWEEK(created_at) = YEARWEEK(%s)",
			current_time( 'mysql' )
		) );

		$stats['total_this_month'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d",
			current_time( 'Y' ),
			current_time( 'm' )
		) );

		// Most popular apps
		$stats['most_popular_apps'] = $wpdb->get_results(
			"SELECT product_name, COUNT(*) as install_count 
			 FROM `{$table}` 
			 WHERE product_name IS NOT NULL
			 GROUP BY product_name 
			 ORDER BY install_count DESC 
			 LIMIT 5",
			ARRAY_A
		);

		// Most active users
		$stats['most_active_users'] = $wpdb->get_results(
			"SELECT user_id, COUNT(*) as usage_count 
			 FROM `{$table}` 
			 GROUP BY user_id 
			 ORDER BY usage_count DESC 
			 LIMIT 5",
			ARRAY_A
		);

		return $stats;
	}

	private static function get_recent_usage( $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d",
			$limit
		), ARRAY_A );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  AJAX HANDLERS
	 * ═══════════════════════════════════════════════════════════════ */

	public static function ajax_get_usage_stats() {
		check_ajax_referer( 'wpwa_usage_tracking', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
		}

		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : null;
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : null;
		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : null;

		$stats = self::get_filtered_stats( $date_from, $date_to, $entry_id );

		wp_send_json_success( $stats );
	}

	private static function get_filtered_stats( $date_from, $date_to, $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$where = [ '1=1' ];
		$params = [];

		if ( $date_from ) {
			$where[] = 'DATE(created_at) >= %s';
			$params[] = $date_from;
		}

		if ( $date_to ) {
			$where[] = 'DATE(created_at) <= %s';
			$params[] = $date_to;
		}

		if ( $entry_id ) {
			$where[] = 'whitelist_entry_id = %d';
			$params[] = $entry_id;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM `{$table}` WHERE {$where_clause} ORDER BY created_at DESC LIMIT 100";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}

	public static function ajax_export_usage() {
		check_ajax_referer( 'wpwa_usage_tracking', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$results = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A );

		// Generate CSV
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=whitelist-usage-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// Headers
		fputcsv( $output, [
			'ID',
			'Whitelist Entry ID',
			'User ID',
			'Site ID',
			'Product Name',
			'Action Type',
			'IP Address',
			'Date/Time',
		] );

		// Data
		foreach ( $results as $row ) {
			fputcsv( $output, [
				$row['id'],
				$row['whitelist_entry_id'],
				$row['user_id'],
				$row['site_id'] ?: 'N/A',
				$row['product_name'] ?: 'Unknown',
				$row['action_type'],
				$row['ip_address'],
				$row['created_at'],
			] );
		}

		fclose( $output );
		exit;
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  HELPERS
	 * ═══════════════════════════════════════════════════════════════ */

	private static function get_client_ip() {
		$ip = '';
		
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_FORWARDED'] ) ) {
			$ip = $_SERVER['HTTP_FORWARDED'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : null;
	}
}

// Bootstrap
WPWA_Whitelist_Tracking::init();