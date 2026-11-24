'use strict';

const { describe, it, before, after } = require('node:test');
const assert = require('node:assert/strict');

function createEl(tag) {
	const children = [];
	const attrs = {};
	const node = {
		tagName: String(tag).toUpperCase(),
		className: '',
		textContent: '',
		children,
		attrs,
		setAttribute(name, value) {
			attrs[name] = String(value);
		},
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
		},
		appendChild(child) {
			children.push(child);
			return child;
		},
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		}
	};
	return node;
}

function makeList(ids) {
	const children = [];
	const list = {
		querySelector(sel) {
			if (sel === '.lobby-feed-empty') {
				return children.find((c) => c.className === 'lobby-feed-empty') || null;
			}
			return null;
		},
		querySelectorAll(sel) {
			if (sel === '[data-event-id]') {
				return children.filter((c) => c.getAttribute && c.getAttribute('data-event-id'));
			}
			if (sel === '.lobby-feed-item.is-new') {
				return children.filter((c) => String(c.className).indexOf('is-new') !== -1);
			}
			return [];
		},
		insertBefore(node) {
			children.unshift(node);
			return node;
		},
		firstChild: null,
		_children: children
	};
	for (const id of ids) {
		children.push({
			className: 'lobby-feed-item',
			getAttribute(name) {
				return name === 'data-event-id' ? String(id) : null;
			}
		});
	}
	return list;
}

describe('lobby feed empty headlines', () => {
	let feed;
	let previousDocument;
	let previousWindow;

	before(() => {
		previousDocument = global.document;
		previousWindow = global.window;
		global.document = {
			createElement: createEl,
			getElementById() {
				return null;
			},
			readyState: 'complete'
		};
		global.window = {
			setTimeout() {
				return 0;
			},
			clearInterval() {},
			setInterval() {
				return 0;
			}
		};
		// Require after DOM stubs so module boot does not throw.
		feed = require('../../scripts/login/lobby-feed.js');
	});

	after(() => {
		global.document = previousDocument;
		global.window = previousWindow;
		delete require.cache[require.resolve('../../scripts/login/lobby-feed.js')];
	});

	it('keeps an empty headline cell so columns stay aligned', () => {
		const list = makeList([]);
		feed.mergeEvents(list, [{
			id: 3173,
			ts: 1,
			time: '23:11',
			eventType: 'Battle',
			size: 'Skirmish',
			outcome: 'Attackers prevailed',
			headline: '',
			universe: 'Universe 1',
			universeId: 1
		}]);

		const row = list._children[0];
		assert.equal(row.getAttribute('data-event-id'), '3173');
		assert.equal(row.children.length, 6);
		assert.equal(row.children[1].className, 'lobby-feed-headline');
		assert.equal(row.children[1].textContent, '');
		assert.equal(row.children[2].className, 'lobby-feed-size');
		assert.equal(row.children[4].className, 'lobby-feed-outcome');
	});
});
