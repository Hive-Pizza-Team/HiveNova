var overviewTimerReloadPending = false;

function updateOverviewTimers() {
	var needsReload = false;

	$('.timer').each(function() {
		var endTs = $(this).data('time');
		if (endTs === undefined || endTs === null) {
			return;
		}

		var remaining = Math.floor(endTs - serverTime.getTime() / 1000);
		if (remaining <= 0) {
			$(this).text(Ready);
			needsReload = true;
		} else {
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
