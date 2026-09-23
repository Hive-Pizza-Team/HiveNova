'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

describe('galaxy iframe navigation', () => {
	it('targets the top window when the galaxy form is inside an iframe', () => {
		const src = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/galaxy.js'),
			'utf8'
		);
		assert.match(src, /window\.top !== window/);
		assert.match(src, /\$\('#galaxy_form'\)\.attr\('target', '_top'\)/);
	});
});
