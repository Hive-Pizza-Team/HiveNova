var resttime	= 0;
var time		= 0;
var endtime		= 0;
var interval	= 0;
var buildname	= "";

function Buildlist() {
	var rest	= resttime - (serverTime.getTime() - startTime) / 1000;
	if (rest <= 0) {
		window.clearInterval(interval);
		$('#time').text(Ready);
		$('#command').remove();
		document.title	= Ready + ' - ' + Gamename;
		window.setTimeout(function() {
			window.location.href = 'game.php?page=buildings';
		}, 1000);
		return;
	}
	document.title = GetRestTimeFormat(rest) + ' - ' + buildname + ' - ' + Gamename;
	
	$('#time').text(GetRestTimeFormat(rest));

	$('.timer').each(function() {
		var endTs = $(this).data('time');
		var remaining = Math.floor(endTs - serverTime.getTime() / 1000);
		if (remaining <= 0) {
			$(this).text(Ready);
		} else {
			$(this).text(GetRestTimeFormat(remaining));
		}
	});

	var timers = $('.timer');
	if (timers.length > 1) {
		var firstEndTs = $(timers[0]).data('time');
		var lastEndTs  = $(timers[timers.length - 1]).data('time');
		var totalInitial  = resttime + (lastEndTs - firstEndTs);
		var elapsed       = Math.round((serverTime.getTime() - startTime) / 1000);
		var totalRemaining = Math.max(0, totalInitial - elapsed);
		$('#total-queue-time').text(totalRemaining > 0 ? GetRestTimeFormat(totalRemaining) : Ready);
	}
}

$(document).ready(function() {
	time		= $('#time').data('time');
	resttime	= $('#progressbar').data('time');
	endtime		= $('.timer:first').data('time');
	buildname	= $('.buildlist > table > tbody > tr > td:first').text().replace(/[0-9]+\.:/, '').trim();
    interval	= window.setInterval(Buildlist, 1000);

	window.setTimeout(function () {
        if(time <= 0) return;

        $('#progressbar').progressbar({
            value: Math.max(100 - (resttime / time) * 100, 0.01)
        });
        $('.ui-progressbar-value').addClass('ui-corner-right').animate({width: "100%"}, resttime * 1000, "linear");
    }, 5);


	Buildlist();
});