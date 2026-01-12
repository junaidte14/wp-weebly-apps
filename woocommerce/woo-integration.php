<?php
/**
 * Enhanced Payment Process Form with Whitelist Subscription Option
 * File: woocommerce/woo-integration.php (UPDATED)
 * 
 * Displays a modern dual-option payment interface:
 * 1. Current app purchase (primary)
 * 2. Whitelist subscription (secondary, highlighted)
 */

function woowa_paymentProcessForm( $params, $pr_id, $final_url, $access_token ) {

	$product = wc_get_product( $pr_id );
	if ( ! $product || ! $product->is_purchasable() ) {
		wp_die( __( 'Product not available.', 'wpwa' ) );
	}

	/* ── Get whitelist product ───────────────────────── */
	$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
	$whitelist_product = $whitelist_product_id ? wc_get_product( $whitelist_product_id ) : null;

	/* ── Current product details ───────────────────────── */
	$is_recurring = ( 'yes' === $product->get_meta( WPWA_Recurring::META_KEY_FLAG ) );
	$cycle_length = absint( $product->get_meta( WPWA_Recurring::META_CYCLE_LENGTH ) );
	$cycle_unit   = esc_html( $product->get_meta( WPWA_Recurring::META_CYCLE_UNIT ) );
	$cycle_price  = $product->get_meta( WPWA_Recurring::META_CYCLE_PRICE );

	$display_price = ( $is_recurring && $cycle_price !== '' )
		? wc_price( $cycle_price )
		: wc_price( $product->get_price() );

	$billing_note = $is_recurring
		? sprintf( __( 'Recurring every %d %s(s)', 'wpwa' ), $cycle_length, $cycle_unit )
		: __( 'One-time purchase', 'wpwa' );

	/* ── Whitelist product details ───────────────────────── */
	$whitelist_price = '';
	$whitelist_billing = '';
	$whitelist_savings = '';
	
	if ( $whitelist_product ) {
		$wl_cycle_length = absint( $whitelist_product->get_meta( WPWA_Recurring::META_CYCLE_LENGTH ) );
		$wl_cycle_unit   = esc_html( $whitelist_product->get_meta( WPWA_Recurring::META_CYCLE_UNIT ) );
		$wl_cycle_price  = $whitelist_product->get_meta( WPWA_Recurring::META_CYCLE_PRICE );
		
		$whitelist_price = $wl_cycle_price !== '' 
			? wc_price( $wl_cycle_price ) 
			: wc_price( $whitelist_product->get_price() );
			
		$whitelist_billing = sprintf( 
			__( 'Every %d %s(s)', 'wpwa' ), 
			$wl_cycle_length, 
			$wl_cycle_unit 
		);
		
		// Calculate potential savings
		$current_price_float = $is_recurring && $cycle_price !== '' ? floatval( $cycle_price ) : floatval( $product->get_price() );
		$whitelist_price_float = $wl_cycle_price !== '' ? floatval( $wl_cycle_price ) : floatval( $whitelist_product->get_price() );
		
		if ( $whitelist_price_float > 0 && $current_price_float > 0 ) {
			$apps_covered = ceil( $whitelist_price_float / $current_price_float );
			if ( $apps_covered > 1 ) {
				$whitelist_savings = sprintf( 
					__( 'Access to ALL our apps (equivalent to %d+ apps!)', 'wpwa' ), 
					$apps_covered 
				);
			}
		}
	}

	/* ── Params from Weebly callback ───────────────────────── */
	$site_id = sanitize_text_field( $params['site_id'] ?? '' );
	$user_id = sanitize_text_field( $params['user_id'] ?? '' );

	/* ── Output ───────────────────────────────────────────── */
	get_header(); ?>

	<style>
		* { box-sizing: border-box; }
		body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif; background: #f5f7fa; }
		
		.wpwa-checkout-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
		
		.wpwa-checkout-header { text-align: center; margin-bottom: 30px; }
		.wpwa-checkout-header h1 { font-size: 28px; margin: 0 0 10px; color: #1a202c; }
		.wpwa-checkout-header p { color: #718096; margin: 0; }
		
		.wpwa-options-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
		
		@media (min-width: 768px) {
			.wpwa-options-grid { grid-template-columns: 1.2fr 1fr; }
		}
		
		/* Primary Product Card */
		.wpwa-product-card {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 2px 12px rgba(0,0,0,0.08);
			overflow: hidden;
			transition: transform 0.2s, box-shadow 0.2s;
			border: 2px solid transparent;
		}
		
		.wpwa-product-card.primary {
			border-color: #3182ce;
		}
		
		.wpwa-product-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 20px rgba(0,0,0,0.12);
		}
		
		.wpwa-card-badge {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			padding: 8px 16px;
			font-size: 12px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			text-align: center;
		}
		
		.wpwa-card-badge.whitelist {
			background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
		}
		
		.wpwa-card-content {
			padding: 30px;
		}
		
		.wpwa-product-image {
			width: 100%;
			height: 200px;
			object-fit: cover;
			background: #f7fafc;
		}
		
		.wpwa-product-title {
			font-size: 24px;
			font-weight: 700;
			color: #1a202c;
			margin: 0 0 10px;
		}
		
		.wpwa-product-desc {
			color: #4a5568;
			line-height: 1.6;
			margin-bottom: 20px;
		}
		
		.wpwa-price-section {
			background: #f7fafc;
			padding: 20px;
			border-radius: 8px;
			margin-bottom: 20px;
		}
		
		.wpwa-price {
			font-size: 36px;
			font-weight: 700;
			color: #2d3748;
			margin: 0 0 5px;
		}
		
		.wpwa-billing-cycle {
			color: #718096;
			font-size: 14px;
		}
		
		.wpwa-features {
			list-style: none;
			padding: 0;
			margin: 20px 0;
		}
		
		.wpwa-features li {
			padding: 8px 0;
			color: #4a5568;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		
		.wpwa-features li:before {
			content: "✓";
			display: inline-block;
			width: 20px;
			height: 20px;
			background: #48bb78;
			color: #fff;
			border-radius: 50%;
			text-align: center;
			line-height: 20px;
			font-weight: 700;
			flex-shrink: 0;
		}
		
		.wpwa-savings-badge {
			background: linear-gradient(135deg, #ffd89b 0%, #ff6b6b 100%);
			color: #fff;
			padding: 12px 16px;
			border-radius: 8px;
			margin-bottom: 20px;
			font-weight: 600;
			text-align: center;
			font-size: 14px;
		}
		
		.wpwa-btn {
			display: block;
			width: 100%;
			padding: 16px 24px;
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s;
			text-align: center;
			text-decoration: none;
		}
		
		.wpwa-btn-primary {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
		}
		
		.wpwa-btn-primary:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
		}
		
		.wpwa-btn-secondary {
			background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
			color: #fff;
			box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
		}
		
		.wpwa-btn-secondary:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 16px rgba(245, 87, 108, 0.5);
		}
		
		.wpwa-notice {
			background: #ebf8ff;
			border-left: 4px solid #3182ce;
			padding: 16px;
			border-radius: 4px;
			margin-top: 30px;
			color: #2c5282;
		}
		
		.wpwa-notice strong {
			display: block;
			margin-bottom: 8px;
		}
		
		.wpwa-notice a {
			color: #3182ce;
			text-decoration: underline;
		}
		
		/* Duration Selector */
		.wpwa-duration-selector {
			margin: 20px 0;
		}
		
		.wpwa-duration-selector label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #2d3748;
		}
		
		.wpwa-duration-options {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
			gap: 10px;
		}
		
		.wpwa-duration-option {
			position: relative;
		}
		
		.wpwa-duration-option input[type="radio"] {
			position: absolute;
			opacity: 0;
		}
		
		.wpwa-duration-option label {
			display: block;
			padding: 12px;
			border: 2px solid #e2e8f0;
			border-radius: 8px;
			text-align: center;
			cursor: pointer;
			transition: all 0.2s;
			background: #fff;
		}
		
		.wpwa-duration-option input[type="radio"]:checked + label {
			border-color: #667eea;
			background: #ebf4ff;
			color: #667eea;
			font-weight: 600;
		}
		
		.wpwa-duration-option label:hover {
			border-color: #cbd5e0;
		}
		
		.wpwa-price-update {
			margin-top: 10px;
			font-size: 14px;
			color: #718096;
		}
		
		/* Mobile Responsive */
		@media (max-width: 767px) {
			.wpwa-checkout-container { padding: 15px; }
			.wpwa-checkout-header h1 { font-size: 22px; }
			.wpwa-product-title { font-size: 20px; }
			.wpwa-price { font-size: 28px; }
			.wpwa-card-content { padding: 20px; }
		}
	</style>

	<div class="wpwa-checkout-container">
		<div class="wpwa-checkout-header">
			<h1><?php esc_html_e( 'Choose Your Access Plan', 'wpwa' ); ?></h1>
			<p><?php esc_html_e( 'Select the option that works best for you', 'wpwa' ); ?></p>
		</div>

		<div class="wpwa-options-grid">
			<!-- PRIMARY: Current App Purchase -->
			<div class="wpwa-product-card primary">
				<div class="wpwa-card-badge"><?php esc_html_e( 'This App Only', 'wpwa' ); ?></div>
				
				<?php if ( has_post_thumbnail( $pr_id ) ) : ?>
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $pr_id, 'large' ) ); ?>" 
					     alt="<?php echo esc_attr( $product->get_name() ); ?>" 
					     class="wpwa-product-image" />
				<?php endif; ?>

				<div class="wpwa-card-content">
					<h2 class="wpwa-product-title"><?php echo esc_html( $product->get_name() ); ?></h2>
					
					<div class="wpwa-price-section">
						<div class="wpwa-price"><?php echo wp_kses_post( $display_price ); ?></div>
						<div class="wpwa-billing-cycle"><?php echo esc_html( $billing_note ); ?></div>
					</div>

					<?php if ( $product->get_short_description() ) : ?>
						<div class="wpwa-product-desc">
							<?php echo wp_kses_post( $product->get_short_description() ); ?>
						</div>
					<?php endif; ?>

					<ul class="wpwa-features">
						<li><?php esc_html_e( 'Full access to this app', 'wpwa' ); ?></li>
						<li><?php esc_html_e( 'Regular updates & support', 'wpwa' ); ?></li>
						<li><?php esc_html_e( 'Cancel anytime', 'wpwa' ); ?></li>
					</ul>

					<form method="post" action="<?php echo esc_url( wc_get_checkout_url() ); ?>">
						<?php wp_nonce_field( 'wpwa_checkout', 'wpwa_nonce' ); ?>
						<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>">
						<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>">
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
						<input type="hidden" name="access_token" value="<?php echo esc_attr( $access_token ); ?>">
						<input type="hidden" name="final_url" value="<?php echo esc_url( $final_url ); ?>">
						<button type="submit" class="wpwa-btn wpwa-btn-primary">
							<?php esc_html_e( 'Purchase This App', 'wpwa' ); ?>
						</button>
					</form>
				</div>
			</div>

			<!-- SECONDARY: Whitelist Subscription (if available) -->
			<?php if ( $whitelist_product ) : ?>
				<div class="wpwa-product-card">
					<div class="wpwa-card-badge whitelist">
						<?php esc_html_e( '✨ Best Value - All Apps', 'wpwa' ); ?>
					</div>

					<div class="wpwa-card-content">
						<h2 class="wpwa-product-title">
							<?php echo esc_html( $whitelist_product->get_name() ); ?>
						</h2>

						<?php if ( $whitelist_savings ) : ?>
							<div class="wpwa-savings-badge">
								💎 <?php echo esc_html( $whitelist_savings ); ?>
							</div>
						<?php endif; ?>

						<div class="wpwa-price-section">
							<div class="wpwa-price" id="whitelist-price-display">
								<?php echo wp_kses_post( $whitelist_price ); ?>
							</div>
							<div class="wpwa-billing-cycle">
								<?php echo esc_html( $whitelist_billing ); ?>
							</div>
						</div>

						<?php if ( $whitelist_product ) : 
                            // Get configured duration options
                            $enable_duration = get_post_meta( $whitelist_product_id, '_wpwa_enable_duration_selector', true );
                            $available_durations = get_post_meta( $whitelist_product_id, '_wpwa_available_durations', true );
                            $default_duration = absint( get_post_meta( $whitelist_product_id, '_wpwa_default_duration', true ) ) ?: 1;
                            $duration_discount = absint( get_post_meta( $whitelist_product_id, '_wpwa_duration_discount', true ) );
                            
                            // Parse available durations
                            $durations = array_filter( array_map( 'absint', explode( ',', $available_durations ?: '1,3,6,12' ) ) );
                            sort( $durations );
                        ?>

                        <!-- Duration Selector (only if enabled) -->
                        <?php if ( 'yes' === $enable_duration && count( $durations ) > 1 ) : ?>
                            <div class="wpwa-duration-selector">
                                <label><?php esc_html_e( 'Select Duration:', 'wpwa' ); ?></label>
                                <div class="wpwa-duration-options">
                                    <?php foreach ( $durations as $duration ) : ?>
                                        <div class="wpwa-duration-option">
                                            <input type="radio" 
                                                name="whitelist_duration" 
                                                id="duration_<?php echo $duration; ?>" 
                                                value="<?php echo $duration; ?>"
                                                <?php checked( $duration, $default_duration ); ?>>
                                            <label for="duration_<?php echo $duration; ?>">
                                                <?php 
                                                echo esc_html( $duration . ' ' );
                                                echo $duration === 1 
                                                    ? esc_html( $wl_cycle_unit ) 
                                                    : esc_html( $wl_cycle_unit . 's' );
                                                
                                                // Show discount badge for 6+ cycles
                                                if ( $duration >= 6 && $duration_discount > 0 ) {
                                                    echo '<span class="wpwa-discount-badge">-' . $duration_discount . '%</span>';
                                                }
                                                ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="wpwa-price-update" id="duration-notice">
                                    <?php esc_html_e( 'Price shown is per billing cycle', 'wpwa' ); ?>
                                </div>
                            </div>
                            
                            <style>
                            .wpwa-discount-badge {
                                display: block;
                                font-size: 10px;
                                color: #48bb78;
                                font-weight: 700;
                                margin-top: 4px;
                            }
                            </style>
                        <?php endif; ?>
                        <?php endif; // if whitelist_product ?>

						<ul class="wpwa-features">
							<li><?php esc_html_e( 'Access to ALL our Weebly apps', 'wpwa' ); ?></li>
							<li><?php esc_html_e( 'Free installation for any app', 'wpwa' ); ?></li>
							<li><?php esc_html_e( 'Priority support', 'wpwa' ); ?></li>
							<li><?php esc_html_e( 'Early access to new apps', 'wpwa' ); ?></li>
						</ul>

						<a href="<?php echo esc_url( add_query_arg( [
							'add-to-cart' => $whitelist_product_id,
							'weebly_user_id' => $user_id,
							'duration' => '1'
						], wc_get_checkout_url() ) ); ?>" 
						   class="wpwa-btn wpwa-btn-secondary" 
						   id="whitelist-purchase-btn">
							<?php esc_html_e( 'Get Unlimited Access', 'wpwa' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="wpwa-notice">
			<strong><?php esc_html_e( 'Need Help Deciding?', 'wpwa' ); ?></strong>
			<?php esc_html_e( 'After completing payment, you\'ll receive an email with installation instructions. For questions, contact us at', 'wpwa' ); ?>
			<a href="https://codoplex.com/contact/" target="_blank" rel="noopener">
				https://codoplex.com/contact/
			</a>
		</div>
	</div>

	<script>
	(function() {
        'use strict';
        
        // Configuration from PHP
        const basePrice = parseFloat('<?php echo $whitelist_price_float ?? 0; ?>');
        const whitelistProductId = parseInt('<?php echo $whitelist_product_id; ?>');
        const userId = '<?php echo esc_js( $user_id ); ?>';
        const cycleUnit = '<?php echo esc_js( $wl_cycle_unit ); ?>';
        const discountPercent = parseInt('<?php echo $duration_discount ?? 0; ?>');
        const checkoutUrl = '<?php echo esc_js( wc_get_checkout_url() ); ?>';
        
        // DOM elements
        const durationRadios = document.querySelectorAll('input[name="whitelist_duration"]');
        const priceDisplay = document.getElementById('whitelist-price-display');
        const purchaseBtn = document.getElementById('whitelist-purchase-btn');
        const durationNotice = document.getElementById('duration-notice');
        
        /**
        * Calculate discounted price
        */
        function calculatePrice(duration) {
            let totalPrice = basePrice * duration;
            
            // Apply discount for 6+ cycles
            if (duration >= 6 && discountPercent > 0) {
                const discount = (totalPrice * discountPercent) / 100;
                totalPrice -= discount;
            }
            
            return totalPrice;
        }
        
        /**
        * Format price for display
        */
        function formatPrice(price) {
            return '$' + price.toFixed(2);
        }
        
        /**
        * Update all dynamic elements based on selected duration
        */
        function updateWhitelistPrice() {
            const selectedDuration = document.querySelector('input[name="whitelist_duration"]:checked');
            if (!selectedDuration) return;
            
            const duration = parseInt(selectedDuration.value);
            const totalPrice = calculatePrice(duration);
            const originalPrice = basePrice * duration;
            const savings = originalPrice - totalPrice;
            
            // Update price display with animation
            if (priceDisplay) {
                priceDisplay.style.transform = 'scale(0.95)';
                priceDisplay.style.opacity = '0.7';
                
                setTimeout(() => {
                    priceDisplay.innerHTML = formatPrice(totalPrice);
                    priceDisplay.style.transform = 'scale(1)';
                    priceDisplay.style.opacity = '1';
                }, 150);
            }
            
            // Update purchase button URL
            if (purchaseBtn) {
                const newUrl = checkoutUrl + 
                    '?add-to-cart=' + whitelistProductId + 
                    '&weebly_user_id=' + encodeURIComponent(userId) + 
                    '&duration=' + duration;
                purchaseBtn.href = newUrl;
                
                // Update button text for emphasis
                if (duration > 1) {
                    purchaseBtn.innerHTML = `✨ Get ${duration} ${cycleUnit}s Access - ${formatPrice(totalPrice)}`;
                } else {
                    purchaseBtn.innerHTML = `Get Unlimited Access`;
                }
            }
            
            // Update notice with detailed breakdown
            if (durationNotice) {
                let noticeHTML = '';
                
                if (duration === 1) {
                    noticeHTML = `💳 ${formatPrice(basePrice)} billed ${cycleUnit}ly`;
                } else {
                    noticeHTML = `<strong>Total: ${formatPrice(totalPrice)}</strong> `;
                    noticeHTML += `(${formatPrice(basePrice)}/${cycleUnit} × ${duration} ${cycleUnit}s)`;
                    
                    if (savings > 0) {
                        noticeHTML += `<br><span style="color:#48bb78;font-weight:600;">🎉 You save ${formatPrice(savings)} (${discountPercent}% off)!</span>`;
                    }
                }
                
                durationNotice.innerHTML = noticeHTML;
                
                // Highlight notice for savings
                if (savings > 0) {
                    durationNotice.style.background = '#f0fff4';
                    durationNotice.style.border = '2px solid #48bb78';
                    durationNotice.style.padding = '12px';
                    durationNotice.style.borderRadius = '8px';
                    durationNotice.style.marginTop = '15px';
                } else {
                    durationNotice.style.background = 'transparent';
                    durationNotice.style.border = 'none';
                    durationNotice.style.padding = '0';
                }
            }
            
            // Add visual feedback to selected option
            document.querySelectorAll('.wpwa-duration-option').forEach(option => {
                option.style.transform = 'scale(1)';
            });
            
            const selectedOption = selectedDuration.closest('.wpwa-duration-option');
            if (selectedOption) {
                selectedOption.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    selectedOption.style.transform = 'scale(1)';
                }, 200);
            }
        }
        
        /**
        * Add smooth transitions
        */
        function initializeAnimations() {
            if (priceDisplay) {
                priceDisplay.style.transition = 'all 0.2s ease';
            }
            
            document.querySelectorAll('.wpwa-duration-option').forEach(option => {
                option.style.transition = 'transform 0.2s ease';
            });
            
            if (durationNotice) {
                durationNotice.style.transition = 'all 0.3s ease';
            }
        }
        
        /**
        * Event listeners
        */
        function bindEvents() {
            durationRadios.forEach(radio => {
                radio.addEventListener('change', updateWhitelistPrice);
            });
            
            // Optional: Add keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    const currentIndex = Array.from(durationRadios).findIndex(r => r.checked);
                    let newIndex;
                    
                    if (e.key === 'ArrowLeft') {
                        newIndex = Math.max(0, currentIndex - 1);
                    } else {
                        newIndex = Math.min(durationRadios.length - 1, currentIndex + 1);
                    }
                    
                    if (durationRadios[newIndex]) {
                        durationRadios[newIndex].checked = true;
                        updateWhitelistPrice();
                    }
                }
            });
        }
        
        /**
        * Initialize on load
        */
        function init() {
            if (durationRadios.length === 0) return;
            
            initializeAnimations();
            bindEvents();
            updateWhitelistPrice();
            
            // Show a subtle intro animation
            setTimeout(() => {
                const whitelistCard = document.querySelector('.wpwa-product-card:not(.primary)');
                if (whitelistCard) {
                    whitelistCard.style.animation = 'wpwa-fade-in-up 0.5s ease';
                }
            }, 300);
        }
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes wpwa-fade-in-up {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .wpwa-duration-option label {
                position: relative;
                overflow: hidden;
            }
            
            .wpwa-duration-option input[type="radio"]:checked + label::after {
                content: '✓';
                position: absolute;
                top: 4px;
                right: 4px;
                width: 20px;
                height: 20px;
                background: #48bb78;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
            }
            
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation: none !important;
                    transition: none !important;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        
        // Analytics tracking (optional)
        window.wpwaTrackDurationChange = function(duration) {
            // Add your analytics code here
            console.log('Duration selected:', duration);
        };
        
    })();
	</script>

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

/**
 * UPDATED: woowa_check_if_order_exists function
 * HPOS-compatible order search using WC_Order_Query instead of direct DB queries
 */
function woowa_check_if_order_exists($product_id, $r_site_id, $r_user_id, $order_status = ['wc-completed', 'wc-processing']) {   
    error_log("🔍 Checking for existing order: product_id={$product_id}, site_id={$r_site_id}, user_id={$r_user_id}");
    // Use WC_Order_Query for HPOS compatibility
    $args = [
        'limit'  => -1,
        'status' => $order_status,
        'type'   => 'shop_order',
        'return' => 'ids'
    ];
    $order_ids = wc_get_orders($args);
    if (empty($order_ids)) {
        error_log("🐞 No orders found with status: " . implode(', ', $order_status));
        return false;
    }
    // Loop through orders to find matching product + site_id + user_id
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;   
        foreach ($order->get_items() as $item_id => $item) {
            $item_product_id = $item->get_product_id();
            // Check product match
            if ((int) $item_product_id !== (int) $product_id) {
                continue;
            }
            // Get site_id and user_id from item meta
            $site_id = $item->get_meta('site_id');
            $user_id = $item->get_meta('user_id');
            // Fallback: Try all meta data if standard get_meta fails
            if (empty($site_id)) {
                $all_meta = $item->get_meta_data();
                foreach ($all_meta as $meta) {
                    // Check if this meta value matches our site_id
                    if ((string) $meta->value === (string) $r_site_id) {
                        $site_id = $meta->value;
                        break;
                    }
                }
            }
            // Compare site_id
            if ((string) $site_id !== (string) $r_site_id) {
                continue;
            }
            // Optional: User ID check
            if (!empty($r_user_id) && !empty($user_id) && (string) $user_id !== (string) $r_user_id) {
                continue;
            }
            // Check if this is a recurring product
            $is_recurring = get_post_meta($product_id, '_wpwa_is_recurring', true);
            if ('yes' === $is_recurring) {
                $expiry = (int) $item->get_meta('_wpwa_expiry');
                $revoked = $item->get_meta('_wpwa_token_revoked');   
                // Skip if revoked or expired
                if ('yes' === $revoked || ($expiry > 0 && time() > $expiry)) {
                    error_log("⚠️ Order {$order_id} found but licence expired/revoked");
                    continue;
                }
            }
            error_log("✅ Valid order found: {$order_id}");
            return $order_id;
        }
    }
    error_log("❌ No valid order found");
    return false;
}

// Output a custom editable field in backend edit order pages under general section
add_action( 'woocommerce_admin_order_data_after_order_details', 'wpwa_editable_order_custom_field', 12, 1 );
/**
 * Admin order field display
 */
function wpwa_editable_order_custom_field( $order ){
    // Get meta data (not item meta data)
    $order_site_id = $order->get_meta('site_id');
    $order_user_id = $order->get_meta('user_id');
    
    // If order meta doesn't exist, try to get from first item
    if ( empty($order_site_id) || empty($order_user_id) ) {
        foreach( $order->get_items() as $item_id => $item ){
            if ( empty($order_site_id) && $item->get_meta('site_id') ) {
                $order_site_id = $item->get_meta('site_id');
            }
            if ( empty($order_user_id) && $item->get_meta('user_id') ) {
                $order_user_id = $item->get_meta('user_id');
            }
            if ( $order_site_id && $order_user_id ) {
                break; // Found both, no need to continue
            }
        }
    }

    // Display the custom editable fields
    woocommerce_wp_text_input( array(
        'id'            => 'wpwa_site_id',
        'label'         => __("Site ID:", "wpwa"),
        'value'         => $order_site_id,
        'wrapper_class' => 'form-field-wide',
    ) );
    
    woocommerce_wp_text_input( array(
        'id'            => 'wpwa_user_id',
        'label'         => __("User ID:", "wpwa"),
        'value'         => $order_user_id,
        'wrapper_class' => 'form-field-wide',
    ) );
}

// Save the custom editable field value as order meta data and update order item //meta data
add_action( 'woocommerce_process_shop_order_meta', 'wpwa_save_order_custom_field_meta_data', 12, 2 );
/**
 * Order meta save handler
 */
function wpwa_save_order_custom_field_meta_data( $post_id, $post ){
    // Get the order object
    $order = wc_get_order( $post_id );
    if ( ! $order ) {
        return;
    }
    // Track if we made any changes
    $updated = false;
    // Save Site ID
    if( isset( $_POST['wpwa_site_id'] ) ){
        $new_site_id = sanitize_text_field( $_POST['wpwa_site_id'] );
        // Update order meta
        $order->update_meta_data( 'site_id', $new_site_id );
        // Update ALL item metas
        foreach( $order->get_items() as $item_id => $item ) {
            $item->update_meta_data( 'site_id', $new_site_id );
            $item->save();
        }   
        $updated = true;
    }
    // Save User ID
    if( isset( $_POST['wpwa_user_id'] ) ){
        $new_user_id = sanitize_text_field( $_POST['wpwa_user_id'] );
        // Update order meta
        $order->update_meta_data( 'user_id', $new_user_id );
        // Update ALL item metas
        foreach( $order->get_items() as $item_id => $item ) {
            $item->update_meta_data( 'user_id', $new_user_id );
            $item->save();
        }   
        $updated = true;
    }   
    // Save the order if we made changes
    if ( $updated ) {
        $order->save();
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

/**
 * 1. Frontend: Display Duration Selector on Product Page
 */
add_action( 'woocommerce_before_add_to_cart_button', 'wpwa_render_duration_field' );
function wpwa_render_duration_field() {
    global $product;

    // Only show if enabled in your Recurring Class settings
    if ( 'yes' !== $product->get_meta( '_wpwa_enable_duration_selector' ) ) {
        return;
    }

    $raw_durations = $product->get_meta( '_wpwa_available_durations' );
    $durations = array_map( 'absint', explode( ',', $raw_durations ) );
    $default   = $product->get_meta( '_wpwa_default_duration' );
    
    // Sort durations to be tidy
    sort( $durations );

    echo '<div class="wpwa-duration-selector" style="margin-bottom: 15px;">';
    echo '<label for="subscription_duration" style="display:block; margin-bottom:5px; font-weight:600;">' . __( 'Select Duration', 'wpwa' ) . '</label>';
    echo '<select name="subscription_duration" id="subscription_duration" class="widefat">';
    
    foreach ( $durations as $duration ) {
        if ( $duration < 1 ) continue;
        $label = sprintf( _n( '%s Cycle', '%s Cycles', $duration, 'wpwa' ), $duration );
        
        // Optional: Add "Best Value" text for 6+ months
        if ( $duration >= 6 ) {
             $discount = $product->get_meta( '_wpwa_duration_discount' );
             if ( $discount ) {
                 $label .= " (Save {$discount}%)";
             }
        }
        
        echo '<option value="' . esc_attr( $duration ) . '" ' . selected( $duration, $default, false ) . '>' . esc_html( $label ) . '</option>';
    }
    
    echo '</select>';
    echo '</div>';
}

/**
 * 2. Cart Data: Save the selection to the Cart Item
 */
add_filter( 'woocommerce_add_cart_item_data', 'wpwa_save_duration_to_cart', 10, 3 );
function wpwa_save_duration_to_cart( $cart_item_data, $product_id, $variation_id ) {
    if ( isset( $_POST['subscription_duration'] ) ) {
        $cart_item_data['subscription_duration'] = absint( $_POST['subscription_duration'] );
        
        // Make the cart item unique so they can have different durations for the same product
        $cart_item_data['unique_key'] = md5( microtime() . rand() ); 
    }
    return $cart_item_data;
}

/**
 * 3. Cart Display: Show selected duration in Cart/Checkout (Optional but recommended)
 */
add_filter( 'woocommerce_get_item_data', 'wpwa_display_duration_in_cart', 10, 2 );
function wpwa_display_duration_in_cart( $item_data, $cart_item ) {
    if ( isset( $cart_item['subscription_duration'] ) ) {
        $item_data[] = array(
            'key'     => __( 'Duration', 'wpwa' ),
            'value'   => sprintf( _n( '%s Cycle', '%s Cycles', $cart_item['subscription_duration'], 'wpwa' ), $cart_item['subscription_duration'] ),
            'display' => '',
        );
    }
    return $item_data;
}

/**
 * WooCommerce Checkout Hooks for Whitelist Subscription
 * File: Add to woocommerce/woo-integration.php
 * 
 * Handles:
 * 1. Capturing weebly_user_id from query parameters
 * 2. Adding it to cart item data
 * 3. Storing in order meta for auto-whitelist functionality
 */

/**
 * Capture weebly_user_id and duration from query parameter and store in session
 */
add_action( 'wp_loaded', 'wpwa_capture_weebly_params_from_url' );
function wpwa_capture_weebly_params_from_url() {
	if ( isset( $_GET['weebly_user_id'] ) && ! empty( $_GET['weebly_user_id'] ) ) {
		WC()->session->set( 'wpwa_weebly_user_id', sanitize_text_field( $_GET['weebly_user_id'] ) );
	}
	
	if ( isset( $_GET['duration'] ) && ! empty( $_GET['duration'] ) ) {
		WC()->session->set( 'wpwa_subscription_duration', absint( $_GET['duration'] ) );
	}
}

/**
 * Add weebly_user_id and duration to cart item data
 */
add_filter( 'woocommerce_add_cart_item_data', 'wpwa_add_whitelist_params_to_cart', 20, 3 );
function wpwa_add_whitelist_params_to_cart( $cart_item_data, $product_id, $variation_id ) {
	// Get whitelist product ID
	$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
	
	// Only add for whitelist product
	if ( (int) $product_id !== (int) $whitelist_product_id ) {
		return $cart_item_data;
	}
	
	// Get from session
	$weebly_user_id = WC()->session->get( 'wpwa_weebly_user_id' );
	$duration = WC()->session->get( 'wpwa_subscription_duration' );
	
	if ( $weebly_user_id ) {
		$cart_item_data['weebly_user_id'] = $weebly_user_id;
	}
	
	if ( $duration && $duration > 1 ) {
		$cart_item_data['subscription_duration'] = $duration;
	}
	
	return $cart_item_data;
}

/**
 * Display weebly_user_id in cart (optional, for transparency)
 */
add_filter( 'woocommerce_get_item_data', 'wpwa_display_whitelist_params_in_cart', 10, 2 );
function wpwa_display_whitelist_params_in_cart( $item_data, $cart_item ) {
	if ( isset( $cart_item['weebly_user_id'] ) ) {
		$item_data[] = array(
			'key'   => __( 'Weebly User ID', 'wpwa' ),
			'value' => esc_html( substr( $cart_item['weebly_user_id'], 0, 20 ) . '...' ),
		);
	}
	
	if ( isset( $cart_item['subscription_duration'] ) && $cart_item['subscription_duration'] > 1 ) {
		$item_data[] = array(
			'key'   => __( 'Subscription Duration', 'wpwa' ),
			'value' => sprintf( __( '%d cycles (prepaid)', 'wpwa' ), $cart_item['subscription_duration'] ),
		);
	}
	
	return $item_data;
}

/**
 * Adjust cart item price based on duration
 */
add_action( 'woocommerce_before_calculate_totals', 'wpwa_adjust_whitelist_price_by_duration', 30 );
function wpwa_adjust_whitelist_price_by_duration( $cart ) {
	$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
	foreach ( $cart->get_cart() as $cart_item ) {
		if ( (int) $cart_item['product_id'] !== (int) $whitelist_product_id ) {
			continue;
		}
		
		$duration = absint( $cart_item['subscription_duration'] ?? 1 );
		
		// Only apply discount for 6+ cycles
		if ( $duration >= 6 ) {
			$discount_percent = absint( get_post_meta( $whitelist_product_id, '_wpwa_duration_discount', true ) );
			
			if ( $discount_percent > 0 ) {
				$product = $cart_item['data'];
				$current_price = floatval( $product->get_price() );
				
				// Calculate discount (already multiplied by duration in previous hook)
				$discount_amount = ( $current_price * $discount_percent ) / 100;
				$new_price = $current_price - $discount_amount;
				
				$product->set_price( $new_price );
			}
		}
	}
}

/**
 * Show duration discount in cart/checkout
 */
add_filter( 'woocommerce_cart_item_price', 'wpwa_show_duration_discount_in_cart', 10, 3 );
function wpwa_show_duration_discount_in_cart( $price_html, $cart_item, $cart_item_key ) {
	$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
	
	if ( (int) $cart_item['product_id'] !== (int) $whitelist_product_id ) {
		return $price_html;
	}
	
	$duration = absint( $cart_item['subscription_duration'] ?? 1 );
	
	if ( $duration >= 6 ) {
		$discount = absint( get_post_meta( $whitelist_product_id, '_wpwa_duration_discount', true ) );
		
		if ( $discount > 0 ) {
			$price_html .= '<br><small class="wpwa-discount-applied" style="color:#48bb78;font-weight:600;">🎉 ' . 
			               sprintf( __( '%d%% prepay discount applied!', 'wpwa' ), $discount ) . 
			               '</small>';
		}
	}
	
	return $price_html;
}

/**
 * Store weebly_user_id and duration in order item meta
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'wpwa_save_whitelist_params_to_order_item', 20, 4 );
function wpwa_save_whitelist_params_to_order_item( $item, $cart_item_key, $values, $order ) {
	if ( isset( $values['weebly_user_id'] ) && ! empty( $values['weebly_user_id'] ) ) {
		$item->add_meta_data( 'weebly_user_id', $values['weebly_user_id'], true );
		$item->add_meta_data( 'user_id', $values['weebly_user_id'], true ); // For consistency
	}
	
	if ( isset( $values['subscription_duration'] ) && $values['subscription_duration'] > 1 ) {
		$item->add_meta_data( '_wpwa_subscription_duration', $values['subscription_duration'], true );
		$item->add_meta_data( '_wpwa_prepaid_cycles', $values['subscription_duration'], true );
	}
}

/**
 * Store weebly_user_id in order meta (for auto-whitelist to work)
 */
add_action( 'woocommerce_checkout_order_processed', 'wpwa_save_weebly_user_id_to_order', 10, 1 );
function wpwa_save_weebly_user_id_to_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}
	
	$whitelist_product_id = WPWA_Whitelist::get_whitelist_product_id();
	
	foreach ( $order->get_items() as $item ) {
		if ( (int) $item->get_product_id() !== (int) $whitelist_product_id ) {
			continue;
		}
		
		$weebly_user_id = $item->get_meta( 'weebly_user_id' );
		if ( $weebly_user_id ) {
			// Store in order meta
			$order->update_meta_data( 'weebly_user_id', $weebly_user_id );
			$order->save();
			
			// Add order note
			$order->add_order_note( sprintf(
				__( 'Whitelist subscription purchased by Weebly User: %s', 'wpwa' ),
				$weebly_user_id
			) );
			
			break;
		}
	}
	
	// Clear session
	WC()->session->__unset( 'wpwa_weebly_user_id' );
	WC()->session->__unset( 'wpwa_subscription_duration' );
}

/**
 * Adjust recurring product expiry based on prepaid duration
 */
add_filter( 'wpwa_recurring_calculate_expiry', 'wpwa_adjust_expiry_for_prepaid_duration', 10, 3 );
function wpwa_adjust_expiry_for_prepaid_duration( $expiry_timestamp, $item, $product ) {
	$duration = absint( $item->get_meta( '_wpwa_prepaid_cycles' ) );
	
	if ( $duration > 1 ) {
		$cycle_length = absint( $product->get_meta( WPWA_Recurring::META_CYCLE_LENGTH ) );
		$cycle_unit = $product->get_meta( WPWA_Recurring::META_CYCLE_UNIT );
		
		// Calculate total duration
		$total_length = $cycle_length * $duration;
		
		$expiry_timestamp = strtotime( "+{$total_length} {$cycle_unit}" );
	}
	
	return $expiry_timestamp;
}