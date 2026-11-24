'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const market = require('../../scripts/game/marketplace.js');

describe('marketplace deal rating', () => {
	it('matches the server ratio at 4:2:1', () => {
		// 60k crystal for 100k metal -> 30000 / 25000
		assert.equal(market.dealRatio({ 902: 60000 }, 1, 100000, [4, 2, 1]).toFixed(2), '1.20');
		assert.equal(market.dealRatio({ 901: 120000 }, 2, 60000, [4, 2, 1]), 1);
	});

	it('returns null for an unknown price resource or zero price', () => {
		assert.equal(market.dealRatio({ 901: 1 }, 9, 100, [4, 2, 1]), null);
		assert.equal(market.dealRatio({ 901: 1 }, 1, 0, [4, 2, 1]), null);
	});

	it('ignores zero or invalid weights instead of dividing by zero', () => {
		assert.equal(market.referenceValue({ 901: 10, 902: 99 }, [1, 0, 1]), 10);
		assert.equal(market.referenceValue({ 901: 10 }, ['x', 2, 1]), 0);
	});

	it('rates good, fair and poor deals', () => {
		assert.equal(market.dealRating(1.05), 'good');
		assert.equal(market.dealRating(1), 'fair');
		assert.equal(market.dealRating(0.95), 'fair');
		assert.equal(market.dealRating(0.949), 'bad');
		assert.equal(market.dealRating(null), 'none');
		assert.equal(market.dealRating(Infinity), 'none');
	});
});

describe('marketplace confirm text', () => {
	it('fills positional placeholders and turns \\n into new lines', () => {
		const text = market.fillTemplate('Buy %1$s from %2$s for %3$s?\\n%4$s (%5$s) %6$s', [
			'60.000 Crystal', 'Orion', '100.000 Metal', '4 HC', '236 Deuterium', '00:45:10'
		]);
		assert.equal(text, 'Buy 60.000 Crystal from Orion for 100.000 Metal?\n4 HC (236 Deuterium) 00:45:10');
	});

	it('supports reordered and bare placeholders', () => {
		assert.equal(market.fillTemplate('%2$s then %1$s', ['a', 'b']), 'b then a');
		assert.equal(market.fillTemplate('%s and %s', ['x', 'y']), 'x and y');
		assert.equal(market.fillTemplate('%3$s!', ['x']), '!');
	});
});

describe('marketplace filters and sorting', () => {
	const offers = [
		{ id: 'own', own: true, getIds: [901], payType: 2, block: 'Your offer', deal: 1, delivery: 50, end: 900 },
		{ id: 'cheap', own: false, getIds: [902], payType: 1, block: '', deal: 1.4, delivery: 300, end: 500 },
		{ id: 'fast', own: false, getIds: [901, 902], payType: 3, block: '', deal: 0.9, delivery: 100, end: 800 },
		{ id: 'blocked', own: false, getIds: [903], payType: 2, block: 'Not enough cargo ships', deal: null, delivery: 10, end: 100 }
	];
	const ids = (list) => list.map((o) => o.id);

	it('filters by what you get, what you pay, and buyability', () => {
		assert.deepEqual(ids(offers.filter((o) => market.matchesFilters(o, { get: '902' }))), ['cheap', 'fast']);
		assert.deepEqual(ids(offers.filter((o) => market.matchesFilters(o, { pay: '2' }))), ['own', 'blocked']);
		assert.deepEqual(ids(offers.filter((o) => market.matchesFilters(o, { onlyBuyable: true }))), ['cheap', 'fast']);
		assert.deepEqual(ids(offers.filter((o) => market.matchesFilters(o, {}))).length, 4);
	});

	it('sorts by best deal, fastest delivery, or ending soon with own offers first', () => {
		assert.deepEqual(ids(offers.slice().sort(market.compareOffers('deal'))), ['own', 'cheap', 'fast', 'blocked']);
		assert.deepEqual(ids(offers.slice().sort(market.compareOffers('delivery'))), ['own', 'blocked', 'fast', 'cheap']);
		assert.deepEqual(ids(offers.slice().sort(market.compareOffers('expires'))), ['own', 'blocked', 'cheap', 'fast']);
	});

	it('formats countdowns like the game clock', () => {
		assert.equal(market.formatSeconds(3725), '01:02:05');
		assert.equal(market.formatSeconds(-4), '00:00:00');
	});
});
