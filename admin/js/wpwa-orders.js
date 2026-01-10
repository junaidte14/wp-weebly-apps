jQuery(document).ready(function ($) {
    'use strict';
    
    // Handle action buttons
    $(document).on('click', '.wpwa-action-btn', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const action = $btn.data('action');
        const orderId = $btn.data('order-id');

        const actionData = {
            action: 'wpwa_handle_order_action',
            nonce: wpwaAjax.nonce,
            action_type: action,
            payment_id: orderId
        };

        if (action === 'notified') {
            actionData.gross_amount = $btn.data('gross');
            actionData.net_amount = $btn.data('net');
            actionData.payable_amount = $btn.data('payable');
            actionData.access_token = $btn.data('token');
            actionData.app_name = $btn.data('app-name');
        } else if (action === 'remove_access' || action === 'delete') {
            actionData.site_id = $btn.data('site-id');
            actionData.user_id = $btn.data('user-id');
            actionData.app_id = $btn.data('app-id');
            actionData.access_token = $btn.data('token');
        }

        if (['delete', 'remove_access', 'refunded'].includes(action)) {
            showConfirmation(action, function () {
                performAction(actionData, $btn);
            });
        } else {
            performAction(actionData, $btn);
        }
    });

    function showConfirmation(action, callback) {
        const messages = {
            delete: wpwaAjax.i18n.confirm_delete,
            remove_access: wpwaAjax.i18n.confirm_remove,
            refunded: wpwaAjax.i18n.confirm_refund
        };

        $('#wpwa-modal-title').text('Confirm ' + action.replace('_', ' ').toUpperCase());
        $('#wpwa-modal-message').text(messages[action] || wpwaAjax.i18n.error_generic);
        $('#wpwa-confirm-modal').fadeIn(200);

        $('#wpwa-modal-confirm').off('click').on('click', function () {
            $('#wpwa-confirm-modal').fadeOut(200);
            callback();
        });
    }

    function performAction(data, $btn) {
        const $row = $btn.closest('.wpwa-order-row');

        $('#wpwa-loading-overlay').fadeIn(200);
        $btn.prop('disabled', true);
        const fd = new FormData();

        // Core required fields
        fd.append('action', data.action);
        fd.append('nonce', data.nonce);
        fd.append('action_type', data.action_type);
        fd.append('payment_id', data.payment_id);
        
        // Append optional fields dynamically (keeps logic flexible)
        Object.keys(data).forEach(key => {
            if (!fd.has(key)) {
                fd.append(key, data[key]);
            }
        });
        
        // Show loading
        $('#wpwa-loading-overlay').fadeIn(200);
        $btn.prop('disabled', true);
        
        fetch(wpwaAjax.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(json => {
            if (json.success) {
                showNotice('success', json.data.message);
        
                if (data.action_type === 'delete') {
                    $row.fadeOut(400, () => $row.remove());
                } else {
                    updateStatusBadge($row, data.action_type);
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                throw new Error(
                    (json.data && json.data.message) || wpwaAjax.i18n.error_generic
                );
            }
        })
        .catch(error => {
            showNotice('error', error.message || wpwaAjax.i18n.error_generic);
            $btn.prop('disabled', false);
        })
        .finally(() => {
            $('#wpwa-loading-overlay').fadeOut(200);
        });

    }

    function updateStatusBadge($row, status) {
        const badges = {
            notified: '<span class="wpwa-badge wpwa-badge-success"><span class="dashicons dashicons-yes"></span> Notified</span>',
            completed: '<span class="wpwa-badge wpwa-badge-info"><span class="dashicons dashicons-saved"></span> Completed</span>',
            'for-testing': '<span class="wpwa-badge wpwa-badge-warning"><span class="dashicons dashicons-admin-tools"></span> Testing</span>',
            refunded: '<span class="wpwa-badge wpwa-badge-danger"><span class="dashicons dashicons-undo"></span> Refunded</span>',
            access_removed: '<span class="wpwa-badge wpwa-badge-default"><span class="dashicons dashicons-lock"></span> Access Removed</span>'
        };

        if (badges[status]) {
            $row.find('.column-status').html(badges[status]);
        }
    }

    function showNotice(type, message) {
        const notice = $('<div class="wpwa-notice ' +
            (type === 'success' ? 'wpwa-notice-success' : 'wpwa-notice-error') +
            '">' + message + '</div>');

        $('#wpwa-notice-container').append(notice);

        setTimeout(() => notice.fadeOut(400, () => notice.remove()), 5000);
    }

    // Modal close
    $('.wpwa-modal-close, #wpwa-modal-cancel').on('click', () => $('#wpwa-confirm-modal').fadeOut(200));

    $(window).on('click', e => {
        if ($(e.target).is('#wpwa-confirm-modal')) {
            $('#wpwa-confirm-modal').fadeOut(200);
        }
    });

    // Dropdown
    $(document).on('click', '.wpwa-dropdown-toggle', function (e) {
        e.stopPropagation();
        const $dropdown = $(this).closest('.wpwa-dropdown');
        $('.wpwa-dropdown').not($dropdown).removeClass('active');
        $dropdown.toggleClass('active');
    });

    $(document).on('click', () => $('.wpwa-dropdown').removeClass('active'));

    // Select all
    $('#wpwa-select-all').on('change', function () {
        $('.wpwa-order-checkbox').prop('checked', this.checked);
    });

    // Export CSV (unchanged)
    $('.wpwa-export-btn').on('click', function () {
        const headers = ['Order ID', 'Product', 'Customer Email', 'Amount', 'Gross', 'Fee', 'Weebly Payout', 'Status'];
        const rows = [];

        $('.wpwa-order-row').each(function () {
            const $row = $(this);
            rows.push([
                $row.find('.column-order-id strong').text(),
                $row.find('.column-product strong').text(),
                $row.find('.column-customer').contents().first().text().trim(),
                $row.find('.column-amount strong').text(),
                ...$row.find('.wpwa-fee-breakdown small').text().replace(/\s+/g, ' ').split(' ').slice(1, 6),
                $row.find('.wpwa-badge').text().trim()
            ]);
        });

        let csv = headers.join(',') + '\n';
        rows.forEach(r => csv += r.map(c => `"${(c || '').replace(/"/g, '""')}"`).join(',') + '\n');

        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'weebly-orders-' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);

        showNotice('success', 'Orders exported successfully!');
    });
});
