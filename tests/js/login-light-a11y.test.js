'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const css = fs.readFileSync(
	path.join(__dirname, '../../styles/resource/css/login/main.css'),
	'utf8'
);

describe('Login light layout a11y polish (L8/L9)', () => {
	it('raises the register page title with a design-token text color', () => {
		assert.match(css, /body\.light\s+#page\s*>\s*h1\s*\{[^}]*color:\s*var\(--color-text-bright/);
	});

	it('keeps language flag sprites clipped and sized so they cannot paint blank', () => {
		assert.match(css, /\.flags\s*\{[^}]*text-indent:\s*100%/);
		assert.match(css, /\.flags\s*\{[^}]*overflow:\s*hidden/);
		assert.match(css, /\.flags\s*\{[^}]*background-size:\s*16px\s+11px/);
		assert.match(css, /\.en\s*\{[^}]*background-image:\s*url\([^)]*us\.png\)/);
	});

	it('lets body.light grow so the theme html background does not band on tall pages', () => {
		assert.match(css, /body\.light\s*\{[^}]*height:\s*auto/);
		assert.match(css, /body\.light\s*\{[^}]*min-height:\s*100%/);
		assert.match(css, /html:has\(body\.light\)\s*\{[^}]*background-image:\s*none/);
	});
});
