'use strict';

const { describe, it, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');

const SCRIPT = path.join(__dirname, '../../sw.js');

function loadSw(opts) {
	opts = opts || {};
	const shown = [];
	const listeners = {};
	global.self = {
		addEventListener(type, fn) {
			listeners[type] = fn;
		},
		skipWaiting() {},
		clients: { claim() { return Promise.resolve(); } },
		registration: {
			scope: opts.scope || 'https://novadev.hive.pizza/',
			active: { scriptURL: opts.scriptURL || 'https://novadev.hive.pizza/sw.js' },
			showNotification: async (title, options) => {
				shown.push({ title, options });
			},
			pushManager: {
				getSubscription: async () => (opts.hasSub ? { endpoint: 'x' } : null)
			}
		},
		location: {
			href: opts.scriptURL || 'https://novadev.hive.pizza/sw.js',
			pathname: opts.pathname || '/sw.js'
		},
		caches: {
			open: async () => ({ addAll: async () => {}, put() {} }),
			keys: async () => [],
			delete: async () => true,
			match: async () => undefined
		}
	};
	delete require.cache[require.resolve(SCRIPT)];
	const api = require(SCRIPT);
	return { api, shown, listeners };
}

describe('sw.js push handler', () => {
	var saved;

	beforeEach(() => {
		saved = { self: global.self };
	});

	afterEach(() => {
		global.self = saved.self;
		delete require.cache[require.resolve(SCRIPT)];
	});

	it('parses the server test payload and always renotifies', () => {
		const { api } = loadSw();
		const payload = api.parsePushPayload({
			data: {
				json() {
					return {
						title: 'HiveNova push test',
						body: 'If you see this, Web Push delivery works.',
						url: 'game.php?page=overview',
						tag: 'push_test',
						data: { url: 'game.php?page=overview', type: 'push_test' }
					};
				}
			}
		});
		assert.equal(payload.title, 'HiveNova push test');
		assert.equal(payload.tag, 'push_test');
		const opts = api.notificationOptions(payload);
		assert.equal(opts.renotify, true);
		assert.equal(opts.silent, false);
		assert.equal(opts.requireInteraction, true);
		assert.equal(opts.data.url, 'game.php?page=overview');
		assert.match(opts.icon, /icon-192\.png$/);
		assert.ok(!opts.icon.startsWith('styles/'), 'icon must be absolute, not relative to SW');
	});

	it('still shows a notification when json() throws', async () => {
		const { api, shown } = loadSw();
		await api.handlePushEvent({
			data: {
				json() { throw new Error('not json'); },
				text() { return 'plain'; }
			}
		});
		assert.equal(shown.length, 1);
		assert.equal(shown[0].title, 'HiveNova');
		assert.equal(shown[0].options.body, 'plain');
		assert.equal(shown[0].options.renotify, true);
	});

	it('waitUntil showNotification is invoked with the parsed title', async () => {
		const { api, shown } = loadSw();
		await api.handlePushEvent({
			data: {
				json() {
					return { title: 'Building complete', body: 'Ore Extractor finished', tag: 'building_complete' };
				}
			}
		});
		assert.equal(shown[0].title, 'Building complete');
		assert.equal(shown[0].options.tag, 'building_complete');
	});

	it('safeGameUrl allows uni-prefixed game URLs', () => {
		const { api } = loadSw();
		assert.equal(api.safeGameUrl('game.php?page=overview'), 'game.php?page=overview');
		assert.equal(api.safeGameUrl('/uni1/game.php?page=buildings'), '/uni1/game.php?page=buildings');
		assert.equal(api.safeGameUrl('https://evil.example/'), api.DEFAULT_GAME_URL);
	});
});
