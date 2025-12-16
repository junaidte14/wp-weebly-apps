<?php
/**
 * WPWA Recurring
 * --------------
 * Lightweight licence / manual-renewal system for WooCommerce
 * (no WooCommerce Subscriptions dependency).
 *
 * @package WPWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Recurring {

	/* ───────────────────────────
	 *   Meta-keys & constants
	 * ───────────────────────── */
	const META_KEY_FLAG        = '_wpwa_is_recurring';           // yes / no
	const META_KEY_TOKEN       = 'access_token';              // encrypted
	const META_KEY_EXPIRE      = '_wpwa_expiry';                 // unix-ts

	const META_CYCLE_LENGTH    = '_wpwa_cycle_length';           // int
	const META_CYCLE_UNIT      = '_wpwa_cycle_unit';             // day|week|month|year
	const META_CYCLE_PRICE     = '_wpwa_cycle_price';            // decimal

	const ACTION_EXPIRE_SCAN   = 'wpwa_daily_check_expired';
	const CATEGORY_WEEBLY_APPS = 'weebly-apps';                  // slug

	/* ───────── bootstrap ───────── */
	public static function init() {

		/* –– product-edit UI –– */
		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'add_admin_fields' ] );
		add_action( 'woocommerce_admin_process_product_object',         [ __CLASS__, 'save_admin_fields' ] );

		/* –– price filter –– */
		add_filter( 'woocommerce_product_get_price',          [ __CLASS__, 'filter_product_price' ], 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', [ __CLASS__, 'filter_product_price' ], 20, 2 );

		add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'inject_cart_price' ], 20 );

		/* –– order workflow –– */
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'handle_order_completed' ] );

		/* –– Action Scheduler –– */
		add_action( self::ACTION_EXPIRE_SCAN, [ __CLASS__, 'maybe_revoke_expired_tokens' ] );

		/* –– front-end UX –– */
		add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'print_recurring_badge' ], 25 );
		add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'maybe_hide_add_to_cart' ], 1 );
		add_filter( 'woocommerce_is_purchasable',         [ __CLASS__, 'maybe_block_repurchase' ], 20, 2 );
		add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'maybe_show_active_notice' ], 10 );
	}

	/* ───────── activation hooks ───────── */
	public static function activate() {
		if ( ! as_next_scheduled_action( self::ACTION_EXPIRE_SCAN ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::ACTION_EXPIRE_SCAN );
		}
	}
	public static function deactivate() {
		as_unschedule_all_actions( self::ACTION_EXPIRE_SCAN );
	}

	/* =======================================================================
	 *  1. Admin: product data panel
	 * ===================================================================== */
	public static function add_admin_fields() {

		woocommerce_wp_checkbox( [
			'id'          => self::META_KEY_FLAG,
			'label'       => __( 'Recurring app?', 'wpwa' ),
			'description' => __( 'Customer must re-purchase each cycle to keep access.', 'wpwa' ),
		] );

		echo '<div class="options_group wpwa-recurring-extras">';

		woocommerce_wp_text_input( [
			'id'                => self::META_CYCLE_LENGTH,
			'label'             => __( 'Cycle length', 'wpwa' ),
			'type'              => 'number',
			'custom_attributes' => [ 'min' => 1, 'step' => 1 ],
		] );

		woocommerce_wp_select( [
			'id'      => self::META_CYCLE_UNIT,
			'label'   => __( 'Cycle unit', 'wpwa' ),
			'options' => [
				'day'   => __( 'Day(s)', 'wpwa' ),
				'week'  => __( 'Week(s)', 'wpwa' ),
				'month' => __( 'Month(s)', 'wpwa' ),
				'year'  => __( 'Year(s)', 'wpwa' ),
			],
		] );

		woocommerce_wp_text_input( [
			'id'          => self::META_CYCLE_PRICE,
			'label'       => __( 'Price per cycle', 'wpwa' ),
			'type'        => 'price',
			'desc_tip'    => true,
			'description' => __( 'Leave empty to use regular product price.', 'wpwa' ),
		] );

		echo '</div>';
	}

	public static function save_admin_fields( $product ) {

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$product->update_meta_data(
			self::META_KEY_FLAG,
			isset( $_POST[ self::META_KEY_FLAG ] ) ? 'yes' : 'no'
		);

		foreach ( [ self::META_CYCLE_LENGTH, self::META_CYCLE_UNIT, self::META_CYCLE_PRICE ] as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$product->update_meta_data( $key, wc_clean( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}

	/* =======================================================================
	 *  2. Pricing
	 * ===================================================================== */
	public static function filter_product_price( $price, $product ) {

		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return $price;
		}
		$cycle_price = $product->get_meta( self::META_CYCLE_PRICE );
		return $cycle_price !== '' ? (string) $cycle_price : $price;
	}

	public static function inject_cart_price( $cart ) {

		if ( did_action( 'woocommerce_before_calculate_totals' ) > 1 ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			$p = $item['data'];
			if ( 'yes' === $p->get_meta( self::META_KEY_FLAG ) ) {
				$cp = $p->get_meta( self::META_CYCLE_PRICE );
				if ( $cp !== '' ) {
					$p->set_price( (float) $cp );
				}
			}
		}
	}

	/* =======================================================================
	 *  3. Order completion: store/renew licence
	 * ===================================================================== */
	public static function handle_order_completed( $order_id ) {

		$order  = wc_get_order( $order_id );
		$logger = wc_get_logger();

		foreach ( $order->get_items() as $item ) {

			$product_id = $item->get_product_id();
			$product    = wc_get_product( $product_id );

			if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
				continue;
			}

			$length = max( 1, (int) $product->get_meta( self::META_CYCLE_LENGTH ) );
			$unit   = $product->get_meta( self::META_CYCLE_UNIT );
			$unit   = in_array( $unit, [ 'day','week','month','year' ], true ) ? $unit : 'day';
			$expiry = strtotime( "+{$length} {$unit}" );

			$prev_items = self::locate_customer_items( $order->get_customer_id(), $product_id );

			/* --- encrypt token placeholder (real token handling omitted) --- */
			$enc_token = self::encrypt_token( md5( uniqid() ) );

			if ( $prev_items ) { // RENEWAL
				foreach ( $prev_items as $prev ) {
					$prev->update_meta_data( self::META_KEY_EXPIRE, $expiry );
					$prev->update_meta_data( '_wpwa_token_revoked', 'no' );
					$prev->save();
				}
				$logger->info( "Renewed licence for product {$product_id} (order {$order_id})", 'wpwa' );
			} else {            // FIRST PURCHASE
				$item->add_meta_data( self::META_KEY_EXPIRE, $expiry );
				$item->add_meta_data( self::META_KEY_TOKEN,  $enc_token );
				$item->save();
				$logger->info( "Created licence for product {$product_id} (order {$order_id})", 'wpwa' );
			}
		}
	}

	/* =======================================================================
	 *  4. Scheduled scan: revoke tokens past expiry
	 * ===================================================================== */
	public static function maybe_revoke_expired_tokens() {

		$logger = wc_get_logger();
		$now    = time();

		$order_ids = wc_get_orders( [
			'status'        => [ 'completed','processing','on-hold' ],
			'type'          => 'shop_order',
			'return'        => 'ids',
			'limit'         => -1,
			'meta_key'      => '_wpwa_expiry',
			'meta_compare'  => '<',
			'meta_value'    => $now,
		] );

		foreach ( $order_ids as $oid ) {
			$order = wc_get_order( $oid );
			foreach ( $order->get_items() as $item ) {
				$expiry = (int) $item->get_meta( self::META_KEY_EXPIRE );
				if ( $expiry && $expiry < $now && 'yes' !== $item->get_meta( '_wpwa_token_revoked' ) ) {
					$item->update_meta_data( '_wpwa_token_revoked', 'yes' );
					$item->save();
					$logger->info( "Revoked token for order {$oid} / item {$item->get_id()}", 'wpwa' );
				}
			}
		}
	}

	/* =======================================================================
	 *  5. Front-end UX helpers
	 * ===================================================================== */
	public static function print_recurring_badge() {
		global $product;
		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return;
		}
		$len = absint( $product->get_meta( self::META_CYCLE_LENGTH ) );
		$unit= esc_html( $product->get_meta( self::META_CYCLE_UNIT ) );
		echo '<p class="wpwa-recurring-badge" style="color:#0073aa;font-weight:600">';
		printf( esc_html__( 'Recurring licence – %d %s(s)', 'wpwa' ), $len, $unit );
		echo '</p>';
	}

	public static function maybe_hide_add_to_cart() {
		global $product;
		if ( $product && ! $product->is_purchasable() ) {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		}
	}

	public static function maybe_block_repurchase( $purchasable, $product ) {

		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) || ! is_user_logged_in() ) {
			return $purchasable;
		}
		foreach ( self::locate_customer_items( get_current_user_id(), $product->get_id() ) as $item ) {
			if ( (int) $item->get_meta( self::META_KEY_EXPIRE ) > time() ) {
				return false; // active licence exists
			}
		}
		return $purchasable;
	}

	public static function maybe_show_active_notice() {
		global $product;
		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) || ! is_user_logged_in() ) {
			return;
		}
		foreach ( self::locate_customer_items( get_current_user_id(), $product->get_id() ) as $item ) {
			$exp = (int) $item->get_meta( self::META_KEY_EXPIRE );
			if ( $exp > time() ) {
				wc_print_notice(
					sprintf( __( 'You already own this licence. Access valid until %s.', 'wpwa' ),
						date_i18n( get_option( 'date_format' ), $exp ) ),
					'notice'
				);
				break;
			}
		}
	}

	/* =======================================================================
	 *  6. Utility helpers
	 * ===================================================================== */
	private static function locate_customer_items( $customer_id, $product_id ): array {

		$orders = wc_get_orders( [
			'customer_id'   => $customer_id,
			'status'        => [ 'completed','processing','on-hold' ],
			'type'          => 'shop_order',
			'limit'         => -1,
			'return'        => 'objects',
		] );

		$found = [];
		foreach ( $orders as $o ) {
			foreach ( $o->get_items() as $item ) {
				if ( (int) $item->get_product_id() === (int) $product_id ) {
					$found[] = $item;
				}
			}
		}
		return $found;
	}

	/** Encrypt token (AES-256-CTR). */
	private static function encrypt_token( string $plain ): string {
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$iv  = substr( hash( 'sha256', SECURE_AUTH_SALT ), 0, 16 );
		return base64_encode( openssl_encrypt( $plain, 'aes-256-ctr', $key, 0, $iv ) );
	}
	/** Decrypt token. Not currently used but here for completeness. */
	private static function decrypt_token( string $enc ): string {
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$iv  = substr( hash( 'sha256', SECURE_AUTH_SALT ), 0, 16 );
		return openssl_decrypt( base64_decode( $enc ), 'aes-256-ctr', $key, 0, $iv );
	}

	/** Check if product belongs to "weebly-apps" category. */
	public static function is_weebly_app( WC_Product $product ): bool {
		return has_term( self::CATEGORY_WEEBLY_APPS, 'product_cat', $product->get_id() );
	}
}

/* ───────── bootstrap ───────── */
WPWA_Recurring::init();
