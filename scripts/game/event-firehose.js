(function (root) {
	'use strict';

	var POLL_MS = 20000;
	var MAX_ROWS = 50;

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

	function parseEvents(bodyText) {
		var data = JSON.parse(bodyText);
		if (!data || !Array.isArray(data.events)) {
			return null;
		}
		return data.events;
	}

	function existingIds(list) {
		var ids = {};
		if (!list) {
			return ids;
		}
		var rows = list.querySelectorAll('[data-event-id]');
		for (var i = 0; i < rows.length; i++) {
			ids[rows[i].getAttribute('data-event-id')] = true;
		}
		return ids;
	}

	function maxId(list) {
		var max = 0;
		if (!list) {
			return max;
		}
		var rows = list.querySelectorAll('[data-event-id]');
		for (var i = 0; i < rows.length; i++) {
			var n = parseInt(rows[i].getAttribute('data-event-id'), 10);
			if (!isNaN(n) && n > max) {
				max = n;
			}
		}
		return max;
	}

	function renderRow(event) {
		var tr = createElement('tr');
		tr.setAttribute('data-event-id', String(event.id));
		['time', 'headline', 'size', 'outcome'].forEach(function (key) {
			var td = createElement('td');
			var value = event[key];
			if ((value == null || value === '') && key === 'headline') {
				value = event.eventType;
			}
			td.textContent = value == null ? '' : String(value);
			tr.appendChild(td);
		});
		return tr;
	}

	function createElement(tag) {
		if (typeof document !== 'undefined' && document.createElement) {
			return document.createElement(tag);
		}
		var node = {
			children: [],
			attrs: {},
			textContent: '',
			setAttribute: function (name, value) {
				this.attrs[name] = value;
			},
			getAttribute: function (name) {
				return this.attrs[name];
			},
			appendChild: function (child) {
				this.children.push(child);
			}
		};
		return node;
	}

	function mergeEvents(list, events) {
		if (!list || !events || !events.length) {
			return;
		}
		var seen = existingIds(list);
		var empty = list.querySelector('.event-firehose-empty');
		if (empty) {
			empty.parentNode.removeChild(empty);
		}
		var added = 0;
		for (var i = events.length - 1; i >= 0; i--) {
			var event = events[i];
			if (!event || typeof event.id === 'undefined') {
				continue;
			}
			var id = String(event.id);
			if (seen[id]) {
				continue;
			}
			seen[id] = true;
			list.insertBefore(renderRow(event), list.firstChild);
			added += 1;
		}
		if (!added) {
			return;
		}
		var rows = list.querySelectorAll('[data-event-id]');
		for (var j = MAX_ROWS; j < rows.length; j++) {
			rows[j].parentNode.removeChild(rows[j]);
		}
	}

	function createPoller(options) {
		var list = options.list;
		var fetchFn = options.fetchFn || fetch;
		var hiddenFn = options.hiddenFn || function () {
			return typeof document !== 'undefined' && document.hidden;
		};
		var intervalMs = options.intervalMs || POLL_MS;
		var baseUrl = options.url || 'game.php?page=eventFirehose&ajax=1';
		var timer = null;
		var stopped = false;

		function pollUrl() {
			var since = maxId(list);
			if (since > 0) {
				return baseUrl + (baseUrl.indexOf('?') === -1 ? '?' : '&') + 'sinceId=' + since;
			}
			return baseUrl;
		}

		function poll() {
			if (stopped || hiddenFn()) {
				return Promise.resolve();
			}
			return fetchFn(pollUrl(), { credentials: 'same-origin' }).then(function (response) {
				return response.text().then(function (bodyText) {
					if (!responseLooksLikeJson(response, bodyText)) {
						stop();
						return;
					}
					try {
						var events = parseEvents(bodyText);
						if (events === null) {
							return;
						}
						mergeEvents(list, events);
					} catch (e) {
						stop();
					}
				});
			}).catch(function () {
				// Keep last list on network error.
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
			isStopped: function () { return stopped; },
			mergeEvents: mergeEvents
		};
	}

	function init(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return null;
		}
		var list = doc.getElementById('event-firehose-list');
		if (!list) {
			return null;
		}
		var poller = createPoller({ list: list });
		if (typeof document !== 'undefined') {
			document.addEventListener('visibilitychange', poller.onVisibility);
		}
		poller.start();
		return poller;
	}

	var api = {
		POLL_MS: POLL_MS,
		responseLooksLikeJson: responseLooksLikeJson,
		parseEvents: parseEvents,
		mergeEvents: mergeEvents,
		createPoller: createPoller,
		init: init
	};

	root.HiveNovaEventFirehose = api;
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
