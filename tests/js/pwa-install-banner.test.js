'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, '../../styles/resource/css/ingame/main.css');
const css = fs.readFileSync(cssPath, 'utf8');

describe('PWA install banner CSS (#566)', () => {
	it('fixes the banner on all viewports so the left nav cannot paint through it', () => {
		const bannerIdx = css.indexOf('.pwa-install-banner:not([hidden])');
		const mobileIdx = css.indexOf('@media screen and (max-width: 699px)');
		assert.notEqual(bannerIdx, -1, 'banner rule exists');
		assert.notEqual(mobileIdx, -1, 'mobile breakpoint exists');
		assert.ok(
			bannerIdx < mobileIdx,
			'base banner rule must apply on desktop, not only inside the mobile query'
		);
		const snippet = css.slice(bannerIdx, mobileIdx);
		assert.match(snippet, /position:\s*fixed/);
		assert.match(snippet, /z-index:\s*550/);
	});
});
