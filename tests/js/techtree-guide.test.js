'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const techtree = require('../../scripts/game/techtree.js');

const root = path.join(__dirname, '../..');

describe('HiveNovaTechTree start-here filter', () => {
	it('treats empty starterIds as show-all', () => {
		assert.equal(techtree.isStarter({ starterIds: [] }, 21), true);
	});

	it('matches starter IDs as strings or numbers', () => {
		const cfg = { starterIds: [21, 113, 202] };
		assert.equal(techtree.isStarter(cfg, 21), true);
		assert.equal(techtree.isStarter(cfg, '113'), true);
		assert.equal(techtree.isStarter(cfg, 401), false);
	});

	it('defaults the page to Start here and next-unlock strip', () => {
		const tpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.techTree.default.tpl'), 'utf8');
		assert.match(tpl, /data-techtree-filter="start"/);
		assert.match(tpl, /tt_start_here/);
		assert.match(tpl, /tt_next_unlock/);
		assert.match(tpl, /tt_go/);
		const js = fs.readFileSync(path.join(root, 'scripts/game/techtree.js'), 'utf8');
		assert.match(js, /defaultFilter/);
	});

	it('marks the Tech Tree cookie on load', () => {
		const seen = [];
		const prev = global.HiveNovaTechTreeNudge;
		global.HiveNovaTechTreeNudge = {
			markSeen(name) { seen.push(name); }
		};
		try {
			techtree.markTechTreeSeen({ seenCookie: 'hn_techtree_seen' });
		} finally {
			global.HiveNovaTechTreeNudge = prev;
		}
		assert.deepEqual(seen, ['hn_techtree_seen']);
	});
});
