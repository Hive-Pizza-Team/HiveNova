function showGalaxyToast(message, className, durationMs) {
	showGameToast(message, className || 'notify-toast', durationMs || 4500);
}

function showGalaxyFleetAlert(message) {
	showGameToast(message, 'notify-error', 4000);
}

function doit(missionID, planetID) {
	$.getJSON("game.php?page=fleetAjax&ajax=1&mission="+missionID+"&planetID="+planetID, function(data)
	{
		$('#slots').text(data.slots);
		if(typeof data.ships !== "undefined")
		{
			$.each(data.ships, function(elementID, value) {
				$('#elementID'+elementID).text(number_format(value));
			});
		}

		if(data.code != 600) {
			showGalaxyFleetAlert(data.mess);
			return;
		}
		
		var statustable	= $('#fleetstatusrow');
		var messages	= statustable.find("~tr");
		if(messages.length == MaxFleetSetting) {
			messages.filter(':last').remove();
		}
		var element		= $('<td />').attr('colspan', 8).attr('class', 'success').text(data.mess).wrap('<tr />').parent();
		statustable.removeAttr('style').after(element);
	});
}

function galaxy_submit(value) {
	$('#auto').attr('name', value);
	$('#galaxy_form').submit();
}

$(function () {
	if (!window.matchMedia('(max-width: 699px)').matches) {
		return;
	}

	var form = $('#galaxy_form');
	var warning = form.attr('data-fuel-warning');
	if (warning) {
		showGalaxyToast(warning, 'notify-toast', 4500);
	}

	form.find('input[name="galaxy"], input[name="system"]').on('change', function () {
		$('#auto').removeAttr('name');
		form.submit();
	});

	// Mobile: sticky tooltips are omitted server-side when hn_compact=1; strip any leftover.
	if (window.matchMedia('(max-width: 699px)').matches) {
		document.querySelectorAll('[data-tooltip-content]').forEach(function (el) {
			el.removeAttribute('data-tooltip-content');
			el.classList.remove('tooltip_sticky', 'tooltip');
		});
	}
});