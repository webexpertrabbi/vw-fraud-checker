(function (wp, $) {
	'use strict';

	$(document).ready(function () {
		$('.vwfc-provider-card').each(function () {
			var $card = $(this);
			var $toggle = $card.find('input[type="checkbox"][name*="[enabled]"]');
			var $status = $card.find('.vwfc-provider-card__status');

			if (!$toggle.length) {
				return;
			}

			var syncState = function () {
				var enabled = $toggle.is(':checked');
				$card.toggleClass('is-enabled', enabled);
				$status.toggleClass('is-enabled', enabled).toggleClass('is-disabled', !enabled);
				$status.text(enabled ? wp.i18n ? wp.i18n.__('Enabled', 'vw-fraud-checker') : 'Enabled' : wp.i18n ? wp.i18n.__('Disabled', 'vw-fraud-checker') : 'Disabled');
			};

			$toggle.on('change', syncState);
			syncState();
		});
	});
})(window.wp || {}, window.jQuery);
