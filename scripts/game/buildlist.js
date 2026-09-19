var resttime	= 0;
var time		= 0;
var endtime		= 0;
var interval	= 0;
var buildname	= "";

function toggleBuildlistExpanded(root, button) {
	if (!root || !root.classList) {
		return false;
	}
	var expanded = root.classList.contains('is-expanded');
	var next = !expanded;
	root.classList.toggle('is-expanded', next);
	if (button) {
		if (button.setAttribute) {
			button.setAttribute('aria-expanded', next ? 'true' : 'false');
		}
		var more = button.getAttribute ? button.getAttribute('data-label-more') : null;
		var less = button.getAttribute ? button.getAttribute('data-label-less') : null;
		if (more && less) {
			button.textContent = next ? less : more;
		}
	}
	return next;
}

function bindBuildlistMoreToggle(root, button) {
	if (!root || !button || typeof button.addEventListener !== 'function') {
		return false;
	}
	button.addEventListener('click', function () {
		toggleBuildlistExpanded(root, button);
	});
	return true;
}

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

	if ($('#total-queue-time').length) {
		var timers = $('.timer');
		var firstEndTs = $(timers[0]).data('time');
		var lastEndTs  = $(timers[timers.length - 1]).data('time');
		var totalInitial  = resttime + (lastEndTs - firstEndTs);
		var elapsed       = Math.round((serverTime.getTime() - startTime) / 1000);
		var totalRemaining = Math.max(0, totalInitial - elapsed);
		$('#total-queue-time').text(totalRemaining > 0 ? GetRestTimeFormat(totalRemaining) : Ready);
	}
}

if (typeof $ === 'function') {
$(document).ready(function() {
	time		= $('#time').data('time');
	resttime	= $('#progressbar').data('time');
	endtime		= $('.timer:first').data('time');
	buildname	= $('.buildlist > table > tbody > tr > td:first').text().replace(/[0-9]+\.:/, '').trim();
    interval	= window.setInterval(Buildlist, 1000);

	window.setTimeout(function () {
		initQueueProgressBar('#progressbar', time, resttime);
	}, 5);


	Buildlist();

	bindBuildlistMoreToggle(document.getElementById('buildlist'), document.getElementById('buildlistMoreToggle'));
});
}

var HiveNovaBuildlist = {
	toggleExpanded: toggleBuildlistExpanded,
	bindMoreToggle: bindBuildlistMoreToggle
};

if (typeof module !== 'undefined' && module.exports) {
	module.exports = HiveNovaBuildlist;
}