/**
 * Techtree expand/collapse — injects category bodies on first open.
 * Config: #techtree-data JSON
 *
 * Filter tabs (Start here / All / category) stay in this file so #611
 * requirement-link edits to buildItem can merge independently.
 */
(function (root) {
	'use strict';

	var RANGES = {
		0: [1, 99],
		100: [101, 199],
		200: [201, 299],
		400: [401, 499],
		500: [501, 599],
		600: [601, 699]
	};

	function readConfig() {
		var tag = document.getElementById('techtree-data');
		if (!tag) return null;
		try {
			return JSON.parse(tag.textContent);
		} catch (e) {
			return null;
		}
	}

	function buildItem(cfg, elementId, reqList) {
		var wrap = document.createElement('div');
		wrap.className = 'techi';
		wrap.id = 'h' + elementId;

		var name = document.createElement('span');
		name.style.cssText = 'max-width:42%;display:inline-block';
		var nameLink = document.createElement('a');
		nameLink.href = '#';
		nameLink.textContent = cfg.names[elementId] || ('#' + elementId);
		nameLink.onclick = function () { return Dialog.info(elementId); };
		name.appendChild(nameLink);
		wrap.appendChild(name);

		var imgLink = document.createElement('a');
		imgLink.href = '#';
		imgLink.onclick = function () { return Dialog.info(elementId); };
		var img = document.createElement('img');
		img.width = 89;
		img.alt = '';
		img.loading = 'lazy';
		img.setAttribute('data-src', cfg.dpath + 'gebaeude/' + elementId + '.' + (cfg.ext[elementId] || 'gif'));
		imgLink.appendChild(img);
		wrap.appendChild(imgLink);
		wrap.appendChild(document.createElement('br'));
		wrap.appendChild(document.createTextNode(cfg.ttRequirements + ' '));
		wrap.appendChild(document.createElement('br'));

		var keys = Object.keys(reqList || {});
		keys.forEach(function (requireId, idx) {
			var need = reqList[requireId];
			var a = document.createElement('a');
			a.href = '#';
			a.onclick = function () { return Dialog.info(parseInt(requireId, 10)); };
			var span = document.createElement('span');
			span.style.color = (need.own < need.count) ? '#ffd600' : 'lime';
			span.textContent = (cfg.names[requireId] || ('#' + requireId))
				+ ' (' + cfg.ttLvl + ' ' + need.own + '/' + need.count + ')';
			a.appendChild(span);
			wrap.appendChild(a);
			if (idx < keys.length - 1) {
				wrap.appendChild(document.createElement('br'));
			}
		});

		return wrap;
	}

	function hasItem(items, id) {
		return Object.prototype.hasOwnProperty.call(items, String(id))
			|| Object.prototype.hasOwnProperty.call(items, id);
	}

	function categoryIds(cfg, catId) {
		var items = cfg.items || {};
		var raw = cfg.order && (cfg.order[String(catId)] || cfg.order[catId]);
		if (Array.isArray(raw) && raw.length) {
			return raw.filter(function (id) {
				return hasItem(items, id);
			});
		}
		var range = RANGES[catId];
		if (!range) return [];
		var ids = [];
		for (var id = range[0]; id <= range[1]; id++) {
			if (hasItem(items, id)) {
				ids.push(id);
			}
		}
		return ids;
	}

	function starterSet(cfg) {
		var ids = cfg && Array.isArray(cfg.starterIds) ? cfg.starterIds : [];
		var set = {};
		ids.forEach(function (id) {
			set[String(id)] = true;
		});
		return set;
	}

	function isStarter(cfg, id) {
		var set = starterSet(cfg);
		if (Object.keys(set).length === 0) {
			return true;
		}
		return !!set[String(id)];
	}

	function ensureCategory(cfg, catId) {
		var body = document.getElementById('body' + catId);
		if (!body || body.dataset.filled === '1') return;
		body.dataset.filled = '1';
		var frag = document.createDocumentFragment();
		var items = cfg.items || {};
		var ids = categoryIds(cfg, catId);
		ids.forEach(function (id) {
			var reqList = items[String(id)] || items[id];
			var node = buildItem(cfg, id, reqList);
			node.setAttribute('data-element-id', String(id));
			frag.appendChild(node);
		});
		body.appendChild(frag);
	}

	function hydrateVisibleImages(catId) {
		var body = document.getElementById('body' + catId);
		if (!body) return;
		var imgs = body.querySelectorAll('img[data-src]');
		for (var i = 0; i < imgs.length; i++) {
			var img = imgs[i];
			if (!img.getAttribute('src')) {
				img.setAttribute('src', img.getAttribute('data-src'));
			}
		}
	}

	function setOpen(catId, open) {
		var header = document.getElementById(String(catId));
		var body = document.getElementById('body' + catId);
		if (header) {
			if (open) {
				header.classList.add('is-open');
			} else {
				header.classList.remove('is-open');
			}
		}
		if (body) {
			if (open) {
				body.classList.add('is-open');
			} else {
				body.classList.remove('is-open');
			}
		}
		if (open) {
			hydrateVisibleImages(catId);
		}
	}

	function bindCategory(cfg, catId) {
		var header = document.getElementById(String(catId));
		if (!header) return;

		header.addEventListener('click', function (e) {
			if (e) {
				e.preventDefault();
			}
			var open = !header.classList.contains('is-open');
			if (open) {
				ensureCategory(cfg, catId);
			}
			setOpen(catId, open);
		});
	}

	function applyItemFilter(cfg, filter) {
		var bodies = document.querySelectorAll('.techtree-body .techi');
		for (var i = 0; i < bodies.length; i++) {
			var el = bodies[i];
			var id = el.getAttribute('data-element-id');
			var hide = filter === 'start' && !isStarter(cfg, id);
			if (hide) {
				el.classList.add('is-filtered-out');
			} else {
				el.classList.remove('is-filtered-out');
			}
		}
	}

	function applyFilter(cfg, filter) {
		var catIds = Object.keys(RANGES);
		var startOnly = filter === 'start';
		var all = filter === 'all';

		catIds.forEach(function (catId) {
			var ids = categoryIds(cfg, catId);
			var visible = ids.filter(function (id) {
				return !startOnly || isStarter(cfg, id);
			});
			var openThis = all || startOnly ? visible.length > 0 : String(filter) === String(catId);
			if (openThis) {
				ensureCategory(cfg, catId);
			}
			setOpen(catId, openThis);
		});
		applyItemFilter(cfg, startOnly ? 'start' : 'all');
	}

	function setSelectedTab(filter) {
		var tabs = document.querySelectorAll('[data-techtree-filter]');
		for (var i = 0; i < tabs.length; i++) {
			var tab = tabs[i];
			var on = String(tab.getAttribute('data-techtree-filter')) === String(filter);
			if (on) {
				tab.classList.add('selected');
			} else {
				tab.classList.remove('selected');
			}
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
		}
	}

	function bindFilters(cfg) {
		var tabs = document.querySelectorAll('[data-techtree-filter]');
		for (var i = 0; i < tabs.length; i++) {
			tabs[i].addEventListener('click', function (e) {
				if (e) {
					e.preventDefault();
				}
				var filter = this.getAttribute('data-techtree-filter') || 'start';
				setSelectedTab(filter);
				applyFilter(cfg, filter);
			});
		}
	}

	function markTechTreeSeen(cfg) {
		var name = (cfg && cfg.seenCookie) || 'hn_techtree_seen';
		if (root.HiveNovaTechTreeNudge && typeof root.HiveNovaTechTreeNudge.markSeen === 'function') {
			root.HiveNovaTechTreeNudge.markSeen(name);
			return;
		}
		document.cookie = name + '=1; path=/; max-age=31536000; SameSite=Lax';
	}

	function boot() {
		var cfg = readConfig();
		if (!cfg) return;

		Object.keys(RANGES).forEach(function (catId) {
			bindCategory(cfg, catId);
		});
		bindFilters(cfg);
		var filter = cfg.defaultFilter || 'start';
		setSelectedTab(filter);
		applyFilter(cfg, filter);
		markTechTreeSeen(cfg);
	}

	var api = {
		readConfig: readConfig,
		isStarter: isStarter,
		applyFilter: applyFilter,
		setSelectedTab: setSelectedTab,
		markTechTreeSeen: markTechTreeSeen,
		boot: boot
	};

	root.HiveNovaTechTree = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', boot);
		} else {
			boot();
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
