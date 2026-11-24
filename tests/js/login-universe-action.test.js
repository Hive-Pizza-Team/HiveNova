'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadMain(windowLike) {
	const src = fs.readFileSync(path.join(__dirname, '../../scripts/login/main.js'), 'utf8');
	// Strip HiveKeychainLogin bottom (uses browser globals); only need updateUrls IIFE.
	const iife = src.split('const HiveKeychainLogin')[0];
	vm.runInNewContext(iife, windowLike);
}

describe('login universe form action (#377)', () => {
	it('keeps register form action relative when data-uni-in-body=1', () => {
		const listeners = [];
		const form = {
			getAttribute(name) {
				if (name === 'data-uni-in-body') return '1';
				if (name === 'data-action') return 'index.php?page=register';
				if (name === 'action') return 'index.php?page=register';
				return null;
			},
			setAttribute(name, value) {
				this[name] = value;
			},
		};
		const select = {
			value: '6',
			closest() { return form; },
			addEventListener(type, fn) { listeners.push([type, fn]); },
		};
		const document = {
			querySelectorAll(sel) {
				if (sel === '#language .flags') return [];
				if (sel === '.changeAction') return [select];
				if (sel === '.fb_login') return [];
				return [];
			},
			addEventListener(type, fn) {
				if (type === 'DOMContentLoaded') fn();
			},
		};
		const sandbox = {
			document,
			window: {},
			LoginConfig: { isMultiUniverse: true, unisWildcast: false, basePath: 'https://novadev.hive.pizza/' },
			console,
		};
		sandbox.window = sandbox;
		loadMain(sandbox);
		assert.equal(form.action, 'index.php?page=register');
		assert.ok(!String(form.action).includes('uni6'));
	});
});
