'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '../..');
const mainCss = fs.readFileSync(path.join(root, 'styles/resource/css/ingame/main.css'), 'utf8');
const tpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.statistics.default.tpl'), 'utf8');

const mobileIdx = mainCss.indexOf('@media screen and (max-width: 699px)');
assert.notEqual(mobileIdx, -1, 'mobile breakpoint exists');
const desktopCss = mainCss.slice(0, mobileIdx);

describe('Statistics mobile overflow (#805)', () => {
	it('keeps desktop .table519 max-width unchanged', () => {
		assert.match(desktopCss, /\.table519 \{[\s\S]*?max-width:\s*519px !important/);
		assert.equal(desktopCss.includes('body#statistics .statistics-sheet'), false);
		assert.equal(desktopCss.includes('body#statistics .table519'), false);
	});

	it('wraps the ranking table in a statistics-sheet', () => {
		assert.match(tpl, /class="statistics-sheet"/);
		assert.match(tpl, /statistics-table--players/);
		assert.match(tpl, /statistics-table--alliances/);
		assert.match(tpl, /class="table519 statistics-table/);
	});

	it('contains page overflow and compacts mobile columns under body#statistics', () => {
		assert.match(mainCss, /\.statistics-sheet \{[\s\S]*?min-width:\s*0/);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics content \{[\s\S]*?overflow-x:\s*hidden/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.statistics-sheet \{[\s\S]*?overflow-x:\s*auto/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.table519 \{[\s\S]*?max-width:\s*100% !important/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.statistics-sheet \.table519 \{[\s\S]*?table-layout:\s*fixed/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.statistics-table--players td:last-child/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.statistics-table--players th:last-child,\s*body#statistics \.statistics-table--players td:last-child \{[\s\S]*?white-space:\s*nowrap/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?body#statistics \.statistics-table--alliances td:nth-child\(5\)/
		);
	});
});
