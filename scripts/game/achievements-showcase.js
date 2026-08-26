(function ($) {
	'use strict';

	function selectedCheckboxes($root) {
		return $root.find('.ach-showcase-cb:checked');
	}

	function updateCount($root) {
		$root.find('#achShowcaseCount').text(selectedCheckboxes($root).length);
	}

	function showStatus($root, msg, isError) {
		var $status = $root.find('#achShowcaseStatus');
		$status
			.text(msg || '')
			.toggleClass('is-error', !!isError)
			.toggleClass('is-ok', !isError && !!msg);
	}

	function init() {
		var $root = $('#achievementsPage');
		if (!$root.length) {
			return;
		}

		var limit = parseInt($root.data('showcase-limit'), 10) || 5;
		var maxMsg = $root.data('showcase-max-msg') || '';
		var savedMsg = $root.data('showcase-saved-msg') || '';

		$root.on('change', '.ach-showcase-cb', function () {
			var $cb = $(this);
			if ($cb.is(':checked') && selectedCheckboxes($root).length > limit) {
				$cb.prop('checked', false);
				showStatus($root, maxMsg, true);
				return;
			}
			updateCount($root);
			showStatus($root, '', false);
		});

		$root.on('click', '#achShowcaseSave', function () {
			var ids = [];
			selectedCheckboxes($root).each(function () {
				ids.push(parseInt($(this).val(), 10));
			});

			var $btn = $(this).prop('disabled', true);
			$.post('game.php?page=achievements&mode=showcase', { ids: ids }, null, 'json')
				.done(function (res) {
					var count = (res && typeof res.count === 'number') ? res.count : ids.length;
					$root.find('#achShowcaseCount').text(count);
					showStatus($root, savedMsg, false);
				})
				.fail(function () {
					showStatus($root, 'Error', true);
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}

	$(init);
})(jQuery);
