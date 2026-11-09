/**
 * Keep browser Back useful on in-game pages.
 *
 * Many templates use <a href="#"> for Dialog/AJAX actions. When preventDefault
 * fails (or is omitted), those clicks push same-document hash entries and Back
 * appears to do nothing. Suppress that, and expose helpers so Message AJAX
 * pagination can record real history via pushState/popstate.
 */
(function (root) {
	'use strict';

	var MESSAGE_STATE_KEY = 'hnMessages';

	function isHashOnlyHref(href) {
		if (href == null) {
			return true;
		}
		var value = String(href).trim();
		return value === '' || value === '#';
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
		if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button) {
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

	function messagesHistoryUrl(messcat, site) {
		var url = 'game.php?page=messages&messcat=' + encodeURIComponent(messcat);
		var page = parseInt(site, 10);
		if (!isNaN(page) && page > 1) {
			url += '&site=' + encodeURIComponent(page);
		}
		return url;
	}

	function shouldReplaceMessagesHistory(currentState) {
		return !(currentState && currentState[MESSAGE_STATE_KEY]);
	}

	function buildMessagesHistoryState(messcat, site) {
		var state = {};
		state[MESSAGE_STATE_KEY] = true;
		state.messcat = parseInt(messcat, 10) || 0;
		state.site = parseInt(site, 10) || 1;
		return state;
	}

	function recordMessagesHistory(historyObj, messcat, site) {
		if (!historyObj || typeof historyObj.pushState !== 'function') {
			return false;
		}
		var state = buildMessagesHistoryState(messcat, site);
		var url = messagesHistoryUrl(state.messcat, state.site);
		if (shouldReplaceMessagesHistory(historyObj.state)) {
			if (typeof historyObj.replaceState === 'function') {
				historyObj.replaceState(state, '', url);
			}
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

	var api = {
		MESSAGE_STATE_KEY: MESSAGE_STATE_KEY,
		isHashOnlyHref: isHashOnlyHref,
		suppressHashOnlyNavigation: suppressHashOnlyNavigation,
		installHashGuard: installHashGuard,
		messagesHistoryUrl: messagesHistoryUrl,
		shouldReplaceMessagesHistory: shouldReplaceMessagesHistory,
		buildMessagesHistoryState: buildMessagesHistoryState,
		recordMessagesHistory: recordMessagesHistory,
		messagesStateFromPopstate: messagesStateFromPopstate
	};

	root.HiveNovaHistoryNav = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		installHashGuard(document);
	}
})(typeof window !== 'undefined' ? window : globalThis);
