<?php
/**
 * Unified Weebly OAuth handler (both phases, auto-detect)
 * Path examples:
 *   /wpwa_phase_one/2042866542
 *   /wpwa_phase_one/?pr_id=1557
 */

defined( 'ABSPATH' ) || exit;

/* ========== libs ========== */
require_once WPWA_BASE_DIR . '/libs/lib/Util/HMAC.php';
require_once WPWA_BASE_DIR . '/libs/lib/Weebly/WeeblyClient.php';

/* ========== tiny debug helper ========== */
if ( ! function_exists( 'wpwa_dbg' ) ) {
	function wpwa_dbg( $label, $data = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
		echo '<pre style="background:#eee;border:1px solid #bbb;padding:6px;margin:6px 0">'
		     . htmlspecialchars( $label . ( is_scalar( $data ) ? $data : print_r( $data, true ) ) )
		     . '</pre>';
		@flush();
	}
}

/* ========== legacy “?…?” fixer ========== */
if ( strpos( $_SERVER['QUERY_STRING'], '?' ) !== false && ! isset( $_GET['do_replace'] ) ) {
	$fixed = str_replace( '?', '&', $_SERVER['QUERY_STRING'] );
	wp_redirect( strtok( $_SERVER['REQUEST_URI'], '?' ) . '?' . $fixed . '&do_replace=1' );
	exit;
}

/* ========== helpers ========== */
function _wpwa_meta( int $id, array $keys ) {
	foreach ( $keys as $k ) {
		if ( $v = get_post_meta( $id, $k, true ) ) { return esc_html( $v ); }
	}
	return '';
}
function _wpwa_pr_from_client( int $client_id ): int {
	$q = new WP_Query( [
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'meta_query'     => [[ 'key' => 'woowa_product_client_id', 'value' => $client_id ]],
	] );
	return $q->have_posts() ? (int) $q->posts[0]->ID : 0;
}

/* ========== PHASE-2 first (authorization_code present) ========== */
if ( isset( $_GET['authorization_code'], $_GET['pr_id'] ) ) {
	$pr_id  = absint( $_GET['pr_id'] );
	wpwa_dbg('🐞 Phase-2: Incoming pr_id', $pr_id);

	if ( ! $pr_id || get_post_type( $pr_id ) !== 'product' || get_post_status( $pr_id ) !== 'publish' || ! wc_get_product( $pr_id ) ) {
		wpwa_dbg('🐞 Phase-2: pr_id validation failed, trying fallback', $pr_id);
		$pr_id = intval(get_post_meta( $pr_id, 'woowa_product_id', true ));
		wpwa_dbg('🐞 Phase-2: fallback pr_id', $pr_id);
	}

	$code   = sanitize_text_field( $_GET['authorization_code'] );
	$user   = sanitize_text_field( $_GET['user_id'] ?? '' );
	$site   = sanitize_text_field( $_GET['site_id'] ?? '' );
	$cb_url = esc_url_raw( $_GET['callback_url'] ?? '' );

	wpwa_dbg('🐞 Phase-2: Received GET', $_GET);

	$cid  = _wpwa_meta( $pr_id, [ 'woowa_product_client_id','wpwa_product_client_id','weebly_product_client_id','wapp_product_client_id' ] );
	$csec = _wpwa_meta( $pr_id, [ 'woowa_product_secret_key','wpwa_product_secret_key','weebly_product_secret_key','wapp_product_secret_key' ] );
	wpwa_dbg('🐞 Phase-2: Client ID and Secret', [ 'cid' => $cid, 'csec' => $csec ]);

	if ( ! $cid || ! $csec ) { wp_die( 'Phase-2: missing client creds' ); }

	$wc = new WeeblyClient( $cid, $csec, $user, $site, null );
	$tok = $wc->getAccessToken( $code, $cb_url );
	wpwa_dbg('🐞 Phase-2: Access token response', $tok);

	if ( empty( $tok->access_token ) ) {
		wp_die( 'Phase-2: token exchange failed (' . ( $tok->error ?? 'unknown' ) . ')' );
	}

	$access = $tok->access_token;
	$product_id = _wpwa_pr_from_client($cid);
	$order = woowa_check_if_order_exists( $pr_id, $site, $user );
	if ( ! ( $order ) ) {
		woowa_paymentProcessForm( $_GET, $pr_id, $tok->callback_url, $access );
	} else {
		$o = wc_get_order( $order );
		$redirect_url = $tok->callback_url; // fallback
		foreach ( $o->get_items() as $iid => $item ) {
			wc_update_order_item_meta( $iid, 'access_token', $access );
			$item_user = wc_get_order_item_meta( $iid, 'user_id' );
			$item_site = wc_get_order_item_meta( $iid, 'site_id' );

			if ( $item_user === $user && $item_site === $site ) {
				$final_url = wc_get_order_item_meta( $iid, 'final_url' );
				if ( $final_url ) {
					$redirect_url = esc_url_raw( $final_url );
				}
			}
		}
		// ✅ Allow external redirect to Weebly
		add_filter( 'allowed_redirect_hosts', function( $hosts ) {
			$hosts[] = 'www.weebly.com';
			return $hosts;
		});
		wp_safe_redirect( $redirect_url );
		exit;
	}
	exit;
}

/* ========== PHASE-1 (build OAuth URL) ========== */
$path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
$segments = explode( '/', $path );
$client_id_path = isset( $segments[1] ) && ctype_digit( $segments[1] ) ? (int) $segments[1] : null;

if ( isset( $_GET['pr_id'] ) ) {
	$pr_id = absint( $_GET['pr_id'] );
} elseif ( $client_id_path ) {
	$pr_id = _wpwa_pr_from_client( $client_id_path );
} else {
	wp_die( 'Phase-1: missing pr_id / client_id' );
}
wpwa_dbg('🐞 Phase-1: Resolved pr_id', $pr_id);

$cid  = _wpwa_meta( $pr_id, [ 'woowa_product_client_id','wpwa_product_client_id','weebly_product_client_id','wapp_product_client_id' ] );
$csec = _wpwa_meta( $pr_id, [ 'woowa_product_secret_key','wpwa_product_secret_key','weebly_product_secret_key','wapp_product_secret_key' ] );
wpwa_dbg('🐞 Phase-1: Client ID and Secret', [ 'cid' => $cid, 'csec' => $csec ]);

if ( ! $cid || ! $csec ) { wp_die( 'Phase-1: missing client creds' ); }

$hmac_parts = [ 'user_id' => $_GET['user_id'] ?? '', 'timestamp' => $_GET['timestamp'] ?? '' ];
if ( isset( $_GET['site_id'] ) ) { $hmac_parts['site_id'] = $_GET['site_id']; }

$is_hmac_valid = HMAC::isHmacValid( http_build_query( $hmac_parts ), $csec, $_GET['hmac'] ?? '' );
wpwa_dbg('🐞 Phase-1: HMAC Validation', [ 'input' => $hmac_parts, 'valid' => $is_hmac_valid ]);
if ( ! $is_hmac_valid ) {
	wp_die( 'Phase-1: HMAC invalid' );
}

$state = rawurlencode( base64_encode( json_encode( [
	'pr_id' => $pr_id,
	'csrf'  => wp_create_nonce( 'wpwa_weebly_oauth' ),
] ) ) );

$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_one/?pr_id=' . $pr_id;
$auth_url = 'https://www.weebly.com/app-center/oauth/authorize?' . http_build_query( [
	'client_id'    => $cid,
	'user_id'      => $_GET['user_id'],
	'site_id'      => $_GET['site_id'] ?? '',
	'redirect_uri' => $redirect_uri,
	'state'        => $state,
], '', '&', PHP_QUERY_RFC3986 );

wpwa_dbg('🐞 Phase-1: Redirecting to OAuth URL', $auth_url);
wp_redirect( $auth_url );
exit;