'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '../..');
const mainCss = fs.readFileSync(path.join(root, 'styles/resource/css/ingame/main.css'), 'utf8');
const tpl = fs.readFileSync(path.join(root, 'styles/templates/game/page.empire.default.tpl'), 'utf8');

describe('Empire mobile overflow (#799)', () => {
	it('keeps the desktop column scheme for wide viewports', () => {
		assert.match(mainCss, /\.empire-overview,\s*\.empire-matrix \{[\s\S]*?width:\s*max\(100%, calc\(11rem \+ 8rem \+ \(var\(--empire-planets, 1\) \* 6\.5rem\)\)\)/);
		assert.match(mainCss, /\.empire-col-label \{\s*width:\s*11rem/);
		assert.match(mainCss, /\.empire-col-total \{\s*width:\s*8rem/);
		assert.match(mainCss, /\.empire-col-planet \{\s*width:\s*6\.5rem/);
	});

	it('contains sheet overflow and shrinks mobile columns to fit 390px', () => {
		assert.match(mainCss, /\.empire-sheet \{[\s\S]*?min-width:\s*0/);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?content:has\(\.empire-sheet\) \{[\s\S]*?overflow-x:\s*hidden/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?\.empire-overview,\s*\.empire-matrix \{[\s\S]*?width:\s*100%[\s\S]*?min-width:\s*calc\(6\.5rem \+ 5\.25rem \+ \(var\(--empire-planets, 1\) \* 5\.25rem\)\)/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?\.empire-col-label \{\s*width:\s*6\.5rem/
		);
		assert.match(
			mainCss,
			/@media screen and \(max-width: 699px\) \{[\s\S]*?\.empire-resource-row \.empire-resource-rate \{\s*display:\s*block/
		);
	});

	it('marks the hourly rate so it can wrap under the amount on mobile', () => {
		assert.match(tpl, /class="empire-resource-rate"/);
		assert.equal((tpl.match(/class="empire-resource-rate"/g) || []).length, 2);
	});
});
