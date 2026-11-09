$(function () {
	if (!document.getElementById('commander')) {
		return;
	}

	function commanderRoot() {
		return document.getElementById('commander');
	}

	function post(mode, data) {
		return $.ajax({
			url: 'game.php?page=commanderAjax&mode=' + mode + '&ajax=1',
			type: 'POST',
			dataType: 'json',
			data: data
		});
	}

	function errorMessage(xhr) {
		return (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error';
	}

	function creditTopnav(reward) {
		if (!reward || typeof reward !== 'object') {
			return;
		}
		if (window.HiveNovaTopnav && typeof window.HiveNovaTopnav.creditResources === 'function') {
			window.HiveNovaTopnav.creditResources({
				metal: reward.metal || 0,
				crystal: reward.crystal || 0,
				deuterium: reward.deuterium || 0
			});
			return;
		}
		['metal', 'crystal', 'deuterium'].forEach(function (name) {
			var add = parseFloat(reward[name]) || 0;
			if (!add) {
				return;
			}
			$('[id="current_' + name + '"]').each(function () {
				var el = $(this);
				var cur = parseFloat(el.attr('data-real'));
				if (!isFinite(cur)) {
					cur = 0;
				}
				var next = cur + add;
				el.attr('data-real', next);
				if (typeof NumberGetHumanReadable === 'function') {
					if (typeof viewShortlyNumber !== 'undefined' && viewShortlyNumber && typeof shortly_number === 'function') {
						el.html(shortly_number(next));
					} else {
						el.html(NumberGetHumanReadable(next));
					}
				}
			});
		});
	}

	function replaceBriefing(html) {
		var root = commanderRoot();
		if (!root || typeof html !== 'string' || html.indexOf('id="commander"') === -1) {
			return false;
		}
		var wrap = document.createElement('div');
		wrap.innerHTML = html.trim();
		var next = wrap.querySelector('#commander') || wrap.firstElementChild;
		if (!next || !root.parentNode) {
			return false;
		}
		root.parentNode.replaceChild(next, root);
		return true;
	}

	function refreshAfterMutation(data) {
		if (data && data.reward) {
			creditTopnav(data.reward);
		}
		if (data && replaceBriefing(data.html)) {
			return;
		}
		// Soft navigation forces a fresh overview GET; location.reload() can
		// restore a bfcache/stale document on some browsers after AJAX POSTs.
		window.location.assign('game.php?page=overview');
	}

	function mutationFailed(xhr, onFail) {
		if (typeof onFail === 'function') {
			onFail();
		}
		alert(errorMessage(xhr));
	}

	$(document).on('click', '#commander .commander-briefing__toggle', function () {
		var root = commanderRoot();
		if (!root) {
			return;
		}
		var expanded = this.getAttribute('aria-expanded') !== 'false';
		this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		root.classList.toggle('is-collapsed', expanded);
	});

	$(document).on('click', '#commander .commander-briefing__select', function () {
		var root = commanderRoot();
		var btn = $(this);
		if (!root || btn.prop('disabled')) {
			return;
		}
		root.querySelectorAll('.commander-briefing__select').forEach(function (el) {
			el.disabled = true;
		});
		btn.addClass('is-pending');
		post('selectDirective', {
			directive_key: btn.attr('data-key'),
			token: btn.attr('data-token')
		}).done(function (data) {
			if (!data || !data.ok) {
				mutationFailed({ responseJSON: data }, function () {
					root.querySelectorAll('.commander-briefing__select').forEach(function (el) {
						el.disabled = false;
					});
					btn.removeClass('is-pending');
				});
				return;
			}
			refreshAfterMutation(data);
		}).fail(function (xhr) {
			mutationFailed(xhr, function () {
				var live = commanderRoot();
				if (live) {
					live.querySelectorAll('.commander-briefing__select').forEach(function (el) {
						el.disabled = false;
					});
				}
				btn.removeClass('is-pending');
			});
		});
	});

	$(document).on('click', '#commander .commander-briefing__claim', function () {
		var btn = $(this);
		if (btn.prop('disabled')) {
			return;
		}
		btn.prop('disabled', true).addClass('is-pending');
		post('claimReward', { token: btn.attr('data-token') }).done(function (data) {
			if (!data || !data.ok) {
				mutationFailed({ responseJSON: data }, function () {
					btn.prop('disabled', false).removeClass('is-pending');
				});
				return;
			}
			refreshAfterMutation(data);
		}).fail(function (xhr) {
			mutationFailed(xhr, function () {
				btn.prop('disabled', false).removeClass('is-pending');
			});
		});
	});

	$(document).on('click', '#commander .commander-briefing__branch', function () {
		var btn = $(this);
		if (btn.prop('disabled')) {
			return;
		}
		btn.prop('disabled', true);
		post('resolveBranch', {
			fleet_id: btn.attr('data-fleet'),
			branch_key: btn.attr('data-branch'),
			token: btn.attr('data-token')
		}).done(function (data) {
			if (!data || !data.ok) {
				mutationFailed({ responseJSON: data }, function () {
					btn.prop('disabled', false);
				});
				return;
			}
			refreshAfterMutation(data);
		}).fail(function (xhr) {
			mutationFailed(xhr, function () {
				btn.prop('disabled', false);
			});
		});
	});
});
