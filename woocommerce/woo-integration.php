<?php
//functions for woocommerce integration will go here
function woowa_paymentProcessForm($params, $pr_id, $final_url, $access_token) {
    $form_post_url = 'https://' . $_SERVER["HTTP_HOST"] . '/wpwa_payment_process/?pr_id=' . $pr_id;
    $pr_item_number = esc_html(get_post_meta( $pr_id, 'woowa_product_item_number', true ));
    get_header();
    ?>
    <div class="wrap">
        <div id="primary" class="content-area">
            <main id="main" class="site-main" role="main">
                <style>
                .card {
                  box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
                  max-width: 50%;
                  margin: auto;
                  text-align: center;
                  font-family: arial;
                }

                .price {
                  color: grey;
                  font-size: 22px;
                }
                
                .card h2, .card p{
                	padding: 0 15px;
                }
                
                @media(max-width: 767px){
                	.card {
                      max-width: 100%;
                    }
                }

                </style>
                <?php
                //place order before sending to checkout
                $product = get_product($pr_id);
                $site_id = $params['site_id'];
                $user_id = $params['user_id'];
                ?>
                <div class="wpwa_product_details">
                	<div class="card">
                      <img src="<?php echo get_the_post_thumbnail_url($pr_id); ?>" alt="product image" style="width:100%" class="img-responsive">
                      <h2><?php echo get_the_title($pr_id); ?></h2>
                      <p class="price"><?php echo wc_price($product->get_price()); ?></p>
                      <p><?php echo $product->get_short_description(); ?></p>
                      <form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action',get_permalink( get_option( 'woocommerce_checkout_page_id' ) ) ) );?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->id); ?>">
                        <input type="hidden" name="site_id" value="<?php echo esc_attr($site_id); ?>">
                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                        <input type="hidden" name="access_token" value="<?php echo esc_attr($access_token); ?>">
                        <input type="hidden" name="final_url" value="<?php echo esc_attr($final_url); ?>">
                        <button type="submit">Place Order</button>
                        </form>

                        <div class="warnings" style="padding: 20px 0;">
                            <p>After completing the payment, you will receive an email contining final URL (final_url). Click on that URL or copy and paste that URL in your browser to complete the app installation process.</p>
                            <p>If you have any questions, contact us at <a href="https://codoplex.com/contact/" target="_blank">https://codoplex.com/contact/</a> page (mention transaction ID, if applicable).</p>
                        </div>
                    </div>
                </div>
                
            </main><!-- #main -->
        </div><!-- #primary -->
        
    </div><!-- .wrap -->

    <?php 
        get_footer();
}

//add custom fields in woocommerce products

function display_woowa_products_meta_box( $woowa_products ) {
    // Retrieve current name of the Director and Movie Rating based on review ID
    if ( function_exists('wp_nonce_field') ){
        wp_nonce_field( basename( __FILE__ ), 'woowa_products_meta_box');
    }
    $wapwa_product_id = intval(get_post_meta( $woowa_products->ID, 'wapwa_product_id', true ));
    $product_item_number = esc_html(get_post_meta( $woowa_products->ID, 'woowa_product_item_number', true ));
    $product_client_id = esc_html(get_post_meta( $woowa_products->ID, 'woowa_product_client_id', true ));
    $product_secret_key = esc_html(get_post_meta( $woowa_products->ID, 'woowa_product_secret_key', true ));
    
    ?>
    <table>
    	<tr>
            <td style="width: 100%"><?php _e('WPWA Product ID', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="wapwa_product_id" value="<?php echo esc_attr($wapwa_product_id); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Item Number', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_products_item_number" value="<?php echo esc_attr($product_item_number); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Client ID', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_products_client_id" value="<?php echo esc_attr($product_client_id); ?>" />
            </td>
        </tr>
        <tr>
            <td style="width: 100%"><?php _e('Secret Key', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_products_secret_key" value="<?php echo esc_attr($product_secret_key); ?>" />
            </td>
        </tr>
        
    </table>
    <?php
}

function register_meta_boxes_for_woowa_products() {
    add_meta_box( 'woowa_products_meta_box',
        __('Product Details'),
        'display_woowa_products_meta_box',
        'product', 'normal', 'high'
    );
}

add_action( 'admin_init', 'register_meta_boxes_for_woowa_products' );

function add_woowa_products_fields( $woowa_products_id, $woowa_products ) {
    // Checks save status
    $is_autosave = wp_is_post_autosave( $woowa_products_id );
    $is_revision = wp_is_post_revision( $woowa_products_id );
    $is_valid_nonce = ( isset( $_POST[ 'woowa_products_meta_box' ] ) && wp_verify_nonce( $_POST[ 'woowa_products_meta_box' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }
    // Check post type
    if ( $woowa_products->post_type == 'product' ) {
        // Store data in post meta table if present in post data
        if ( isset( $_POST['wapwa_product_id'] ) ) {
            update_post_meta( $woowa_products_id, 'wapwa_product_id', sanitize_text_field($_POST['wapwa_product_id']) );
        }
        if ( isset( $_POST['woowa_products_item_number'] ) && $_POST['woowa_products_item_number'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_item_number', sanitize_text_field($_POST['woowa_products_item_number']) );
        }
        if ( isset( $_POST['woowa_products_client_id'] ) && $_POST['woowa_products_client_id'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_client_id', sanitize_text_field($_POST['woowa_products_client_id']) );
        }
        if ( isset( $_POST['woowa_products_secret_key'] ) && $_POST['woowa_products_secret_key'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_secret_key', sanitize_text_field($_POST['woowa_products_secret_key']) );
        }
    }
}

add_action( 'save_post', 'add_woowa_products_fields', 10, 2 );


function display_woowa_products_auth_url_meta_box( $woowa_products ) {
    // Retrieve current name of the Director and Movie Rating based on review ID
    if ( function_exists('wp_nonce_field') ){
        wp_nonce_field( basename( __FILE__ ), 'woowa_products_auth_url_meta_box');
    }
    $product_auth_url = esc_html(get_post_meta( $woowa_products->ID, 'woowa_product_auth_url', true ));
    $product_center_url = esc_url(get_post_meta( $woowa_products->ID, 'woowa_product_center_url', true ));
    $product_wreview = esc_html(get_post_meta( $woowa_products->ID, 'woowa_product_wreview', true ));
    if($product_auth_url == ''){
        $product_auth_url = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_one/?pr_id='.$woowa_products->ID;
    }
    
    ?>
    <table>
        <tr>
            <td style="width: 100%"><?php _e('Callback URL to be added in manifest file of Weebly App', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_products_auth_url" value="<?php echo esc_attr($product_auth_url); ?>" />
            </td>
        </tr>
    <tr>
            <td style="width: 100%"><?php _e('Weebly App Center URL', 'woowa'); ?></td>
            <td>
                <input type="text" size="80" name="woowa_products_center_url" value="<?php echo esc_attr($product_center_url); ?>" />
            </td>
        </tr>
    
    <tr>
            <td><?php _e('Weebly Review Status', 'woowa'); ?></td>
            <td>
                <select name="woowa_product_wreview">
                    <option value="live" <?php if($product_wreview == 'live'){echo 'selected';}?> ><?php _e('Live', 'woowa'); ?></option>
                    <option value="inreview" <?php if($product_wreview == 'inreview'){echo 'selected';}?> ><?php _e('In Review', 'woowa'); ?></option>
                </select>
            </td>
        </tr>
        
    </table>
    <?php
}

function register_meta_boxes_for_woowa_products_auth_url() {
    add_meta_box( 'woowa_products_auth_url_meta_box',
        __('Callback URL'),
        'display_woowa_products_auth_url_meta_box',
        'product', 'normal', 'high'
    );
}

add_action( 'admin_init', 'register_meta_boxes_for_woowa_products_auth_url' );

function add_woowa_products_auth_url_fields( $woowa_products_id, $woowa_products ) {
    // Checks save status
    $is_autosave = wp_is_post_autosave( $woowa_products_id );
    $is_revision = wp_is_post_revision( $woowa_products_id );
    $is_valid_nonce = ( isset( $_POST[ 'woowa_products_auth_url_meta_box' ] ) && wp_verify_nonce( $_POST[ 'woowa_products_auth_url_meta_box' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }
    // Check post type
    if ( $woowa_products->post_type == 'product' ) {
        // Store data in post meta table if present in post data
        if ( isset( $_POST['woowa_products_auth_url'] ) && $_POST['woowa_products_auth_url'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_auth_url', sanitize_text_field($_POST['woowa_products_auth_url']) );
        }
    if ( isset( $_POST['woowa_product_wreview'] ) && $_POST['woowa_product_wreview'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_wreview', sanitize_text_field($_POST['woowa_product_wreview']) );
        }
    if ( isset( $_POST['woowa_products_center_url'] ) && $_POST['woowa_products_center_url'] != '' ) {
            update_post_meta( $woowa_products_id, 'woowa_product_center_url', sanitize_text_field($_POST['woowa_products_center_url']) );
        }
    }
}

add_action( 'save_post', 'add_woowa_products_auth_url_fields', 10, 2 );

/**
 * Add custom cart item data
 */
function woowa_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
     if( isset( $_POST['site_id'] ) ) {
        $cart_item_data['site_id'] = sanitize_text_field( $_POST['site_id'] );
     }
     if( isset( $_POST['user_id'] ) ) {
        $cart_item_data['user_id'] = sanitize_text_field( $_POST['user_id'] );
     }
     if( isset( $_POST['access_token'] ) ) {
        $cart_item_data['access_token'] = sanitize_text_field( $_POST['access_token'] );
     }
     if( isset( $_POST['final_url'] ) ) {
        $cart_item_data['final_url'] = sanitize_text_field( $_POST['final_url'] );
     }
     return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'woowa_add_cart_item_data', 10, 3 );

/**
 * Display custom item data in the cart
 */
function woowa_get_item_data( $item_data, $cart_item_data ) {
 if( isset( $cart_item_data['site_id'] ) ) {
     $item_data[] = array(
     'key' => __( 'site_id', 'woowa' ),
     'value' => wc_clean( $cart_item_data['site_id'] )
     );
 }
 if( isset( $cart_item_data['user_id'] ) ) {
     $item_data[] = array(
     'key' => __( 'user_id', 'woowa' ),
     'value' => wc_clean( $cart_item_data['user_id'] )
     );
 }
 return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'woowa_get_item_data', 10, 2 );

/**
 * Add custom meta to order
 */
function woowa_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
     if( isset( $values['site_id'] ) ) {
         $item->add_meta_data(
         __( 'site_id', 'woowa' ),
         $values['site_id'],
         true
         );
     }
     if( isset( $values['user_id'] ) ) {
         $item->add_meta_data(
         __( 'user_id', 'woowa' ),
         $values['user_id'],
         true
         );
     }
     if( isset( $values['access_token'] ) ) {
         $item->add_meta_data(
         __( 'access_token', 'woowa' ),
         $values['access_token'],
         true
         );
     }
     if( isset( $values['final_url'] ) ) {
         $item->add_meta_data(
         __( 'final_url', 'woowa' ),
         $values['final_url'],
         true
         );
     }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'woowa_checkout_create_order_line_item', 10, 4 );

/**
 * Check if a *valid* completed order already exists for this
 * product + site + customer combination.
 *
 * Returns the order-ID if a *still-active* order-item is found,
 * otherwise returns false (so the user must pay again).
 */
function woowa_check_if_order_exists( $product_id, $r_site_id, $r_user_id, $order_status = [ 'wc-completed' ] ) {
	global $wpdb;
	// 1. Get all completed orders that match  site_id  first (fast SQL filter)
	$results = $wpdb->get_col("
		SELECT order_items.order_id
		FROM {$wpdb->prefix}woocommerce_order_items         AS order_items
		LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_meta
		         ON order_items.order_item_id = order_meta.order_item_id
		LEFT JOIN {$wpdb->posts} AS posts
		         ON order_items.order_id = posts.ID
		WHERE posts.post_type   = 'shop_order'
		  AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' )
		  AND order_items.order_item_type = 'line_item'
		  AND order_meta.meta_key   = 'site_id'
		  AND order_meta.meta_value = %s
	", $r_site_id );
	/* -----------------------------------------------------------------
	 * 2. Loop through each candidate order-item and verify:
	 *    • same product
	 *    • same Weebly user_id
	 *    • recurring item still active  (not expired / not revoked)
	 * ----------------------------------------------------------------*/
	foreach ( $results as $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			continue;
		}

		foreach ( $order->get_items() as $item_id => $item ) {

			$site_id = wc_get_order_item_meta( $item_id, 'site_id', true );
			$user_id = wc_get_order_item_meta( $item_id, 'user_id', true );
			$pr_id   = wc_get_order_item_meta( $item_id, '_product_id', true );

			if ( (int) $pr_id   !== (int) $product_id ||
			     (string) $site_id !== (string) $r_site_id ||
			     (string) $user_id !== (string) $r_user_id ) {
				continue; // not the same app / customer
			}

			/* ---- Is this product flagged as recurring? ---- */
			$is_recurring = get_post_meta( $product_id, '_wpwa_is_recurring', true );

			if ( 'yes' === $is_recurring ) {

				$expiry  = (int) wc_get_order_item_meta( $item_id, '_wpwa_expiry', true );
				$revoked = wc_get_order_item_meta( $item_id, '_wpwa_token_revoked', true );

				// Already expired or manually revoked → treat as *no* valid order
				if ( $revoked === 'yes' || ( $expiry && time() > $expiry ) ) {
					continue;
				}
			}

			/* —— If we reach here, we found a VALID existing order —— */
			return $order_id;
		}
	}

	return false;
}

// Output a custom editable field in backend edit order pages under general section
add_action( 'woocommerce_admin_order_data_after_order_details', 'wpwa_editable_order_custom_field', 12, 1 );
function wpwa_editable_order_custom_field( $order ){
    // Loop through order items
    foreach( $order->get_items() as $item_id => $item ){
        if( $item->get_meta('site_id') ){
            $item_value_site_id = $item->get_meta('site_id');  
        }
        if( $item->get_meta('user_id') ){
            $item_value_user_id = $item->get_meta('user_id');
        }
        
        echo '<input type="hidden" name="item_id_ref" value="' . $item_id . '">';
    }

    // Get meta data (not item meta data)
    $updated_value_site_id = $order->get_meta('site_id');
    $updated_value_user_id = $order->get_meta('user_id');

    // Replace "custom meta" value by the meta data if it exist
    $value_site_id = $updated_value_site_id ? $updated_value_site_id : ( isset($item_value_site_id) ? $item_value_site_id : '');
    $value_user_id = $updated_value_user_id ? $updated_value_user_id : ( isset($item_value_user_id) ? $item_value_user_id : '');

    // Display the custom editable field
    woocommerce_wp_text_input( array(
        'id'            => 'site_id',
        'label'         => __("Site ID:", "wpwa"),
        'value'         => $value_site_id,
        'wrapper_class' => 'form-field-wide',
    ) );
    
    woocommerce_wp_text_input( array(
        'id'            => 'user_id',
        'label'         => __("User ID:", "wpwa"),
        'value'         => $value_user_id,
        'wrapper_class' => 'form-field-wide',
    ) );
}

// Save the custom editable field value as order meta data and update order item //meta data
add_action( 'woocommerce_process_shop_order_meta', 'wpwa_save_order_custom_field_meta_data', 12, 2 );
function wpwa_save_order_custom_field_meta_data( $post_id, $post ){
    if( isset( $_POST[ 'site_id' ] ) ){
        update_post_meta( $post_id, 'site_id', sanitize_text_field( $_POST[ 'site_id' ] ) );
        if( isset( $_POST[ 'item_id_ref' ] ) ){
            wc_update_order_item_meta( $_POST[ 'item_id_ref' ], 'site_id', $_POST[ 'site_id' ] );
        }
    }
    
    if( isset( $_POST[ 'user_id' ] ) ){
        update_post_meta( $post_id, 'user_id', sanitize_text_field( $_POST[ 'user_id' ] ) );
        if( isset( $_POST[ 'item_id_ref' ] ) ){
            wc_update_order_item_meta( $_POST[ 'item_id_ref' ], 'user_id', $_POST[ 'user_id' ] );
        }
    }
}

add_action( 'woocommerce_payment_complete_order_status', 'woowa_wc_auto_complete_paid_order', 10, 3 );
function woowa_wc_auto_complete_paid_order( $status, $order_id, $order ) {
    return 'completed';
}

function woowa_custom_woocommerce_purchase_button($button, $product) {
    // Define your category slug
    $target_category = 'weebly-apps'; 
    // Check if the product belongs to the target category
    if (has_term($target_category, 'product_cat', $product->get_id())) {
        // Get the custom meta field value
        $purchase_link = get_post_meta($product->get_id(), 'woowa_product_center_url', true);
        if (!empty($purchase_link)) {
            // Generate a custom button instead of Add to Cart
            return '<p style="clear:both;"><a href="' . esc_url($purchase_link) . '" class="button weebly-purchase-btn" target="_blank">Purchase From Weebly App Center</a></p><br><br>';
        }
    }

    return $button;
}
add_filter('woocommerce_loop_add_to_cart_link', 'woowa_custom_woocommerce_purchase_button', 10, 2);

// Modify the single product page Add to Cart button as well
function woowa_custom_woocommerce_single_product_button() {
    global $product;
    $target_category = 'weebly-apps'; 

    if (has_term($target_category, 'product_cat', $product->get_id())) {
        $purchase_link = get_post_meta($product->get_id(), 'woowa_product_center_url', true);

        if (!empty($purchase_link)) {
            echo '<p style="clear:both;"><a href="' . esc_url($purchase_link) . '" class="button weebly-purchase-btn" target="_blank">Purchase From Weebly App Center</a></p><br><br>';
            return;
        }
    }

    woocommerce_template_single_add_to_cart();
}
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
add_action('woocommerce_single_product_summary', 'woowa_custom_woocommerce_single_product_button', 30);

?>