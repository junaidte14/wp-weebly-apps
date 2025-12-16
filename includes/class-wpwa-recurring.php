<?php
/**
 * Handle per-product recurring settings and automated token renewals.
 *
 * @package WPWA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPWA_Recurring {

	/* ────────────────────────────
	 *        Meta-key constants
	 * ────────────────────────── */
	const META_KEY_FLAG        = '_wpwa_is_recurring';   // yes / no
	const META_KEY_TOKEN       = 'access_token';
	const META_KEY_EXPIRE      = '_wpwa_expiry';

	const META_CYCLE_LENGTH    = '_wpwa_cycle_length';   // int
	const META_CYCLE_UNIT      = '_wpwa_cycle_unit';     // day|week|month|year
	const META_CYCLE_PRICE     = '_wpwa_cycle_price';    // decimal string

	/* ────────────────────────────
	 *              Setup
	 * ────────────────────────── */
	public static function init() {

		/* product-edit UI */
		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'add_checkbox' ] );
		add_action( 'woocommerce_admin_process_product_object',         [ __CLASS__, 'save_checkbox' ] );

		add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'add_extra_fields' ], 20 );
		add_action( 'woocommerce_admin_process_product_object',         [ __CLASS__, 'save_extra_fields' ], 20 );

		/* price override on front / cart */
		add_filter( 'woocommerce_product_get_price',         [ __CLASS__, 'override_display_price' ], 20, 2 );
		add_filter( 'woocommerce_product_get_regular_price', [ __CLASS__, 'override_display_price' ], 20, 2 );
		add_action( 'woocommerce_before_calculate_totals',   [ __CLASS__, 'set_cart_item_price' ], 20 );

		/* order lifecycle */
		add_action( 'woocommerce_order_status_completed',    [ __CLASS__, 'handle_order_completion' ] );

		/* daily cron */
		add_action( 'wpwa_daily_check_expired',              [ __CLASS__, 'maybe_revoke_expired' ] );

		/* admin JS to hide/show extra fields */
		add_action( 'admin_enqueue_scripts',                 [ __CLASS__, 'enqueue_admin_assets' ] );

        add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'display_recurring_details'], 25 );

        add_filter( 'woocommerce_is_purchasable', [ __CLASS__, 'maybe_disallow_if_already_purchased' ], 20, 2 );
        add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'maybe_show_purchase_notice' ], 10 );


	}

	/* ───── 1. “Recurring app?” checkbox ───── */

	public static function add_checkbox() {
		woocommerce_wp_checkbox( [
			'id'          => self::META_KEY_FLAG,
			'label'       => __( 'Recurring app?', 'wpwa' ),
			'description' => __( 'If enabled, user must repurchase every billing cycle to keep access.', 'wpwa' ),
		] );
	}

	public static function save_checkbox( $product ) {
		$product->update_meta_data(
			self::META_KEY_FLAG,
			isset( $_POST[ self::META_KEY_FLAG ] ) ? 'yes' : 'no'
		);
	}

	/* ───── 1-b. Extra cycle fields  ───── */

	public static function add_extra_fields() {

		echo '<div class="options_group wpwa_recurring_fields">';

		woocommerce_wp_text_input( [
			'id'                => self::META_CYCLE_LENGTH,
			'label'             => __( 'Cycle length', 'wpwa' ),
			'desc_tip'          => true,
			'description'       => __( 'Number of time units per billing cycle.', 'wpwa' ),
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
			'desc_tip'    => true,
			'description' => __( 'Leave empty to use the product’s regular price.', 'wpwa' ),
			'type'        => 'price',
		] );

		echo '</div>';
	}

	public static function save_extra_fields( $product ) {
		foreach ( [ self::META_CYCLE_LENGTH, self::META_CYCLE_UNIT, self::META_CYCLE_PRICE ] as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$product->update_meta_data( $key, wc_clean( $_POST[ $key ] ) );
			}
		}
	}

	/* ───── 2. Dynamic price overrides ───── */

	/** Catalog / checkout price */
	public static function override_display_price( $price, $product ) {

		if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
			return $price;
		}

		$cycle_price = $product->get_meta( self::META_CYCLE_PRICE );
		return $cycle_price !== '' ? (float) $cycle_price : $price;   // fall back to regular price
	}

	/** Keep cart totals in sync */
	public static function set_cart_item_price( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {

			$product = $cart_item['data'];

			if ( 'yes' === $product->get_meta( self::META_KEY_FLAG ) ) {
				$cycle_price = $product->get_meta( self::META_CYCLE_PRICE );
				if ( $cycle_price !== '' ) {
					$product->set_price( (float) $cycle_price );
				}
			}
		}
	}

	/* ───── 3. Handle first purchase / renewal ───── */

	public static function handle_order_completion( $order_id ) {
        $order = wc_get_order( $order_id );
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $product    = wc_get_product( $product_id );
            /* ── Skip non-recurring products ── */
            if ( 'yes' !== get_post_meta( $product_id, self::META_KEY_FLAG, true ) ) {
                continue;
            }
            /* ── Work-out this product’s duration ── */
            $length = (int) get_post_meta( $product_id, self::META_CYCLE_LENGTH, true );
            $length = $length > 0 ? $length : 30;     // sane default
            $unit   = get_post_meta( $product_id, self::META_CYCLE_UNIT, true );
            $unit   = in_array( $unit, [ 'day','week','month','year' ], true ) ? $unit : 'day';

            $period  = "+{$length} {$unit}" . ( $length > 1 ? 's' : '' );
            $expires = strtotime( $period );

            /* ── Renewal vs first-time ── */
            $prev_items = self::locate_previous_item( $order->get_customer_id(), $product_id );
            /* ------------------------------------------------------------
            *  Should this item run the Weebly-token flow?
            * ---------------------------------------------------------- */
            $do_weebly = function_exists( 'is_weebly_app' ) && is_weebly_app( $product );
            if ( $prev_items ) { /* ---------- RENEWAL ---------- */
                foreach ( $prev_items as $prev ) {
                    $prev->update_meta_data( self::META_KEY_EXPIRE, $expires );
                    $prev->update_meta_data( '_wpwa_token_revoked', 'no' );
                    $prev->save();
                    if ( $do_weebly ) {
                        //WPWA_Weebly_API::refresh_token( $prev->get_meta( self::META_KEY_TOKEN ) );
                    }
                }
            } else { /* ---------- FIRST-TIME ---------- */
                if ( $do_weebly ) {
                    //$token = WPWA_Weebly_API::generate_token( $order, $item );
                    //$item->add_meta_data( self::META_KEY_TOKEN, $token );
                }
                $item->add_meta_data( self::META_KEY_EXPIRE, $expires );
                $item->save();
            }
        }
    }


	/* ───── 4. Daily cron – revoke expired tokens ───── */

	public static function maybe_revoke_expired() {
		$orders = wc_get_orders( [
			'status' => [ 'completed' ],
			'limit'  => -1,
		] );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$expiry = (int) $item->get_meta( self::META_KEY_EXPIRE );
				if ( $expiry && $expiry < time() && 'yes' !== $item->get_meta( '_wpwa_token_revoked' ) ) {
					//WPWA_Weebly_API::revoke_token( $item->get_meta( self::META_KEY_TOKEN ) );
					$item->update_meta_data( '_wpwa_token_revoked', 'yes' );
					$item->save();
				}
			}
		}
	}

	/* ───── 5. Locate historical order-items ───── */

	private static function locate_previous_item( $customer_id, $product_id ) {

		$orders = wc_get_orders( [
			'customer_id' => $customer_id,
			'status'      => [ 'completed' ],
			'limit'       => -1,
		] );

		$matches = [];

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() === (int) $product_id ) {
					$matches[] = $item;
				}
			}
		}
		return $matches;
	}

	/* ───── 6. Admin JS to toggle extra fields ───── */
	public static function enqueue_admin_assets( $hook ) {
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			wp_enqueue_script(
				'wpwa-recurring-admin',
				plugins_url( '/js/wpwa-admin-recurring.js', dirname( __DIR__ ) ),
				[ 'jquery' ],
				WPWA_PLUGIN_VERSION,
				true
			);
		}
	}

    public static function display_recurring_details() {
        global $product;
        $is_recurring = $product->get_meta( '_wpwa_is_recurring' );
        $length       = $product->get_meta( '_wpwa_cycle_length' );
        $unit         = $product->get_meta( '_wpwa_cycle_unit' );
        $price        = $product->get_meta( '_wpwa_cycle_price' );
        if ( 'yes' === $is_recurring && $length && $unit ) {
            echo '<p>';
            printf( __( 'Recurring every %d %s(s)', 'wpwa' ), $length, $unit );
            if ( $price ) {
                echo ' - ' . wc_price( $price );
            }
            echo '</p>';
        }
    }

    public static function maybe_disallow_if_already_purchased( $purchasable, $product ) {
        if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) ) {
            return $purchasable;
        }

        if ( ! is_user_logged_in() ) {
            return $purchasable; // allow guest checkout
        }

        $current_user = get_current_user_id();
        $previous     = self::locate_previous_item( $current_user, $product->get_id() );

        foreach ( $previous as $item ) {
            $expiry = (int) $item->get_meta( self::META_KEY_EXPIRE );
            if ( $expiry && $expiry > time() ) {
                return false; // already active
            }
        }

        return $purchasable;
    }

    public static function maybe_show_purchase_notice() {
        global $product;

        if ( 'yes' !== $product->get_meta( self::META_KEY_FLAG ) || ! is_user_logged_in() ) {
            return;
        }

        $current_user = get_current_user_id();
        $previous     = self::locate_previous_item( $current_user, $product->get_id() );

        foreach ( $previous as $item ) {
            $expiry = (int) $item->get_meta( self::META_KEY_EXPIRE );
            if ( $expiry && $expiry > time() ) {
                $expires_date = date_i18n( get_option( 'date_format' ), $expiry );
                echo '<p class="woocommerce-info">';
                printf( __( 'You have already purchased this product. Access is valid until %s.', 'wpwa' ), $expires_date );
                echo '</p>';
                break;
            }
        }
    }



}

/* ───────── Bootstrap ───────── */
WPWA_Recurring::init();
