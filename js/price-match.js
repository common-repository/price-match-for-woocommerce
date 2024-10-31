jQuery(document).ready(function($) {
	$('.hmwcpm-link').click(function() {
		$(this).siblings('.hmwcpm-form').slideToggle();
	});
	if ($('.hmwcpm-message').length) {
		$('html, body').animate({
			'scrollTop': $('.hmwcpm-message').offset().top - 100
		}, 500);
	}
});