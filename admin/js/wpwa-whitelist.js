/**
 * WPWA Whitelist Management - Frontend Controller
 * File: admin/js/wpwa-whitelist.js
 */

jQuery(document).ready(function ($) {
	'use strict';

	let allEntries = [];
	let currentEditId = null;

	/* ═══════════════════════════════════════════════════════════════
	 *  INITIALIZATION
	 * ═══════════════════════════════════════════════════════════════ */

	init();

	function init() {
		loadEntries();
		bindEvents();
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  EVENT BINDINGS
	 * ═══════════════════════════════════════════════════════════════ */

	function bindEvents() {
		// Add new entry
		$('#wpwa-add-entry-btn').on('click', openAddModal);

		// Edit entry
		$(document).on('click', '.wpwa-edit-entry', function () {
			const id = $(this).data('id');
			openEditModal(id);
		});

		// Delete entry
		$(document).on('click', '.wpwa-delete-entry', function () {
			const id = $(this).data('id');
			deleteEntry(id);
		});

		// Form submission
		$('#wpwa-entry-form').on('submit', saveEntry);

		// Modal close
		$('.wpwa-modal-close, .wpwa-modal-cancel').on('click', closeModal);

		// Type change - show/hide fields
		$('#whitelist_type').on('change', toggleFieldsByType);

		// Subscription order check
		$('#subscription_order_id').on('blur', checkSubscription);

		// Filters
		$('#wpwa-filter-type, #wpwa-filter-status').on('change', filterEntries);
		$('#wpwa-search').on('keyup', filterEntries);

		// Click outside modal to close
		$(window).on('click', function (e) {
			if ($(e.target).is('#wpwa-entry-modal')) {
				closeModal();
			}
		});
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  DATA OPERATIONS
	 * ═══════════════════════════════════════════════════════════════ */

	function loadEntries() {
		showLoading();

		$.post(wpwaWhitelist.ajaxurl, {
			action: 'wpwa_whitelist_get_entries',
			nonce: wpwaWhitelist.nonce
		}, function (response) {
			if (response.success) {
				allEntries = response.data.entries;
				renderEntries(allEntries);
			} else {
				showNotice('error', response.data.message || wpwaWhitelist.i18n.error_generic);
			}
		}).fail(function () {
			showNotice('error', wpwaWhitelist.i18n.error_generic);
		}).always(function () {
			hideLoading();
		});
	}

	function saveEntry(e) {
		e.preventDefault();

		const formData = {
			action: 'wpwa_whitelist_save_entry',
			nonce: wpwaWhitelist.nonce,
			id: $('#entry_id').val(),
			whitelist_type: $('#whitelist_type').val(),
			user_id: $('#user_id').val().trim(),
			site_id: '',
			email: $('#email').val().trim(),
			customer_name: $('#customer_name').val().trim(),
			notes: $('#notes').val().trim(),
			subscription_order_id: $('#subscription_order_id').val()
		};

		// Validation
		if (!validateForm(formData)) {
			return;
		}

		showLoading();

		$.post(wpwaWhitelist.ajaxurl, formData, function (response) {
			if (response.success) {
				showNotice('success', wpwaWhitelist.i18n.success_saved);
				closeModal();
				loadEntries();
			} else {
				showNotice('error', response.data.message || wpwaWhitelist.i18n.error_generic);
			}
		}).fail(function () {
			showNotice('error', wpwaWhitelist.i18n.error_generic);
		}).always(function () {
			hideLoading();
		});
	}

	function deleteEntry(id) {
		if (!confirm(wpwaWhitelist.i18n.confirm_delete)) {
			return;
		}

		showLoading();

		$.post(wpwaWhitelist.ajaxurl, {
			action: 'wpwa_whitelist_delete_entry',
			nonce: wpwaWhitelist.nonce,
			id: id
		}, function (response) {
			if (response.success) {
				showNotice('success', wpwaWhitelist.i18n.success_deleted);
				loadEntries();
			} else {
				showNotice('error', response.data.message || wpwaWhitelist.i18n.error_generic);
			}
		}).fail(function () {
			showNotice('error', wpwaWhitelist.i18n.error_generic);
		}).always(function () {
			hideLoading();
		});
	}

	function checkSubscription() {
		const orderId = $('#subscription_order_id').val();
		if (!orderId) {
			$('#subscription_status_display').html('');
			return;
		}

		$.post(wpwaWhitelist.ajaxurl, {
			action: 'wpwa_whitelist_check_subscription',
			nonce: wpwaWhitelist.nonce,
			order_id: orderId
		}, function (response) {
			if (response.success) {
				const status = response.data.status;
				const expiry = response.data.expiry;

				let statusClass = 'invalid';
				let statusText = 'Invalid or Not Found';

				if (status === 'active') {
					statusClass = 'active';
					statusText = 'Active' + (expiry ? ' (expires: ' + expiry + ')' : '');
				} else if (status === 'expired') {
					statusClass = 'expired';
					statusText = 'Expired';
				} else if (status === 'revoked') {
					statusClass = 'expired';
					statusText = 'Revoked';
				}

				$('#subscription_status_display').html(
					'<div class="subscription-status ' + statusClass + '">' +
					'<strong>Subscription Status:</strong> ' + statusText +
					'</div>'
				);
			}
		});
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  UI RENDERING
	 * ═══════════════════════════════════════════════════════════════ */

	function renderEntries(entries) {
		const $tbody = $('#wpwa-whitelist-tbody');
		$tbody.empty();

		if (!entries || entries.length === 0) {
			$tbody.html('<tr><td colspan="8" class="wpwa-loading">No whitelist entries found.</td></tr>');
			return;
		}

		entries.forEach(function (entry) {
			$tbody.append(renderRow(entry));
		});
	}

	function renderRow(entry) {
		const typeBadge = getTypeBadge(entry.whitelist_type);
		const statusBadge = getStatusBadge(entry);
		const subscriptionDisplay = getSubscriptionDisplay(entry);

		return `
			<tr>
				<td>${typeBadge}</td>
				<td>${escapeHtml(entry.user_id || '—')}</td>
				<td>
					${entry.customer_name ? '<strong>' + escapeHtml(entry.customer_name) + '</strong><br>' : ''}
					${entry.email ? '<small>' + escapeHtml(entry.email) + '</small>' : ''}
				</td>
				<td>${subscriptionDisplay}</td>
				<td>${entry.expiry_date ? formatDate(entry.expiry_date) : 'No Expiry'}</td>
				<td>${statusBadge}</td>
				<td>
					<button class="button button-small wpwa-edit-entry" data-id="${entry.id}">
						<span class="dashicons dashicons-edit"></span> Edit
					</button>
					<button class="button button-small wpwa-delete-entry" data-id="${entry.id}">
						<span class="dashicons dashicons-trash"></span> Delete
					</button>
				</td>
			</tr>
		`;
	}

	function getTypeBadge(type) {
		const labels = {
			global_user: 'Global User',
			user_id: 'User ID',
			site_user: 'Site + User'
		};
		return `<span class="wpwa-type-badge wpwa-type-${type}">${labels[type] || type}</span>`;
	}

	function getStatusBadge(entry) {
		if (entry.is_active) {
			return '<span class="wpwa-status-badge wpwa-status-active"><span class="dashicons dashicons-yes"></span> Active</span>';
		}
		return '<span class="wpwa-status-badge wpwa-status-expired"><span class="dashicons dashicons-no"></span> Expired</span>';
	}

	function getSubscriptionDisplay(entry) {
		if (!entry.subscription_order_id) {
			return '—';
		}

		const status = entry.subscription_status;
		let badge = '';

		if (status === 'active') {
			badge = '<span class="wpwa-status-badge wpwa-status-active">Active</span>';
		} else if (status === 'expired') {
			badge = '<span class="wpwa-status-badge wpwa-status-expired">Expired</span>';
		} else if (status === 'revoked') {
			badge = '<span class="wpwa-status-badge wpwa-status-revoked">Revoked</span>';
		}

		return `Order #${entry.subscription_order_id}<br>${badge}`;
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  MODAL OPERATIONS
	 * ═══════════════════════════════════════════════════════════════ */

	function openAddModal() {
		currentEditId = null;
		$('#wpwa-modal-title').text('Add Whitelist Entry');
		$('#wpwa-entry-form')[0].reset();
		$('#entry_id').val('');
		$('#subscription_status_display').html('');
		toggleFieldsByType();
		$('#wpwa-entry-modal').fadeIn(200);
	}

	function openEditModal(id) {
		const entry = allEntries.find(e => e.id == id);
		if (!entry) return;

		currentEditId = id;
		$('#wpwa-modal-title').text('Edit Whitelist Entry');

		$('#entry_id').val(entry.id);
		$('#whitelist_type').val(entry.whitelist_type);
		$('#user_id').val(entry.user_id || '');
		$('#site_id').val(entry.site_id || '');
		$('#email').val(entry.email || '');
		$('#customer_name').val(entry.customer_name || '');
		$('#notes').val(entry.notes || '');
		$('#subscription_order_id').val(entry.subscription_order_id || '');

		toggleFieldsByType();
		checkSubscription();
		$('#wpwa-entry-modal').fadeIn(200);
	}

	function closeModal() {
		$('#wpwa-entry-modal').fadeOut(200);
		currentEditId = null;
	}

	function toggleFieldsByType() {
		const type = $('#whitelist_type').val();

		if (type === 'site_user') {
			$('#site_id_group').show();
			$('#site_id').attr('required', true);
			$('#site_id_required').show();
		} else {
			$('#site_id_group').hide();
			$('#site_id').attr('required', false);
			$('#site_id_required').hide();
		}

		if (type === 'global_user' || type === 'user_id') {
			$('#user_id').attr('required', true);
			$('#user_id_required').show();
		} else {
			$('#user_id').attr('required', true);
			$('#user_id_required').show();
		}
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  FILTERING
	 * ═══════════════════════════════════════════════════════════════ */

	function filterEntries() {
		const typeFilter = $('#wpwa-filter-type').val();
		const statusFilter = $('#wpwa-filter-status').val();
		const searchTerm = $('#wpwa-search').val().toLowerCase();

		let filtered = allEntries.filter(function (entry) {
			// Type filter
			if (typeFilter && entry.whitelist_type !== typeFilter) {
				return false;
			}

			// Status filter
			if (statusFilter === 'active' && !entry.is_active) {
				return false;
			}
			if (statusFilter === 'expired' && entry.is_active) {
				return false;
			}

			// Search filter
			if (searchTerm) {
				const searchable = [
					entry.user_id,
					entry.site_id,
					entry.email,
					entry.customer_name,
					entry.notes
				].join(' ').toLowerCase();

				if (searchable.indexOf(searchTerm) === -1) {
					return false;
				}
			}

			return true;
		});

		renderEntries(filtered);
	}

	/* ═══════════════════════════════════════════════════════════════
	 *  VALIDATION & HELPERS
	 * ═══════════════════════════════════════════════════════════════ */

	function validateForm(data) {
		if (!data.whitelist_type) {
			showNotice('error', 'Please select a whitelist type.');
			return false;
		}

		if ((data.whitelist_type === 'global_user' || data.whitelist_type === 'user_id') && !data.user_id) {
			showNotice('error', 'User ID is required for this whitelist type.');
			return false;
		}

		if (data.whitelist_type === 'site_user' && (!data.user_id || !data.site_id)) {
			showNotice('error', 'Both User ID and Site ID are required for Site + User type.');
			return false;
		}

		return true;
	}

	function showNotice(type, message) {
		const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
		const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>');

		$('#wpwa-notice-container').html(notice);

		setTimeout(function () {
			notice.fadeOut(400, function () {
				$(this).remove();
			});
		}, 5000);
	}

	function showLoading() {
		$('#wpwa-loading-overlay').fadeIn(200);
	}

	function hideLoading() {
		$('#wpwa-loading-overlay').fadeOut(200);
	}

	function escapeHtml(text) {
		if (!text) return '';
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function (m) { return map[m]; });
	}

	function formatDate(dateString) {
		const date = new Date(dateString);
		return date.toLocaleString();
	}
});