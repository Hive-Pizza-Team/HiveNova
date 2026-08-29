/**
 * Imperium matrix sections — fetch + inject build/fleet/defense/missile/tech on expand.
 */
(function ($) {
	'use strict';

	var SECTIONS = ['build', 'fleet', 'defense', 'missiles', 'tech'];
	var matrixPromise = null;

	function matrixUrl() {
		var tag = document.getElementById('empire-matrix-config');
		return (tag && tag.getAttribute('data-url')) || 'game.php?page=imperium&mode=matrix&ajax=1';
	}

	function loadMatrix() {
		if (matrixPromise) {
			return matrixPromise;
		}
		matrixPromise = fetch(matrixUrl(), { credentials: 'same-origin' })
			.then(function (res) {
				if (!res.ok) {
					throw new Error('matrix ' + res.status);
				}
				return res.json();
			})
			.catch(function (err) {
				matrixPromise = null;
				throw err;
			});
		return matrixPromise;
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
		var planetIds = cfg.planetIds || [];
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
				var values = row.values || {};
				planetIds.forEach(function (planetId) {
					var td = document.createElement('td');
					td.textContent = fmtNumber(values[planetId] || 0);
					tr.appendChild(td);
				});
			}

			frag.appendChild(tr);
		});

		host.appendChild(frag);
	}

	$(function () {
		SECTIONS.forEach(function (section) {
			var details = document.getElementById('empire-details-' + section);
			if (!details) return;
			details.addEventListener('toggle', function () {
				if (!details.open) {
					return;
				}
				loadMatrix().then(function (cfg) {
					renderSection(cfg, section);
				}).catch(function () {
					details.open = false;
				});
			});
		});
	});
})(jQuery);
