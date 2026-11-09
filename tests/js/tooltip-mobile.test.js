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

	it('ignores blank tooltip content so empty #tooltip chrome is not shown', () => {
		assert.equal(tooltip.tooltipTriggerContent(undefined), '');
		assert.equal(tooltip.tooltipTriggerContent('   '), '');
		assert.equal(tooltip.tooltipTriggerContent('<span>Spy</span>'), '<span>Spy</span>');
	});

	it('scrubs Fancybox and empty tooltip leftovers after playercard navigation', () => {
		const calls = [];
		const tip = {
			stop() {
				calls.push('stop');
				return this;
			},
			hide() {
				calls.push('hide');
				return this;
			},
			empty() {
				calls.push('empty');
				return this;
			},
			removeData() {
				calls.push('removeData');
				return this;
			},
			css() {
				return this;
			},
			removeClass() {
				calls.push('removeClass');
				return this;
			},
			removeAttr() {
				return this;
			},
		};
		const overlay = { hide() { calls.push('overlay'); } };
		const wrap = { hide() { calls.push('wrap'); } };
		const root$ = Object.assign(
			(sel) => {
				if (sel === '#tooltip') return tip;
				if (sel === '#fancybox-overlay') return overlay;
				if (sel === '#fancybox-wrap') return wrap;
				return { hide() { return this; } };
			},
			{
				fancybox: {
					close() {
						calls.push('close');
					},
				},
			}
		);
		assert.equal(tooltip.scrubFancyboxAndTooltip(root$), true);
		assert.ok(calls.includes('close'));
		assert.ok(calls.includes('overlay'));
		assert.ok(calls.includes('wrap'));
		assert.ok(calls.includes('stop'));
		assert.ok(calls.includes('hide'));
		assert.ok(calls.includes('empty'));
		assert.ok(calls.includes('removeData'));
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

	it('always scrubs dialog chrome on pageshow', () => {
		assert.match(src, /scrubFancyboxAndTooltip/);
		assert.match(src, /\$\(window\)\.on\('pageshow'/);
		assert.doesNotMatch(src, /overlay\.is\(':visible'\)/);
	});

	it('resets overlay and skips empty sticky content before show', () => {
		assert.match(src, /tooltipTriggerContent\(\$\(this\)\.attr\('data-tooltip-content'\)\)/);
		assert.match(src, /resetTooltipOverlay\(tip\)/);
		assert.match(src, /if \(!content\) \{\s*return;/);
	});
});
