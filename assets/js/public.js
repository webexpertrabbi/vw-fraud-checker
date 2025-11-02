(function ($) {
	'use strict';

	if (typeof window.vwFraudChecker === 'undefined') {
		return;
	}

	const root = $('.vw-fraud-checker-form');

	if (!root.length) {
		return;
	}

	root.on('submit', 'form', function (event) {
		event.preventDefault();

		const form = $(this);
		const phone = form.find('input[name="phone"]').val();
		const results = form.closest('.vw-fraud-checker-form').find('.vw-fraud-checker-results');

		results.attr('hidden', true).empty();

		$.ajax({
			method: 'GET',
			url: vwFraudChecker.endpoints.check,
			data: { phone },
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', vwFraudChecker.nonce);
			},
		})
			.done(function (response) {
				results.removeAttr('hidden').text(JSON.stringify(response, null, 2));
			})
			.fail(function () {
				results.removeAttr('hidden').text('Unable to fetch results right now.');
			});
	});
})(window.jQuery);
