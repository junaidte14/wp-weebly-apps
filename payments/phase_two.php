<?php
/* -----------------------------------------------------------
 * 0. OLD-FLOW one-time redirect (fix “?” inside QS)
 * ----------------------------------------------------------*/
if (strpos($_SERVER['QUERY_STRING'], '?') !== false && !isset($_GET['do_replace'])) {
    $qs = str_replace('?', '&', $_SERVER['QUERY_STRING']);
    header('Location: https://' . $_SERVER['HTTP_HOST'] .
           '/wpwa_phase_two/?' . $qs . '&do_replace=1');
    exit;
}

//------------------------------------------------------------------
//  PHASE 2 – User returns here after granting access
//------------------------------------------------------------------

$request = $_SERVER['REQUEST_URI'];
$params  = parseRequest($request);

/* ----------------------------------------
 * Resolve pr_id from ?pr_id or state param
 * ----------------------------------------*/
$pr_id = 0;

if (!empty($_GET['pr_id'])) {
    $pr_id = intval($_GET['pr_id']);
} elseif (!empty($params['state'])) {
    $state = json_decode(base64_decode($params['state']), true) ?: [];

    if (empty($state['csrf']) || !wp_verify_nonce($state['csrf'], 'wpwa_weebly_oauth')) {
        wp_die('Invalid OAuth state (possible CSRF)');
    }

    $pr_id = intval($state['pr_id'] ?? 0);
}

if ($pr_id === 0) {
    wp_die('Missing pr_id (state or query parameter not found)');
}

/* --------------------------------------------------------
 * “do_replace” redirect still needed for old flow
 * --------------------------------------------------------*/
if (!isset($_GET['do_replace'])) {
    $query = str_replace('?', '&', $_SERVER['QUERY_STRING']);
    header('Location: https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_two/?' . $query . '&do_replace=1');
    exit;
}

/* --------------------------------------------------------
 * Meta fallback helper (shared with phase_one)
 * --------------------------------------------------------*/
function wpwa_get_meta_fallback(int $post_id, array $keys) {
    foreach ($keys as $key) {
        $val = get_post_meta($post_id, $key, true);
        if (!empty($val)) {
            return esc_html($val);
        }
    }
    return '';
}

/* --------------------------------------------------------
 * Look up client_id and client_secret
 * --------------------------------------------------------*/
$client_id = wpwa_get_meta_fallback($pr_id, [
    'woowa_product_client_id',
    'wpwa_product_client_id',
    'weebly_product_client_id',
    'wapp_product_client_id'
]);

$client_secret = wpwa_get_meta_fallback($pr_id, [
    'woowa_product_secret_key',
    'wpwa_product_secret_key',
    'weebly_product_secret_key',
    'wapp_product_secret_key'
]);

if (empty($client_id) || empty($client_secret)) {
    wp_die("Missing client credentials for product ID {$pr_id}");
}

/* --------------------------------------------------------
 * Exchange authorization code for access token
 * --------------------------------------------------------*/
require_once WPWA_BASE_DIR . '/libs/lib/Weebly/WeeblyClient.php';
$wc = new WeeblyClient($client_id, $client_secret, $params['user_id'], $params['site_id'], null);
$token = $wc->getAccessToken($params['authorization_code'], $params['callback_url']);

if ($token->access_token === null) {
    wp_die('<h3>Error: ' . esc_html($token->error ?: 'Unable to get Access Token') . '</h3>');
}

/* --------------------------------------------------------
 * WooCommerce order flow
 * -------------------------------------------------------*/
$access_token = $token->access_token;
$site_id      = $params['site_id'];
$user_id      = $params['user_id'];

$order_id = woowa_check_if_order_exists( $pr_id, $site_id, $user_id );

if ( ! $order_id ) {
	// ➜  No valid order – show payment form
	woowa_paymentProcessForm( $params, $pr_id, $token->callback_url, $access_token );
} else {
	// ➜  User already covered – just update token & redirect
	$order = wc_get_order( $order_id );
	foreach ( $order->get_items() as $item_id => $item ) {
		wc_update_order_item_meta( $item_id, 'access_token', $access_token );
	}
	wp_safe_redirect( $token->callback_url );
	exit;
}

?>
