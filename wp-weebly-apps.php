<?php
/**
 * @link              https://codoplex.com
 * @since             1.0.0
 * @package           wpwa
 *
 * @wordpress-plugin
 * Plugin Name:       Wp Weebly Apps
 * Plugin URI:        https://codoplex.com
 * Description:       A WordPress plugin to sell Weebly apps.
 * Version:           1.1.0
 * Author:            CODOPLEX
 * Author URI:        https://codoplex.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpwa
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
|--------------------------------------------------------------------------
| CONSTANTS
|--------------------------------------------------------------------------
*/
 
if ( ! defined( 'WPWA_BASE_FILE' ) )
    define( 'WPWA_BASE_FILE', __FILE__ );
if ( ! defined( 'WPWA_BASE_DIR' ) )
    define( 'WPWA_BASE_DIR', dirname( WPWA_BASE_FILE ) );
if ( ! defined( 'WPWA_PLUGIN_URL' ) )
    define( 'WPWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

global $wpwa_plugin_name;
$wpwa_plugin_name = 'wpwa';

global $wpwa_plugin_version;
$wpwa_plugin_version = '1.0';

if ( ! defined( 'WPWA_PLUGIN_VERSION' ) )
    define( 'WPWA_PLUGIN_VERSION', '1.0.0' );

require_once plugin_dir_path( __FILE__ ) . 'admin/admin-ajax-handlers.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/admin-enqueue.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/wpwa-analytics.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/recurring-orders-page.php';
require_once( plugin_dir_path( __FILE__ ) . 'settings/menu_pages.php');
add_action('admin_menu', 'wpwa_admin_actions');
require_once( plugin_dir_path( __FILE__ ) . 'custom_posts/products/products.php');
require_once( plugin_dir_path( __FILE__ ) . 'woocommerce/woo-integration.php');
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-recurring.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-whitelist.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-whitelist-auto-add.php'; 
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-whitelist-emails.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-whitelist-tracking.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-subscription-helper.php';

register_uninstall_hook('uninstall.php', '');

add_action( 'parse_request', function () {
	$path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	if ( strpos( $path, 'wpwa_phase_one' ) === 0 ) {
		require_once WPWA_BASE_DIR . '/payments/phase_one.php';
		exit;
	}
}, 0 );

/*translations*/
add_action('plugins_loaded', 'wpwa_load_textdomain');
function wpwa_load_textdomain() {
    load_plugin_textdomain( 'wpwa', false, WPWA_BASE_DIR . '/lang/' );
}

/**
 * The code that runs during plugin activation.
 */
function activate_wpwa() {
    WPWA_Recurring::activate();
    WPWA_Whitelist::activate();
    WPWA_Whitelist_Tracking::activate();
}
/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wpwa() {
    WPWA_Recurring::deactivate();
    WPWA_Whitelist::deactivate();
}
register_activation_hook( __FILE__, 'activate_wpwa' );
register_deactivation_hook( __FILE__, 'deactivate_wpwa' );

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});