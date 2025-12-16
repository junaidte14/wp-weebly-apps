<?php
/*create custom post type wpwa_products*/

function create_post_wpwa_products() {
    register_post_type( 'wpwa_products',
        array(
            'labels' => array(
                'name' => __('Products', 'wpwa'),
                'singular_name' => __('Product', 'wpwa'),
                'add_new' => __('Add New', 'wpwa'),
                'add_new_item' => __('Add New Product', 'wpwa'),
                'edit' => __('Edit', 'wpwa'),
                'edit_item' => __('Edit Product', 'wpwa'),
                'new_item' => __('New Product', 'wpwa'),
                'view' => __('View', 'wpwa'),
                'view_item' => __('View Product', 'wpwa'),
                'search_items' => __('Search Products', 'wpwa'),
                'not_found' => __('No Product found', 'wpwa'),
                'not_found_in_trash' => __('No Products found in Trash', 'wpwa'),
                'parent' => __('Parent Product', 'wpwa')
            ),
 
            'public' => true,
            'show_in_rest' => true,
            'menu_position' => 15,
            'supports' => array( 'title', 'editor', 'comments', 'thumbnail' , 'excerpt' ),
            'taxonomies' => array( '' ),
            'has_archive' => true,
            'show_in_menu' => false,
        )
    );
}

add_action( 'init', 'create_post_wpwa_products' );

function display_wpwa_products_meta_box( $wpwa_products ) {
    // Retrieve current name of the Director and Movie Rating based on review ID
    if ( function_exists('wp_nonce_field') ){
        wp_nonce_field( basename( __FILE__ ), 'wpwa_products_meta_box');
    }
    $woowa_product_id = intval(get_post_meta( $wpwa_products->ID, 'woowa_product_id', true ));
    $product_price = floatval(get_post_meta( $wpwa_products->ID, 'wpwa_product_price', true ));
    $product_item_number = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_item_number', true ));
    $product_paccount = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_paccount', true ));
    $product_client_id = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_client_id', true ));
    $product_secret_key = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_secret_key', true ));
    
    ?>
    <table>
    	<tr>
            <td style="width: 100%"><?php _e('Woocommerce Product ID', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_product_id" value="<?php echo esc_attr($woowa_product_id); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Price', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_price" value="<?php echo esc_attr($product_price); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Item Number', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_item_number" value="<?php echo esc_attr($product_item_number); ?>" />
            </td>
        </tr>
        <tr>
            <td><?php _e('Payment Account', 'wpwa'); ?></td>
            <td>
                <select name="wpwa_products_paccount">
                    <option value="" <?php if($product_paccount == ''){echo 'selected';}?> ><?php _e('Select Payment Account', 'wpwa'); ?></option>
                    <option value="sandbox" <?php if($product_paccount == 'sandbox'){echo 'selected';}?> ><?php _e('Sandbox', 'wpwa'); ?></option>
                    <option value="live" <?php if($product_paccount == 'live'){echo 'selected';}?> ><?php _e('Live', 'wpwa'); ?></option>
                </select>
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Client ID', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_client_id" value="<?php echo esc_attr($product_client_id); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Secret Key', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_secret_key" value="<?php echo esc_attr($product_secret_key); ?>" />
            </td>
        </tr>
        
    </table>
    <?php
}

function register_meta_boxes_for_products() {
    add_meta_box( 'wpwa_products_meta_box',
        __('Product Details'),
        'display_wpwa_products_meta_box',
        'wpwa_products', 'normal', 'high'
    );
}

add_action( 'admin_init', 'register_meta_boxes_for_products' );

function add_wpwa_products_fields( $wpwa_products_id, $wpwa_products ) {
    // Checks save status
    $is_autosave = wp_is_post_autosave( $wpwa_products_id );
    $is_revision = wp_is_post_revision( $wpwa_products_id );
    $is_valid_nonce = ( isset( $_POST[ 'wpwa_products_meta_box' ] ) && wp_verify_nonce( $_POST[ 'wpwa_products_meta_box' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }
    // Check post type
    if ( $wpwa_products->post_type == 'wpwa_products' ) {
        // Store data in post meta table if present in post data
        if ( isset( $_POST['woowa_product_id'] ) ) {
            update_post_meta( $wpwa_products_id, 'woowa_product_id', sanitize_text_field($_POST['woowa_product_id']) );
        }
        if ( isset( $_POST['wpwa_products_price'] ) && $_POST['wpwa_products_price'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_price', sanitize_text_field($_POST['wpwa_products_price']) );
        }
        if ( isset( $_POST['wpwa_products_item_number'] ) && $_POST['wpwa_products_item_number'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_item_number', sanitize_text_field($_POST['wpwa_products_item_number']) );
        }
        if ( isset( $_POST['wpwa_products_paccount'] ) && $_POST['wpwa_products_paccount'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_paccount', sanitize_text_field($_POST['wpwa_products_paccount']) );
        }
        if ( isset( $_POST['wpwa_products_client_id'] ) && $_POST['wpwa_products_client_id'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_client_id', sanitize_text_field($_POST['wpwa_products_client_id']) );
        }
        if ( isset( $_POST['wpwa_products_secret_key'] ) && $_POST['wpwa_products_secret_key'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_secret_key', sanitize_text_field($_POST['wpwa_products_secret_key']) );
        }
    }
}

add_action( 'save_post', 'add_wpwa_products_fields', 10, 2 );


function display_wpwa_products_auth_url_meta_box( $wpwa_products ) {
    // Retrieve current name of the Director and Movie Rating based on review ID
    if ( function_exists('wp_nonce_field') ){
        wp_nonce_field( basename( __FILE__ ), 'wpwa_products_auth_url_meta_box');
    }
    $product_auth_url = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_auth_url', true ));
	$product_center_url = esc_url(get_post_meta( $wpwa_products->ID, 'wpwa_product_center_url', true ));
	$product_wreview = esc_html(get_post_meta( $wpwa_products->ID, 'wpwa_product_wreview', true ));
    if($product_auth_url == ''){
        $product_auth_url = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_one/?pr_id='.$wpwa_products->ID;
    }
    
    ?>
    <table>
        <tr>
            <td style="width: 100%"><?php _e('Callback URL to be added in manifest file of Weebly App', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_auth_url" value="<?php echo esc_attr($product_auth_url); ?>" />
            </td>
        </tr>
		<tr>
            <td style="width: 100%"><?php _e('Weebly App Center URL', 'wpwa'); ?></td>
            <td>
                <input type="text" size="80" name="wpwa_products_center_url" value="<?php echo esc_attr($product_center_url); ?>" />
            </td>
        </tr>
		
		<tr>
            <td><?php _e('Weebly Review Status', 'wpwa'); ?></td>
            <td>
                <select name="wpwa_product_wreview">
                    <option value="live" <?php if($product_wreview == 'live'){echo 'selected';}?> ><?php _e('Live', 'wpwa'); ?></option>
                    <option value="inreview" <?php if($product_wreview == 'inreview'){echo 'selected';}?> ><?php _e('In Review', 'wpwa'); ?></option>
                </select>
            </td>
        </tr>
        
    </table>
    <?php
}

function register_meta_boxes_for_products_auth_url() {
    add_meta_box( 'wpwa_products_auth_url_meta_box',
        __('Callback URL'),
        'display_wpwa_products_auth_url_meta_box',
        'wpwa_products', 'normal', 'high'
    );
}

add_action( 'admin_init', 'register_meta_boxes_for_products_auth_url' );

function add_wpwa_products_auth_url_fields( $wpwa_products_id, $wpwa_products ) {
    // Checks save status
    $is_autosave = wp_is_post_autosave( $wpwa_products_id );
    $is_revision = wp_is_post_revision( $wpwa_products_id );
    $is_valid_nonce = ( isset( $_POST[ 'wpwa_products_auth_url_meta_box' ] ) && wp_verify_nonce( $_POST[ 'wpwa_products_auth_url_meta_box' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }
    // Check post type
    if ( $wpwa_products->post_type == 'wpwa_products' ) {
        // Store data in post meta table if present in post data
        if ( isset( $_POST['wpwa_products_auth_url'] ) && $_POST['wpwa_products_auth_url'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_auth_url', sanitize_text_field($_POST['wpwa_products_auth_url']) );
        }
		if ( isset( $_POST['wpwa_product_wreview'] ) && $_POST['wpwa_product_wreview'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_wreview', sanitize_text_field($_POST['wpwa_product_wreview']) );
        }
		if ( isset( $_POST['wpwa_products_center_url'] ) && $_POST['wpwa_products_center_url'] != '' ) {
            update_post_meta( $wpwa_products_id, 'wpwa_product_center_url', sanitize_text_field($_POST['wpwa_products_center_url']) );
        }
    }
}

add_action( 'save_post', 'add_wpwa_products_auth_url_fields', 10, 2 );

?>