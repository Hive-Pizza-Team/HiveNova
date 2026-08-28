/**
 * Public lobby activity feed poller (no jQuery).
 */
(function () {
	'use strict';

	var POLL_MS = 20000;
	var MAX_ROWS = 25;

	function responseLooksLikeJson(response, bodyText) {
		if (!response || !response.ok) {
			return false;
		}
		var url = response.url || '';
		if (url.indexOf('page=login') !== -1 && url.indexOf('mode=activity') === -1) {
			return false;
		}
		var trimmed = (bodyText || '').replace(/^\s+/, '');
		return trimmed.charAt(0) === '{';
	}

	function maxId(list) {
		var max = 0;
		if (!list) {
			return max;
		}
		list.querySelectorAll('[data-event-id]').forEach(function (row) {
			var n = parseInt(row.getAttribute('data-event-id'), 10);
			if (!isNaN(n) && n > max) {
				max = n;
			}
		});
		return max;
	}

	function existingIds(list) {
		var ids = {};
		list.querySelectorAll('[data-event-id]').forEach(function (row) {
			ids[row.getAttribute('data-event-id')] = true;
		});
		return ids;
	}

	function renderItem(event) {
		var li = document.createElement('li');
		li.className = 'lobby-feed-item is-new';
		li.setAttribute('data-event-id', String(event.id));
		li.setAttribute('data-ts', String(event.ts || 0));

		var uni = document.createElement('span');
		uni.className = 'lobby-feed-uni';
		uni.textContent = event.universe == null ? '' : String(event.universe);

		var size = document.createElement('span');
		size.className = 'lobby-feed-size';
		size.textContent = event.size == null ? '' : String(event.size);

		var type = document.createElement('span');
		type.className = 'lobby-feed-type';
		type.textContent = event.eventType == null ? '' : String(event.eventType);

		var outcome = document.createElement('span');
		outcome.className = 'lobby-feed-outcome';
		outcome.textContent = event.outcome == null ? '' : String(event.outcome);

		var time = document.createElement('time');
		time.className = 'lobby-feed-time';
		time.setAttribute('datetime', String(event.ts || ''));
		time.textContent = event.time == null ? '' : String(event.time);

		li.appendChild(uni);
		li.appendChild(size);
		li.appendChild(type);
		li.appendChild(outcome);
		li.appendChild(time);
		return li;
	}

	function mergeEvents(list, events) {
		if (!list || !events || !events.length) {
			return;
		}
		var seen = existingIds(list);
		var empty = list.querySelector('.lobby-feed-empty');
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
			list.insertBefore(renderItem(event), list.firstChild);
			added += 1;
		}
		if (!added) {
			return;
		}
		var rows = list.querySelectorAll('[data-event-id]');
		for (var j = MAX_ROWS; j < rows.length; j++) {
			rows[j].parentNode.removeChild(rows[j]);
		}
		window.setTimeout(function () {
			list.querySelectorAll('.lobby-feed-item.is-new').forEach(function (row) {
				row.classList.remove('is-new');
			});
		}, 1600);
	}

	function createPoller(options) {
		var list = options.list;
		var fetchFn = options.fetchFn || fetch;
		var pollUrl = options.pollUrl;
		var timer = null;
		var stopped = false;

		function poll() {
			if (stopped || !pollUrl) {
				return;
			}
			var since = maxId(list);
			var url = pollUrl + (pollUrl.indexOf('?') === -1 ? '?' : '&') + 'sinceId=' + encodeURIComponent(String(since));
			fetchFn(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
				.then(function (response) {
					return response.text().then(function (bodyText) {
						return { response: response, bodyText: bodyText };
					});
				})
				.then(function (pack) {
					if (!responseLooksLikeJson(pack.response, pack.bodyText)) {
						return;
					}
					var data = JSON.parse(pack.bodyText);
					if (data && Array.isArray(data.events)) {
						mergeEvents(list, data.events);
					}
				})
				.catch(function () {
					/* keep quiet on lobby — feed is decorative */
				});
		}

		function start() {
			if (timer) {
				return;
			}
			timer = window.setInterval(poll, POLL_MS);
		}

		function stop() {
			stopped = true;
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		return { start: start, stop: stop, poll: poll, mergeEvents: mergeEvents };
	}

	function boot(doc) {
		doc = doc || document;
		var list = doc.getElementById('lobby-feed');
		if (!list) {
			return null;
		}
		var pollUrl = list.getAttribute('data-poll-url') || '';
		var poller = createPoller({ list: list, pollUrl: pollUrl });
		poller.start();
		return poller;
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				boot(document);
			});
		} else {
			boot(document);
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			createPoller: createPoller,
			mergeEvents: mergeEvents,
			responseLooksLikeJson: responseLooksLikeJson,
			boot: boot
		};
	}
})();
