/**
 * Lightweight tab panels — replaces jQuery UI $.tabs() on game pages.
 * Markup: #root > ul > li > a[href="#panelId"], sibling #panelId panels.
 */
(function (root) {
	'use strict';

	function panelIdFromHref(href) {
		if (!href) return null;
		var hash = href.indexOf('#');
		if (hash === -1) return null;
		var id = href.slice(hash + 1);
		return id || null;
	}

	function getNav(rootEl) {
		if (!rootEl) return null;
		return rootEl.querySelector(':scope > ul') || rootEl.querySelector('ul');
	}

	function getLinks(rootEl) {
		var nav = getNav(rootEl);
		if (!nav) return [];
		return Array.prototype.slice.call(nav.querySelectorAll('a[href^="#"]'));
	}

	function findById(rootEl, id) {
		if (!id) return null;
		var doc = rootEl.ownerDocument || (typeof document !== 'undefined' ? document : null);
		if (doc && typeof doc.getElementById === 'function') {
			var byDoc = doc.getElementById(id);
			if (byDoc) return byDoc;
		}
		if (typeof rootEl.querySelector === 'function') {
			return rootEl.querySelector('[id="' + String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
		}
		return null;
	}

	function getPanels(rootEl, links) {
		var panels = [];
		var seen = {};
		links.forEach(function (link) {
			var id = panelIdFromHref(link.getAttribute('href'));
			if (!id || seen[id]) return;
			var panel = findById(rootEl, id);
			if (panel) {
				seen[id] = true;
				panels.push(panel);
			}
		});
		return panels;
	}

	function setActive(rootEl, activeIndex) {
		var links = getLinks(rootEl);
		if (!links.length) return -1;
		var index = parseInt(activeIndex, 10);
		if (isNaN(index) || index < 0 || index >= links.length) {
			index = 0;
		}
		var panels = getPanels(rootEl, links);
		links.forEach(function (link, i) {
			var li = link.parentElement;
			var on = i === index;
			link.classList.toggle('is-active', on);
			if (li) {
				li.classList.toggle('is-active', on);
				if (on) {
					li.setAttribute('aria-selected', 'true');
				} else {
					li.setAttribute('aria-selected', 'false');
				}
			}
			link.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		panels.forEach(function (panel, i) {
			var on = i === index;
			panel.classList.toggle('is-active', on);
			panel.hidden = !on;
			panel.style.display = on ? '' : 'none';
		});
		rootEl.setAttribute('data-active-tab', String(index));
		return index;
	}

	function init(rootEl, options) {
		options = options || {};
		if (!rootEl) return null;
		rootEl.classList.add('simple-tabs');
		var links = getLinks(rootEl);
		if (!links.length) return null;

		var nav = getNav(rootEl);
		if (nav) {
			nav.classList.add('simple-tabs__nav');
			nav.setAttribute('role', 'tablist');
		}
		links.forEach(function (link) {
			var li = link.parentElement;
			if (li) {
				li.setAttribute('role', 'presentation');
			}
			link.setAttribute('role', 'tab');
		});
		getPanels(rootEl, links).forEach(function (panel) {
			panel.classList.add('simple-tabs__panel');
			panel.setAttribute('role', 'tabpanel');
		});

		var initial = typeof options.active === 'number' ? options.active : 0;
		setActive(rootEl, initial);

		rootEl.addEventListener('click', function (e) {
			var link = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
			if (!link || !rootEl.contains(link)) return;
			var linksNow = getLinks(rootEl);
			var index = linksNow.indexOf(link);
			if (index === -1) return;
			e.preventDefault();
			setActive(rootEl, index);
			if (typeof options.onChange === 'function') {
				options.onChange(index, link);
			}
		});

		return {
			root: rootEl,
			activate: function (index) {
				return setActive(rootEl, index);
			},
			activeIndex: function () {
				return parseInt(rootEl.getAttribute('data-active-tab') || '0', 10) || 0;
			}
		};
	}

	var api = {
		panelIdFromHref: panelIdFromHref,
		setActive: setActive,
		init: init
	};

	root.HiveNovaSimpleTabs = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
})(typeof window !== 'undefined' ? window : globalThis);
