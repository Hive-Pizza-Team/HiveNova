/**
 * Overview planet rename / delete popup.
 * Tabs: #tabs (via HiveNovaSimpleTabs). Requires simple-tabs.js first.
 */
(function (root) {
	'use strict';

	function activeTabFromSearch(search) {
		return String(search || '').indexOf('tab=delete') !== -1 ? 1 : 0;
	}

	function initTabs(doc, locationSearch) {
		var tabs = (doc || document).getElementById('tabs');
		if (!tabs || !root.HiveNovaSimpleTabs) {
			return null;
		}
		return root.HiveNovaSimpleTabs.init(tabs, {
			active: activeTabFromSearch(locationSearch)
		});
	}

	function checkrename() {
		if ($.trim($('#name').val()) == '') {
			return false;
		}
		$.getJSON('game.php?page=overview&mode=rename&name=' + $('#name').val(), function (response) {
			if (!response.error) {
				parent.location.reload();
			} else {
				alert(response.message);
			}
		});
	}

	function checkcancel() {
		var password = $('#password').val();
		if (password == '') {
			return false;
		}
		$.post('game.php?page=overview', { mode: 'delete', password: password }, function (response) {
			if (response.ok) {
				parent.location.reload();
			} else {
				alert(response.message);
			}
		}, 'json');
	}

	root.checkrename = checkrename;
	root.checkcancel = checkcancel;

	var api = {
		activeTabFromSearch: activeTabFromSearch,
		initTabs: initTabs
	};
	root.HiveNovaOverviewActions = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		function boot() {
			initTabs(document, (root.location && root.location.search) || '');
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot);
		} else {
			boot();
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
