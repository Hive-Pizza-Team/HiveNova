'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

describe('galaxy iframe navigation', () => {
	const galaxySrc = fs.readFileSync(
		path.join(__dirname, '../../scripts/game/galaxy.js'),
		'utf8'
	);
	const tooltipSrc = fs.readFileSync(
		path.join(__dirname, '../../scripts/base/tooltip.js'),
		'utf8'
	);

	it('targets the top window when the galaxy form is inside an iframe', () => {
		assert.match(galaxySrc, /window\.top !== window/);
		assert.match(galaxySrc, /\$\('#galaxy_form'\)\.attr\('target', '_top'\)/);
	});

	it('scrubs Fancybox/tooltip leftovers on galaxy ready (#522)', () => {
		assert.match(galaxySrc, /scrubFancyboxAndTooltip/);
		assert.match(tooltipSrc, /function scrubFancyboxAndTooltip/);
		assert.match(tooltipSrc, /\$\(window\)\.on\('pageshow'/);
		assert.match(tooltipSrc, /scrubFancyboxAndTooltip\(\$\)/);
	});
});
