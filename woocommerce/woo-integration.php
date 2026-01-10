<?php
/**
 * Render a minimalist checkout card for the Weebly flow.
 *
 * @param array  $params       Query params from phase-2
 * @param int    $pr_id        WooCommerce product ID
 * @param string $final_url    URL Weebly will return to after token exchange
 * @param string $access_token OAuth token to save post-purchase
 */
function woowa_paymentProcessForm( $params, $pr_id, $final_url, $access_token ) {

	$product = wc_get_product( $pr_id );
	if ( ! $product || ! $product->is_purchasable() ) {
		wp_die( __( 'Product not available.', 'wpwa' ) );
	}

	/* ── Recurring-vs-one-time details ───────────────────────── */
	$is_recurring = ( 'yes' === $product->get_meta( WPWA_Recurring::META_KEY_FLAG ) );

	$cycle_length = absint( $product->get_meta( WPWA_Recurring::META_CYCLE_LENGTH ) );
	$cycle_unit   = esc_html( $product->get_meta( WPWA_Recurring::META_CYCLE_UNIT ) );
	$cycle_price  = $product->get_meta( WPWA_Recurring::META_CYCLE_PRICE );

	$display_price = ( $is_recurring && $cycle_price !== '' )
		? wc_price( $cycle_price )
		: wc_price( $product->get_price() );

	$billing_note  = $is_recurring
		? sprintf( __( 'Recurring every %d %s(s)', 'wpwa' ), $cycle_length, $cycle_unit )
		: __( 'One-time purchase', 'wpwa' );

	/* ── Params from Weebly callback ─────────────────────────── */
	$site_id = sanitize_text_field( $params['site_id'] ?? '' );
	$user_id = sanitize_text_field( $params['user_id'] ?? '' );

	/* ── Output ─────────────────────────────────────────────── */
	get_header(); ?>

	<style>
		/* keep CSS minimal; move to file in production */
		.wpwa-product-wrap{max-width:800px;margin:30px auto;padding:20px}
		.wpwa-card{box-shadow:0 2px 8px rgba(0,0,0,.1);border-radius:12px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,Cantarell,"Open Sans","Helvetica Neue",sans-serif;background:#fff;text-align:center}
		.wpwa-card__img{width:100%;max-height:280px;object-fit:cover;display:none}
		.wpwa-card h2{font-size:24px;margin:20px 0 10px;padding:0 20px}
		.wpwa-card p{padding:0 20px;font-size:16px;color:#666}
		.wpwa-card p.price{font-size:20px;color:#444;margin:0 0 15px}
		.wpwa-billing-note{font-size:14px;color:#777;margin-bottom:15px}
		.wpwa-card form.cart{margin:20px 0}
		.wpwa-card form.cart .button{background:#0073aa;color:#fff;padding:12px 30px;font-size:16px;border:none;border-radius:6px;cursor:pointer}
		.wpwa-card form.cart .button:hover{background:#005f8d}
		.wpwa-card__notice{background:#f8f9fa;border-top:1px solid #eee;margin-top:30px;padding:15px 20px;font-size:14px;color:#555}
	</style>

	<div class="wpwa-product-wrap">
		<div class="wpwa-card">

			<img src="<?php echo esc_url( get_the_post_thumbnail_url( $pr_id ) ); ?>"
			     alt="<?php echo esc_attr( $product->get_name() ); ?>"
			     class="wpwa-card__img" />

			<h2><?php echo esc_html( $product->get_name() ); ?></h2>

			<p class="price"><?php echo wp_kses_post( $display_price ); ?></p>

			<p class="wpwa-billing-note"><?php echo esc_html( $billing_note ); ?></p>

			<p><?php echo wp_kses_post( $product->get_short_description() ); ?></p>

			<form class="cart"
			      action="<?php echo esc_url( get_permalink( wc_get_page_id( 'checkout' ) ) ); ?>"
			      method="post">
				<?php wp_nonce_field( 'wpwa_checkout', 'wpwa_nonce' ); ?>
				<input type="hidden" name="add-to-cart"  value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="site_id"      value="<?php echo esc_attr( $site_id ); ?>">
				<input type="hidden" name="user_id"      value="<?php echo esc_attr( $user_id ); ?>">
				<input type="hidden" name="access_token" value="<?php echo esc_attr( $access_token ); ?>">
				<input type="hidden" name="final_url"    value="<?php echo esc_url( $final_url ); ?>">
				<button type="submit" class="button alt">
					<?php esc_html_e( 'Place Order', 'wpwa' ); ?>
				</button>
			</form>

			<div class="wpwa-card__notice">
				<p><?php esc_html_e( 'After completing the payment you will receive an e-mail containing the final URL. Open that URL to finish installation.', 'wpwa' ); ?></p>
				<p>
					<?php esc_html_e( 'Questions?', 'wpwa' ); ?>
					<a href="https://codoplex.com/contact/" target="_blank" rel="noopener">
						https://codoplex.com/contact/
					</a>
				</p>
			</div>

		</div><!-- .wpwa-card -->
	</div><!-- .wpwa-product-wrap -->

	<?php get_footer();
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
         $item->add_meta_data( 'site_id', $values['site_id'], true );
     }
     if( isset( $values['user_id'] ) ) {
         $item->add_meta_data( 'user_id', $values['user_id'], true );
     }
     if( isset( $values['access_token'] ) ) {
         $item->add_meta_data( 'access_token', $values['access_token'], true );
     }
     if( isset( $values['final_url'] ) ) {
         $item->add_meta_data( 'final_url', $values['final_url'], true );
     }
}

add_action( 'woocommerce_checkout_create_order_line_item', 'woowa_checkout_create_order_line_item', 10, 4 );

function woowa_check_if_order_exists( $product_id, $r_site_id, $r_user_id, $order_status = [ 'wc-completed', 'wc-processing' ] ) {
    global $wpdb;

    // 1. Sanitize Status
    $status_placeholders = implode( "','", array_map( 'esc_sql', $order_status ) );

    // 2. Prepare SQL
    // OPTIMIZATION: We removed the check for 'meta_key = site_id'. 
    // Why? Because if the key was saved wrongly as "Site ID", we still want to find this value.
    // We strictly match the meta_value (the ID itself) which is unique enough.
    $sql = $wpdb->prepare( "
        SELECT DISTINCT order_items.order_id
        FROM {$wpdb->prefix}woocommerce_order_items AS order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_meta
            ON order_items.order_item_id = order_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts
            ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
          AND posts.post_status IN ( '$status_placeholders' )
          AND order_items.order_item_type = 'line_item'
          AND order_meta.meta_value = %s 
    ", $r_site_id );

    $results = $wpdb->get_col( $sql );

    if ( empty( $results ) ) {
        error_log("🐞 No orders found via SQL for site_id: " . $r_site_id);
        return false;
    }

    // 3. Loop and Validate
    foreach ( $results as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) continue;

        foreach ( $order->get_items() as $item ) {
            // Retrieve meta safely. 
            // Note: If you saved it as 'Site ID' previously, $item->get_meta('site_id') might fail.
            // We iterate manually if get_meta fails, or rely on the SQL match we just found.
            
            $site_id = $item->get_meta( 'site_id' ); 
            
            // FALLBACK: If standard get_meta fails (due to translation bug), try getting all meta
            if ( empty( $site_id ) ) {
                $all_meta = $item->get_meta_data();
                foreach($all_meta as $meta) {
                    if ( $meta->value == $r_site_id ) {
                        $site_id = $meta->value; // Found it under a different key name
                        break;
                    }
                }
            }

            $user_id = $item->get_meta( 'user_id' );
            $pr_id   = $item->get_product_id();

            // Compare Product ID and Site ID
            if ( (int) $pr_id !== (int) $product_id ) continue;
            if ( (string) $site_id !== (string) $r_site_id ) continue;
            
            // Optional: User ID check (Only if User ID is strictly required to match)
            if ( !empty($r_user_id) && !empty($user_id) && (string)$user_id !== (string)$r_user_id ) {
                 continue;
            }

            // Recurring Logic
            $is_recurring = get_post_meta( $product_id, '_wpwa_is_recurring', true );
            if ( 'yes' === $is_recurring ) {
                $expiry  = (int) $item->get_meta( '_wpwa_expiry' );
                $revoked = $item->get_meta( '_wpwa_token_revoked' );
                if ( 'yes' === $revoked || ( $expiry > 0 && time() > $expiry ) ) {
                    continue; 
                }
            }

            return $order_id; // Valid order found
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

//custom thank you page
add_action( 'woocommerce_before_thankyou', 'wpwa_show_final_step_notice_multiple_with_names', 20 );
function wpwa_show_final_step_notice_multiple_with_names( $order ) {
	if ( ! $order || ! is_a( $order, 'WC_Order' ) ) return;

	$shown_client_ids = [];
	$weebly_links     = [];

	foreach ( $order->get_items() as $item ) {
		$product_id = $item->get_product_id();
		if ( ! $product_id ) continue;

		$product    = wc_get_product( $product_id );
		$client_id  = get_post_meta( $product_id, 'woowa_product_client_id', true );
		$product_name = $product ? $product->get_name() : 'Weebly App Product';

		if ( empty( $client_id ) || in_array( $client_id, $shown_client_ids, true ) ) continue;

		$final_url = esc_url( "https://www.weebly.com/app-center/oauth/finish?client_id={$client_id}" );
		$weebly_links[] = [
			'name' => $product_name,
			'url'  => $final_url,
		];
		$shown_client_ids[] = $client_id;
	}

	if ( ! empty( $weebly_links ) ) {
		echo '<div class="woocommerce-message" style="border-left: 5px solid #cc0000; padding: 15px; margin-top: 20px;">';
		echo '<strong>⚠️ Final Step Required:</strong><br>';
		echo 'To complete your app connection, please click the link(s) below for each product:<br><br>';
		foreach ( $weebly_links as $link ) {
			echo '<div style="margin-bottom: 10px;">';
			printf(
				'<strong>%s:</strong> <a href="%s" target="_blank" style="font-size: 16px; color: #0073aa;">✅ Finish Connecting</a>',
				esc_html( $link['name'] ),
				$link['url']
			);
			echo '</div>';
		}
		echo '</div>';
	}
}

?>