(function ($) {
	function parseShipValue($input) {
		var v = parseInt(String($input.val()).replace(/\D/g, ''), 10);
		return isNaN(v) ? 0 : Math.max(0, v);
	}

	function adjustShipInput(inputId, delta) {
		var $input = $('#' + inputId);
		if (!$input.length) {
			return;
		}
		var max = parseInt(String($('#' + inputId.replace('_input', '_value')).text()).replace(/\D/g, ''), 10);
		if (isNaN(max)) {
			max = 999999999;
		}
		var next = Math.min(max, Math.max(0, parseShipValue($input) + delta));
		$input.val(next > 0 ? next : '');
	}

	$(function () {
		if (!window.matchMedia('(max-width: 699px)').matches) {
			return;
		}

		$(document).on('click', '.fleet-step-minus', function () {
			adjustShipInput($(this).data('target'), -1);
		});

		$(document).on('click', '.fleet-step-plus', function () {
			var step = parseInt($(this).data('step'), 10) || 1;
			adjustShipInput($(this).data('target'), step);
		});
	});
})(jQuery);
