/**
 * Battle simulator — ACS slot tabs + column reset.
 * Tabs: #tabs (via HiveNovaSimpleTabs). Requires simple-tabs.js first.
 */
(function (root) {
	'use strict';

	function add() {
		var form = document.getElementById('form');
		if (!form) return false;
		form.setAttribute('action', 'game.php?page=battleSimulator&action=moreslots');
		form.setAttribute('method', 'POST');
		form.submit();
		return true;
	}

	function check() {
		$.post('game.php?page=battleSimulator&mode=send', $('#form').serialize(), function (data) {
			try {
				data = $.parseJSON(data);
				window.open('game.php?page=raport&raport=' + data, '_top').focus();
			} catch (e) {
				Dialog.alert(data);
				Dialog.alert('game.php?page=raport&raport=' + data);
				return false;
			}
		});
		return true;
	}

	function resetColumn(button) {
		var cell = button.parentElement;
		var row = cell && cell.parentElement;
		if (!cell || !row) return;
		var index = Array.prototype.indexOf.call(row.children, cell);
		if (index < 0) return;

		var next = row.nextElementSibling;
		while (next) {
			if (next.tagName === 'TR') {
				var targetCell = next.children[index];
				if (targetCell) {
					var inputs = targetCell.querySelectorAll('input');
					for (var i = 0; i < inputs.length; i++) {
						inputs[i].value = '0';
					}
				}
			}
			next = next.nextElementSibling;
		}
	}

	function initTabs(doc) {
		var tabs = (doc || document).getElementById('tabs');
		if (!tabs || !root.HiveNovaSimpleTabs) {
			return null;
		}
		return root.HiveNovaSimpleTabs.init(tabs, { active: 0 });
	}

	function bindReset(rootEl) {
		if (!rootEl) return;
		rootEl.addEventListener('click', function (e) {
			var btn = e.target && e.target.closest ? e.target.closest('button.reset') : null;
			if (!btn || !rootEl.contains(btn)) return;
			e.preventDefault();
			resetColumn(btn);
		});
	}

	root.add = add;
	root.check = check;

	var api = {
		resetColumn: resetColumn,
		initTabs: initTabs,
		bindReset: bindReset
	};
	root.HiveNovaBattleSim = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		function boot() {
			var tabs = initTabs(document);
			bindReset(document.getElementById('tabs') || document);
			return tabs;
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot);
		} else {
			boot();
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
