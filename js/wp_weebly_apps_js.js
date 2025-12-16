jQuery(document).ready(function($) {
	$(document).bind('scroll', function(ev) {
		var scrollOffset = $(document).scrollTop();
		if($("#codo_wpwa_stats").length != 0) {
			var containerOffset = $('#codo_wpwa_stats').offset().top - window.innerHeight;
			if (scrollOffset > containerOffset) {
			   $('.count').each(function () {
					$(this).prop('Counter',0).animate({
						Counter: $(this).text()
					}, {
						duration: 4000,
						easing: 'swing',
						step: function (now) {
							$(this).text(Math.ceil(now));
						}
					});
				});
			   // unbind event
				$(document).unbind('scroll');
			}  		
		}
		
	});
	
});