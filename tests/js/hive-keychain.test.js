'use strict';

const { describe, it, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const path = require('path');

const SCRIPT = path.join(__dirname, '../../scripts/game/hive-keychain.js');

function loadApi() {
	delete require.cache[require.resolve(SCRIPT)];
	return require(SCRIPT);
}

describe('HiveNovaKeychain', () => {
	let saved;

	beforeEach(() => {
		saved = globalThis.hive_keychain;
		delete globalThis.hive_keychain;
		delete globalThis.HiveNovaKeychain;
	});

	afterEach(() => {
		if (typeof saved === 'undefined') {
			delete globalThis.hive_keychain;
		} else {
			globalThis.hive_keychain = saved;
		}
	});

	it('get returns null when the bridge is missing', () => {
		const api = loadApi();
		assert.equal(api.get(), null);
	});

	it('get returns window.hive_keychain when present', () => {
		const bridge = { requestSendToken() {} };
		globalThis.hive_keychain = bridge;
		const api = loadApi();
		assert.equal(api.get(), bridge);
	});

	it('wait resolves immediately when already injected', async () => {
		const bridge = { requestSendToken() {} };
		globalThis.hive_keychain = bridge;
		const api = loadApi();
		assert.equal(await api.wait(500, 50), bridge);
	});

	it('wait resolves after a delayed injection (in-app browser)', async () => {
		const api = loadApi();
		const bridge = { requestSendToken() {} };
		setTimeout(() => {
			globalThis.hive_keychain = bridge;
		}, 80);
		assert.equal(await api.wait(1000, 25), bridge);
	});

	it('wait returns null after timeout when never injected', async () => {
		const api = loadApi();
		assert.equal(await api.wait(120, 40), null);
	});
});
