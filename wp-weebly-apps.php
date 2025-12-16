<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
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

/**
 * The code that runs during plugin activation.
 */
function activate_wpwa() {
    if ( ! wp_next_scheduled( 'wpwa_daily_check_expired' ) ) {
        wp_schedule_event( time(), 'daily', 'wpwa_daily_check_expired' );
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wpwa() {
    wp_clear_scheduled_hook( 'wpwa_daily_check_expired' );
}

register_activation_hook( __FILE__, 'activate_wpwa' );
register_deactivation_hook( __FILE__, 'deactivate_wpwa' );

/*Plugin Menu Handling*/
require_once plugin_dir_path( __FILE__ ) . 'admin/recurring-orders-page.php';
require_once( plugin_dir_path( __FILE__ ) . 'settings/menu_pages.php');
add_action('admin_menu', 'wpwa_admin_actions');
/*Register custom post types*/
require_once( plugin_dir_path( __FILE__ ) . 'custom_posts/products/products.php');
//woocommerce integration
require_once( plugin_dir_path( __FILE__ ) . 'woocommerce/woo-integration.php');
/*Run Weebly App*/
require_once( plugin_dir_path( __FILE__ ) . 'weebly_functions.php');
// recurring functionality
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpwa-recurring.php';

/*Table Removal After Uninstalling the plugin*/

register_uninstall_hook('uninstall.php', '');

add_action('parse_request', 'wpwa_custom_url_handler');

function wpwa_custom_url_handler() {
    $check_part = explode('?', $_SERVER["REQUEST_URI"]);
    $check_url_part = $check_part[0];
   if($check_url_part == '/wpwa_phase_one/') {
      require_once( WPWA_BASE_DIR. '/payments/phase_one.php');
      exit();
   }

   if($check_url_part == '/wpwa_phase_two/') {
      require_once( WPWA_BASE_DIR. '/payments/phase_two.php');
      exit();
   }

}


/*Manage plugin scripts and styles*/
function wpwa_scripts_styles(){
    wp_register_style( 'wpwa_styles', plugins_url( '/css/wp_weebly_apps_styles.css', __FILE__ ) );
    wp_register_script( 'wpwa_script', plugins_url( '/js/wp_weebly_apps_js.js', __FILE__ ), array('jquery'), '', true);
	wp_enqueue_script( 'wpwa_script' );
    wp_enqueue_style( 'wpwa_styles' );
}
add_action( 'wp_enqueue_scripts', 'wpwa_scripts_styles' );

/*translations*/
add_action('plugins_loaded', 'wpwa_load_textdomain');
function wpwa_load_textdomain() {
    load_plugin_textdomain( 'wpwa', false, WPWA_BASE_DIR . '/lang/' );
}

