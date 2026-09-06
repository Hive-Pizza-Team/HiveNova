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
