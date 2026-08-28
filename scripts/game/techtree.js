/**
 * Techtree expand/collapse — injects category bodies on first open.
 * Config: #techtree-data JSON
 */
(function () {
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

	function ensureCategory(cfg, catId) {
		var body = document.getElementById('body' + catId);
		if (!body || body.dataset.filled === '1') return;
		body.dataset.filled = '1';
		var range = RANGES[catId];
		if (!range) return;
		var frag = document.createDocumentFragment();
		var items = cfg.items || {};
		for (var id = range[0]; id <= range[1]; id++) {
			if (!Object.prototype.hasOwnProperty.call(items, String(id))
				&& !Object.prototype.hasOwnProperty.call(items, id)) {
				continue;
			}
			var reqList = items[String(id)] || items[id];
			frag.appendChild(buildItem(cfg, id, reqList));
		}
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

	function boot() {
		var cfg = readConfig();
		if (!cfg) return;

		Object.keys(RANGES).forEach(function (catId) {
			bindCategory(cfg, catId);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
