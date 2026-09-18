'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const nudge = require('../../scripts/game/techtree-nudge.js');

describe('HiveNovaTechTreeNudge', () => {
	it('exports the shared cookie name', () => {
		assert.equal(nudge.COOKIE, 'hn_techtree_seen');
	});

	it('markSeen writes the cookie via the setter', () => {
		const calls = [];
		nudge.markSeen('hn_techtree_seen', (name, value) => {
			calls.push([name, value]);
		});
		assert.deepEqual(calls, [['hn_techtree_seen', '1']]);
	});

	it('dismiss hides the banner and marks seen', () => {
		const listeners = {};
		const rootEl = {
			hidden: false,
			querySelector(sel) {
				if (sel !== '[data-techtree-nudge-dismiss]') {
					return null;
				}
				return {
					addEventListener(type, fn) {
						listeners[type] = fn;
					}
				};
			}
		};
		let marked = false;
		assert.equal(nudge.bind(rootEl, () => { marked = true; }), true);
		listeners.click();
		assert.equal(marked, true);
		assert.equal(rootEl.hidden, true);
	});

	it('bind returns false without a dismiss control', () => {
		assert.equal(nudge.bind(null), false);
		assert.equal(nudge.bind({ querySelector() { return null; } }), false);
	});
});
