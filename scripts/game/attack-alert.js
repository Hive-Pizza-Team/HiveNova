(function (root) {
	'use strict';

	var POLL_MS = 20000;

	function applyCount(banner, count) {
		if (!banner) {
			return;
		}
		var n = parseInt(count, 10);
		if (isNaN(n) || n < 0) {
			return;
		}
		banner.setAttribute('data-count', String(n));
		var countEl = banner.querySelector('[data-attack-alert-count]');
		if (countEl) {
			countEl.textContent = n > 0 ? ' (' + n + ')' : '';
		}
		if (n > 0) {
			banner.removeAttribute('hidden');
		} else {
			banner.setAttribute('hidden', 'hidden');
		}
	}

	function responseLooksLikeJson(response, bodyText) {
		if (!response || !response.ok) {
			return false;
		}
		var url = response.url || '';
		if (url.indexOf('index.php') !== -1) {
			return false;
		}
		var trimmed = (bodyText || '').replace(/^\s+/, '');
		return trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[';
	}

	function parseCount(bodyText) {
		var data = JSON.parse(bodyText);
		if (!data || typeof data.count === 'undefined') {
			return null;
		}
		return data.count;
	}

	function createPoller(options) {
		var banner = options.banner;
		var fetchFn = options.fetchFn || fetch;
		var hiddenFn = options.hiddenFn || function () {
			return typeof document !== 'undefined' && document.hidden;
		};
		var intervalMs = options.intervalMs || POLL_MS;
		var url = options.url || 'game.php?page=attackAlert&ajax=1';
		var timer = null;
		var stopped = false;

		function poll() {
			if (stopped || hiddenFn()) {
				return Promise.resolve();
			}
			return fetchFn(url, { credentials: 'same-origin' }).then(function (response) {
				return response.text().then(function (bodyText) {
					if (!responseLooksLikeJson(response, bodyText)) {
						stop();
						return;
					}
					try {
						var count = parseCount(bodyText);
						if (count === null) {
							return;
						}
						applyCount(banner, count);
					} catch (e) {
						stop();
					}
				});
			}).catch(function () {
				// Keep last banner state on network error.
			});
		}

		function start() {
			if (stopped) {
				return;
			}
			poll();
			if (timer) {
				clearInterval(timer);
			}
			timer = setInterval(poll, intervalMs);
		}

		function stop() {
			stopped = true;
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
		}

		function onVisibility() {
			if (stopped) {
				return;
			}
			if (hiddenFn()) {
				if (timer) {
					clearInterval(timer);
					timer = null;
				}
				return;
			}
			start();
		}

		return {
			poll: poll,
			start: start,
			stop: stop,
			onVisibility: onVisibility,
			isStopped: function () { return stopped; }
		};
	}

	function init(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return null;
		}
		var banner = doc.getElementById('attack-alert');
		if (!banner) {
			return null;
		}
		var poller = createPoller({ banner: banner });
		if (typeof document !== 'undefined') {
			document.addEventListener('visibilitychange', poller.onVisibility);
		}
		poller.start();
		return poller;
	}

	var api = {
		POLL_MS: POLL_MS,
		applyCount: applyCount,
		responseLooksLikeJson: responseLooksLikeJson,
		parseCount: parseCount,
		createPoller: createPoller,
		init: init
	};

	root.HiveNovaAttackAlert = api;
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
