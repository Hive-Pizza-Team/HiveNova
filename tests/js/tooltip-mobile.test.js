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

	it('keeps hover tooltips for a fine pointer and suppresses them after a tap', () => {
		assert.equal(tooltip.shouldUseHoverTooltip(false, false), true);
		assert.equal(tooltip.shouldUseHoverTooltip(true, false), false);
		assert.equal(tooltip.shouldUseHoverTooltip(false, true), false);
		assert.equal(tooltip.isRecentTooltipTouch(1_000, 0), false);
		assert.equal(
			tooltip.isRecentTooltipTouch(1_100, 1_000, tooltip.TOOLTIP_TOUCH_HOVER_GUARD_MS),
			true
		);
		assert.equal(
			tooltip.isRecentTooltipTouch(1_000 + tooltip.TOOLTIP_TOUCH_HOVER_GUARD_MS, 1_000),
			false
		);
	});

	it('swallows the synthesized click after touchend on a wide fine-pointer viewport', () => {
		assert.equal(tooltip.tooltipClickAction(true, false, false), 'swallow');
		assert.equal(tooltip.tooltipClickAction(false, true, false), 'swallow');
		assert.equal(tooltip.tooltipClickAction(false, false, false), 'ignore');
		assert.equal(tooltip.tooltipClickAction(false, false, true), 'open');
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
		assert.match(src, /position:\s*'fixed'/);
	});

	it('does not gate touchend on the width breakpoint', () => {
		const touchend = src.slice(src.indexOf(".live('touchend'"), src.indexOf(".live('click'"));
		assert.doesNotMatch(touchend, /if\s*\(\s*!isMobileTooltip\(\)\s*\)/);
		assert.match(touchend, /openMobileTooltip/);
	});

	it('does not assign href="#" on tooltip triggers', () => {
		assert.doesNotMatch(src, /attr\('href',\s*'#'\)/);
		assert.match(src, /javascript:void\(0\)/);
	});
});

describe('mobile tooltip overlay css', () => {
	const css = fs.readFileSync(
		path.join(__dirname, '../../styles/resource/css/ingame/main.css'),
		'utf8'
	);

	it('applies the fixed tap overlay outside the 699px breakpoint', () => {
		const ruleAt = css.indexOf('#tooltip.tooltip-mobile-active');
		const mobileAt = css.indexOf('@media screen and (max-width: 699px)');
		assert.ok(ruleAt !== -1);
		assert.ok(mobileAt !== -1);
		assert.ok(ruleAt < mobileAt);
		const rule = css.slice(ruleAt, css.indexOf('#tooltip.tooltip-mobile-active table'));
		assert.match(rule, /position:\s*fixed/);
		assert.match(rule, /max-height:\s*70vh/);
	});
});
