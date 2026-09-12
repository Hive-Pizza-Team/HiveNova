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

	var SEND_TEST_TAGS = ['push_test', 'hivenova'];
	var SEND_TEST_LIST_WAIT_MS = 1500;
	var LOCAL_TEST_TITLE = 'HiveNova local test';
	var LOCAL_TEST_BODY = 'If you see this, permission and notification UI work.';

	function notificationIconUrl() {
		try {
			if (typeof location !== 'undefined' && location.origin) {
				return location.origin + '/styles/resource/images/pwa/icon-192.png';
			}
		} catch (e) {}
		return '/styles/resource/images/pwa/icon-192.png';
	}

	function localTestTag(now) {
		var ts = typeof now === 'number' ? now : Date.now();
		return 'hivenova-local-test-' + ts;
	}

	function testNotificationOptions(tag, body) {
		return {
			body: body || LOCAL_TEST_BODY,
			icon: notificationIconUrl(),
			tag: tag,
			renotify: true,
			silent: false,
			requireInteraction: true
		};
	}

	function summarizeNotifications(list) {
		var out = [];
		var i;
		var n;
		if (!list || !list.length) {
			return out;
		}
		for (i = 0; i < list.length; i++) {
			n = list[i];
			if (!n) {
				continue;
			}
			out.push({
				title: n.title || '',
				tag: n.tag || '',
				body: n.body || ''
			});
		}
		return out;
	}

	function isSendTestNotificationTag(tag) {
		if (typeof tag !== 'string' || tag === '') {
			return false;
		}
		return tag === 'hivenova' || tag === 'push_test' || tag.indexOf('push_test-') === 0;
	}

	function filterNotificationsByTags(list, tags) {
		var out = [];
		var i;
		var n;
		if (!list || !list.length) {
			return out;
		}
		if (!tags || !tags.length) {
			for (i = 0; i < list.length; i++) {
				if (list[i]) {
					out.push(list[i]);
				}
			}
			return out;
		}
		for (i = 0; i < list.length; i++) {
			n = list[i];
			if (n && tags.indexOf(n.tag) !== -1) {
				out.push(n);
			}
		}
		return out;
	}

	function filterSendTestNotifications(list) {
		var out = [];
		var i;
		var n;
		if (!list || !list.length) {
			return out;
		}
		for (i = 0; i < list.length; i++) {
			n = list[i];
			if (n && isSendTestNotificationTag(n.tag)) {
				out.push(n);
			}
		}
		return out;
	}

	function getRegistrationNotifications(reg) {
		if (!reg || typeof reg.getNotifications !== 'function') {
			return Promise.resolve([]);
		}
		return Promise.resolve(reg.getNotifications()).then(function (list) {
			return Array.isArray(list) ? list : [];
		}).catch(function () {
			return [];
		});
	}

	function registrationOwnsPushSubscription(reg) {
		if (!reg || !reg.pushManager || typeof reg.pushManager.getSubscription !== 'function') {
			return Promise.resolve(false);
		}
		return reg.pushManager.getSubscription().then(function (sub) {
			return !!sub;
		}).catch(function () {
			return false;
		});
	}

	function registrationForShow() {
		return registerServiceWorker().then(function (registered) {
			var readyPromise = (typeof navigator !== 'undefined'
				&& navigator.serviceWorker
				&& navigator.serviceWorker.ready)
				? Promise.resolve(navigator.serviceWorker.ready).catch(function () { return null; })
				: Promise.resolve(null);
			return readyPromise.then(function (readyReg) {
				return registrationOwnsPushSubscription(registered).then(function (regOwns) {
					if (regOwns) {
						return registered;
					}
					return registrationOwnsPushSubscription(readyReg).then(function (readyOwns) {
						if (readyOwns) {
							return readyReg;
						}
						return registered || readyReg;
					});
				});
			});
		});
	}

	function showPageNotification(title, options) {
		if (typeof Notification !== 'function') {
			return null;
		}
		try {
			return new Notification(title, options);
		} catch (e) {
			return null;
		}
	}

	function visibilityResult(ok, via, listed, extra) {
		var result = {
			ok: !!ok,
			via: via,
			shown: listed && listed.length ? listed.length : 0,
			notifications: summarizeNotifications(listed || [])
		};
		var key;
		if (extra) {
			for (key in extra) {
				if (Object.prototype.hasOwnProperty.call(extra, key)) {
					result[key] = extra[key];
				}
			}
		}
		return result;
	}

	function mergeServerAndListed(server, listed) {
		var out = {};
		var key;
		if (server && typeof server === 'object') {
			for (key in server) {
				if (Object.prototype.hasOwnProperty.call(server, key)) {
					out[key] = server[key];
				}
			}
		}
		out.shown = listed && listed.length ? listed.length : 0;
		out.notifications = summarizeNotifications(listed || []);
		return out;
	}

	function readPushScriptInfo() {
		var src = '';
		var revQuery = '';
		try {
			if (typeof document !== 'undefined' && document.querySelector) {
				var el = document.querySelector('script[src*="push-subscribe.js"]');
				if (el && typeof el.getAttribute === 'function') {
					src = el.getAttribute('src') || '';
				}
			}
		} catch (e) {}
		if (src) {
			var match = src.match(/[?&]v=([^&]*)/);
			if (match) {
				revQuery = match[1] || '';
			}
		}
		return { scriptSrc: src, revQuery: revQuery };
	}

	function evaluateSelfCheckPass(result) {
		if (!result || typeof result !== 'object') {
			return false;
		}
		var status = result.status || {};
		var local = result.showLocalTest || {};
		var send = result.sendTest || {};
		var functionsOk = result.typeofSendTest === 'function' && result.typeofShowLocalTest === 'function';
		var subscribed = !!status.subscribed;
		var deliveredOne = send.delivered === 1;
		var localListed = typeof local.shown === 'number' && local.shown >= 1;
		var localHonest = local.reason === 'not_listed';
		return !!(functionsOk && subscribed && deliveredOne && (localListed || localHonest));
	}

	function summarizeSelfCheckLocal(local) {
		local = local && typeof local === 'object' ? local : {};
		return {
			ok: !!local.ok,
			via: local.via || '',
			shown: typeof local.shown === 'number' ? local.shown : 0,
			tag: local.tag || '',
			reason: local.reason || null,
			notifications: summarizeNotifications(local.notifications || [])
		};
	}

	function summarizeSelfCheckSend(send) {
		send = send && typeof send === 'object' ? send : {};
		return {
			ok: !!send.ok,
			delivered: typeof send.delivered === 'number' ? send.delivered : 0,
			attempted: typeof send.attempted === 'number' ? send.attempted : 0,
			shown: typeof send.shown === 'number' ? send.shown : 0,
			notifications: summarizeNotifications(send.notifications || [])
		};
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

	function unsubscribeOtherPushRegistrations(keepReg) {
		if (typeof navigator === 'undefined'
			|| !navigator.serviceWorker
			|| typeof navigator.serviceWorker.getRegistrations !== 'function') {
			return Promise.resolve();
		}
		return navigator.serviceWorker.getRegistrations().then(function (regs) {
			var tasks = [];
			var i;
			var r;
			if (!regs || !regs.length) {
				return;
			}
			for (i = 0; i < regs.length; i++) {
				r = regs[i];
				if (!r || r === keepReg) {
					continue;
				}
				if (keepReg && r.scope && keepReg.scope && r.scope === keepReg.scope) {
					continue;
				}
				if (!r.pushManager || typeof r.pushManager.getSubscription !== 'function') {
					continue;
				}
				tasks.push(r.pushManager.getSubscription().then(function (sub) {
					if (sub && typeof sub.unsubscribe === 'function') {
						return sub.unsubscribe();
					}
				}).catch(function () {}));
			}
			return Promise.all(tasks);
		}).catch(function () {});
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
			return unsubscribeOtherPushRegistrations(reg).then(function () {
				return postSubscribe(subscription);
			});
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
		notificationIconUrl: notificationIconUrl,
		localTestTag: localTestTag,
		testNotificationOptions: testNotificationOptions,
		summarizeNotifications: summarizeNotifications,
		filterNotificationsByTags: filterNotificationsByTags,
		filterSendTestNotifications: filterSendTestNotifications,
		isSendTestNotificationTag: isSendTestNotificationTag,
		evaluateSelfCheckPass: evaluateSelfCheckPass,
		readPushScriptInfo: readPushScriptInfo,
		SEND_TEST_TAGS: SEND_TEST_TAGS,
		sendTest: function (options) {
			options = options || {};
			var waitMs = typeof options.waitMs === 'number' ? options.waitMs : SEND_TEST_LIST_WAIT_MS;
			return fetch('game.php?page=push&mode=test', {
				method: 'POST',
				credentials: 'same-origin'
			}).then(function (r) { return r.json(); }).then(function (server) {
				var wait = (server && server.delivered > 0 && waitMs > 0)
					? new Promise(function (resolve) { setTimeout(resolve, waitMs); })
					: Promise.resolve();
				return wait.then(function () {
					return registrationForShow().then(function (reg) {
						return getRegistrationNotifications(reg).then(function (all) {
							return mergeServerAndListed(server, filterSendTestNotifications(all));
						});
					}).catch(function () {
						return mergeServerAndListed(server, []);
					});
				});
			});
		},

		showLocalTest: function (options) {
			options = options || {};
			if (typeof Notification === 'undefined' || Notification.permission !== 'granted') {
				return Promise.reject(new Error('denied'));
			}
			var title = LOCAL_TEST_TITLE;
			var body = LOCAL_TEST_BODY;
			var tag = typeof options.tag === 'string' && options.tag !== ''
				? options.tag
				: localTestTag(options.now);
			var notifyOpts = testNotificationOptions(tag, body);
			return registrationForShow().then(function (reg) {
				var pageFallback = function (viaWhenPageFails) {
					var pageNote = showPageNotification(title, notifyOpts);
					if (!pageNote) {
						return visibilityResult(false, viaWhenPageFails, [], { reason: 'not_listed', tag: tag });
					}
					if (!reg || typeof reg.getNotifications !== 'function') {
						return visibilityResult(false, 'page', [], { reason: 'not_listed', tag: tag, pageCreated: true });
					}
					return getRegistrationNotifications(reg).then(function (after) {
						var listed = filterNotificationsByTags(after, [tag]);
						if (listed.length > 0) {
							return visibilityResult(true, 'page', listed, { tag: tag });
						}
						return visibilityResult(false, 'page', [], { reason: 'not_listed', tag: tag, pageCreated: true });
					});
				};
				if (!reg || typeof reg.showNotification !== 'function') {
					if (typeof Notification === 'function') {
						return pageFallback('registration');
					}
					throw new Error('no_sw');
				}
				return Promise.resolve(reg.showNotification(title, notifyOpts)).then(function () {
					return getRegistrationNotifications(reg).then(function (all) {
						var listed = filterNotificationsByTags(all, [tag]);
						if (listed.length > 0) {
							return visibilityResult(true, 'registration', listed, { tag: tag });
						}
						return pageFallback('registration');
					});
				});
			});
		},

		selfCheck: function (options) {
			options = options || {};
			var info = readPushScriptInfo();
			var result = {
				revQuery: info.revQuery,
				scriptSrc: info.scriptSrc,
				typeofSendTest: typeof api.sendTest,
				typeofShowLocalTest: typeof api.showLocalTest,
				status: null,
				showLocalTest: null,
				sendTest: null,
				pass: false,
				toastVisible: null
			};
			var localOpts = {};
			if (typeof options.now === 'number') {
				localOpts.now = options.now;
			}
			if (typeof options.tag === 'string' && options.tag !== '') {
				localOpts.tag = options.tag;
			}
			var sendOpts = {
				waitMs: typeof options.waitMs === 'number' ? options.waitMs : SEND_TEST_LIST_WAIT_MS
			};
			return fetchStatus().then(function (status) {
				status = status && typeof status === 'object' ? status : {};
				result.status = {
					subscribed: !!status.subscribed,
					vapidOk: !!status.vapidOk
				};
				return api.showLocalTest(localOpts).catch(function (err) {
					return {
						ok: false,
						via: '',
						shown: 0,
						tag: '',
						reason: err && err.message ? String(err.message) : 'failed',
						notifications: []
					};
				});
			}).then(function (local) {
				result.showLocalTest = summarizeSelfCheckLocal(local);
				return api.sendTest(sendOpts).catch(function () {
					return { ok: false, delivered: 0, attempted: 0, shown: 0, notifications: [] };
				});
			}).then(function (send) {
				result.sendTest = summarizeSelfCheckSend(send);
				result.pass = evaluateSelfCheckPass(result);
				return result;
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
