<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  Helper: call Weebly de-authorize endpoint and return true/false
 * -----------------------------------------------------------------------*/
function wpwa_weebly_revoke_access( $site_id, $app_id, $access_token ) {

	$url  = "https://api.weebly.com/v1/user/sites/{$site_id}/apps/{$app_id}/deauthorize";

	$args = [
		'method'  => 'POST',
		'timeout' => 30,
		'headers' => [
			'cache-control'           => 'no-cache',
			'Content-Type'            => 'application/json',
			'x-weebly-access-token'   => $access_token,
		],
		'body'    => wp_json_encode( [
			'site_id'          => $site_id,
			'platform_app_id'  => $app_id,
		] ),
	];

	$response   = wp_remote_post( $url, $args );
	$http_code  = wp_remote_retrieve_response_code( $response );

	return (int) $http_code === 200;
}

/* -------------------------------------------------------------------------
 *  UI callback for wpwa-recurring-orders page
 * -----------------------------------------------------------------------*/
function wpwa_render_recurring_orders_page() {

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( __( 'You do not have permission.', 'wpwa' ) );
	}

	/* ── 1  Handle POST (revoke / restore) ─────────────────────────── */
	if ( isset( $_POST['wpwa_do_revoke'] ) && check_admin_referer( 'wpwa_revoke_nonce' ) ) {

		$order_id = absint( $_POST['order_id']  ?? 0 );
		$item_id  = absint( $_POST['item_id']   ?? 0 );
		$action   = sanitize_text_field( $_POST['wpwa_do_revoke'] ); // revoke | restore

		if ( $order_id && $item_id ) {

			$order = wc_get_order( $order_id );
			$item  = $order ? $order->get_item( $item_id ) : false;

			if ( $item ) {

				/* pull stored meta */
				$site_id      = $item->get_meta( 'site_id' );
				$user_id      = $item->get_meta( 'user_id' );
				$app_id 	  = $item->get_product_id();
				$access_token = $item->get_meta( WPWA_Recurring::META_KEY_TOKEN );

				if ( 'revoke' === $action ) {

					$ok = wpwa_weebly_revoke_access( $site_id, $app_id, $access_token );

					if ( $ok ) {
						$item->update_meta_data( '_wpwa_token_revoked', 'yes' );
						$order->add_order_note( sprintf(
							/* translators: 1: site id 2: app id */
							__( 'Access revoked manually (Site ID: %1$s / App ID: %2$s).', 'wpwa' ),
							$site_id, $app_id
						) );
						$notice = 'revoked=1';
					} else {
						$notice = 'error=1';
					}

				} elseif ( 'restore' === $action ) {

					// === Optional: call your refresh endpoint here if needed ===
					$item->update_meta_data( '_wpwa_token_revoked', 'no' );
					$order->add_order_note( __( 'Access restored manually in admin.', 'wpwa' ) );
					$notice = 'restored=1';
				}

				$item->save();
			}
		}

		/* redirect to avoid resubmission & pass status */
		$redirect = add_query_arg( $notice ?? 'updated=1',
			admin_url( 'admin.php?page=wpwa_recurring_orders' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/* ── 2  Query recurring items ─────────────────────────────────── */
	$orders = wc_get_orders( [
		'status' => [ 'completed' ],
		'limit'  => -1,
	] );

	$rows = [];

	foreach ( $orders as $order ) {
		foreach ( $order->get_items() as $item_id => $item ) {

			$product_id = $item->get_product_id();
			if ( 'yes' !== get_post_meta( $product_id, '_wpwa_is_recurring', true ) ) {
				continue;
			}

			$rows[] = [
				'order_id'   => $order->get_id(),
				'item_id'    => $item_id,
				'customer'   => $order->get_formatted_billing_full_name(),
				'product'    => get_the_title( $product_id ),
				'site_id'    => $item->get_meta( 'site_id' ),
				'user_id'    => $item->get_meta( 'user_id' ),
				'app_id'     => $item->get_meta( 'app_id' ),
				'expires'    => $item->get_meta( '_wpwa_expiry' ),
				'revoked'    => $item->get_meta( '_wpwa_token_revoked' ) === 'yes',
			];
		}
	}

	/* ── 3  Render table ──────────────────────────────────────────── */
	echo '<div class="wrap"><h1>' . esc_html__( 'Recurring Orders', 'wpwa' ) . '</h1>';

	// admin notices
	if ( isset( $_GET['revoked'] ) ) {
		echo '<div class="updated notice"><p>' . esc_html__( 'Access revoked successfully.', 'wpwa' ) . '</p></div>';
	} elseif ( isset( $_GET['restored'] ) ) {
		echo '<div class="updated notice"><p>' . esc_html__( 'Access restored successfully.', 'wpwa' ) . '</p></div>';
	} elseif ( isset( $_GET['error'] ) ) {
		echo '<div class="error notice"><p>' . esc_html__( 'Weebly API error – please check the token and try again.', 'wpwa' ) . '</p></div>';
	}

	echo '<table class="widefat striped fixed">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Order #',   'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Customer',  'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Product',   'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Site ID',   'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'User ID',   'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Expires',   'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Status',    'wpwa' ) . '</th>';
	echo '<th>' . esc_html__( 'Action',    'wpwa' ) . '</th>';
	echo '</tr></thead><tbody>';

	if ( ! $rows ) {
		echo '<tr><td colspan="8">' . esc_html__( 'No recurring orders found.', 'wpwa' ) . '</td></tr>';
	}

	foreach ( $rows as $r ) {

		$expiry_ts = (int) $r['expires'];
		$pretty    = $expiry_ts ? date_i18n( get_option( 'date_format' ), $expiry_ts ) : '—';

		$status = $r['revoked'] ? __( 'Revoked', 'wpwa' )
								: ( $expiry_ts && $expiry_ts < time()
									? __( 'Expired', 'wpwa' )
									: __( 'Active',  'wpwa' ) );

		$action_label = $r['revoked'] ? __( 'Restore', 'wpwa' ) : __( 'Revoke', 'wpwa' );
		$action_value = $r['revoked'] ? 'restore'              : 'revoke';

		echo '<tr>';
		echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $r['order_id'] . '&action=edit' ) ) . '">#' . esc_html( $r['order_id'] ) . '</a></td>';
		echo '<td>' . esc_html( $r['customer'] ) . '</td>';
		echo '<td>' . esc_html( $r['product']  ) . '</td>';
		echo '<td>' . esc_html( $r['site_id']  ) . '</td>';
		echo '<td>' . esc_html( $r['user_id']  ) . '</td>';
		echo '<td>' . esc_html( $pretty       ) . '</td>';
		echo '<td>' . esc_html( $status       ) . '</td>';
		echo '<td>
				<form method="post" style="margin:0;">
					' . wp_nonce_field( 'wpwa_revoke_nonce', '_wpnonce', true, false ) . '
					<input type="hidden" name="order_id"  value="' . esc_attr( $r['order_id'] ) . '">
					<input type="hidden" name="item_id"   value="' . esc_attr( $r['item_id'] ) . '">
					<button class="button button-small" name="wpwa_do_revoke" value="' . esc_attr( $action_value ) . '">' . esc_html( $action_label ) . '</button>
				</form>
		      </td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';
}
