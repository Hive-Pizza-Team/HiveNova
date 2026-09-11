'use strict';

const { describe, it, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');

const SCRIPT = path.join(__dirname, '../../scripts/game/push-subscribe.js');

function makeCheckbox(checked) {
	return {
		checked: !!checked,
		length: 1,
		prop(name, value) {
			if (name === 'checked' && value !== undefined) {
				this.checked = value;
			}
			return this.checked;
		},
		on() {
			return this;
		}
	};
}

function defineNavigator(value) {
	Object.defineProperty(global, 'navigator', {
		configurable: true,
		writable: true,
		value: value
	});
}

function loadPush(opts) {
	opts = opts || {};
	const checkbox = opts.checkbox || makeCheckbox(false);
	const errorEl = opts.errorEl || {
		textContent: '',
		hidden: true,
		attrs: {
			'data-msg-denied': 'blocked',
			'data-msg-failed': 'failed'
		},
		getAttribute(name) {
			return this.attrs[name] || '';
		}
	};
	const fetches = [];
	global.window = global;
	global.Notification = opts.Notification || { permission: 'default' };
	global.PushManager = opts.PushManager || function () {};
	defineNavigator(opts.navigator || {});
	global.document = {
		getElementById(id) {
			return id === 'pushAlertsError' ? errorEl : null;
		}
	};
	global.fetch = function (url, init) {
		fetches.push({ url: url, init: init || {} });
		const responder = opts.fetchImpl || function () {
			return Promise.resolve({
				ok: true,
				json: async () => ({ configured: true, publicKey: 'pk', enabled: false })
			});
		};
		return responder(url, init);
	};
	global.$ = function (arg) {
		if (typeof arg === 'function') {
			if (opts.runReady !== false) {
				arg();
			}
			return;
		}
		if (arg === '#pushAlerts') {
			return checkbox;
		}
		return { length: 0, on() { return this; }, prop() { return this; } };
	};

	delete require.cache[require.resolve(SCRIPT)];
	const api = require(SCRIPT);
	return { api, checkbox, errorEl, fetches };
}

describe('HiveNovaPush', () => {
	var saved;

	beforeEach(() => {
		saved = {
			window: global.window,
			document: global.document,
			fetch: global.fetch,
			$: global.$,
			Notification: global.Notification,
			PushManager: global.PushManager,
			MessageChannel: global.MessageChannel,
			navigator: global.navigator
		};
	});

	afterEach(() => {
		global.window = saved.window;
		global.document = saved.document;
		global.fetch = saved.fetch;
		global.$ = saved.$;
		global.Notification = saved.Notification;
		global.PushManager = saved.PushManager;
		global.MessageChannel = saved.MessageChannel;
		defineNavigator(saved.navigator);
		delete require.cache[require.resolve(SCRIPT)];
	});

	it('enable with activate skips the disabled preference gate', async () => {
		let subscribed = false;
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						pushManager: {
							getSubscription: async () => {
								subscribed = true;
								return { toJSON() { return { endpoint: 'x' }; } };
							},
							subscribe: async () => {
								throw new Error('subscribe should not run when a subscription exists');
							}
						}
					})
				}
			},
			fetchImpl(url) {
				if (String(url).includes('mode=status')) {
					return Promise.resolve({
						ok: true,
						json: async () => ({ configured: true, publicKey: 'pk', enabled: false })
					});
				}
				return Promise.resolve({ ok: true, json: async () => ({ ok: true }) });
			}
		});

		await assert.rejects(() => api.enable(), { message: 'disabled' });
		const result = await api.enable({ activate: true });
		assert.deepEqual(result, { ok: true });
		assert.equal(subscribed, true);
	});

	it('serviceWorkerUrl uses origin-root sw.js under /uniN/', () => {
		const { api } = loadPush({ runReady: false });
		assert.equal(api.serviceWorkerUrl('/uni1/game.php'), '/sw.js');
		assert.equal(api.serviceWorkerUrl('/uni2/game.php'), '/sw.js');
		assert.equal(api.serviceWorkerUrl('/index.php'), 'sw.js');
		assert.equal(api.serviceWorkerUrl('/game.php'), 'sw.js');
	});

	it('subscriptionPayload copies supportedContentEncodings when toJSON omits it', () => {
		const { api } = loadPush({ runReady: false });
		global.PushManager.supportedContentEncodings = ['aes128gcm'];
		const json = api.subscriptionPayload({
			toJSON() { return { endpoint: 'https://fcm.googleapis.com/fcm/send/x', keys: { p256dh: 'a', auth: 'b' } }; }
		});
		assert.equal(json.contentEncoding, 'aes128gcm');
		assert.equal(json.endpoint, 'https://fcm.googleapis.com/fcm/send/x');
	});

	it('showLocalTest uses registration.showNotification', async () => {
		let shown;
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async (title, opts) => {
							shown = { title, opts };
						}
					})
				}
			}
		});
		const result = await api.showLocalTest();
		assert.deepEqual(result, { ok: true, via: 'registration' });
		assert.equal(shown.title, 'HiveNova local test');
		assert.equal(shown.opts.renotify, true);
	});

	it('pingServiceWorker reports the owning worker', async () => {
		const { api } = loadPush({
			runReady: false,
			navigator: {
				serviceWorker: {
					register: async () => ({
						scope: 'https://example.test/',
						active: {
							scriptURL: 'https://example.test/sw.js',
							postMessage(_data, transfer) {
								const port = transfer[0];
								port.postMessage({
									ok: true,
									type: 'hivenova-pong',
									scope: 'https://example.test/',
									scriptURL: 'https://example.test/sw.js',
									hasPushSubscription: true
								});
							}
						}
					})
				}
			}
		});
		global.MessageChannel = class {
			constructor() {
				this.port1 = { onmessage: null };
				this.port2 = {
					postMessage: (data) => {
						if (this.port1.onmessage) {
							this.port1.onmessage({ data });
						}
					}
				};
			}
		};
		const result = await api.pingServiceWorker();
		assert.equal(result.ok, true);
		assert.equal(result.hasPushSubscription, true);
		assert.equal(result.scriptURL, 'https://example.test/sw.js');
	});

	it('sendTest posts a logged-in test ping', async () => {
		const { api, fetches } = loadPush({
			runReady: false,
			fetchImpl() {
				return Promise.resolve({
					ok: true,
					json: async () => ({ ok: true, delivered: 1, subscribed: true })
				});
			}
		});
		const result = await api.sendTest();
		assert.deepEqual(result, { ok: true, delivered: 1, subscribed: true });
		assert.equal(fetches.some((f) => String(f.url).includes('mode=test') && f.init.method === 'POST'), true);
	});

	it('denied subscribe unchecks, shows error, and turns preference off', async () => {
		const { api, checkbox, errorEl, fetches } = loadPush({ runReady: false });
		checkbox.checked = true;

		await api.handleSubscribeFailure(new Error('denied'), {
			uncheckSettings: true,
			showError: true
		});

		assert.equal(checkbox.checked, false);
		assert.equal(errorEl.hidden, false);
		assert.equal(errorEl.textContent, 'blocked');
		assert.equal(fetches.some((f) => String(f.url).includes('mode=unsubscribe')), true);
	});

	it('resubscribes when the existing PushManager key does not match VAPID', async () => {
		let unsubscribed = false;
		let subscribedWith;
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						pushManager: {
							getSubscription: async () => ({
								options: { applicationServerKey: api.urlBase64ToUint8Array('old-key') },
								toJSON() { return { endpoint: 'old' }; },
								async unsubscribe() { unsubscribed = true; }
							}),
							subscribe: async (opts) => {
								subscribedWith = opts;
								return { toJSON() { return { endpoint: 'new' }; } };
							}
						}
					})
				}
			},
			fetchImpl(url) {
				if (String(url).includes('mode=status')) {
					return Promise.resolve({
						ok: true,
						json: async () => ({ configured: true, publicKey: 'new-key', enabled: true })
					});
				}
				return Promise.resolve({ ok: true, json: async () => ({ ok: true }) });
			}
		});

		assert.equal(api.applicationServerKeysMatch({
			options: { applicationServerKey: api.urlBase64ToUint8Array('new-key') }
		}, 'new-key'), true);
		assert.equal(api.applicationServerKeysMatch({
			options: { applicationServerKey: api.urlBase64ToUint8Array('old-key') }
		}, 'new-key'), false);

		const result = await api.enable({ activate: true });
		assert.deepEqual(result, { ok: true });
		assert.equal(unsubscribed, true);
		assert.ok(subscribedWith.applicationServerKey);
	});

	it('reuses an existing subscription when the VAPID key matches', async () => {
		let subscribeCalls = 0;
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => {
						const key = api.urlBase64ToUint8Array('pk');
						return {
							pushManager: {
								getSubscription: async () => ({
									options: { applicationServerKey: key },
									toJSON() { return { endpoint: 'same' }; }
								}),
								subscribe: async () => {
									subscribeCalls += 1;
									throw new Error('subscribe should not run when the key matches');
								}
							}
						};
					}
				}
			},
			fetchImpl(url) {
				if (String(url).includes('mode=status')) {
					return Promise.resolve({
						ok: true,
						json: async () => ({ configured: true, publicKey: 'pk', enabled: true })
					});
				}
				return Promise.resolve({ ok: true, json: async () => ({ ok: true }) });
			}
		});

		const result = await api.enable({ activate: true });
		assert.deepEqual(result, { ok: true });
		assert.equal(subscribeCalls, 0);
	});

	it('maybeAutoSubscribe turns preference off when notifications are blocked', async () => {
		const { api, checkbox, fetches } = loadPush({
			runReady: false,
			Notification: { permission: 'denied' },
			fetchImpl() {
				return Promise.resolve({
					ok: true,
					json: async () => ({ configured: true, publicKey: 'pk', enabled: true })
				});
			}
		});
		checkbox.checked = true;
		await api.maybeAutoSubscribe();
		assert.equal(checkbox.checked, false);
		assert.equal(fetches.some((f) => String(f.url).includes('mode=unsubscribe')), true);
	});
});
