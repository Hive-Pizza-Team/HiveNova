'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '../..');
const css = fs.readFileSync(path.join(root, 'styles/resource/css/ingame/main.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'scripts/game/achievement-celebration.js'), 'utf8');
const tpl = fs.readFileSync(path.join(root, 'styles/templates/game/layout.full.tpl'), 'utf8');

describe('Achievement celebration inactive a11y (L7)', () => {
	it('marks the root hidden in the layout template', () => {
		assert.match(
			tpl,
			/id="achievement-celebration-root"[^>]*\bhidden\b/
		);
		assert.match(
			tpl,
			/id="achievement-celebration-root"[^>]*aria-hidden="true"/
		);
	});

	it('forces display:none while [hidden] so flex does not expose the modal', () => {
		const idx = css.indexOf('.achievement-celebration[hidden]');
		assert.notEqual(idx, -1, '[hidden] rule exists');
		assert.match(css.slice(idx, idx + 120), /display:\s*none\s*!important/);
	});

	it('toggles hidden when opening and closing the celebration', () => {
		assert.match(js, /removeAttr\(\s*['"]hidden['"]\s*\)/);
		assert.match(js, /hidden:\s*['"]hidden['"]/);
		assert.match(js, /aria-hidden['"]:\s*['"]true['"]/);
		assert.match(js, /attr\(\s*['"]aria-hidden['"]\s*,\s*['"]false['"]\s*\)/);
	});
});
