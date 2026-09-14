'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const tooltip = require('../../scripts/base/tooltip.js');

describe('mobile tooltip helpers', () => {
	it('treats missing and hash hrefs as inert javascript:void(0)', () => {
		assert.equal(tooltip.normalizeTooltipTriggerHref(undefined), tooltip.MOBILE_TOOLTIP_INERT_HREF);
		assert.equal(tooltip.normalizeTooltipTriggerHref(''), tooltip.MOBILE_TOOLTIP_INERT_HREF);
		assert.equal(tooltip.normalizeTooltipTriggerHref('#'), tooltip.MOBILE_TOOLTIP_INERT_HREF);
		assert.equal(
			tooltip.normalizeTooltipTriggerHref('?page=fleetTable'),
			'?page=fleetTable'
		);
	});

	it('uses coarse-pointer media so landscape phones get tap tooltips', () => {
		const query = tooltip.mobileTooltipMediaQuery();
		assert.match(query, /max-width:\s*699px/);
		assert.match(query, /hover:\s*none/);
		assert.match(query, /pointer:\s*coarse/);
	});

	it('ignores dismiss during the opening-gesture guard', () => {
		const openedAt = 1_000;
		const guardUntil = tooltip.armMobileTooltipGuard(openedAt, tooltip.MOBILE_TOOLTIP_GUARD_MS);
		assert.equal(tooltip.isMobileTooltipGuardActive(1_100, guardUntil), true);
		assert.equal(tooltip.shouldDismissMobileTooltip(false, 1_100, guardUntil), false);
		assert.equal(tooltip.shouldDismissMobileTooltip(false, openedAt + tooltip.MOBILE_TOOLTIP_GUARD_MS + 1, guardUntil), true);
		assert.equal(tooltip.shouldDismissMobileTooltip(true, 1_500, guardUntil), false);
	});

	it('treats a short finger move as a tap and a drag as a scroll', () => {
		const start = { x: 10, y: 20 };
		assert.equal(tooltip.isStationaryTooltipTouch(start, { x: 14, y: 22 }), true);
		assert.equal(tooltip.isStationaryTooltipTouch(start, { x: 40, y: 80 }), false);
		assert.equal(tooltip.isStationaryTooltipTouch(null, { x: 0, y: 0 }), true);
	});
});

describe('tooltip.js mobile bindings', () => {
	const src = fs.readFileSync(
		path.join(__dirname, '../../scripts/base/tooltip.js'),
		'utf8'
	);

	it('opens sticky tips on touchend with a click fallback', () => {
		assert.match(src, /\.live\('touchend'/);
		assert.match(src, /\.live\('click'/);
		assert.match(src, /openMobileTooltip/);
	});

	it('does not assign href="#" on tooltip triggers', () => {
		assert.doesNotMatch(src, /attr\('href',\s*'#'\)/);
		assert.match(src, /javascript:void\(0\)/);
	});
});
