(function (root) {
	function urlBase64ToUint8Array(base64String) {
		var padding = '='.repeat((4 - base64String.length % 4) % 4);
		var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		var rawData = window.atob(base64);
		var outputArray = new Uint8Array(rawData.length);
		for (var i = 0; i < rawData.length; ++i) {
			outputArray[i] = rawData.charCodeAt(i);
		}
		return outputArray;
	}

	var SUBSCRIBE_FATAL_ERRORS = [
		'subscribe_failed',
		'invalid_subscription',
		'empty_body',
		'invalid_json',
		'method_not_allowed'
	];

	function isSubscribeFatalError(err) {
		return err && SUBSCRIBE_FATAL_ERRORS.indexOf(err.message) !== -1;
	}

	function syncServerPushPreferenceOff() {
		return fetch('game.php?page=push&mode=unsubscribe', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({})
		}).catch(function () {});
	}

	function pushAlertsCheckbox() {
		if (typeof $ === 'undefined') {
			return { length: 0, prop: function () { return this; } };
		}
		return $('#pushAlerts');
	}

	function uncheckPushAlerts() {
		var box = pushAlertsCheckbox();
		if (box.length) {
			box.prop('checked', false);
		}
	}

	function errorElement() {
		if (typeof document === 'undefined' || !document.getElementById) {
			return null;
		}
		return document.getElementById('pushAlertsError');
	}

	function clearPushError() {
		var el = errorElement();
		if (!el) {
			return;
		}
		el.textContent = '';
		el.hidden = true;
	}

	function showPushError(code) {
		var el = errorElement();
		if (!el) {
			return;
		}
		var msg = '';
		if (code === 'denied') {
			msg = el.getAttribute('data-msg-denied') || '';
		} else {
			msg = el.getAttribute('data-msg-failed') || '';
		}
		if (!msg) {
			return;
		}
		el.textContent = msg;
		el.hidden = false;
	}

	function shouldUncheckOnError(code) {
		return code === 'denied'
			|| code === 'not_configured'
			|| code === 'unsupported'
			|| code === 'no_sw'
			|| code === 'disabled'
			|| SUBSCRIBE_FATAL_ERRORS.indexOf(code) !== -1;
	}

	function shouldSyncPreferenceOff(code) {
		return code === 'denied'
			|| code === 'unsupported'
			|| code === 'no_sw'
			|| isSubscribeFatalError({ message: code });
	}

	function handleSubscribeFailure(err, options) {
		options = options || {};
		if (!err) {
			return Promise.resolve();
		}
		var code = err.message || '';
		if (options.uncheckSettings && shouldUncheckOnError(code)) {
			uncheckPushAlerts();
		}
		if (options.showError && code !== 'disabled' && code !== 'not_configured') {
			showPushError(code);
		}
		if (shouldSyncPreferenceOff(code)) {
			return syncServerPushPreferenceOff();
		}
		return Promise.resolve();
	}

	function serviceWorkerUrl(pathname) {
		var path = pathname;
		if (path == null && typeof location !== 'undefined' && location.pathname) {
			path = location.pathname;
		}
		path = path || '';
		// Relative 'sw.js' from /uni1/game.php registers a second worker at /uni1/sw.js.
		// Lobby already owns /sw.js (scope /). Share that registration so push
		// lands on the worker that controls the page.
		if (/^\/uni[0-9]+(\/|$)/.test(path)) {
			return '/sw.js';
		}
		return 'sw.js';
	}

	function registerServiceWorker() {
		if (!('serviceWorker' in navigator)) {
			return Promise.resolve(null);
		}
		return navigator.serviceWorker.register(serviceWorkerUrl(), { updateViaCache: 'none' }).catch(function () {
			return null;
		});
	}

	function fetchStatus() {
		return fetch('game.php?page=push&mode=status', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function subscriptionPayload(subscription) {
		var json = subscription && typeof subscription.toJSON === 'function'
			? subscription.toJSON()
			: {};
		if (!json || typeof json !== 'object') {
			json = {};
		}
		if (!json.contentEncoding) {
			var encodings = (typeof PushManager !== 'undefined' && PushManager.supportedContentEncodings) || [];
			if (encodings && encodings.length && typeof encodings[0] === 'string') {
				json.contentEncoding = encodings[0];
			}
		}
		return json;
	}

	function postSubscribe(subscription) {
		return fetch('game.php?page=push&mode=subscribe', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(subscriptionPayload(subscription))
		}).then(function (response) {
			if (response.ok) {
				return response.json();
			}
			return response.json().catch(function () {
				return {};
			}).then(function (body) {
				var err = new Error(body.error || 'subscribe_failed');
				err.status = response.status;
				throw err;
			});
		});
	}

	function keyBytes(value) {
		if (!value) {
			return null;
		}
		if (value instanceof Uint8Array) {
			return value;
		}
		if (typeof ArrayBuffer !== 'undefined' && value instanceof ArrayBuffer) {
			return new Uint8Array(value);
		}
		if (typeof ArrayBuffer !== 'undefined' && ArrayBuffer.isView && ArrayBuffer.isView(value)) {
			return new Uint8Array(value.buffer, value.byteOffset, value.byteLength);
		}
		return null;
	}

	function keysEqual(left, right) {
		if (!left || !right || left.length !== right.length) {
			return false;
		}
		for (var i = 0; i < left.length; i++) {
			if (left[i] !== right[i]) {
				return false;
			}
		}
		return true;
	}

	function applicationServerKeysMatch(subscription, publicKey) {
		if (!subscription || !publicKey) {
			return false;
		}
		var current = subscription.options && subscription.options.applicationServerKey;
		var currentBytes = keyBytes(current);
		if (!currentBytes) {
			return false;
		}
		return keysEqual(currentBytes, urlBase64ToUint8Array(publicKey));
	}

	function subscribeWithRegistration(reg, publicKey) {
		return reg.pushManager.getSubscription().then(function (existing) {
			if (existing) {
				var current = existing.options && existing.options.applicationServerKey;
				// Browsers that omit options.applicationServerKey cannot be verified;
				// reuse to avoid unsubscribe/resubscribe on every page load.
				if (!current || applicationServerKeysMatch(existing, publicKey)) {
					return existing;
				}
				return existing.unsubscribe().then(function () {
					return reg.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: urlBase64ToUint8Array(publicKey)
					});
				});
			}
			return reg.pushManager.subscribe({
				userVisibleOnly: true,
				applicationServerKey: urlBase64ToUint8Array(publicKey)
			});
		}).then(function (subscription) {
			return postSubscribe(subscription);
		});
	}

	var api = {
		fetchStatus: fetchStatus,
		handleSubscribeFailure: handleSubscribeFailure,
		clearPushError: clearPushError,
		showPushError: showPushError,
		urlBase64ToUint8Array: urlBase64ToUint8Array,
		applicationServerKeysMatch: applicationServerKeysMatch,
		serviceWorkerUrl: serviceWorkerUrl,
		subscriptionPayload: subscriptionPayload,
		sendTest: function () {
			return fetch('game.php?page=push&mode=test', {
				method: 'POST',
				credentials: 'same-origin'
			}).then(function (r) { return r.json(); });
		},

		showLocalTest: function () {
			if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
				return Promise.reject(new Error('denied'));
			}
			return registerServiceWorker().then(function (reg) {
				if (!reg || typeof reg.showNotification !== 'function') {
					throw new Error('no_sw');
				}
				return Promise.resolve(reg.showNotification('HiveNova local test', {
					body: 'If you see this, permission and notification UI work.',
					tag: 'hivenova-local-test',
					renotify: true,
					silent: false
				})).then(function () {
					return { ok: true, via: 'registration' };
				});
			});
		},

		pingServiceWorker: function () {
			return registerServiceWorker().then(function (reg) {
				if (!reg) {
					return { ok: false, reason: 'no_sw' };
				}
				var worker = reg.active || (navigator.serviceWorker && navigator.serviceWorker.controller) || null;
				if (!worker) {
					return { ok: false, reason: 'no_active_worker', scriptURL: reg.scope || '' };
				}
				return new Promise(function (resolve) {
					var settled = false;
					var finish = function (value) {
						if (settled) {
							return;
						}
						settled = true;
						resolve(value);
					};
					var channel = typeof MessageChannel === 'function' ? new MessageChannel() : null;
					if (!channel) {
						finish({ ok: false, reason: 'no_channel' });
						return;
					}
					var timer = setTimeout(function () {
						finish({ ok: false, reason: 'timeout', scope: reg.scope || '' });
					}, 2000);
					channel.port1.onmessage = function (event) {
						clearTimeout(timer);
						var data = event && event.data && typeof event.data === 'object' ? event.data : {};
						finish({
							ok: !!data.ok,
							type: data.type || 'hivenova-pong',
							scope: data.scope || reg.scope || '',
							scriptURL: data.scriptURL || '',
							hasPushSubscription: !!data.hasPushSubscription
						});
					};
					try {
						worker.postMessage({ type: 'hivenova-ping' }, [channel.port2]);
					} catch (e) {
						clearTimeout(timer);
						finish({ ok: false, reason: 'postmessage_failed' });
					}
				});
			});
		},

		enable: function (options) {
			options = options || {};
			if (!('Notification' in window) || !('PushManager' in window)) {
				return Promise.reject(new Error('unsupported'));
			}
			return fetchStatus().then(function (cfg) {
				if (!cfg.configured || !cfg.publicKey) {
					throw new Error('not_configured');
				}
				if (!cfg.enabled && !options.activate) {
					throw new Error('disabled');
				}

				var permissionPromise;
				if (options.skipPermissionRequest && Notification.permission === 'granted') {
					permissionPromise = Promise.resolve('granted');
				} else if (Notification.permission === 'granted') {
					permissionPromise = Promise.resolve('granted');
				} else if (Notification.permission === 'denied') {
					permissionPromise = Promise.resolve('denied');
				} else {
					permissionPromise = Notification.requestPermission();
				}

				return permissionPromise.then(function (perm) {
					if (perm !== 'granted') {
						throw new Error('denied');
					}
					return registerServiceWorker().then(function (reg) {
						if (!reg) {
							throw new Error('no_sw');
						}
						return subscribeWithRegistration(reg, cfg.publicKey);
					});
				});
			});
		},

		disable: function () {
			return registerServiceWorker().then(function (reg) {
				var endpoint = null;
				if (reg && reg.pushManager) {
					return reg.pushManager.getSubscription().then(function (sub) {
						if (sub) {
							endpoint = sub.endpoint;
							return sub.unsubscribe();
						}
					}).then(function () {
						return fetch('game.php?page=push&mode=unsubscribe', {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({ endpoint: endpoint })
						});
					});
				}
				return fetch('game.php?page=push&mode=unsubscribe', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({})
				});
			});
		},

		maybeAutoSubscribe: function () {
			if (!('Notification' in window) || !('PushManager' in window)) {
				return Promise.resolve();
			}
			if (Notification.permission === 'denied') {
				return fetchStatus().then(function (cfg) {
					if (cfg && cfg.enabled) {
						return handleSubscribeFailure(new Error('denied'), {
							uncheckSettings: true,
							showError: true
						});
					}
				});
			}
			return fetchStatus().then(function (cfg) {
				if (!cfg.configured || !cfg.enabled) {
					return;
				}
				return api.enable({
					skipPermissionRequest: Notification.permission === 'granted'
				}).catch(function (err) {
					return handleSubscribeFailure(err, {
						uncheckSettings: true,
						showError: true
					});
				});
			});
		}
	};

	root.HiveNovaPush = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof $ === 'function') {
		$(function () {
			registerServiceWorker().then(function () {
				api.maybeAutoSubscribe();
			});

			$('#pushAlerts').on('change', function () {
				clearPushError();
				if (!this.checked) {
					api.disable();
				} else {
					api.enable({ activate: true }).catch(function (err) {
						handleSubscribeFailure(err, { uncheckSettings: true, showError: true });
					});
				}
			});
		});
	}
})(typeof window !== 'undefined' ? window : globalThis);
