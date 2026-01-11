/**
 * WPWA Recurring - Admin JavaScript
 * Handles show/hide of recurring product fields
 */
(function($) {
	'use strict';

	function toggleRecurringFields() {
		const $checkbox = $('#_wpwa_is_recurring');
		const $fields = $('.wpwa-recurring-fields');

		if ($checkbox.is(':checked')) {
			$fields.slideDown(300);
		} else {
			$fields.slideUp(300);
		}
	}

	function validateRecurringFields() {
		const $checkbox = $('#_wpwa_is_recurring');
		
		if (!$checkbox.is(':checked')) {
			return true;
		}

		const cycleLength = $('#_wpwa_cycle_length').val();
		const cycleUnit = $('#_wpwa_cycle_unit').val();

		if (!cycleLength || cycleLength < 1) {
			alert('Please enter a valid billing cycle length.');
			return false;
		}

		if (!cycleUnit) {
			alert('Please select a billing cycle unit.');
			return false;
		}

		return true;
	}

	// Initialize on document ready
	$(document).ready(function() {
		// Initial state
		toggleRecurringFields();

		// Toggle on checkbox change
		$('#_wpwa_is_recurring').on('change', toggleRecurringFields);

		// Validate before publish/update
		$('form#post').on('submit', function(e) {
			if (!validateRecurringFields()) {
				e.preventDefault();
				return false;
			}
		});

		// Format price field
		$('#_wpwa_cycle_price').on('blur', function() {
			const val = $(this).val();
			if (val && !isNaN(val)) {
				$(this).val(parseFloat(val).toFixed(2));
			}
		});

		// Add visual feedback for grace period
		$('#_wpwa_grace_period').on('input', function() {
			const days = parseInt($(this).val()) || 0;
			const $hint = $(this).next('.description');
			
			if (days === 0) {
				$hint.html('<strong style="color:#c62828;">⚠️ Access will be revoked immediately after expiry!</strong>');
			} else {
				$hint.html('Days after expiry before access is revoked (0 = immediate)');
			}
		});
	});

})(jQuery);