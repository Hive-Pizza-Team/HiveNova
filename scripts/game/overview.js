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

	// Fleet event countdowns (expedition EndStay/Return, etc.). Reloading lets
	// FleetHandler run so commander briefing progress and active-expedition
	// rows refresh without a manual extra reload (#455).
	$('.fleets').each(function() {
		var fleetTime = $(this).data('fleet-time');
		if (fleetTime === undefined || fleetTime === null) {
			return;
		}

		var remaining = Math.floor(fleetTime - (serverTime.getTime() - startTime) / 1000);
		if (remaining <= 0) {
			$(this).text('-');
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

function formatSeasonWipeResetAt(closesAt) {
	try {
		return new Date(closesAt * 1000).toLocaleString(undefined, {
			dateStyle: 'medium',
			timeStyle: 'long'
		});
	} catch (e) {
		return new Date(closesAt * 1000).toString();
	}
}

function updateSeasonWipeResetLabels() {
	document.querySelectorAll('.season-wipe-countdown').forEach(function(el) {
		var closes = parseInt(el.getAttribute('data-closes-at'), 10);
		var target = el.querySelector('.season-wipe-reset-at');
		if (!target || !closes) {
			return;
		}
		target.textContent = formatSeasonWipeResetAt(closes);
	});
}

$(document).ready(function()
{
	updateSeasonWipeResetLabels();

	window.setInterval(updateOverviewTimers, 1000);
	updateOverviewTimers();
});
