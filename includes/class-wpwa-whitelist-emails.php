<?php
/**
 * WPWA Whitelist - Email Notifications
 * File: includes/class-wpwa-whitelist-emails.php
 * 
 * Sends email notifications for whitelist events:
 * - Whitelist activated
 * - Whitelist expiring soon (3 days before)
 * - Whitelist expired
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPWA_Whitelist_Emails {

	/* ───────── Bootstrap ───────── */
	public static function init() {
		// Hook into whitelist creation
		add_action( 'wpwa_whitelist_entry_created', [ __CLASS__, 'send_activation_email' ], 10, 2 );
		
		// Hook into whitelist renewal
		add_action( 'wpwa_whitelist_entry_renewed', [ __CLASS__, 'send_renewal_email' ], 10, 2 );
		
		// Daily cron to check expiring entries
		add_action( 'wpwa_check_expiring_whitelist', [ __CLASS__, 'check_expiring_entries' ] );
		
		// Schedule cron if not scheduled
		if ( ! wp_next_scheduled( 'wpwa_check_expiring_whitelist' ) ) {
			wp_schedule_event( time(), 'daily', 'wpwa_check_expiring_whitelist' );
		}

		// Settings page integration
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  SETTINGS
	 * ═══════════════════════════════════════════════════════════════ */

	public static function register_settings() {
		register_setting( 'wpwa_whitelist_emails', 'wpwa_email_activation_enabled', [
			'type'    => 'boolean',
			'default' => true,
		] );

		register_setting( 'wpwa_whitelist_emails', 'wpwa_email_expiring_enabled', [
			'type'    => 'boolean',
			'default' => true,
		] );

		register_setting( 'wpwa_whitelist_emails', 'wpwa_email_expired_enabled', [
			'type'    => 'boolean',
			'default' => true,
		] );

		register_setting( 'wpwa_whitelist_emails', 'wpwa_email_expiring_days', [
			'type'    => 'integer',
			'default' => 3,
		] );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  EMAIL SENDING
	 * ═══════════════════════════════════════════════════════════════ */

	/**
	 * Send activation email when whitelist is created
	 */
	public static function send_activation_email( $entry_id, $data ) {
		if ( ! get_option( 'wpwa_email_activation_enabled', true ) ) {
			return;
		}

		$email = $data['email'] ?? '';
		if ( ! $email ) {
			return;
		}

		$customer_name = $data['customer_name'] ?? 'Valued Customer';
		$type = $data['whitelist_type'] ?? 'user_id';
		$expiry = $data['expiry_date'] ?? null;

		$type_labels = [
			'user_id'     => 'User Access (any app)'
		];

		$subject = sprintf(
			__( 'Your Whitelist Access Has Been Activated - %s', 'wpwa' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_email_template( 'activation', [
			'customer_name' => $customer_name,
			'type'          => $type_labels[ $type ] ?? $type,
			'user_id'       => $data['user_id'] ?? '',
			'site_id'       => $data['site_id'] ?? '',
			'expiry'        => $expiry ? date_i18n( get_option( 'date_format' ), strtotime( $expiry ) ) : 'Never',
		] );

		self::send_email( $email, $subject, $message );

		// Log the email
		self::log_email_sent( $entry_id, 'activation', $email );
	}

	/**
	 * Send renewal email when whitelist is renewed
	 */
	public static function send_renewal_email( $entry_id, $order_id ) {
		if ( ! get_option( 'wpwa_email_activation_enabled', true ) ) {
			return;
		}

		$entry = self::get_entry( $entry_id );
		if ( ! $entry || ! $entry['email'] ) {
			return;
		}

		$subject = sprintf(
			__( 'Your Whitelist Access Has Been Renewed - %s', 'wpwa' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_email_template( 'renewal', [
			'customer_name' => $entry['customer_name'] ?? 'Valued Customer',
			'expiry'        => $entry['expiry_date'] ? date_i18n( get_option( 'date_format' ), strtotime( $entry['expiry_date'] ) ) : 'Never',
			'order_id'      => $order_id,
		] );

		self::send_email( $entry['email'], $subject, $message );
		self::log_email_sent( $entry_id, 'renewal', $entry['email'] );
	}

	/**
	 * Check for expiring entries and send warnings
	 */
	public static function check_expiring_entries() {
		if ( ! get_option( 'wpwa_email_expiring_enabled', true ) ) {
			return;
		}

		$days_before = absint( get_option( 'wpwa_email_expiring_days', 3 ) );
		$warning_date = date( 'Y-m-d H:i:s', strtotime( "+{$days_before} days" ) );
		$now = current_time( 'mysql' );

		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;

		// Find entries expiring within warning period that haven't been warned yet
		$entries = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$table}` 
			 WHERE expiry_date IS NOT NULL 
			   AND expiry_date > %s 
			   AND expiry_date <= %s
			   AND (notes NOT LIKE %s OR notes IS NULL)",
			$now,
			$warning_date,
			'%expiring_email_sent%'
		), ARRAY_A );

		foreach ( $entries as $entry ) {
			self::send_expiring_email( $entry );
		}

		// Check for already expired entries
		if ( get_option( 'wpwa_email_expired_enabled', true ) ) {
			$expired = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM `{$table}` 
				 WHERE expiry_date IS NOT NULL 
				   AND expiry_date <= %s
				   AND (notes NOT LIKE %s OR notes IS NULL)",
				$now,
				'%expired_email_sent%'
			), ARRAY_A );

			foreach ( $expired as $entry ) {
				self::send_expired_email( $entry );
			}
		}
	}

	/**
	 * Send expiring soon email
	 */
	private static function send_expiring_email( $entry ) {
		if ( ! $entry['email'] ) {
			return;
		}

		$days_left = floor( ( strtotime( $entry['expiry_date'] ) - time() ) / DAY_IN_SECONDS );

		$subject = sprintf(
			__( 'Your Whitelist Access Expires in %d Days - %s', 'wpwa' ),
			$days_left,
			get_bloginfo( 'name' )
		);

		$message = self::get_email_template( 'expiring', [
			'customer_name' => $entry['customer_name'] ?? 'Valued Customer',
			'days_left'     => $days_left,
			'expiry_date'   => date_i18n( get_option( 'date_format' ), strtotime( $entry['expiry_date'] ) ),
			'renewal_url'   => self::get_renewal_url( $entry ),
		] );

		self::send_email( $entry['email'], $subject, $message );
		self::log_email_sent( $entry['id'], 'expiring', $entry['email'] );

		// Mark as warned
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->prefix}" . WPWA_Whitelist::TABLE_NAME . "` 
			 SET notes = CONCAT(COALESCE(notes, ''), '\n[expiring_email_sent: %s]')
			 WHERE id = %d",
			current_time( 'mysql' ),
			$entry['id']
		) );
	}

	/**
	 * Send expired email
	 */
	private static function send_expired_email( $entry ) {
		if ( ! $entry['email'] ) {
			return;
		}

		$subject = sprintf(
			__( 'Your Whitelist Access Has Expired - %s', 'wpwa' ),
			get_bloginfo( 'name' )
		);

		$message = self::get_email_template( 'expired', [
			'customer_name' => $entry['customer_name'] ?? 'Valued Customer',
			'expiry_date'   => date_i18n( get_option( 'date_format' ), strtotime( $entry['expiry_date'] ) ),
			'renewal_url'   => self::get_renewal_url( $entry ),
		] );

		self::send_email( $entry['email'], $subject, $message );
		self::log_email_sent( $entry['id'], 'expired', $entry['email'] );

		// Mark as notified
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->prefix}" . WPWA_Whitelist::TABLE_NAME . "` 
			 SET notes = CONCAT(COALESCE(notes, ''), '\n[expired_email_sent: %s]')
			 WHERE id = %d",
			current_time( 'mysql' ),
			$entry['id']
		) );
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  EMAIL TEMPLATES
	 * ═══════════════════════════════════════════════════════════════ */

	private static function get_email_template( $type, $data ) {
		$templates = [
			'activation' => self::activation_template( $data ),
			'renewal'    => self::renewal_template( $data ),
			'expiring'   => self::expiring_template( $data ),
			'expired'    => self::expired_template( $data ),
		];

		return $templates[ $type ] ?? '';
	}

	private static function activation_template( $data ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #2271b1; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
				.content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
				.info-box { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 20px 0; }
				.info-label { font-weight: bold; color: #1976d2; }
				.button { display: inline-block; background: #2271b1; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>🎉 Whitelist Activated!</h1>
				</div>
				<div class="content">
					<p>Dear <?php echo esc_html( $data['customer_name'] ); ?>,</p>
					
					<p>Great news! Your whitelist access has been activated on <strong><?php echo get_bloginfo( 'name' ); ?></strong>.</p>
					
					<div class="info-box">
						<p class="info-label">Access Type:</p>
						<p><?php echo esc_html( $data['type'] ); ?></p>
						
						<?php if ( ! empty( $data['user_id'] ) ) : ?>
							<p class="info-label">Weebly User ID:</p>
							<p><?php echo esc_html( $data['user_id'] ); ?></p>
						<?php endif; ?>
						
						<p class="info-label">Expires:</p>
						<p><?php echo esc_html( $data['expiry'] ); ?></p>
					</div>
					
					<p><strong>What does this mean?</strong></p>
					<p>You can now install our Weebly apps without any payment! Simply visit the Weebly App Center and install any of our apps - you'll get free access automatically.</p>
					
					<p>If you have any questions, feel free to reach out to our support team.</p>
					
					<p>Best regards,<br>
					The <?php echo get_bloginfo( 'name' ); ?> Team</p>
				</div>
				<div class="footer">
					<p>&copy; <?php echo date( 'Y' ); ?> <?php echo get_bloginfo( 'name' ); ?>. All rights reserved.</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	private static function renewal_template( $data ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #2ecc71; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
				.content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
				.info-box { background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>✅ Whitelist Renewed!</h1>
				</div>
				<div class="content">
					<p>Dear <?php echo esc_html( $data['customer_name'] ); ?>,</p>
					
					<p>Your whitelist access has been successfully renewed!</p>
					
					<div class="info-box">
						<p><strong>New Expiry Date:</strong> <?php echo esc_html( $data['expiry'] ); ?></p>
						<p><strong>Order:</strong> #<?php echo esc_html( $data['order_id'] ); ?></p>
					</div>
					
					<p>You can continue to enjoy free access to our Weebly apps.</p>
					
					<p>Thank you for your continued support!</p>
					
					<p>Best regards,<br>
					The <?php echo get_bloginfo( 'name' ); ?> Team</p>
				</div>
				<div class="footer">
					<p>&copy; <?php echo date( 'Y' ); ?> <?php echo get_bloginfo( 'name' ); ?>. All rights reserved.</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	private static function expiring_template( $data ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #f39c12; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
				.content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
				.warning-box { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #f39c12; }
				.button { display: inline-block; background: #f39c12; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>⚠️ Whitelist Expiring Soon</h1>
				</div>
				<div class="content">
					<p>Dear <?php echo esc_html( $data['customer_name'] ); ?>,</p>
					
					<p>This is a friendly reminder that your whitelist access is expiring soon.</p>
					
					<div class="warning-box">
						<p><strong>Days Remaining:</strong> <?php echo esc_html( $data['days_left'] ); ?> days</p>
						<p><strong>Expiry Date:</strong> <?php echo esc_html( $data['expiry_date'] ); ?></p>
					</div>
					
					<p>After this date, you'll need to purchase our apps from the Weebly App Center to continue using them.</p>
					
					<p>To continue enjoying free access, please renew your whitelist subscription:</p>
					
					<a href="<?php echo esc_url( $data['renewal_url'] ); ?>" class="button">Renew Now</a>
					
					<p style="margin-top: 30px;">If you have any questions, please don't hesitate to contact us.</p>
					
					<p>Best regards,<br>
					The <?php echo get_bloginfo( 'name' ); ?> Team</p>
				</div>
				<div class="footer">
					<p>&copy; <?php echo date( 'Y' ); ?> <?php echo get_bloginfo( 'name' ); ?>. All rights reserved.</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	private static function expired_template( $data ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #e74c3c; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
				.content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
				.error-box { background: #f8d7da; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #e74c3c; }
				.button { display: inline-block; background: #e74c3c; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>❌ Whitelist Expired</h1>
				</div>
				<div class="content">
					<p>Dear <?php echo esc_html( $data['customer_name'] ); ?>,</p>
					
					<p>Your whitelist access has expired.</p>
					
					<div class="error-box">
						<p><strong>Expired On:</strong> <?php echo esc_html( $data['expiry_date'] ); ?></p>
					</div>
					
					<p>You'll now need to purchase our apps from the Weebly App Center if you'd like to continue using them.</p>
					
					<p>Alternatively, you can renew your whitelist subscription to restore free access:</p>
					
					<a href="<?php echo esc_url( $data['renewal_url'] ); ?>" class="button">Renew Whitelist Access</a>
					
					<p style="margin-top: 30px;">Thank you for being a valued customer!</p>
					
					<p>Best regards,<br>
					The <?php echo get_bloginfo( 'name' ); ?> Team</p>
				</div>
				<div class="footer">
					<p>&copy; <?php echo date( 'Y' ); ?> <?php echo get_bloginfo( 'name' ); ?>. All rights reserved.</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  HELPERS
	 * ═══════════════════════════════════════════════════════════════ */

	private static function send_email( $to, $subject, $message ) {
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		];

		return wp_mail( $to, $subject, $message, $headers );
	}

	private static function log_email_sent( $entry_id, $type, $email ) {
		error_log( sprintf(
			'WPWA Whitelist Email: Sent %s email to %s for entry #%d',
			$type,
			$email,
			$entry_id
		) );
	}

	private static function get_entry( $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . WPWA_Whitelist::TABLE_NAME;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM `{$table}` WHERE id = %d",
			$entry_id
		), ARRAY_A );
	}

	private static function get_renewal_url( $entry ) {
		$product_id = WPWA_Whitelist::get_whitelist_product_id();
		if ( $product_id ) {
			return add_query_arg( 'add-to-cart', $product_id, wc_get_cart_url() );
		}
		return home_url();
	}
}

// Bootstrap
WPWA_Whitelist_Emails::init();