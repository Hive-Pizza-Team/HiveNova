'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const topNav = require('../../scripts/game/top-nav.js');

describe('top-nav iframe breakout', () => {
	it('replaces the top location when a data-top-nav link is framed', () => {
		let assigned = null;
		const top = {
			location: {
				set href(url) {
					assigned = url;
				},
				get href() {
					return assigned;
				}
			}
		};
		const framed = {
			top,
			location: { href: 'game.php?page=playerCard' }
		};
		assert.equal(
			topNav.navigateTopIfFramed(framed, 'game.php?page=galaxy&galaxy=1&system=3'),
			true
		);
		assert.equal(assigned, 'game.php?page=galaxy&galaxy=1&system=3');
	});

	it('leaves a top-level window alone', () => {
		const topLevel = { location: { href: 'game.php?page=playerCard' } };
		topLevel.top = topLevel;
		assert.equal(
			topNav.navigateTopIfFramed(topLevel, 'game.php?page=galaxy&galaxy=1&system=3'),
			false
		);
	});

	it('installs a click handler that preventDefault only when framed', () => {
		const listeners = [];
		const framedTop = { location: {} };
		let assigned = null;
		Object.defineProperty(framedTop.location, 'href', {
			set(url) { assigned = url; },
			get() { return assigned; }
		});
		const framed = { top: framedTop, location: { href: 'game.php?page=playerCard' } };
		const doc = {
			addEventListener(type, handler) {
				listeners.push([type, handler]);
			}
		};
		topNav.installTopNav(doc, framed);
		assert.equal(listeners.length, 1);
		assert.equal(listeners[0][0], 'click');

		let prevented = false;
		const event = {
			target: {
				closest(selector) {
					assert.equal(selector, 'a[data-top-nav]');
					return { href: 'game.php?page=galaxy&galaxy=2&system=4' };
				}
			},
			preventDefault() {
				prevented = true;
			}
		};
		listeners[0][1](event);
		assert.equal(prevented, true);
		assert.equal(assigned, 'game.php?page=galaxy&galaxy=2&system=4');
	});
});

describe('top-nav wiring', () => {
	it('is loaded from the game header', () => {
		const header = fs.readFileSync(
			path.join(__dirname, '../../styles/templates/game/main.header.tpl'),
			'utf8'
		);
		assert.match(header, /scripts\/game\/top-nav\.js\?v=\{\$REV\}/);
	});
});
