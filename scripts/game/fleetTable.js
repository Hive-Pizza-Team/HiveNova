$(function() {
	var slotSpan = $('#activeFleetSlots');
	var activeSlots = parseInt(slotSpan.text(), 10);
	var maxSlots = parseInt(slotSpan.data('max'), 10);

	window.setInterval(function() {
		$('.fleets').each(function() {
			var $el = $(this);
			var s = $el.data('fleet-time') - (serverTime.getTime() - startTime) / 1000;
			if (s <= 0) {
				if (!$el.data('slot-freed')) {
					$el.data('slot-freed', true);
					activeSlots = Math.max(0, activeSlots - 1);
					slotSpan.text(activeSlots);
					if (activeSlots < maxSlots) {
						$('#fleetContinueRow').show();
					}
				}
				$el.text('-');
			} else {
				$el.text(GetRestTimeFormat(s));
			}
		});
	}, 1000);
});
