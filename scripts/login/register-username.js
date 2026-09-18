/**
 * Live register username availability (debounced AJAX + clickable suggestions).
 */
(function (root) {
	'use strict';

	function debounce(fn, waitMs) {
		var timer = null;
		return function () {
			var ctx = this;
			var args = arguments;
			if (timer) {
				clearTimeout(timer);
			}
			timer = setTimeout(function () {
				timer = null;
				fn.apply(ctx, args);
			}, waitMs);
		};
	}

	function parseConfig(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return null;
		}
		var tag = doc.getElementById('reg-username-check-config');
		if (!tag || !tag.textContent) {
			return null;
		}
		try {
			var parsed = JSON.parse(tag.textContent);
			return parsed && typeof parsed === 'object' ? parsed : null;
		} catch (e) {
			return null;
		}
	}

	function currentUniverse(doc, input) {
		var form = input && input.form ? input.form : null;
		var sel = (form && form.querySelector) ? form.querySelector('.changeAction') : null;
		if (!sel && doc && doc.querySelector) {
			sel = doc.querySelector('.changeAction');
		}
		return sel ? String(sel.value || '') : '';
	}

	function buildUrl(config, username, uni, hiveSignup) {
		var base = (config && config.url) || 'index.php?page=register&mode=checkUsername&ajax=1';
		var join = base.indexOf('?') === -1 ? '?' : '&';
		return base + join +
			'username=' + encodeURIComponent(username) +
			'&uni=' + encodeURIComponent(uni) +
			'&hiveSignup=' + (hiveSignup ? '1' : '0');
	}

	function wrapFor(input) {
		var wrap = input.parentElement;
		if (!wrap) {
			return { mark: null, message: null, suggestions: null };
		}
		return {
			mark: wrap.querySelector('.reg-username-mark'),
			message: wrap.parentElement ? wrap.parentElement.querySelector('.reg-username-message') : null,
			suggestions: wrap.parentElement ? wrap.parentElement.querySelector('.reg-username-suggestions') : null
		};
	}

	function clearView(view) {
		if (view.mark) {
			view.mark.textContent = '';
			view.mark.classList.remove('is-available', 'is-unavailable');
		}
		if (view.message) {
			view.message.textContent = '';
			view.message.classList.remove('is-visible', 'is-ok');
		}
		if (view.suggestions) {
			view.suggestions.innerHTML = '';
			view.suggestions.setAttribute('hidden', 'hidden');
		}
	}

	function render(view, payload, i18n, onPick, doc) {
		payload = payload || {};
		i18n = i18n || {};
		doc = doc || (typeof document !== 'undefined' ? document : null);
		var available = !!payload.available;
		var hiveOwn = !!payload.hiveOwn;
		var message = payload.message ? String(payload.message) : '';
		var suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];

		if (view.mark) {
			view.mark.textContent = available ? '\u2705' : '\u274C';
			view.mark.classList.toggle('is-available', available);
			view.mark.classList.toggle('is-unavailable', !available);
		}

		if (view.message) {
			view.message.textContent = message;
			view.message.classList.toggle('is-visible', message !== '');
			view.message.classList.toggle('is-ok', available || hiveOwn);
		}

		if (!view.suggestions) {
			return;
		}
		view.suggestions.innerHTML = '';
		if (available || suggestions.length === 0 || !doc) {
			view.suggestions.setAttribute('hidden', 'hidden');
			return;
		}

		if (i18n.suggestions) {
			var label = doc.createElement('li');
			label.className = 'reg-username-suggestions-label';
			label.textContent = i18n.suggestions;
			view.suggestions.appendChild(label);
		}

		suggestions.forEach(function (name) {
			var item = doc.createElement('li');
			var btn = doc.createElement('button');
			btn.type = 'button';
			btn.className = 'reg-username-suggestion';
			btn.textContent = String(name);
			btn.addEventListener('click', function () {
				if (typeof onPick === 'function') {
					onPick(String(name));
				}
			});
			item.appendChild(btn);
			view.suggestions.appendChild(item);
		});
		view.suggestions.removeAttribute('hidden');
	}

	function bindField(input, config, deps) {
		deps = deps || {};
		var doc = deps.documentObj || (typeof document !== 'undefined' ? document : null);
		var fetchFn = deps.fetchFn || (typeof fetch === 'function' ? fetch.bind(root) : null);
		var hiveSignup = input.getAttribute('data-username-check') === 'hive';
		var view = wrapFor(input);
		var seq = 0;
		var debounceMs = (config && config.debounceMs) || 300;

		function applyValue(name) {
			input.value = name;
			if (hiveSignup) {
				input.value = String(input.value || '').toLowerCase().trim();
			}
			runCheck();
		}

		function runCheck() {
			var username = String(input.value || '').trim();
			if (hiveSignup) {
				username = username.toLowerCase();
				if (input.value !== username) {
					input.value = username;
				}
			}
			if (username === '') {
				clearView(view);
				return Promise.resolve(null);
			}
			if (!fetchFn) {
				return Promise.resolve(null);
			}

			var requestId = ++seq;
			var url = buildUrl(config, username, currentUniverse(doc, input), hiveSignup);
			return Promise.resolve(fetchFn(url, { credentials: 'same-origin' })).then(function (res) {
				if (typeof res.json === 'function') {
					return res.json();
				}
				return res;
			}).then(function (payload) {
				if (requestId !== seq) {
					return payload;
				}
				if (String(input.value || '').trim() !== username &&
					String(input.value || '').trim().toLowerCase() !== username) {
					return payload;
				}
				if (payload && payload.ok === false && payload.reason === 'rate_limited') {
					return payload;
				}
				render(view, payload, config.i18n || {}, applyValue, doc);
				return payload;
			}).catch(function () {
				if (requestId !== seq) {
					return null;
				}
				return null;
			});
		}

		var debounced = debounce(runCheck, debounceMs);
		input.addEventListener('input', debounced);
		input.addEventListener('blur', runCheck);

		if (doc) {
			doc.querySelectorAll('.changeAction').forEach(function (sel) {
				sel.addEventListener('change', function () {
					if (String(input.value || '').trim() !== '') {
						runCheck();
					}
				});
			});
		}

		return {
			runCheck: runCheck,
			applyValue: applyValue,
			view: view
		};
	}

	function bind(doc, fetchFn) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return [];
		}
		var config = parseConfig(doc);
		if (!config) {
			return [];
		}
		var inputs = doc.querySelectorAll('[data-username-check]');
		var bound = [];
		inputs.forEach(function (input) {
			bound.push(bindField(input, config, { documentObj: doc, fetchFn: fetchFn }));
		});
		return bound;
	}

	function boot() {
		bind();
	}

	var api = {
		debounce: debounce,
		parseConfig: parseConfig,
		buildUrl: buildUrl,
		render: render,
		clearView: clearView,
		bindField: bindField,
		bind: bind,
		boot: boot
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else {
		root.HiveNovaRegisterUsername = api;
		if (typeof document !== 'undefined') {
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', boot);
			} else {
				boot();
			}
		}
	}
})(typeof globalThis !== 'undefined' ? globalThis : this);
