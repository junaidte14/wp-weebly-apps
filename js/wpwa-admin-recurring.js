(function($){
	// Show extra fields only when “Recurring app?” is checked
	function toggle() {
		if ( $('#_wpwa_is_recurring').is(':checked') ) {
			$('.wpwa_recurring_fields').show();
		} else {
			$('.wpwa_recurring_fields').hide();
		}
	}
	$(document).ready(toggle);
	$('#_wpwa_is_recurring').on('change', toggle);
})(jQuery);
