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
		},
		querySelector(sel) {
			if (String(sel).indexOf('push-subscribe.js') !== -1) {
				return opts.scriptEl || null;
			}
			return null;
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

	it('showLocalTest lists only the unique tag just created', async () => {
		let shown;
		const leftover = {
			title: 'HiveNova push test',
			tag: 'push_test',
			body: 'If you see this, Web Push delivery works.'
		};
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async (title, opts) => {
							shown = { title, opts };
						},
						async getNotifications() {
							return [
								leftover,
								{ title: shown.title, tag: shown.opts.tag, body: shown.opts.body }
							];
						}
					})
				}
			}
		});
		const result = await api.showLocalTest({ now: 1700000000123 });
		assert.equal(result.ok, true);
		assert.equal(result.via, 'registration');
		assert.equal(result.shown, 1);
		assert.equal(result.tag, 'hivenova-local-test-1700000000123');
		assert.deepEqual(result.notifications, [{
			title: 'HiveNova local test',
			tag: 'hivenova-local-test-1700000000123',
			body: 'If you see this, permission and notification UI work.'
		}]);
		assert.equal(shown.opts.requireInteraction, true);
		assert.equal(shown.opts.silent, false);
		assert.equal(shown.opts.renotify, true);
		assert.match(shown.opts.icon, /\/styles\/resource\/images\/pwa\/icon-192\.png$/);
		assert.ok(!shown.opts.icon.startsWith('styles/'));
	});

	it('showLocalTest is not ok when only a leftover push_test is listed', async () => {
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async () => {},
						async getNotifications() {
							return [{
								title: 'HiveNova push test',
								tag: 'push_test',
								body: 'If you see this, Web Push delivery works.'
							}];
						}
					})
				}
			}
		});
		const result = await api.showLocalTest({ now: 42 });
		assert.equal(result.ok, false);
		assert.equal(result.reason, 'not_listed');
		assert.equal(result.shown, 0);
		assert.deepEqual(result.notifications, []);
		assert.equal(result.tag, 'hivenova-local-test-42');
	});

	it('showLocalTest falls back to page Notification when SW list stays empty', async () => {
		const created = [];
		function FakeNotification(title, opts) {
			created.push({ title, opts });
			this.title = title;
			this.body = opts.body;
			this.tag = opts.tag;
		}
		FakeNotification.permission = 'granted';
		const { api } = loadPush({
			runReady: false,
			Notification: FakeNotification,
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async () => {},
						async getNotifications() { return []; }
					})
				}
			}
		});
		const empty = await api.showLocalTest({ now: 7 });
		assert.equal(empty.ok, false);
		assert.equal(empty.via, 'page');
		assert.equal(empty.reason, 'not_listed');
		assert.equal(empty.pageCreated, true);
		assert.equal(empty.shown, 0);
		assert.equal(created.length, 1);
		assert.equal(created[0].opts.requireInteraction, true);
		assert.equal(created[0].opts.tag, 'hivenova-local-test-7');
	});

	it('showLocalTest reports via page when the page path is what gets listed', async () => {
		let pageCreated = false;
		function FakeNotification(title, opts) {
			pageCreated = true;
			this.title = title;
			this.body = opts.body;
			this.tag = opts.tag;
		}
		FakeNotification.permission = 'granted';
		const { api } = loadPush({
			runReady: false,
			Notification: FakeNotification,
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async () => {},
						async getNotifications() {
							if (!pageCreated) {
								return [];
							}
							return [{
								title: 'HiveNova local test',
								tag: 'hivenova-local-test-8',
								body: 'page'
							}];
						}
					})
				}
			}
		});
		const listedPage = await api.showLocalTest({ now: 8 });
		assert.equal(listedPage.ok, true);
		assert.equal(listedPage.via, 'page');
		assert.equal(listedPage.shown, 1);
		assert.equal(listedPage.notifications[0].tag, 'hivenova-local-test-8');
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

	it('sendTest posts a logged-in test ping and reports listed push tags', async () => {
		const { api, fetches } = loadPush({
			runReady: false,
			navigator: {
				serviceWorker: {
					register: async () => ({
						async getNotifications() {
							return [
								{ title: 'HiveNova local test', tag: 'hivenova-local-test-1', body: 'local' },
								{ title: 'HiveNova push test', tag: 'push_test-1700000000', body: 'If you see this, Web Push delivery works.' }
							];
						}
					})
				}
			},
			fetchImpl() {
				return Promise.resolve({
					ok: true,
					json: async () => ({ ok: true, delivered: 1, subscribed: true })
				});
			}
		});
		const result = await api.sendTest({ waitMs: 0 });
		assert.equal(result.ok, true);
		assert.equal(result.delivered, 1);
		assert.equal(result.shown, 1);
		assert.deepEqual(result.notifications, [{
			title: 'HiveNova push test',
			tag: 'push_test-1700000000',
			body: 'If you see this, Web Push delivery works.'
		}]);
		assert.equal(fetches.some((f) => String(f.url).includes('mode=test') && f.init.method === 'POST'), true);
	});

	it('sendTest reports shown 0 when the browser listed nothing after deliver', async () => {
		const { api } = loadPush({
			runReady: false,
			navigator: {
				serviceWorker: {
					register: async () => ({
						async getNotifications() { return []; }
					})
				}
			},
			fetchImpl() {
				return Promise.resolve({
					ok: true,
					json: async () => ({ ok: true, delivered: 2, attempted: 2, failed: 0 })
				});
			}
		});
		const result = await api.sendTest({ waitMs: 0 });
		assert.equal(result.ok, true);
		assert.equal(result.delivered, 2);
		assert.equal(result.shown, 0);
		assert.deepEqual(result.notifications, []);
	});

	it('sendTest tag helpers ignore leftover local-test entries', () => {
		const { api } = loadPush({ runReady: false });
		assert.equal(api.isSendTestNotificationTag('push_test'), true);
		assert.equal(api.isSendTestNotificationTag('push_test-123'), true);
		assert.equal(api.isSendTestNotificationTag('hivenova'), true);
		assert.equal(api.isSendTestNotificationTag('hivenova-local-test-9'), false);
		const listed = api.filterSendTestNotifications([
			{ title: 'local', tag: 'hivenova-local-test-9', body: 'x' },
			{ title: 'push', tag: 'push_test-1', body: 'y' }
		]);
		assert.equal(listed.length, 1);
		assert.equal(listed[0].tag, 'push_test-1');
	});

	it('evaluateSelfCheckPass is client-automatable only (no toast claim)', () => {
		const { api } = loadPush({ runReady: false });
		const base = {
			typeofSendTest: 'function',
			typeofShowLocalTest: 'function',
			status: { subscribed: true, vapidOk: true },
			showLocalTest: { ok: true, shown: 1, reason: null },
			sendTest: { delivered: 1 },
			toastVisible: null
		};
		assert.equal(api.evaluateSelfCheckPass(base), true);
		assert.equal(api.evaluateSelfCheckPass({
			...base,
			showLocalTest: { ok: false, shown: 0, reason: 'not_listed' }
		}), true);
		assert.equal(api.evaluateSelfCheckPass({
			...base,
			sendTest: { delivered: 2 }
		}), false);
		assert.equal(api.evaluateSelfCheckPass({
			...base,
			status: { subscribed: false, vapidOk: true }
		}), false);
		assert.equal(api.evaluateSelfCheckPass({
			...base,
			showLocalTest: { ok: false, shown: 0, reason: 'denied' }
		}), false);
		assert.equal(api.evaluateSelfCheckPass({
			...base,
			toastVisible: true
		}), true);
	});

	it('selfCheck returns one pasteable JSON object', async () => {
		let shown;
		const { api } = loadPush({
			runReady: false,
			scriptEl: {
				getAttribute(name) {
					return name === 'src' ? 'scripts/game/push-subscribe.js?v=2.0.1789169999' : '';
				}
			},
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => ({
						showNotification: async (title, opts) => {
							shown = { title, opts };
						},
						async getNotifications() {
							return [
								{ title: 'HiveNova push test', tag: 'push_test', body: 'leftover' },
								{ title: shown.title, tag: shown.opts.tag, body: shown.opts.body },
								{ title: 'HiveNova push test', tag: 'push_test-1700000000', body: 'If you see this, Web Push delivery works.' }
							];
						}
					})
				}
			},
			fetchImpl(url) {
				if (String(url).includes('mode=status')) {
					return Promise.resolve({
						ok: true,
						json: async () => ({
							configured: true,
							publicKey: 'pk',
							enabled: true,
							subscribed: true,
							vapidOk: true
						})
					});
				}
				if (String(url).includes('mode=test')) {
					return Promise.resolve({
						ok: true,
						json: async () => ({ ok: true, delivered: 1, attempted: 1, failed: 0 })
					});
				}
				return Promise.resolve({ ok: true, json: async () => ({}) });
			}
		});
		const result = await api.selfCheck({ waitMs: 0, now: 99 });
		assert.equal(result.revQuery, '2.0.1789169999');
		assert.equal(result.scriptSrc, 'scripts/game/push-subscribe.js?v=2.0.1789169999');
		assert.equal(result.typeofSendTest, 'function');
		assert.equal(result.typeofShowLocalTest, 'function');
		assert.deepEqual(result.status, { subscribed: true, vapidOk: true });
		assert.equal(result.showLocalTest.ok, true);
		assert.equal(result.showLocalTest.via, 'registration');
		assert.equal(result.showLocalTest.shown, 1);
		assert.equal(result.showLocalTest.tag, 'hivenova-local-test-99');
		assert.equal(result.showLocalTest.notifications[0].tag, 'hivenova-local-test-99');
		assert.equal(result.sendTest.ok, true);
		assert.equal(result.sendTest.delivered, 1);
		assert.equal(result.sendTest.attempted, 1);
		assert.equal(result.sendTest.shown, 2);
		assert.equal(result.pass, true);
		assert.equal(result.toastVisible, null);
		assert.ok(!Object.prototype.hasOwnProperty.call(result, 'toastSeen'));
	});

	it('enable unsubscribes leftover dual-SW registrations', async () => {
		let staleUnsubscribed = false;
		const keepReg = {
			scope: 'https://example.test/',
			pushManager: {
				getSubscription: async () => ({ toJSON() { return { endpoint: 'keep' }; } }),
				subscribe: async () => {
					throw new Error('subscribe should not run when a subscription exists');
				}
			}
		};
		const staleReg = {
			scope: 'https://example.test/uni1/',
			pushManager: {
				getSubscription: async () => ({
					async unsubscribe() { staleUnsubscribed = true; }
				})
			}
		};
		const { api } = loadPush({
			runReady: false,
			Notification: { permission: 'granted' },
			navigator: {
				serviceWorker: {
					register: async () => keepReg,
					getRegistrations: async () => [keepReg, staleReg]
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
		const result = await api.enable({ activate: true });
		assert.deepEqual(result, { ok: true });
		assert.equal(staleUnsubscribed, true);
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
