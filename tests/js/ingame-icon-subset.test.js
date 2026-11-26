'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '../..');
const script = path.join(root, 'scripts/dev/build-ingame-icon-subset.mjs');
const bottomnav = fs.readFileSync(
	path.join(root, 'styles/templates/game/main.bottomnav.tpl'),
	'utf8',
);
const topnav = fs.readFileSync(
	path.join(root, 'styles/templates/game/main.topnav.tpl'),
	'utf8',
);

describe('ingame Font Awesome subset', () => {
	it('covers every fa-* class in game templates, including font glyphs', () => {
		const result = spawnSync(process.execPath, [script], {
			cwd: root,
			encoding: 'utf8',
		});
		assert.equal(
			result.status,
			0,
			result.stdout + result.stderr,
		);
		assert.match(result.stdout, /OK — subset CSS and woff\/woff2/);
	});

	it('keeps the mobile Tech Tree icon on fa-sitemap and the other nav icons', () => {
		assert.match(bottomnav, /fas fa-sitemap/);
		assert.match(bottomnav, /fas fa-home/);
		assert.match(bottomnav, /fas fa-industry/);
		assert.match(bottomnav, /fas fa-rocket/);
		assert.match(bottomnav, /fas fa-star/);
		assert.match(bottomnav, /fas fa-ellipsis-h/);
		assert.match(topnav, /fas fa-bars/);
	});
});
