'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const firehose = require('../../scripts/game/event-firehose.js');

function makeList(ids) {
	const children = [];
	const list = {
		querySelector(sel) {
			if (sel === '.event-firehose-empty') {
				return children.find((c) => c.className === 'event-firehose-empty') || null;
			}
			return null;
		},
		querySelectorAll(sel) {
			if (sel === '[data-event-id]') {
				return children.filter((c) => c.getAttribute);
			}
			return [];
		},
		insertBefore(node) {
			children.unshift(node);
		},
		firstChild: null,
		_children: children
	};
	for (const id of ids) {
		children.push({
			getAttribute(name) { return name === 'data-event-id' ? String(id) : null; }
		});
	}
	return list;
}

describe('HiveNovaEventFirehose', () => {
	it('rejects login redirects as JSON', () => {
		assert.equal(firehose.responseLooksLikeJson({ ok: true, url: 'https://x/index.php?code=3' }, '{}'), false);
		assert.equal(firehose.responseLooksLikeJson({ ok: true, url: 'https://x/game.php?page=eventFirehose' }, '{"events":[]}'), true);
		assert.equal(firehose.responseLooksLikeJson({ ok: false, url: 'https://x/game.php' }, '{"events":[]}'), false);
	});

	it('stops polling when the body is not JSON', async () => {
		const list = makeList([]);
		let calls = 0;
		const poller = firehose.createPoller({
			list,
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

	it('does not poll while the tab is hidden', async () => {
		let calls = 0;
		const poller = firehose.createPoller({
			list: makeList([]),
			hiddenFn: () => true,
			fetchFn: async () => {
				calls += 1;
				return { ok: true, url: 'https://x/game.php', text: async () => '{"events":[]}' };
			}
		});
		await poller.poll();
		assert.equal(calls, 0);
		assert.equal(poller.isStopped(), false);
	});

	it('does not duplicate event ids when merging', () => {
		const list = makeList([2]);
		firehose.mergeEvents(list, [
			{ id: 2, time: 'a', eventType: 'Battle', size: 'Skirmish', outcome: 'Draw' },
			{ id: 3, time: 'b', eventType: 'Battle', size: 'Clash', outcome: 'Draw' }
		]);
		const ids = list.querySelectorAll('[data-event-id]').map((n) => n.getAttribute('data-event-id'));
		assert.deepEqual(ids.sort(), ['2', '3']);
	});
});
