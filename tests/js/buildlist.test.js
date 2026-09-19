'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const buildlist = require('../../scripts/game/buildlist.js');

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

describe('HiveNovaBuildlist queue toggle', () => {
	it('expands and collapses the remaining queue rows', () => {
		const root = {
			classList: createClassList(['infos1', 'buildlist--collapsible'])
		};
		const button = {
			attrs: {
				'aria-expanded': 'false',
				'data-label-more': 'Show remaining queue',
				'data-label-less': 'Hide remaining queue'
			},
			textContent: 'Show remaining queue',
			getAttribute(name) { return this.attrs[name] || null; },
			setAttribute(name, value) { this.attrs[name] = String(value); }
		};

		assert.equal(buildlist.toggleExpanded(root, button), true);
		assert.equal(root.classList.contains('is-expanded'), true);
		assert.equal(button.attrs['aria-expanded'], 'true');
		assert.equal(button.textContent, 'Hide remaining queue');

		assert.equal(buildlist.toggleExpanded(root, button), false);
		assert.equal(root.classList.contains('is-expanded'), false);
		assert.equal(button.attrs['aria-expanded'], 'false');
		assert.equal(button.textContent, 'Show remaining queue');
	});

	it('binds a click listener and ignores missing nodes', () => {
		const listeners = {};
		const button = {
			addEventListener(type, fn) { listeners[type] = fn; }
		};
		const root = { classList: createClassList(['buildlist--collapsible']) };

		assert.equal(buildlist.bindMoreToggle(null, button), false);
		assert.equal(buildlist.bindMoreToggle(root, null), false);
		assert.equal(buildlist.bindMoreToggle(root, button), true);
		listeners.click();
		assert.equal(root.classList.contains('is-expanded'), true);
	});

	it('keeps the Buildings queue toggle next to the active row', () => {
		const src = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/buildlist.js'),
			'utf8'
		);
		assert.match(src, /bindBuildlistMoreToggle/);
		assert.match(src, /buildlistMoreToggle/);
		assert.doesNotMatch(src, /overflow:\s*auto/);
	});
});
