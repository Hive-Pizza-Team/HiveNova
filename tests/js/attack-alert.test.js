'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const alert = require('../../scripts/game/attack-alert.js');

describe('HiveNovaAttackAlert', () => {
	it('shows and hides the banner from count', () => {
		const banner = {
			attrs: { hidden: 'hidden', 'data-count': '0' },
			countText: '',
			setAttribute(name, value) { this.attrs[name] = value; },
			removeAttribute(name) { delete this.attrs[name]; },
			querySelector() {
				const self = this;
				return {
					set textContent(v) { self.countText = v; },
					get textContent() { return self.countText; }
				};
			}
		};
		alert.applyCount(banner, 2);
		assert.equal(banner.attrs.hidden, undefined);
		assert.equal(banner.attrs['data-count'], '2');
		assert.equal(banner.countText, ' (2)');
		alert.applyCount(banner, 0);
		assert.equal(banner.attrs.hidden, 'hidden');
		assert.equal(banner.countText, '');
	});

	it('rejects login redirects as JSON', () => {
		assert.equal(alert.responseLooksLikeJson({ ok: true, url: 'https://x/index.php?code=3' }, '{}'), false);
		assert.equal(alert.responseLooksLikeJson({ ok: true, url: 'https://x/game.php?page=attackAlert' }, '{"count":1}'), true);
		assert.equal(alert.responseLooksLikeJson({ ok: false, url: 'https://x/game.php' }, '{"count":1}'), false);
	});

	it('stops polling when the body is not JSON', async () => {
		const banner = {
			attrs: {},
			setAttribute() {},
			removeAttribute() {},
			querySelector() { return null; }
		};
		let calls = 0;
		const poller = alert.createPoller({
			banner,
			hiddenFn: () => false,
			fetchFn: async () => {
				calls += 1;
				return { ok: true, url: 'https://x/index.php?code=3', text: async () => '<html>login</html>' };
			}
		});
		await poller.poll();
		assert.equal(poller.isStopped(), true);
		assert.equal(calls, 1);
	});
});
