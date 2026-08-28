'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const prefetch = require('../../scripts/login/prefetch-assets.js');

describe('HiveNovaAssetPrefetch', () => {
	it('skips when Save-Data is on', () => {
		assert.equal(prefetch.shouldPrefetch({ connection: { saveData: true } }), false);
	});

	it('skips on 2g', () => {
		assert.equal(prefetch.shouldPrefetch({ connection: { effectiveType: '2g' } }), false);
		assert.equal(prefetch.shouldPrefetch({ connection: { effectiveType: 'slow-2g' } }), false);
	});

	it('allows 4g and missing connection info', () => {
		assert.equal(prefetch.shouldPrefetch({ connection: { effectiveType: '4g' } }), true);
		assert.equal(prefetch.shouldPrefetch({}), true);
		assert.equal(prefetch.shouldPrefetch(null), true);
	});

	it('runQueue respects concurrency and drains all urls', async () => {
		const seen = [];
		let inflight = 0;
		let maxInflight = 0;
		const result = await prefetch.runQueue(['a', 'b', 'c', 'd', 'e'], 2, (url) => {
			seen.push(url);
			inflight++;
			maxInflight = Math.max(maxInflight, inflight);
			return new Promise((resolve) => {
				setTimeout(() => {
					inflight--;
					resolve(url);
				}, 5);
			});
		});
		assert.deepEqual(seen.sort(), ['a', 'b', 'c', 'd', 'e']);
		assert.equal(result.loaded, 5);
		assert.ok(maxInflight <= 2);
	});

	it('startPrefetch skips empty or save-data', async () => {
		const skippedEmpty = await prefetch.startPrefetch([], { delayMs: 0, whenIdle: (fn) => fn() });
		assert.equal(skippedEmpty.skipped, true);

		const skippedSave = await prefetch.startPrefetch(['x.gif'], {
			delayMs: 0,
			whenIdle: (fn) => fn(),
			navigator: { connection: { saveData: true } },
			documentObj: { readyState: 'complete' }
		});
		assert.equal(skippedSave.skipped, true);
	});

	it('startPrefetch loads urls after idle', async () => {
		const loaded = [];
		const result = await prefetch.startPrefetch(['one.gif', 'two.gif'], {
			delayMs: 0,
			whenIdle: (fn) => fn(),
			documentObj: { readyState: 'complete' },
			prefetchFn: (url) => {
				loaded.push(url);
				return Promise.resolve(url);
			}
		});
		assert.equal(result.loaded, 2);
		assert.deepEqual(loaded.sort(), ['one.gif', 'two.gif']);
	});

	it('readUrlsFromDom parses json script tag', () => {
		const tag = { textContent: '["styles/theme/hive/gebaeude/1.gif"]' };
		const doc = {
			getElementById(id) {
				return id === 'prefetch-assets-data' ? tag : null;
			}
		};
		assert.deepEqual(prefetch.readUrlsFromDom(doc), ['styles/theme/hive/gebaeude/1.gif']);
		assert.deepEqual(prefetch.readUrlsFromDom({ getElementById() { return null; } }), []);
	});
});
