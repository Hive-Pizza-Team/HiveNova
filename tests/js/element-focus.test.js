'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const focus = require('../../scripts/game/element-focus.js');

function createClassList(initial) {
	const classes = new Set(initial || []);
	return {
		add(name) { classes.add(name); },
		remove(name) { classes.delete(name); },
		contains(name) { return classes.has(name); },
		toArray() { return [...classes]; }
	};
}

function createEl(id) {
	return {
		id,
		classList: createClassList(),
		scrolled: false,
		clickCount: 0,
		click() { this.clickCount += 1; },
		scrollIntoView() { this.scrolled = true; }
	};
}

function makeDoc(ids) {
	const nodes = {};
	(ids || []).forEach((id) => {
		nodes[id] = createEl(id);
	});
	return {
		nodes,
		getElementById(id) {
			return nodes[id] || null;
		}
	};
}

describe('HiveNovaElementFocus', () => {
	it('accepts research/ship/building/officer fragments only', () => {
		assert.equal(focus.fragmentId('#t115'), 't115');
		assert.equal(focus.fragmentId('s202'), 's202');
		assert.equal(focus.fragmentId('#g21'), 'g21');
		assert.equal(focus.fragmentId('#o603'), 'o603');
		assert.equal(focus.fragmentId('#h202'), '');
		assert.equal(focus.fragmentId('#menu'), '');
		assert.equal(focus.fragmentId(''), '');
	});

	it('clicks All filters then highlights and scrolls the target', () => {
		const doc = makeDoc(['t115', 'lab5', 'ship3', 'btn3']);
		assert.equal(focus.focusById(doc, 't115'), true);
		assert.equal(doc.nodes.lab5.clickCount, 1);
		assert.equal(doc.nodes.ship3.clickCount, 1);
		assert.equal(doc.nodes.btn3.clickCount, 1);
		assert.equal(doc.nodes.t115.classList.contains('element-focus'), true);
		assert.equal(doc.nodes.t115.scrolled, true);
	});

	it('returns false when the hash target is missing', () => {
		const doc = makeDoc(['lab5']);
		assert.equal(focus.focusById(doc, 't115'), false);
	});

	it('boots from location.hash', () => {
		const doc = makeDoc(['s202', 'ship3']);
		assert.equal(focus.boot(doc, { hash: '#s202' }), true);
		assert.equal(doc.nodes.s202.classList.contains('element-focus'), true);
		assert.equal(focus.boot(doc, { hash: '' }), false);
	});
});
