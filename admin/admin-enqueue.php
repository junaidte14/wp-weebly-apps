<?php 

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_action('admin_enqueue_scripts', function ($hook) {
    // Load only on your orders page
    
    if ( isset($_GET['page']) && $_GET['page'] === 'wpwa_manage_orders' ){
        wp_enqueue_script(
            'wpwa-orders-js',
            plugin_dir_url(__FILE__) . 'js/wpwa-orders.js',
            ['jquery'],
            '1.0.0',
            true
        );
    
        wp_localize_script(
            'wpwa-orders-js',
            'wpwaAjax',
            [
                'ajaxurl' => admin_url('admin-ajax.php') . '?action=wpwa_handle_order_action',
                'nonce'   => wp_create_nonce('wpwa_order_action'),
                'i18n'    => [
                    'confirm_delete' => 'Are you sure you want to delete this order? This will also remove app access.',
                    'confirm_remove' => 'Are you sure you want to remove app access for this order?',
                    'confirm_refund' => 'Mark this order as refunded?',
                    'error_generic'  => 'An error occurred',
                ],
            ]
        );   
    }
});
