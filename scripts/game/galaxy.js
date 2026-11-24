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

function breakGalaxyOutOfIframe(win) {
	win = win || window;
	if (!win.top || win.top === win) {
		return false;
	}
	try {
		win.top.location.replace(win.location.href);
		return true;
	} catch (err) {
		return false;
	}
}

$(function () {
	// Stats → playercard Fancybox → homeplanet can leave Galaxy painted inside the
	// iframe. Break out immediately; form target=_top remains as a fallback.
	if (breakGalaxyOutOfIframe(window)) {
		return;
	}
	if (window.top && window.top !== window) {
		$('#galaxy_form').attr('target', '_top');
	}

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
});

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		breakGalaxyOutOfIframe: breakGalaxyOutOfIframe
	};
}
