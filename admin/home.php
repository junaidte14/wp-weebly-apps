<?php
/**
 * WPWA Analytics Dashboard - Optimized & Clean Version
 * Complete analytics dashboard with real-time metrics
 */

// Security check
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Enqueue Chart.js from CDN
wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array('jquery'), '3.9.1', true);

$initial_data = wpwa_calculate_dashboard_metrics('this_month');
?>

<div class="wrap wpwa-dashboard">
    <h1 class="wpwa-dashboard-title">
        <span class="dashicons dashicons-chart-area"></span>
        <?php _e('Weebly Apps Analytics Dashboard', 'wpwa'); ?>
    </h1>
    
    <div class="wpwa-dashboard-filters">
        <div class="wpwa-filter-group">
            <label><?php _e('Period:', 'wpwa'); ?></label>
            <select id="wpwa-date-range" class="wpwa-select">
                <option value="today">Today</option>
                <option value="last_7_days">Last 7 Days</option>
                <option value="this_month" selected>This Month</option>
                <option value="last_month">Last Month</option>
                <option value="custom">Custom Range</option>
            </select>
        </div>
        
        <div id="wpwa-custom-dates" style="display:none;">
            <input type="date" id="wpwa-start-date">
            <input type="date" id="wpwa-end-date">
            <button id="wpwa-apply-custom" class="button button-primary">Apply</button>
        </div>
        
        <button id="wpwa-refresh" class="button"><span class="dashicons dashicons-update"></span> Refresh</button>
        <button id="wpwa-export" class="button"><span class="dashicons dashicons-download"></span> Export</button>
    </div>
    
    <div class="wpwa-metrics-grid">
        <div class="wpwa-metric-card wpwa-revenue">
            <div class="wpwa-metric-icon"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="wpwa-metric-content">
                <h3>Total Revenue</h3>
                <div class="wpwa-metric-value" id="metric-revenue">$<?php echo number_format($initial_data['current']['total_revenue'], 2); ?></div>
                <div class="wpwa-growth" id="growth-revenue"><?php echo wpwa_growth_badge($initial_data['growth']['revenue']); ?></div>
            </div>
        </div>
        
        <div class="wpwa-metric-card wpwa-orders">
            <div class="wpwa-metric-icon"><span class="dashicons dashicons-cart"></span></div>
            <div class="wpwa-metric-content">
                <h3>Total Orders</h3>
                <div class="wpwa-metric-value" id="metric-orders"><?php echo number_format($initial_data['current']['total_orders']); ?></div>
                <div class="wpwa-growth" id="growth-orders"><?php echo wpwa_growth_badge($initial_data['growth']['orders']); ?></div>
            </div>
        </div>
        
        <div class="wpwa-metric-card wpwa-profit">
            <div class="wpwa-metric-icon"><span class="dashicons dashicons-chart-line"></span></div>
            <div class="wpwa-metric-content">
                <h3>Net Profit</h3>
                <div class="wpwa-metric-value" id="metric-profit">$<?php echo number_format($initial_data['current']['net_profit'], 2); ?></div>
                <div class="wpwa-growth" id="growth-profit"><?php echo wpwa_growth_badge($initial_data['growth']['profit']); ?></div>
            </div>
        </div>
        
        <div class="wpwa-metric-card wpwa-avg">
            <div class="wpwa-metric-icon"><span class="dashicons dashicons-tag"></span></div>
            <div class="wpwa-metric-content">
                <h3>Avg Order Value</h3>
                <div class="wpwa-metric-value" id="metric-avg">$<?php echo number_format($initial_data['current']['avg_order_value'], 2); ?></div>
                <div class="wpwa-growth" id="growth-avg"><?php echo wpwa_growth_badge($initial_data['growth']['avg_order']); ?></div>
            </div>
        </div>
    </div>
    
    <div class="wpwa-secondary-metrics">
        <div class="wpwa-secondary-card">
            <span class="dashicons dashicons-admin-generic"></span>
            <div>
                <div class="label">Processing Fees</div>
                <div class="value" id="metric-fees">$<?php echo number_format($initial_data['current']['total_fees'], 2); ?></div>
            </div>
        </div>
        <div class="wpwa-secondary-card">
            <span class="dashicons dashicons-share"></span>
            <div>
                <div class="label">Weebly Payout (30%)</div>
                <div class="value" id="metric-weebly">$<?php echo number_format($initial_data['current']['total_weebly_payout'], 2); ?></div>
            </div>
        </div>
        <div class="wpwa-secondary-card">
            <span class="dashicons dashicons-businessman"></span>
            <div>
                <div class="label">Your Earnings</div>
                <div class="value" id="metric-earnings">$<?php echo number_format($initial_data['current']['net_profit'], 2); ?></div>
            </div>
        </div>
    </div>
    
    <div class="wpwa-charts-row">
        <div class="wpwa-chart-container wpwa-large">
            <h2>Revenue & Profit Trend</h2>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="revenue-chart"></canvas>
            </div>
        </div>
        <div class="wpwa-chart-container wpwa-small">
            <h2>Order Status</h2>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="status-chart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="wpwa-tables-row">
        <div class="wpwa-table-wrap">
            <h2><span class="dashicons dashicons-products"></span> Top Products</h2>
            <table class="wpwa-data-table" id="top-products">
                <thead><tr><th>Rank</th><th>Product</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php if (!empty($initial_data['top_products'])) : 
                        $i = 1; 
                        foreach ($initial_data['top_products'] as $p) : ?>
                        <tr><td><?php echo $i++; ?></td><td><?php echo esc_html($p['name']); ?></td><td><?php echo $p['orders']; ?></td><td>$<?php echo number_format($p['revenue'], 2); ?></td></tr>
                    <?php endforeach; 
                    else : ?>
                        <tr><td colspan="4" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="wpwa-table-wrap">
            <h2><span class="dashicons dashicons-groups"></span> Top Customers</h2>
            <table class="wpwa-data-table" id="top-customers">
                <thead><tr><th>Rank</th><th>Customer</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                    <?php if (!empty($initial_data['top_customers'])) : 
                        $i = 1; 
                        foreach ($initial_data['top_customers'] as $c) : ?>
                        <tr><td><?php echo $i++; ?></td><td><?php echo esc_html($c['email']); ?></td><td><?php echo $c['orders']; ?></td><td>$<?php echo number_format($c['revenue'], 2); ?></td></tr>
                    <?php endforeach; 
                    else : ?>
                        <tr><td colspan="4" style="text-align:center;">No data available</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="wpwa-loading" style="display:none;"><div class="wpwa-spinner"></div></div>

<style>
.wpwa-dashboard{background:#f5f5f5;padding:20px;margin:20px 20px 20px 0}.wpwa-dashboard-title{display:flex;align-items:center;gap:12px;font-size:28px;margin-bottom:25px;color:#1d2327}.wpwa-dashboard-title .dashicons{font-size:32px;width:32px;height:32px;color:#2271b1}.wpwa-dashboard-filters{background:#fff;padding:20px;margin-bottom:25px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.05);display:flex;gap:20px;align-items:center;flex-wrap:wrap}.wpwa-filter-group{display:flex;gap:10px;align-items:center}.wpwa-select,.wpwa-date-input{padding:8px 12px;border:1px solid #ddd;border-radius:4px}.wpwa-metrics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:25px}.wpwa-metric-card{background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);display:flex;gap:20px;align-items:center;transition:transform .2s,box-shadow .2s;border-left:4px solid}.wpwa-metric-card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.12)}.wpwa-revenue{border-color:#3498db}.wpwa-orders{border-color:#9b59b6}.wpwa-profit{border-color:#2ecc71}.wpwa-avg{border-color:#f39c12}.wpwa-metric-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}.wpwa-revenue .wpwa-metric-icon{background:rgba(52,152,219,.1);color:#3498db}.wpwa-orders .wpwa-metric-icon{background:rgba(155,89,182,.1);color:#9b59b6}.wpwa-profit .wpwa-metric-icon{background:rgba(46,204,113,.1);color:#2ecc71}.wpwa-avg .wpwa-metric-icon{background:rgba(243,156,18,.1);color:#f39c12}.wpwa-metric-icon .dashicons{font-size:32px;width:32px;height:32px}.wpwa-metric-content{flex:1}.wpwa-metric-content h3{font-size:13px;color:#666;margin:0 0 8px 0;font-weight:500;text-transform:uppercase}.wpwa-metric-value{font-size:32px;font-weight:700;color:#1d2327;margin-bottom:8px}.wpwa-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600}.wpwa-badge.positive{color:#2ecc71;background:rgba(46,204,113,.1)}.wpwa-badge.negative{color:#e74c3c;background:rgba(231,76,60,.1)}.wpwa-secondary-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px}.wpwa-secondary-card{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:20px;border-radius:8px;display:flex;gap:15px;align-items:center;box-shadow:0 4px 15px rgba(102,126,234,.4)}.wpwa-secondary-card:nth-child(2){background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);box-shadow:0 4px 15px rgba(245,87,108,.4)}.wpwa-secondary-card:nth-child(3){background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);box-shadow:0 4px 15px rgba(79,172,254,.4)}.wpwa-secondary-card .dashicons{font-size:36px;width:36px;height:36px}.wpwa-secondary-card .label{font-size:12px;opacity:.9}.wpwa-secondary-card .value{font-size:24px;font-weight:700}.wpwa-charts-row{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:25px}.wpwa-chart-container{background:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);position:relative}.wpwa-chart-container h2{margin:0 0 20px 0;font-size:18px}.wpwa-chart-container.wpwa-large{min-height:400px}.wpwa-chart-container.wpwa-small{min-height:400px}.wpwa-chart-container canvas{max-height:350px!important;display:block;box-sizing:border-box}.wpwa-tables-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:20px;margin-bottom:25px}.wpwa-table-wrap{background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow:hidden}.wpwa-table-wrap h2{padding:20px 25px;margin:0;background:#f9f9f9;border-bottom:2px solid #e5e5e5;font-size:16px;display:flex;align-items:center;gap:8px}.wpwa-data-table{width:100%;border-collapse:collapse}.wpwa-data-table th{background:#fafafa;padding:12px 25px;text-align:left;font-weight:600;font-size:12px;color:#666;text-transform:uppercase}.wpwa-data-table td{padding:15px 25px;border-bottom:1px solid #f0f0f0}.wpwa-data-table tr:hover{background:#f9f9f9}#wpwa-loading{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9999}.wpwa-spinner{width:50px;height:50px;border:4px solid #f3f3f3;border-top:4px solid #2271b1;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
</style>

<script>
jQuery(document).ready(function($) {
    let revenueChart, statusChart;
    const nonce = '<?php echo wp_create_nonce('wpwa_dashboard_nonce'); ?>';
    
    function waitForChart(callback) {
        if (typeof Chart !== 'undefined') {
            callback();
        } else {
            setTimeout(function() { waitForChart(callback); }, 100);
        }
    }
    
    function formatCurrency(value) {
        return '€' + Number(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function formatNumber(value) {
        return Number(value).toLocaleString();
    }
    
    function renderGrowthBadge(growth) {
        const isPositive = growth >= 0;
        const icon = isPositive ? 'arrow-up-alt' : 'arrow-down-alt';
        const className = isPositive ? 'positive' : 'negative';
        return `<span class="wpwa-badge ${className}"><span class="dashicons dashicons-${icon}"></span>${growth > 0 ? '+' : ''}${growth.toFixed(1)}%</span>`;
    }
    
    function initCharts(data) {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not loaded');
            return;
        }
        
        try {
            const chartData = data.chart_data || {};
            const labels = Object.keys(chartData);
            const revenueData = labels.map(l => parseFloat(chartData[l].revenue) || 0);
            const profitData = labels.map(l => parseFloat(chartData[l].profit) || 0);
            
            if (revenueChart) {
                revenueChart.destroy();
                revenueChart = null;
            }
            if (statusChart) {
                statusChart.destroy();
                statusChart = null;
            }
            
            const revenueCanvas = document.getElementById('revenue-chart');
            if (revenueCanvas) {
                const revenueCtx = revenueCanvas.getContext('2d');
                revenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Revenue',
                            data: revenueData,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Profit',
                            data: profitData,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 2,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '€' + value.toFixed(0);
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            const statusCanvas = document.getElementById('status-chart');
            if (statusCanvas && data.status_distribution) {
                const statusCtx = statusCanvas.getContext('2d');
                const statuses = data.status_distribution;
                const statusData = [
                    statuses.notified || 0,
                    statuses.completed || 0,
                    statuses['for-testing'] || 0,
                    statuses.refunded || 0,
                    statuses.pending || 0
                ];
                
                statusChart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Notified', 'Completed', 'Testing', 'Refunded', 'Pending'],
                        datasets: [{
                            data: statusData,
                            backgroundColor: ['#2ecc71', '#3498db', '#f39c12', '#e74c3c', '#95a5a6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 1,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        } catch(e) {
            console.error('Chart initialization error:', e);
        }
    }
    
    function updateDashboard(dateRange, startDate, endDate) {
        $('#wpwa-loading').fadeIn(200);
        fetch(ajaxurl+'?action=wpwa_get_dashboard_data', {
			method: "POST",
			headers: {
				"Content-Type": "application/json"
			},
			body: JSON.stringify({ 
				nonce: nonce,
				date_range: dateRange,
				start_date: startDate,
				end_date: endDate
			})
		})
		.then(response => response.json())
		.then(response => {
			console.log(response);
			if (response.success) {
				const data = response.data;
			
				$('#metric-revenue').text(formatCurrency(data.current.total_revenue));
				$('#metric-orders').text(formatNumber(data.current.total_orders));
				$('#metric-profit').text(formatCurrency(data.current.net_profit));
				$('#metric-avg').text(formatCurrency(data.current.avg_order_value));
				$('#metric-fees').text(formatCurrency(data.current.total_fees));
				$('#metric-weebly').text(formatCurrency(data.current.total_weebly_payout));
				$('#metric-earnings').text(formatCurrency(data.current.net_profit));
				
				$('#growth-revenue').html(renderGrowthBadge(data.growth.revenue));
				$('#growth-orders').html(renderGrowthBadge(data.growth.orders));
				$('#growth-profit').html(renderGrowthBadge(data.growth.profit));
				$('#growth-avg').html(renderGrowthBadge(data.growth.avg_order));
				
				initCharts(data);
				
				$('#top-products tbody').empty();
				if (data.top_products && data.top_products.length > 0) {
					data.top_products.forEach((p, i) => {
						$('#top-products tbody').append(
							`<tr><td>${i+1}</td><td>${p.name}</td><td>${p.orders}</td><td>${formatCurrency(p.revenue)}</td></tr>`
						);
					});
				} else {
					$('#top-products tbody').append('<tr><td colspan="4" style="text-align:center;">No data available</td></tr>');
				}
				
				$('#top-customers tbody').empty();
				if (data.top_customers && data.top_customers.length > 0) {
					data.top_customers.forEach((c, i) => {
						$('#top-customers tbody').append(
							`<tr><td>${i+1}</td><td>${c.email}</td><td>${c.orders}</td><td>${formatCurrency(c.revenue)}</td></tr>`
						);
					});
				} else {
					$('#top-customers tbody').append('<tr><td colspan="4" style="text-align:center;">No data available</td></tr>');
				}
			} else {
				console.error('Dashboard update failed:', response);
				alert('Failed to update dashboard. Please try again.');
			}
			$('#wpwa-loading').fadeOut(200);
		})
		.catch(error => {
			console.error('AJAX error:', error);
			alert('Network error. Please check your connection and try again.');
			$('#wpwa-loading').fadeOut(200);
		});
    }
    
    waitForChart(function() {
        const initialData = <?php echo json_encode($initial_data); ?>;
        initCharts(initialData);
    });
    
    $('#wpwa-date-range').on('change', function() {
        if($(this).val() === 'custom') {
            $('#wpwa-custom-dates').slideDown();
        } else {
            $('#wpwa-custom-dates').slideUp();
            updateDashboard($(this).val(), '', '');
        }
    });
    
    $('#wpwa-apply-custom, #wpwa-refresh').on('click', function() {
        updateDashboard($('#wpwa-date-range').val(), $('#wpwa-start-date').val(), $('#wpwa-end-date').val());
    });
    
    $('#wpwa-export').on('click', function() {
        let csv = 'Weebly Apps Analytics Report\n';
        csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';
        csv += 'Total Revenue,' + $('#metric-revenue').text() + '\n';
        csv += 'Total Orders,' + $('#metric-orders').text() + '\n';
        csv += 'Net Profit,' + $('#metric-profit').text() + '\n';
        csv += 'Avg Order Value,' + $('#metric-avg').text() + '\n';
        csv += 'Processing Fees,' + $('#metric-fees').text() + '\n';
        csv += 'Weebly Payout,' + $('#metric-weebly').text() + '\n\n';
        
        csv += 'Top Products\n';
        $('#top-products tbody tr').each(function() {
            const cells = $(this).find('td');
            if (cells.length > 1) {
                csv += cells.eq(1).text() + ',' + cells.eq(2).text() + ',' + cells.eq(3).text() + '\n';
            }
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'wpwa-analytics-' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });
});
</script>