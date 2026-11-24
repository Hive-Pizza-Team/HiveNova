'use strict';

/**
 * Hive Keychain detection for extension + mobile in-app browser.
 * The mobile WebView injects window.hive_keychain asynchronously; a sync
 * typeof check on first tap often false-negatives (#434).
 */
(function (root) {
	function getHiveKeychain() {
		var fromWindow = root && root.hive_keychain;
		if (fromWindow && typeof fromWindow === 'object') {
			return fromWindow;
		}
		try {
			if (typeof hive_keychain !== 'undefined' && hive_keychain && typeof hive_keychain === 'object') {
				return hive_keychain;
			}
		} catch (e) {
			/* ignore ReferenceError in strict scopes */
		}
		return null;
	}

	function waitForHiveKeychain(timeoutMs, intervalMs) {
		timeoutMs = typeof timeoutMs === 'number' ? timeoutMs : 2500;
		intervalMs = typeof intervalMs === 'number' ? intervalMs : 100;

		return new Promise(function (resolve) {
			var existing = getHiveKeychain();
			if (existing) {
				resolve(existing);
				return;
			}

			var start = Date.now();
			var timer = setInterval(function () {
				var kc = getHiveKeychain();
				if (kc || Date.now() - start >= timeoutMs) {
					clearInterval(timer);
					resolve(kc);
				}
			}, intervalMs);
		});
	}

	var api = {
		get: getHiveKeychain,
		wait: waitForHiveKeychain,
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	root.HiveNovaKeychain = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
