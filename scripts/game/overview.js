var overviewTimerReloadPending = false;

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
		var wasPositive = $(this).data('overview-timer-was-positive');
		if (remaining <= 0) {
			$(this).text(Ready);
			// Reload only on a >0 → <=0 transition observed on this element.
			// A shared flag across .timer / .fleets would reload every tick when one
			// fleet row is already due on load while another timer is still running.
			if (wasPositive === true) {
				needsReload = true;
			}
			$(this).data('overview-timer-was-positive', false);
		} else {
			$(this).data('overview-timer-was-positive', true);
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
		var wasPositive = $(this).data('overview-timer-was-positive');
		if (remaining <= 0) {
			$(this).text('-');
			if (wasPositive === true) {
				needsReload = true;
			}
			$(this).data('overview-timer-was-positive', false);
		} else {
			$(this).data('overview-timer-was-positive', true);
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
