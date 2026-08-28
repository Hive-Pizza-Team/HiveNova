/**
 * Imperium matrix sections — inject build/fleet/defense/missile/tech rows on expand.
 * Config: #empire-matrix-data JSON
 */
(function ($) {
	'use strict';

	var SECTIONS = ['build', 'fleet', 'defense', 'missiles', 'tech'];

	function readConfig() {
		var tag = document.getElementById('empire-matrix-data');
		if (!tag) return null;
		try {
			return JSON.parse(tag.textContent);
		} catch (e) {
			return null;
		}
	}

	function fmtNumber(n) {
		if (typeof window.shortly_number === 'function') {
			return window.shortly_number(n);
		}
		return String(n);
	}

	function renderSection(cfg, section) {
		var host = document.getElementById('empire-' + section);
		if (!host || host.dataset.filled === '1') return;
		host.dataset.filled = '1';

		var rows = (cfg.sections && cfg.sections[section]) || [];
		var frag = document.createDocumentFragment();

		rows.forEach(function (row) {
			var tr = document.createElement('tr');

			var nameTd = document.createElement('td');
			var nameLink = document.createElement('a');
			nameLink.href = '#';
			nameLink.textContent = row.name;
			nameLink.onclick = function () { return Dialog.info(row.id); };
			nameTd.appendChild(nameLink);
			tr.appendChild(nameTd);

			var totalTd = document.createElement('td');
			totalTd.textContent = fmtNumber(row.total);
			tr.appendChild(totalTd);

			if (section === 'tech') {
				var spanTd = document.createElement('td');
				spanTd.colSpan = Math.max(1, cfg.colspan - 2);
				spanTd.textContent = fmtNumber(row.total);
				tr.appendChild(spanTd);
			} else {
				Object.keys(row.values || {}).forEach(function (planetId) {
					var td = document.createElement('td');
					td.textContent = fmtNumber(row.values[planetId]);
					tr.appendChild(td);
				});
			}

			frag.appendChild(tr);
		});

		host.appendChild(frag);
	}

	$(function () {
		var cfg = readConfig();
		if (!cfg) return;

		SECTIONS.forEach(function (section) {
			var details = document.getElementById('empire-details-' + section);
			if (!details) return;
			details.addEventListener('toggle', function () {
				if (details.open) {
					renderSection(cfg, section);
				}
			});
		});
	});
})(jQuery);
