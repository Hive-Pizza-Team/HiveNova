/**
 * Break Fancybox iframe links out to the top window (playercard homeplanet,
 * alliance info, alliance-apply homeplanet). target="_top" stays as a fallback;
 * some Fancybox iframe navigations still painted Galaxy inside the frame (#704).
 *
 * Templates use data-top-nav instead of inline onclick braces, which Smarty
 * treats as tags (#748).
 */
(function (root) {
	'use strict';

	function navigateTopIfFramed(win, href) {
		win = win || (typeof window !== 'undefined' ? window : null);
		if (!win || !win.top || win.top === win) {
			return false;
		}
		try {
			win.top.location.href = href;
			return true;
		} catch (err) {
			return false;
		}
	}

	function closestTopNavAnchor(el) {
		if (!el) {
			return null;
		}
		if (typeof el.closest === 'function') {
			return el.closest('a[data-top-nav]');
		}
		while (el && el.nodeType === 1) {
			if (el.tagName === 'A' && el.hasAttribute && el.hasAttribute('data-top-nav')) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function installTopNav(doc, win) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		win = win || (typeof window !== 'undefined' ? window : null);
		if (!doc || typeof doc.addEventListener !== 'function') {
			return;
		}
		doc.addEventListener('click', function (event) {
			var anchor = closestTopNavAnchor(event.target);
			if (!anchor) {
				return;
			}
			if (navigateTopIfFramed(win, anchor.href)) {
				if (typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
			}
		});
	}

	var api = {
		navigateTopIfFramed: navigateTopIfFramed,
		closestTopNavAnchor: closestTopNavAnchor,
		installTopNav: installTopNav
	};

	root.HiveNovaTopNav = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		installTopNav(document, root);
	}
})(typeof window !== 'undefined' ? window : globalThis);
