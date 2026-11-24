'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const offer = require('../../scripts/game/market-offer.js');

const RATIO = [4, 2, 1];

describe('trade form allowed price range (mirrors MarketAbuseRules)', () => {
	it('gives 0.5x..2x of the goods value in the wanted resource', () => {
		// 60k crystal = 30k units
		const goods = offer.goodsValue(0, { 902: 60000 }, 0, RATIO);
		assert.equal(goods, 30000);
		assert.deepEqual(offer.allowedRange(goods, 1, RATIO, 0.5, 2), { min: 60000, max: 240000 });
		assert.deepEqual(offer.allowedRange(goods, 2, RATIO, 0.5, 2), { min: 30000, max: 120000 });
		assert.deepEqual(offer.allowedRange(goods, 3, RATIO, 0.5, 2), { min: 15000, max: 60000 });
	});

	it('uses the ships build value for a fleet offer', () => {
		assert.equal(offer.goodsValue(1, { 901: 999999 }, 1250, RATIO), 1250);
		assert.deepEqual(offer.allowedRange(1250, 2, RATIO, 0.5, 2), { min: 1250, max: 5000 });
	});

	it('returns null without goods or with an unknown resource', () => {
		assert.equal(offer.allowedRange(0, 1, RATIO, 0.5, 2), null);
		assert.equal(offer.allowedRange(100, 4, RATIO, 0.5, 2), null);
		assert.equal(offer.allowedRange(100, 1, RATIO, 0, 2), null);
	});

	it('ignores junk in the inputs', () => {
		assert.equal(offer.referenceValue({ 901: 'abc', 902: '-5', 903: '10' }, RATIO), 10);
	});
});

describe('trade form listing fee', () => {
	it('is 1% of ships + cargo in deuterium, rounded up, at least 1', () => {
		// 10 HC = 45k units, 400k metal = 100k units
		assert.equal(offer.listingFee(45000, { 901: 400000 }, RATIO, 0.01), 1450);
		assert.equal(offer.listingFee(0, { 903: 10 }, RATIO, 0.01), 1);
		assert.equal(offer.listingFee(1250, { 903: 1 }, RATIO, 0.01), 13);
	});
});

describe('trade form text helpers', () => {
	it('formats numbers with dots and fills placeholders', () => {
		assert.equal(offer.formatNumber(1234567), '1.234.567');
		assert.equal(offer.fillTemplate('Allowed price: %1$s – %2$s %3$s', ['1', '2', 'Metal']), 'Allowed price: 1 – 2 Metal');
		assert.equal(offer.fillTemplate('fee %1$s (%2$s% of it)', ['5', '1']), 'fee 5 (1% of it)');
	});
});
