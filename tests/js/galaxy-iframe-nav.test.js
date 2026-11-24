'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// galaxy.js touches jQuery on load; stub a minimal $ for Node require.
global.$ = function () {
	return {
		attr() { return this; },
		find() { return this; },
		on() { return this; }
	};
};
global.$.fn = {};

const galaxy = require('../../scripts/game/galaxy.js');

describe('galaxy iframe navigation', () => {
	it('targets the top window when the galaxy form is inside an iframe', () => {
		const src = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/galaxy.js'),
			'utf8'
		);
		assert.match(src, /breakGalaxyOutOfIframe/);
		assert.match(src, /win\.top\.location\.replace/);
		assert.match(src, /window\.top !== window/);
		assert.match(src, /\$\('#galaxy_form'\)\.attr\('target', '_top'\)/);
	});

	it('replaces the top location when galaxy is framed', () => {
		let replaced = null;
		const top = {
			location: {
				replace(url) {
					replaced = url;
				}
			}
		};
		const framed = {
			top,
			location: { href: 'game.php?page=galaxy&galaxy=1&system=3' }
		};
		assert.equal(galaxy.breakGalaxyOutOfIframe(framed), true);
		assert.equal(replaced, 'game.php?page=galaxy&galaxy=1&system=3');
	});

	it('leaves a top-level galaxy window alone', () => {
		const topLevel = { location: { href: 'game.php?page=galaxy' } };
		topLevel.top = topLevel;
		assert.equal(galaxy.breakGalaxyOutOfIframe(topLevel), false);
	});
});
