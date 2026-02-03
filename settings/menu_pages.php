<?php
function display_wpwa_setup_page() {
    require_once(WPWA_BASE_DIR . '/admin/home.php');
}


function display_wpwa_manage_orders_page() {
    require_once(WPWA_BASE_DIR. '/admin/manage_orders_page.php');
}

function wpwa_admin_actions() {
	global $wpwa_plugin_name;
	add_menu_page( __('WPWA Dashboard Page', 'wpwa'), 'WP Weebly Apps', 'administrator', $wpwa_plugin_name, 'display_wpwa_setup_page', ''
    );
    add_submenu_page( $wpwa_plugin_name, __('WPWA Dashboard', 'wpwa'), 'Dashboard', 'administrator', $wpwa_plugin_name, 'display_wpwa_setup_page'
    );
    add_submenu_page( $wpwa_plugin_name, __('WPWA Products', 'wpwa'), 'Products', 'administrator','edit.php?post_type=wpwa_products');
    add_submenu_page( $wpwa_plugin_name, __('WPWA Manage Orders', 'wpwa'), 'Manage Orders', 'administrator', 'wpwa_manage_orders', 'display_wpwa_manage_orders_page');
    add_submenu_page( $wpwa_plugin_name, __('Recurring Orders', 'wpwa'), __('Recurring Orders', 'wpwa'), 'administrator', 'wpwa_recurring_orders', 'wpwa_render_recurring_orders_page'
    );
    add_submenu_page(
        $wpwa_plugin_name,
        __('WC Migration', 'wpwa'),
        __('WC Migration', 'wpwa'),
        'administrator',
        'wpwa_wc_migration',
        'wpwa_render_migration_dashboard'
    );
}

/* Parent Menu Fix */
add_filter( 'parent_file', 'wpwa_parent_file' );
 
/**
 * Fix Parent Admin Menu Item
 */
function wpwa_parent_file( $parent_file ){
 
    /* Get current screen */
    global $current_screen, $self, $wpwa_plugin_name;
 
    if ( in_array( $current_screen->base, array( 'post', 'edit' ) ) && 
        (
            'wpwa_products' == $current_screen->post_type
        ) 
    ) {
        $parent_file = $wpwa_plugin_name;
    }
 
    return $parent_file;
}

add_filter( 'submenu_file', 'wpwa_submenu_file' );
 
/**
 * Fix Sub Menu Item Highlights
 */
function wpwa_submenu_file( $submenu_file ){
 
    /* Get current screen */
    global $current_screen, $self;
 
    if ( in_array( $current_screen->base, array( 'post', 'edit' ) ) && 'wpwa_products' == $current_screen->post_type ) {
        $submenu_file = 'edit.php?post_type=wpwa_products';
    }
 
    return $submenu_file;
}

?>