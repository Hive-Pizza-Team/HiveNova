/**
 * Dismissible first-session Tech Tree nudge.
 * Cookie hn_techtree_seen also set when the Tech Tree page loads.
 */
(function (root) {
	'use strict';

	var COOKIE = 'hn_techtree_seen';
	var MAX_AGE = 31536000;

	function cookieSetter(doc) {
		return function (name, value) {
			if (!doc) {
				return;
			}
			doc.cookie = String(name) + '=' + String(value)
				+ '; path=/; max-age=' + MAX_AGE + '; SameSite=Lax';
		};
	}

	function markSeen(name, setCookieFn) {
		var cookieName = name || COOKIE;
		if (typeof setCookieFn === 'function') {
			setCookieFn(cookieName, '1');
			return;
		}
		if (root.jQuery && typeof root.jQuery.cookie === 'function') {
			root.jQuery.cookie(cookieName, '1', { path: '/', expires: 365 });
			return;
		}
		if (typeof document !== 'undefined') {
			cookieSetter(document)(cookieName, '1');
		}
	}

	function bind(rootEl, markFn) {
		if (!rootEl) {
			return false;
		}
		var dismiss = rootEl.querySelector('[data-techtree-nudge-dismiss]');
		if (!dismiss || typeof dismiss.addEventListener !== 'function') {
			return false;
		}
		dismiss.addEventListener('click', function () {
			(markFn || markSeen)();
			if ('hidden' in rootEl) {
				rootEl.hidden = true;
			} else {
				rootEl.setAttribute('hidden', 'hidden');
			}
		});
		return true;
	}

	function init(doc) {
		var host = doc || (typeof document !== 'undefined' ? document : null);
		if (!host || typeof host.querySelector !== 'function') {
			return;
		}
		bind(host.querySelector('[data-techtree-nudge]'));
	}

	var api = {
		COOKIE: COOKIE,
		markSeen: markSeen,
		bind: bind,
		init: init
	};

	root.HiveNovaTechTreeNudge = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () { init(document); });
		} else {
			init(document);
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
