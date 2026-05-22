function showGalaxyFleetAlert(message) {
	var tip = $('#tooltip');
	tip.stop(true, true);
	tip.html(message)
		.removeClass('tooltip-mobile-active tooltip_sticky_div')
		.addClass('notify notify-error')
		.css({
			position: 'fixed',
			top: '50%',
			left: '50%',
			transform: 'translate(-50%, -50%)',
			right: 'auto',
			bottom: 'auto',
			zIndex: 400,
			maxWidth: 'calc(100vw - 32px)',
			textAlign: 'center'
		})
		.show();
	window.setTimeout(function () {
		tip.fadeOut(400, function () {
			tip.removeClass('notify notify-error').css({ transform: '', zIndex: '' });
		});
	}, 4000);
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