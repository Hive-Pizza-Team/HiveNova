'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const { createAssetLoader } = require('../../scripts/game/galaxy-planet-preview-utils.js');

describe('galaxy-planet-preview asset loader', () => {
	it('resets the cached promise after a failed load', async () => {
		let calls = 0;
		const loader = createAssetLoader(function (src) {
			calls += 1;
			return Promise.reject(new Error('failed ' + src));
		});
		const config = {
			threeSrc: './three.min.js',
			planetSrc: './overview-planet.js'
		};

		await assert.rejects(() => loader.ensureAssets(config));
		assert.equal(loader.hasPendingAssets(), false);
		assert.equal(calls, 1);

		await assert.rejects(() => loader.ensureAssets(config));
		assert.equal(calls, 2);
	});

	it('reuses the same promise while a load is in flight', async () => {
		const loader = createAssetLoader(function () {
			return Promise.resolve();
		});
		const config = {
			threeSrc: './three.min.js',
			planetSrc: './overview-planet.js'
		};

		const first = loader.ensureAssets(config);
		const second = loader.ensureAssets(config);
		assert.equal(first, second);
		await first;
	});
});
