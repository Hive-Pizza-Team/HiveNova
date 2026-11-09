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
 * 3) Message AJAX pagination (legacy) can record category/side history so Back
 *    restores the prior inbox view.
 */
(function (root) {
	'use strict';

	var DIALOG_STATE_KEY = 'hnDialog';
	var MESSAGE_STATE_KEY = 'hnMessages';

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

	function currentMessagesFilter(loc) {
		loc = loc || (typeof location !== 'undefined' ? location : null);
		if (!loc || !loc.search) {
			return '';
		}
		try {
			var filter = new URLSearchParams(loc.search).get('filter');
			return filter ? String(filter) : '';
		} catch (e) {
			return '';
		}
	}

	function messagesHistoryUrl(messcat, site, filter) {
		var url = 'game.php?page=messages&category=' + encodeURIComponent(messcat);
		var page = parseInt(site, 10);
		if (!isNaN(page) && page > 1) {
			url += '&side=' + encodeURIComponent(page);
		}
		if (filter) {
			url += '&filter=' + encodeURIComponent(filter);
		}
		return url;
	}

	function buildMessagesHistoryState(messcat, site) {
		var state = {};
		state[MESSAGE_STATE_KEY] = true;
		state.messcat = parseInt(messcat, 10) || 0;
		state.site = parseInt(site, 10) || 1;
		return state;
	}

	function recordMessagesHistory(historyObj, messcat, site, loc) {
		if (!historyObj || typeof historyObj.pushState !== 'function') {
			return false;
		}
		var state = buildMessagesHistoryState(messcat, site);
		var url = messagesHistoryUrl(state.messcat, state.site, currentMessagesFilter(loc));
		if (historyObj.state && historyObj.state[MESSAGE_STATE_KEY]) {
			historyObj.pushState(state, '', url);
		} else if (typeof historyObj.replaceState === 'function') {
			historyObj.replaceState(state, '', url);
		} else {
			historyObj.pushState(state, '', url);
		}
		return true;
	}

	function messagesStateFromPopstate(event) {
		var state = event && event.state;
		if (!state || !state[MESSAGE_STATE_KEY]) {
			return null;
		}
		return {
			messcat: parseInt(state.messcat, 10) || 0,
			site: parseInt(state.site, 10) || 1
		};
	}

	function seedMessagesHistoryFromLocation(historyObj, loc, fallbackMesscat) {
		loc = loc || (typeof location !== 'undefined' ? location : null);
		if (!loc || !historyObj) {
			return false;
		}
		var params;
		try {
			params = new URLSearchParams(loc.search || '');
		} catch (e) {
			return false;
		}
		var cat = parseInt(params.get('category') || params.get('messcat') || fallbackMesscat || 100, 10);
		var site = parseInt(params.get('side') || params.get('site') || 1, 10);
		return recordMessagesHistory(historyObj, cat, site, loc);
	}

	function _resetForTests() {
		dialogOpen = false;
		ignoreNextPopstate = false;
		popstateBound = false;
	}

	var api = {
		DIALOG_STATE_KEY: DIALOG_STATE_KEY,
		MESSAGE_STATE_KEY: MESSAGE_STATE_KEY,
		isHashOnlyHref: isHashOnlyHref,
		shouldStripEmptyHash: shouldStripEmptyHash,
		stripEmptyHash: stripEmptyHash,
		suppressHashOnlyNavigation: suppressHashOnlyNavigation,
		installHashGuard: installHashGuard,
		recordDialogOpen: recordDialogOpen,
		recordDialogClose: recordDialogClose,
		handlePopstate: handlePopstate,
		installPopstate: installPopstate,
		messagesHistoryUrl: messagesHistoryUrl,
		buildMessagesHistoryState: buildMessagesHistoryState,
		recordMessagesHistory: recordMessagesHistory,
		messagesStateFromPopstate: messagesStateFromPopstate,
		seedMessagesHistoryFromLocation: seedMessagesHistoryFromLocation,
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
