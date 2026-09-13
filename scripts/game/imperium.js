/**
 * Imperium matrix sections — render build/fleet/defense/missile/tech on expand.
 * Prefers the JSON embedded on the page; falls back to AJAX.
 */
(function (root) {
	'use strict';

	var SECTIONS = ['build', 'fleet', 'defense', 'missiles', 'tech'];

	function matrixUrl(doc) {
		var tag = doc && doc.getElementById('empire-matrix-config');
		return (tag && tag.getAttribute('data-url')) || 'game.php?page=imperium&mode=matrix&ajax=1';
	}

	function looksLikeJson(text) {
		var trimmed = String(text || '').replace(/^\s+/, '');
		return trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[';
	}

	function parseMatrixPayload(text) {
		if (!looksLikeJson(text)) {
			return null;
		}
		try {
			var data = JSON.parse(text);
			if (!data || typeof data !== 'object' || !data.sections) {
				return null;
			}
			return data;
		} catch (err) {
			return null;
		}
	}

	function readEmbeddedPayload(doc) {
		var tag = doc && doc.getElementById('empire-matrix-config');
		if (!tag) {
			return null;
		}
		return parseMatrixPayload(tag.textContent || '');
	}

	function fmtNumber(n) {
		if (typeof root.shortly_number === 'function') {
			return root.shortly_number(n);
		}
		return String(n);
	}

	function cellAmount(values, planetId) {
		if (!values || typeof values !== 'object') {
			return 0;
		}
		if (Object.prototype.hasOwnProperty.call(values, planetId)) {
			return values[planetId];
		}
		var asNum = Number(planetId);
		if (!isNaN(asNum) && Object.prototype.hasOwnProperty.call(values, asNum)) {
			return values[asNum];
		}
		return 0;
	}

	function renderSection(cfg, section, doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return 0;
		}
		var host = doc.getElementById('empire-' + section);
		if (!host || host.dataset.filled === '1') {
			return 0;
		}
		host.dataset.filled = '1';
		host.textContent = '';

		var rows = (cfg && cfg.sections && cfg.sections[section]) || [];
		var planetIds = (cfg && cfg.planetIds) || [];
		var frag = doc.createDocumentFragment ? doc.createDocumentFragment() : { children: [], appendChild: function (n) { this.children.push(n); return n; } };
		var rendered = 0;

		rows.forEach(function (row) {
			var tr = doc.createElement('tr');

			var nameTd = doc.createElement('td');
			var nameLink = doc.createElement('a');
			nameLink.href = '#';
			nameLink.textContent = row.name;
			nameLink.onclick = function () {
				if (typeof root.Dialog !== 'undefined' && root.Dialog.info) {
					return root.Dialog.info(row.id);
				}
				return false;
			};
			nameTd.appendChild(nameLink);
			tr.appendChild(nameTd);

			var totalTd = doc.createElement('td');
			// shortly_number returns HTML (&nbsp; + unit); textContent would show the entity literally.
			totalTd.innerHTML = fmtNumber(row.total);
			tr.appendChild(totalTd);

			if (section === 'tech') {
				var spanTd = doc.createElement('td');
				spanTd.colSpan = Math.max(1, (cfg.colspan || 3) - 2);
				spanTd.innerHTML = fmtNumber(row.total);
				tr.appendChild(spanTd);
			} else {
				var values = row.values || {};
				planetIds.forEach(function (planetId) {
					var td = doc.createElement('td');
					td.innerHTML = fmtNumber(cellAmount(values, planetId));
					tr.appendChild(td);
				});
			}

			frag.appendChild(tr);
			rendered += 1;
		});

		host.appendChild(frag);
		return rendered;
	}

	function bindEmpireMatrix(doc, opts) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		opts = opts || {};
		if (!doc) {
			return null;
		}

		var embedded = readEmbeddedPayload(doc);
		var fetchFn = opts.fetchFn || (typeof root.fetch === 'function' ? root.fetch.bind(root) : null);
		var matrixPromise = embedded ? Promise.resolve(embedded) : null;

		function loadMatrix() {
			if (matrixPromise) {
				return matrixPromise;
			}
			if (!fetchFn) {
				return Promise.reject(new Error('no fetch'));
			}
			matrixPromise = fetchFn(matrixUrl(doc), { credentials: 'same-origin' })
				.then(function (res) {
					if (!res || !res.ok) {
						throw new Error('matrix ' + (res && res.status));
					}
					return res.text ? res.text() : Promise.resolve('');
				})
				.then(function (text) {
					var parsed = parseMatrixPayload(text);
					if (!parsed) {
						throw new Error('matrix not json');
					}
					return parsed;
				})
				.catch(function (err) {
					matrixPromise = null;
					throw err;
				});
			return matrixPromise;
		}

		function fillOpenSection(section, details) {
			loadMatrix().then(function (cfg) {
				renderSection(cfg, section, doc);
			}).catch(function () {
				if (details) {
					details.open = false;
				}
			});
		}

		SECTIONS.forEach(function (section) {
			var details = doc.getElementById('empire-details-' + section);
			if (!details) {
				return;
			}
			details.addEventListener('toggle', function () {
				if (!details.open) {
					return;
				}
				fillOpenSection(section, details);
			});
			if (details.open) {
				fillOpenSection(section, details);
			}
		});

		return {
			loadMatrix: loadMatrix,
			embedded: embedded
		};
	}

	var api = {
		SECTIONS: SECTIONS,
		matrixUrl: matrixUrl,
		looksLikeJson: looksLikeJson,
		parseMatrixPayload: parseMatrixPayload,
		readEmbeddedPayload: readEmbeddedPayload,
		cellAmount: cellAmount,
		renderSection: renderSection,
		bindEmpireMatrix: bindEmpireMatrix
	};

	root.HiveNovaImperium = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () { bindEmpireMatrix(document); });
		} else {
			bindEmpireMatrix(document);
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
