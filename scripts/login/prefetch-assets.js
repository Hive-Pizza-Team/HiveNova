/**
 * Warm browser cache for in-game theme images while the user is on login.
 * Reads #prefetch-assets-data JSON; skips Save-Data / 2G; runs after load + idle.
 */
(function (root) {
	'use strict';

	function shouldPrefetch(nav) {
		if (!nav || !nav.connection) {
			return true;
		}
		if (nav.connection.saveData) {
			return false;
		}
		var type = nav.connection.effectiveType;
		if (type === 'slow-2g' || type === '2g') {
			return false;
		}
		return true;
	}

	function prefetchUrl(url) {
		return new Promise(function (resolve) {
			var img = new Image();
			img.onload = img.onerror = function () {
				resolve(url);
			};
			img.src = url;
		});
	}

	function runQueue(urls, concurrency, prefetchFn) {
		concurrency = Math.max(1, concurrency | 0);
		prefetchFn = prefetchFn || prefetchUrl;
		var index = 0;
		var active = 0;
		var done = 0;
		var total = urls.length;

		return new Promise(function (resolve) {
			if (!total) {
				resolve({ loaded: 0 });
				return;
			}

			function pump() {
				while (active < concurrency && index < total) {
					var url = urls[index++];
					active++;
					prefetchFn(url).then(function () {
						active--;
						done++;
						if (done >= total) {
							resolve({ loaded: done });
						} else {
							pump();
						}
					});
				}
			}

			pump();
		});
	}

	function whenIdle(fn, timeoutMs) {
		if (typeof requestIdleCallback === 'function') {
			requestIdleCallback(fn, { timeout: timeoutMs || 3000 });
		} else {
			setTimeout(fn, 0);
		}
	}

	function startPrefetch(urls, options) {
		options = options || {};
		var nav = options.navigator || (typeof navigator !== 'undefined' ? navigator : null);
		var concurrency = options.concurrency != null ? options.concurrency : 4;
		var delayMs = options.delayMs != null ? options.delayMs : 1500;
		var win = options.windowObj || (typeof window !== 'undefined' ? window : null);
		var doc = options.documentObj || (typeof document !== 'undefined' ? document : null);
		var prefetchFn = options.prefetchFn || prefetchUrl;
		var scheduleIdle = options.whenIdle || whenIdle;

		if (!shouldPrefetch(nav) || !urls || !urls.length) {
			return Promise.resolve({ skipped: true, loaded: 0 });
		}

		return new Promise(function (resolve) {
			function kick() {
				setTimeout(function () {
					scheduleIdle(function () {
						runQueue(urls, concurrency, prefetchFn).then(resolve);
					}, options.idleTimeoutMs || 3000);
				}, delayMs);
			}

			if (!doc || doc.readyState === 'complete') {
				kick();
			} else if (win) {
				win.addEventListener('load', kick);
			} else {
				kick();
			}
		});
	}

	function readUrlsFromDom(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return [];
		}
		var tag = doc.getElementById('prefetch-assets-data');
		if (!tag || !tag.textContent) {
			return [];
		}
		try {
			var parsed = JSON.parse(tag.textContent);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function boot() {
		startPrefetch(readUrlsFromDom());
	}

	var api = {
		shouldPrefetch: shouldPrefetch,
		prefetchUrl: prefetchUrl,
		runQueue: runQueue,
		startPrefetch: startPrefetch,
		readUrlsFromDom: readUrlsFromDom,
		boot: boot
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else {
		root.HiveNovaAssetPrefetch = api;
		if (typeof document !== 'undefined') {
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', boot);
			} else {
				boot();
			}
		}
	}
})(typeof globalThis !== 'undefined' ? globalThis : this);
