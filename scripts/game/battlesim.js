/**
 * Battle simulator — ACS slot tabs + column reset.
 * Tabs: #tabs (via HiveNovaSimpleTabs). Requires simple-tabs.js first.
 * Extra ACS slots are cloned client-side (no full page POST).
 */
(function (root) {
	'use strict';

	var tabsApi = null;

	function panelCount(tabs) {
		return tabs.querySelectorAll(':scope > div[id^="tabs-"]').length;
	}

	function stripDefenseColumn(panel) {
		var defenseCell = panel.querySelector('td.transparent[style*="width"]');
		if (defenseCell && defenseCell.parentElement) {
			defenseCell.parentElement.removeChild(defenseCell);
		}
	}

	function renumberSlot(panel, index) {
		panel.id = 'tabs-' + index;
		var inputs = panel.querySelectorAll('input[name^="battleinput["]');
		for (var i = 0; i < inputs.length; i++) {
			inputs[i].name = inputs[i].name.replace(/^battleinput\[\d+]/, 'battleinput[' + index + ']');
			inputs[i].value = '0';
		}
		if (index > 0) {
			stripDefenseColumn(panel);
		}
	}

	function add() {
		var tabs = document.getElementById('tabs');
		var form = document.getElementById('form');
		if (!tabs || !form) {
			return false;
		}

		var source = document.getElementById('tabs-0');
		var nav = tabs.querySelector(':scope > ul');
		if (!source || !nav) {
			return false;
		}

		var nextIndex = panelCount(tabs);
		if (nextIndex >= 10) {
			return false;
		}

		var firstLink = nav.querySelector('a');
		var baseLabel = firstLink ? (firstLink.textContent || '').replace(/\s*\d+\s*$/, '') : 'ACS';
		baseLabel = baseLabel.replace(/\s+$/, '');

		var clone = source.cloneNode(true);
		renumberSlot(clone, nextIndex);
		clone.classList.add('simple-tabs__panel');
		clone.setAttribute('role', 'tabpanel');
		clone.hidden = true;
		clone.style.display = 'none';
		clone.classList.remove('is-active');

		var li = document.createElement('li');
		li.setAttribute('role', 'presentation');
		var a = document.createElement('a');
		a.href = '#tabs-' + nextIndex;
		a.textContent = baseLabel + ' ' + (nextIndex + 1);
		a.setAttribute('role', 'tab');
		a.setAttribute('aria-selected', 'false');
		li.appendChild(a);
		nav.appendChild(li);
		tabs.appendChild(clone);

		var slotsInput = document.getElementById('slots');
		if (slotsInput) {
			slotsInput.value = String(nextIndex + 2);
		}

		if (tabsApi && typeof tabsApi.activate === 'function') {
			tabsApi.activate(nextIndex);
		} else if (root.HiveNovaSimpleTabs) {
			tabsApi = root.HiveNovaSimpleTabs.init(tabs, { active: nextIndex });
		}
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
		bindReset: bindReset,
		addSlot: add
	};
	root.HiveNovaBattleSim = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		function boot() {
			tabsApi = initTabs(document);
			bindReset(document.getElementById('tabs') || document);
			return tabsApi;
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot);
		} else {
			boot();
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
