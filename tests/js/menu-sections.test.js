'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const menuSections = require('../../scripts/game/menu-sections.js');

function createClassList(initial) {
	const classes = new Set(initial || []);
	return {
		add(name) { classes.add(name); },
		remove(name) { classes.delete(name); },
		toggle(name, force) {
			const on = typeof force === 'boolean' ? force : !classes.has(name);
			if (on) classes.add(name);
			else classes.delete(name);
			return on;
		},
		contains(name) { return classes.has(name); }
	};
}

function createMemoryStorage(seed) {
	const map = new Map(Object.entries(seed || {}));
	return {
		getItem(key) { return map.has(key) ? map.get(key) : null; },
		setItem(key, value) { map.set(key, String(value)); },
		removeItem(key) { map.delete(key); }
	};
}

describe('HiveNovaMenuSections', () => {
	it('defaults Advanced closed and keeps Basics / Administration open', () => {
		assert.equal(menuSections.resolveExpanded('basics', {}, false), true);
		assert.equal(menuSections.resolveExpanded('empire', {}, false), false);
		assert.equal(menuSections.resolveExpanded('account', {}, false), true);
	});

	it('forces open the section that contains the active page', () => {
		assert.equal(menuSections.resolveExpanded('empire', { empire: false }, true), true);
	});

	it('honors persisted collapse state when the section is inactive', () => {
		assert.equal(menuSections.resolveExpanded('basics', { basics: false }, false), false);
		assert.equal(menuSections.resolveExpanded('empire', { empire: true }, false), true);
	});

	it('toggles is-collapsed and aria-expanded on the section button', () => {
		const button = {
			attrs: { 'aria-expanded': 'true' },
			getAttribute(name) { return this.attrs[name] || null; },
			setAttribute(name, value) { this.attrs[name] = String(value); }
		};
		const group = {
			classList: createClassList([]),
			querySelector(sel) { return sel === '[data-menu-toggle]' ? button : null; }
		};

		assert.equal(menuSections.setExpanded(group, false), false);
		assert.equal(group.classList.contains('is-collapsed'), true);
		assert.equal(button.attrs['aria-expanded'], 'false');

		assert.equal(menuSections.setExpanded(group, true), true);
		assert.equal(group.classList.contains('is-collapsed'), false);
		assert.equal(button.attrs['aria-expanded'], 'true');
	});

	it('persists toggles through localStorage', () => {
		const storage = createMemoryStorage();
		const listeners = {};
		const prefs = {};
		const button = {
			attrs: { 'aria-expanded': 'true' },
			getAttribute(name) { return this.attrs[name] || null; },
			setAttribute(name, value) { this.attrs[name] = String(value); },
			addEventListener(type, fn) { listeners[type] = fn; }
		};
		const group = {
			classList: createClassList([]),
			getAttribute(name) { return name === 'data-menu-group' ? 'empire' : null; },
			querySelector(sel) { return sel === '[data-menu-toggle]' ? button : null; }
		};

		assert.equal(menuSections.bindToggle(group, function (id, next) {
			prefs[id] = next;
			menuSections.writePrefs(prefs, storage);
		}), true);
		listeners.click();
		assert.equal(group.classList.contains('is-collapsed'), true);
		assert.equal(
			JSON.parse(storage.getItem(menuSections.STORAGE_KEY)).empire,
			false
		);
		listeners.click();
		assert.equal(group.classList.contains('is-collapsed'), false);
		assert.equal(
			JSON.parse(storage.getItem(menuSections.STORAGE_KEY)).empire,
			true
		);
	});

	it('ships with collapsible nav markup and hive toggle styles', () => {
		const root = path.join(__dirname, '../..');
		const nav = fs.readFileSync(path.join(root, 'styles/templates/game/main.navigation.tpl'), 'utf8');
		const hiveCss = fs.readFileSync(path.join(root, 'styles/theme/hive/formate.css'), 'utf8');
		const mainCss = fs.readFileSync(path.join(root, 'styles/resource/css/ingame/main.css'), 'utf8');
		const src = fs.readFileSync(path.join(root, 'scripts/game/menu-sections.js'), 'utf8');

		assert.match(nav, /menu-section__toggle/);
		assert.match(nav, /data-menu-group="empire"/);
		assert.match(nav, /class="menu-pin"/);
		assert.match(hiveCss, /#menu \.menu-section__toggle:hover/);
		assert.match(mainCss, /menu-group\.is-collapsed \.menu-group__list/);
		assert.match(src, /hivenova\.menuSections\.v1/);
	});
});
