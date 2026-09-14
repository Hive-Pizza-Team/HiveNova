'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const tpl = fs.readFileSync(
	path.join(__dirname, '../../styles/templates/game/page.galaxy.default.tpl'),
	'utf8'
);

describe('galaxy compact mode keeps fleet quick-action triggers', () => {
	it('always emits planet/moon/debris sticky mission menus', () => {
		assert.match(tpl, /\{capture name="planetTooltip"\}/);
		assert.match(tpl, /\{capture name="moonTooltip"\}/);
		assert.match(tpl, /\{capture name="debrisTooltip"\}/);
		assert.match(
			tpl,
			/<a href="javascript:void\(0\)" class="tooltip_sticky[^"]*"[^>]*data-tooltip-content="\{\$smarty\.capture\.planetTooltip/
		);
		assert.match(
			tpl,
			/<a href="javascript:void\(0\)" class="tooltip_sticky[^"]*"[^>]*data-tooltip-content="\{\$smarty\.capture\.moonTooltip/
		);
		assert.match(
			tpl,
			/<a href="javascript:void\(0\)" class="tooltip_sticky"[^>]*data-tooltip-content="\{\$smarty\.capture\.debrisTooltip/
		);
	});

	it('does not replace occupied planet/moon/debris thumbs with inert compact spans', () => {
		assert.doesNotMatch(
			tpl,
			/\{else\}\s*<span[^>]*>\s*\{include file="shared\.planet-thumb\.tpl" texture=\$currentPlanet\.planet\.image/
		);
		assert.doesNotMatch(
			tpl,
			/\{else\}\s*<span[^>]*>\s*\{include file="shared\.planet-thumb\.tpl" texture='mond'/
		);
		assert.doesNotMatch(
			tpl,
			/\{else\}\s*\{include file="shared\.planet-thumb\.tpl" texture='debris'/
		);
	});

	it('still gates hive planet-viz extras on compact, not the mission menu', () => {
		assert.match(
			tpl,
			/class="tooltip_sticky\{if !\$galaxyCompact && \$dpath\|strstr:'\/hive\/'\} galaxy-planet-preview\{\/if\}"/
		);
		assert.match(tpl, /\{if \$dpath\|strstr:'\/hive\/' && empty\(\$galaxyCompact\)\}/);
	});
});
