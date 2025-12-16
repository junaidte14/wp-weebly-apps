<?php
/* -----------------------------------------------------------
 * 0. OLD-FLOW one-time redirect  (fix “?pr_id=…?user_id=…”)
 * ----------------------------------------------------------*/
if (strpos($_SERVER['QUERY_STRING'], '?') !== false && !isset($_GET['do_replace'])) {
    $qs = str_replace('?', '&', $_SERVER['QUERY_STRING']);   // ? → &
    header('Location: https://' . $_SERVER['HTTP_HOST'] .
           '/wpwa_phase_one/?' . $qs . '&do_replace=1');
    exit;
}

//------------------------------------------------------------------
//  PHASE 1 – Validate HMAC and redirect to Weebly OAuth
//------------------------------------------------------------------

$request = $_SERVER['REQUEST_URI'];
$params  = parseRequest($request); // your helper

/* ------------------------------------------
 * Detect OLD (via ?pr_id=) vs NEW ({client_id})
 * ------------------------------------------*/
$has_pr_query = isset($_GET['pr_id']) && $_GET['pr_id'] !== '';
$client_id_from_url = null;

if (!$has_pr_query) {
    if (preg_match('#/wpwa_phase_one/([0-9]+)/?#', $request, $m)) {
        $client_id_from_url = $m[1];
    }
}

/* ---------------------------------------
 * Meta Fallback Helper (NEW)
 * --------------------------------------*/
function wpwa_get_meta_fallback( int $post_id, array $keys ) {
    foreach ( $keys as $key ) {
        $val = get_post_meta( $post_id, $key, true );
        if ( ! empty( $val ) ) {
            return esc_html( $val );
        }
    }
    return '';
}

/* ----------------------------------------
 * Resolve pr_id, client_id, client_secret
 * ----------------------------------------*/
if ($has_pr_query) {
    $pr_id = intval($_GET['pr_id']);
} else {
    if (!$client_id_from_url) {
        wp_die('Missing client_id in callback URL');
    }

    $q = new WP_Query([
        'post_type'      => ['product'],
        'posts_per_page' => 1,
        'meta_query'     => [[
            'key'   => 'wpwa_product_client_id',
            'value' => $client_id_from_url,
        ]]
    ]);
    if (!$q->have_posts()) {
        wp_die("No product found for client_id {$client_id_from_url}");
    }
    $pr_id = $q->posts[0]->ID;
}

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
    wp_die("Client credentials missing for product ID {$pr_id}");
}

/* ----------------------------------------
 * Validate Weebly HMAC
 * ----------------------------------------*/
require_once WPWA_BASE_DIR . '/libs/lib/Util/HMAC.php';

$hmac_params = ['user_id'=>$params['user_id'], 'timestamp'=>$params['timestamp']];
if (isset($params['site_id'])) $hmac_params['site_id']=$params['site_id'];

if (!HMAC::isHmacValid(http_build_query($hmac_params), $client_secret, $params['hmac'])) {
    wp_die('<h3>Unable to verify HMAC. Request is invalid.</h3>');
}

/* ----------------------------------------
 * Build the Authorization URL
 * ----------------------------------------*/
require_once WPWA_BASE_DIR . '/libs/lib/Weebly/WeeblyClient.php';
$wc = new WeeblyClient($client_id, $client_secret, $params['user_id'], $params['site_id'], null);

if ($has_pr_query) {
    $redirect_raw = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_two/?pr_id=' . $pr_id;
    $url = $wc->getAuthorizationUrl([], rawurlencode($redirect_raw), $params['callback_url']);
} else {
    $redirect_raw = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_two/';
    $state = base64_encode(json_encode([
        'pr_id' => $pr_id,
        'csrf'  => wp_create_nonce('wpwa_weebly_oauth')
    ]));
    $url = $wc->getAuthorizationUrl([], rawurlencode($redirect_raw), $params['callback_url']);
    $url .= '&state=' . rawurlencode($state);
}

/* ----------------------------------------
 * Keep do_replace redirect for cleanup
 * ----------------------------------------*/
if (!isset($_GET['do_replace'])) {
    $query = str_replace('?', '&', $_SERVER['QUERY_STRING']);
    $self  = $has_pr_query
           ? '/wpwa_phase_one/?'
           : "/wpwa_phase_one/{$client_id_from_url}/?";
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $self . $query . '&do_replace=1');
    exit;
}

/* ----------------------------------------
 * Redirect to Weebly OAuth
 * ----------------------------------------*/
//die($url);
wp_redirect($url);
exit;
?>
