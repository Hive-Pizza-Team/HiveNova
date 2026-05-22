var overviewTimerReloadPending = false;
var overviewTimersWereActive = false;

function updateOverviewTimers() {
	var needsReload = false;

	$('.timer').each(function() {
		// data-time is seconds remaining at page load (same as buildings/research pages),
		// not a unix timestamp — serverTime is a local clock, not epoch seconds.
		var secondsLeft = $(this).data('time');
		if (secondsLeft === undefined || secondsLeft === null) {
			return;
		}

		var remaining = Math.floor(secondsLeft - (serverTime.getTime() - startTime) / 1000);
		if (remaining <= 0) {
			$(this).text(Ready);
			// Only reload after a timer was actively counting down. Timers already
			// at zero on first paint (e.g. shipyard queue with elapsed endtime)
			// would otherwise reload the page in an infinite loop.
			if (overviewTimersWereActive) {
				needsReload = true;
			}
		} else {
			overviewTimersWereActive = true;
			$(this).text(GetRestTimeFormat(remaining));
		}
	});

	if (needsReload && !overviewTimerReloadPending) {
		overviewTimerReloadPending = true;
		window.setTimeout(function() {
			window.location.href = 'game.php?page=overview';
		}, 1000);
	}
}

$(document).ready(function()
{
	window.setInterval(function() {
		$('.fleets').each(function() {
			var s		= $(this).data('fleet-time') - (serverTime.getTime() - startTime) / 1000;
			if(s <= 0) {
				$(this).text('-');
			} else {
				$(this).text(GetRestTimeFormat(s));
			}
		})
	}, 1000);

	window.setInterval(updateOverviewTimers, 1000);
	updateOverviewTimers();
});
