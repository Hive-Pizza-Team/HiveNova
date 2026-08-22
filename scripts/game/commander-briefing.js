$(function () {
	var root = document.getElementById('commander');
	if (!root) {
		return;
	}

	$(root).on('click', '.commander-briefing__toggle', function () {
		var expanded = this.getAttribute('aria-expanded') !== 'false';
		this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		$(root).find('.commander-briefing__body').toggle(!expanded);
	});

	function post(mode, data) {
		return $.ajax({
			url: 'game.php?page=commanderAjax&mode=' + mode,
			type: 'POST',
			dataType: 'json',
			data: data
		});
	}

	$(root).on('click', '.commander-briefing__select', function () {
		var btn = $(this);
		post('selectDirective', {
			directive_key: btn.data('key'),
			token: btn.data('token')
		}).done(function () {
			window.location.reload();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error';
			alert(msg);
		});
	});

	$(root).on('click', '.commander-briefing__claim', function () {
		post('claimReward', { token: $(this).data('token') }).done(function () {
			window.location.reload();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error';
			alert(msg);
		});
	});

	$(root).on('click', '.commander-briefing__branch', function () {
		var btn = $(this);
		post('resolveBranch', {
			fleet_id: btn.data('fleet'),
			branch_key: btn.data('branch'),
			token: btn.data('token')
		}).done(function () {
			window.location.reload();
		}).fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error';
			alert(msg);
		});
	});
});
