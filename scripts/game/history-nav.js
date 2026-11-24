/**
 * Keep browser Back useful on in-game pages.
 *
 * Root causes:
 * 1) Templates use <a href="#"> for Dialog/AJAX actions. When preventDefault is
 *    omitted (or fails), those clicks push same-document hash entries and Back
 *    appears to do nothing while the rendered page stays the same.
 * 2) Fancybox iframe dialogs do not participate in history, so Back while a
 *    dialog is open can be a no-op. Push a stub entry on open; popstate closes
 *    the dialog first, then further Back navigates to the previous game page.
 *
 * Message inbox pagination uses real category/side links (shared.messages.pagination.tpl);
 * do not wire AJAX message history here — that path requested a non-existent mode.
 */
(function (root) {
	'use strict';

	var DIALOG_STATE_KEY = 'hnDialog';

	var dialogOpen = false;
	var ignoreNextPopstate = false;
	var popstateBound = false;

	function isHashOnlyHref(href) {
		if (href == null) {
			return true;
		}
		var value = String(href).trim();
		return value === '' || value === '#';
	}

	function shouldStripEmptyHash(href, hash) {
		href = String(href || '');
		hash = String(hash || '');
		return href.slice(-1) === '#' && (hash === '' || hash === '#');
	}

	function stripEmptyHash(loc, hist) {
		loc = loc || (typeof location !== 'undefined' ? location : null);
		hist = hist || (typeof history !== 'undefined' ? history : null);
		if (!loc || !hist || typeof hist.replaceState !== 'function') {
			return false;
		}
		if (!shouldStripEmptyHash(loc.href, loc.hash)) {
			return false;
		}
		var clean = String(loc.href).replace(/#$/, '');
		hist.replaceState(hist.state, '', clean);
		return true;
	}

	function closestAnchor(target) {
		if (!target) {
			return null;
		}
		if (typeof target.closest === 'function') {
			return target.closest('a[href]');
		}
		var node = target;
		while (node && node.nodeType === 1) {
			if (String(node.tagName || '').toLowerCase() === 'a' && node.getAttribute) {
				return node;
			}
			node = node.parentNode;
		}
		return null;
	}

	function suppressHashOnlyNavigation(event) {
		if (!event || event.defaultPrevented) {
			return;
		}
		if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
			return;
		}
		if (typeof event.button === 'number' && event.button !== 0) {
			return;
		}
		var anchor = closestAnchor(event.target);
		if (!anchor) {
			return;
		}
		if (!isHashOnlyHref(anchor.getAttribute('href'))) {
			return;
		}
		event.preventDefault();
	}

	function installHashGuard(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc || typeof doc.addEventListener !== 'function') {
			return false;
		}
		doc.addEventListener('click', suppressHashOnlyNavigation, true);
		return true;
	}

	function closeFancybox() {
		if (typeof root.jQuery === 'function' && root.jQuery.fancybox) {
			root.jQuery.fancybox.close();
			return true;
		}
		return false;
	}

	function recordDialogOpen(historyObj) {
		historyObj = historyObj || (typeof root.history !== 'undefined' ? root.history : null);
		if (!historyObj || typeof historyObj.pushState !== 'function') {
			return false;
		}
		if (dialogOpen) {
			return true;
		}
		var state = {};
		state[DIALOG_STATE_KEY] = true;
		try {
			historyObj.pushState(
				state,
				'',
				root.location && root.location.href ? root.location.href : undefined
			);
		} catch (e) {
			return false;
		}
		dialogOpen = true;
		return true;
	}

	function recordDialogClose(historyObj) {
		historyObj = historyObj || (typeof root.history !== 'undefined' ? root.history : null);
		if (!dialogOpen) {
			return false;
		}
		dialogOpen = false;
		if (!historyObj || typeof historyObj.back !== 'function') {
			return false;
		}
		if (historyObj.state && historyObj.state[DIALOG_STATE_KEY]) {
			ignoreNextPopstate = true;
			historyObj.back();
			return true;
		}
		return false;
	}

	function handlePopstate(event, closer) {
		if (ignoreNextPopstate) {
			ignoreNextPopstate = false;
			return { handled: false, reason: 'ignore' };
		}
		if (!dialogOpen) {
			return { handled: false, reason: 'no-dialog' };
		}
		dialogOpen = false;
		closer = closer || closeFancybox;
		closer();
		return { handled: true, reason: 'close-dialog' };
	}

	function installPopstate(win, closer) {
		win = win || root;
		if (!win || typeof win.addEventListener !== 'function' || popstateBound) {
			return false;
		}
		popstateBound = true;
		win.addEventListener('popstate', function (event) {
			handlePopstate(event, closer);
		});
		return true;
	}

	function _resetForTests() {
		dialogOpen = false;
		ignoreNextPopstate = false;
		popstateBound = false;
	}

	var api = {
		DIALOG_STATE_KEY: DIALOG_STATE_KEY,
		isHashOnlyHref: isHashOnlyHref,
		shouldStripEmptyHash: shouldStripEmptyHash,
		stripEmptyHash: stripEmptyHash,
		suppressHashOnlyNavigation: suppressHashOnlyNavigation,
		installHashGuard: installHashGuard,
		recordDialogOpen: recordDialogOpen,
		recordDialogClose: recordDialogClose,
		handlePopstate: handlePopstate,
		installPopstate: installPopstate,
		_resetForTests: _resetForTests,
		_isDialogOpenForTests: function () { return dialogOpen; }
	};

	root.HiveNovaHistoryNav = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		installHashGuard(document);
		stripEmptyHash();
		installPopstate(root);
	}
})(typeof window !== 'undefined' ? window : globalThis);
