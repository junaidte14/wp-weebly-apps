<?php
/**
 * Whitelist Usage Tracking Admin Page
 * File: admin/whitelist-usage-page.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap wpwa-usage-wrap">
	<h1 class="wpwa-page-title">
		<span class="dashicons dashicons-chart-line"></span>
		<?php _e( 'Whitelist Usage Tracking', 'wpwa' ); ?>
	</h1>

	<div id="wpwa-notice-container"></div>

	<!-- Statistics Overview -->
	<div class="wpwa-stats-grid">
		<div class="wpwa-stat-card wpwa-stat-primary">
			<div class="wpwa-stat-icon">
				<span class="dashicons dashicons-download"></span>
			</div>
			<div class="wpwa-stat-content">
				<div class="wpwa-stat-value"><?php echo number_format( $stats['total_installs'] ); ?></div>
				<div class="wpwa-stat-label"><?php _e( 'Total Installs', 'wpwa' ); ?></div>
			</div>
		</div>

		<div class="wpwa-stat-card wpwa-stat-success">
			<div class="wpwa-stat-icon">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="wpwa-stat-content">
				<div class="wpwa-stat-value"><?php echo number_format( $stats['unique_users'] ); ?></div>
				<div class="wpwa-stat-label"><?php _e( 'Unique Users', 'wpwa' ); ?></div>
			</div>
		</div>

		<div class="wpwa-stat-card wpwa-stat-info">
			<div class="wpwa-stat-icon">
				<span class="dashicons dashicons-admin-site"></span>
			</div>
			<div class="wpwa-stat-content">
				<div class="wpwa-stat-value"><?php echo number_format( $stats['unique_sites'] ); ?></div>
				<div class="wpwa-stat-label"><?php _e( 'Unique Sites', 'wpwa' ); ?></div>
			</div>
		</div>

		<div class="wpwa-stat-card wpwa-stat-warning">
			<div class="wpwa-stat-icon">
				<span class="dashicons dashicons-calendar"></span>
			</div>
			<div class="wpwa-stat-content">
				<div class="wpwa-stat-value"><?php echo number_format( $stats['total_today'] ); ?></div>
				<div class="wpwa-stat-label"><?php _e( 'Today', 'wpwa' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Time-Based Stats -->
	<div class="wpwa-time-stats">
		<div class="wpwa-time-stat">
			<span class="wpwa-time-label"><?php _e( 'This Week:', 'wpwa' ); ?></span>
			<span class="wpwa-time-value"><?php echo number_format( $stats['total_this_week'] ); ?> installs</span>
		</div>
		<div class="wpwa-time-stat">
			<span class="wpwa-time-label"><?php _e( 'This Month:', 'wpwa' ); ?></span>
			<span class="wpwa-time-value"><?php echo number_format( $stats['total_this_month'] ); ?> installs</span>
		</div>
	</div>

	<!-- Two Column Layout -->
	<div class="wpwa-two-column">
		<!-- Most Popular Apps -->
		<div class="wpwa-panel">
			<h2><?php _e( 'Most Popular Apps', 'wpwa' ); ?></h2>
			
			<?php if ( ! empty( $stats['most_popular_apps'] ) ) : ?>
				<table class="wpwa-simple-table">
					<thead>
						<tr>
							<th><?php _e( 'App Name', 'wpwa' ); ?></th>
							<th><?php _e( 'Installs', 'wpwa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['most_popular_apps'] as $app ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $app['product_name'] ); ?></strong></td>
								<td><?php echo number_format( $app['install_count'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="wpwa-no-data"><?php _e( 'No data available yet.', 'wpwa' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Most Active Users -->
		<div class="wpwa-panel">
			<h2><?php _e( 'Most Active Users', 'wpwa' ); ?></h2>
			
			<?php if ( ! empty( $stats['most_active_users'] ) ) : ?>
				<table class="wpwa-simple-table">
					<thead>
						<tr>
							<th><?php _e( 'User ID', 'wpwa' ); ?></th>
							<th><?php _e( 'Installs', 'wpwa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['most_active_users'] as $user ) : ?>
							<tr>
								<td><code><?php echo esc_html( $user['user_id'] ); ?></code></td>
								<td><?php echo number_format( $user['usage_count'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="wpwa-no-data"><?php _e( 'No data available yet.', 'wpwa' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Recent Usage Activity -->
	<div class="wpwa-recent-activity">
		<div class="wpwa-activity-header">
			<h2><?php _e( 'Recent Activity', 'wpwa' ); ?></h2>
			<form method="post" action="<?php echo admin_url( 'admin-ajax.php' ); ?>" id="wpwa-export-form">
				<?php wp_nonce_field( 'wpwa_usage_tracking', 'nonce' ); ?>
				<input type="hidden" name="action" value="wpwa_export_usage">
				<button type="submit" class="button">
					<span class="dashicons dashicons-download"></span>
					<?php _e( 'Export CSV', 'wpwa' ); ?>
				</button>
			</form>
		</div>

		<div class="wpwa-table-container">
			<table class="wp-list-table widefat fixed striped wpwa-usage-table">
				<thead>
					<tr>
						<th><?php _e( 'Date/Time', 'wpwa' ); ?></th>
						<th><?php _e( 'User ID', 'wpwa' ); ?></th>
						<th><?php _e( 'Site ID', 'wpwa' ); ?></th>
						<th><?php _e( 'App', 'wpwa' ); ?></th>
						<th><?php _e( 'Action', 'wpwa' ); ?></th>
						<th><?php _e( 'IP Address', 'wpwa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $recent_usage ) ) : ?>
						<?php foreach ( $recent_usage as $usage ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $usage['created_at'] ) ) ); ?></td>
								<td><code><?php echo esc_html( $usage['user_id'] ); ?></code></td>
								<td><?php echo $usage['site_id'] ? '<code>' . esc_html( $usage['site_id'] ) . '</code>' : '—'; ?></td>
								<td><strong><?php echo esc_html( $usage['product_name'] ?: 'Unknown' ); ?></strong></td>
								<td>
									<span class="wpwa-action-badge wpwa-action-<?php echo esc_attr( $usage['action_type'] ); ?>">
										<?php echo esc_html( ucfirst( $usage['action_type'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $usage['ip_address'] ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6" class="wpwa-no-data">
								<?php _e( 'No usage data recorded yet.', 'wpwa' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<style>
.wpwa-usage-wrap { background: #f5f5f5; padding: 20px; margin: 20px 20px 20px 0; }
.wpwa-page-title { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
.wpwa-page-title .dashicons { font-size: 32px; width: 32px; height: 32px; }

/* Stats Grid */
.wpwa-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
.wpwa-stat-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); display: flex; gap: 20px; align-items: center; border-left: 4px solid; transition: transform .2s, box-shadow .2s; }
.wpwa-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.12); }

.wpwa-stat-primary { border-color: #3498db; }
.wpwa-stat-success { border-color: #2ecc71; }
.wpwa-stat-info { border-color: #9b59b6; }
.wpwa-stat-warning { border-color: #f39c12; }

.wpwa-stat-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.wpwa-stat-primary .wpwa-stat-icon { background: rgba(52,152,219,.1); color: #3498db; }
.wpwa-stat-success .wpwa-stat-icon { background: rgba(46,204,113,.1); color: #2ecc71; }
.wpwa-stat-info .wpwa-stat-icon { background: rgba(155,89,182,.1); color: #9b59b6; }
.wpwa-stat-warning .wpwa-stat-icon { background: rgba(243,156,18,.1); color: #f39c12; }

.wpwa-stat-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
.wpwa-stat-value { font-size: 36px; font-weight: 700; color: #1d2327; margin-bottom: 4px; }
.wpwa-stat-label { font-size: 14px; color: #666; text-transform: uppercase; }

/* Time Stats */
.wpwa-time-stats { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; gap: 40px; justify-content: center; box-shadow: 0 4px 15px rgba(102,126,234,.4); }
.wpwa-time-stat { display: flex; gap: 10px; align-items: center; }
.wpwa-time-label { font-size: 14px; opacity: .9; }
.wpwa-time-value { font-size: 20px; font-weight: 700; }

/* Two Column */
.wpwa-two-column { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 25px; }
.wpwa-panel { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.wpwa-panel h2 { margin: 0 0 20px 0; font-size: 18px; }

.wpwa-simple-table { width: 100%; border-collapse: collapse; }
.wpwa-simple-table th { background: #f9f9f9; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; border-bottom: 2px solid #e5e5e5; }
.wpwa-simple-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
.wpwa-simple-table tr:hover { background: #f9f9f9; }

.wpwa-no-data { text-align: center; padding: 30px; color: #666; font-style: italic; }

/* Recent Activity */
.wpwa-recent-activity { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.wpwa-activity-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.wpwa-activity-header h2 { margin: 0; }

.wpwa-table-container { overflow-x: auto; }
.wpwa-usage-table { background: #fff; }
.wpwa-usage-table thead th { background: #f9f9f9; font-weight: 600; padding: 12px; }
.wpwa-usage-table tbody td { padding: 12px; vertical-align: middle; }
.wpwa-usage-table code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 12px; }

.wpwa-action-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.wpwa-action-install { background: #d4edda; color: #155724; }
.wpwa-action-access { background: #d1ecf1; color: #0c5460; }
.wpwa-action-renewal { background: #fff3cd; color: #856404; }

/* Responsive */
@media screen and (max-width: 782px) {
	.wpwa-stats-grid { grid-template-columns: 1fr; }
	.wpwa-two-column { grid-template-columns: 1fr; }
	.wpwa-time-stats { flex-direction: column; gap: 15px; }
}
</style>

<script>
jQuery(document).ready(function($) {
	$('#wpwa-export-form').on('submit', function() {
		// Form will submit normally and trigger download
		return true;
	});
});
</script>