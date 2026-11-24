'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');

const root = path.join(__dirname, '../..');
const mainJs = fs.readFileSync(path.join(root, 'scripts/login/main.js'), 'utf8');
const assetRevision = fs.readFileSync(path.join(root, 'includes/classes/AssetRevision.php'), 'utf8');
const navTpl = fs.readFileSync(path.join(root, 'styles/templates/login/main.navigation.tpl'), 'utf8');

describe('login language switcher', () => {
	it('persists explicit choices via lang_choice, not legacy lang', () => {
		assert.match(mainJs, /LANG_COOKIE:\s*'lang_choice'/);
		assert.match(mainJs, /setCookie\(Login\.LANG_COOKIE/);
		assert.match(mainJs, /document\.cookie = 'lang=; expires=/);
		assert.equal(/setCookie\(\s*'lang'\s*,/.test(mainJs), false);
	});

	it('exposes a labeled select and is fingerprinted for cache busting', () => {
		assert.match(navTpl, /class="language-compact"/);
		assert.match(navTpl, /id="language-compact"/);
		assert.match(navTpl, /\$LNG\.registerLanguage/);
		assert.match(assetRevision, /scripts\/login\/main\.js/);
		assert.match(assetRevision, /styles\/resource\/css\/login\/main\.css/);
	});
});
