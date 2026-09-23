'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const search = require('../../scripts/game/search.js');

const searchJs = fs.readFileSync(path.join(__dirname, '../../scripts/game/search.js'), 'utf8');
const searchTpl = fs.readFileSync(path.join(__dirname, '../../styles/templates/game/page.search.default.tpl'), 'utf8');
const assetRevision = fs.readFileSync(path.join(__dirname, '../../includes/classes/AssetRevision.php'), 'utf8');

function collection(el) {
	const items = el ? [el] : [];
	const api = {
		length: items.length,
		_el: el,
		val(value) {
			if (!el) {
				return value === undefined ? '' : api;
			}
			if (value === undefined) {
				return el.value;
			}
			el.value = value;
			return api;
		},
		on(type, fn) {
			if (el) {
				el.listeners[type] = fn;
			}
			return api;
		},
		remove() {
			if (el && typeof el.onRemove === 'function') {
				el.onRemove();
			}
			return api;
		},
		empty() {
			if (el) {
				el.innerHTML = '';
			}
			return api;
		},
		html(data) {
			if (el) {
				el.innerHTML = data;
			}
			return api;
		},
		after(data) {
			if (el) {
				el.afterHTML = data;
			}
			return api;
		},
		first() {
			return api;
		},
		hide() {
			if (el) {
				el.hidden = true;
			}
			return api;
		},
		show() {
			if (el) {
				el.hidden = false;
			}
			return api;
		},
		prop(name, value) {
			if (!el) {
				return api;
			}
			if (value === undefined) {
				return el[name];
			}
			el[name] = value;
			return api;
		}
	};
	return api;
}

function createEnv(opts) {
	opts = opts || {};
	const nodes = {
		'#searchtext': { value: opts.query || '', listeners: {} },
		'#searchbutton': { listeners: {} },
		'#type': { value: opts.type || 'playername', listeners: {} },
		'#searchEmpty': { hidden: true, listeners: {} },
		'#loading': { hidden: true, listeners: {} },
		'#searchResults': { innerHTML: opts.resultsHtml || '', listeners: {} },
		'#resulttable': { removed: false, onRemove() { this.removed = true; nodes['#resulttable'] = null; }, listeners: {} },
		'#content > table:not(.hack), content > table:not(.hack)': {
			afterHTML: '',
			listeners: {}
		}
	};

	function $(selector) {
		if (selector === '#resulttable' && !nodes['#resulttable']) {
			return collection(null);
		}
		return collection(nodes[selector] || null);
	}
	$.fn = {};
	$.trim = function (value) {
		return String(value == null ? '' : value).replace(/^\s+|\s+$/g, '');
	};
	$.gets = [];
	$.get = function (url, cb) {
		$.gets.push({ url: url, cb: cb });
	};

	return { $, nodes };
}

describe('HiveNovaSearch helpers', () => {
	it('trims the search term', () => {
		assert.equal(search.searchQuery('  TideFen98  '), 'TideFen98');
		assert.equal(search.searchQuery(''), '');
		assert.equal(search.searchQuery(null), '');
		assert.equal(search.searchQuery(undefined), '');
	});

	it('treats Enter and numpad Enter as submit, not ignored keys', () => {
		assert.equal(search.isSubmitSearchKey({ keyCode: 13 }), true);
		assert.equal(search.isSubmitSearchKey({ keyCode: 108 }), true);
		assert.equal(search.isIgnoredSearchKey({ keyCode: 13 }), false);
		assert.equal(search.isIgnoredSearchKey({ keyCode: 108 }), false);
		assert.equal(search.isIgnoredSearchKey({ keyCode: 40 }), true);
		assert.equal(search.isIgnoredSearchKey({ keyCode: 65 }), false);
	});

	it('builds the AJAX result URL', () => {
		assert.equal(
			search.resultUrl('playername', 'TideFen98'),
			'game.php?page=search&mode=result&type=playername&search=TideFen98&ajax=1'
		);
		assert.equal(
			search.resultUrl('allytag', 'a&b'),
			'game.php?page=search&mode=result&type=allytag&search=a%26b&ajax=1'
		);
	});

	it('prefers the dedicated #searchResults mount', () => {
		assert.equal(search.resultsMountSelector(), '#searchResults');
		assert.match(search.fallbackMountSelector(), /#content > table:not\(\.hack\)/);
		assert.match(search.fallbackMountSelector(), /content > table:not\(\.hack\)/);

		const { $ } = createEnv();
		assert.equal(search.mountResultsHtml($, '<table id="resulttable">ok</table>'), 'searchResults');
		assert.equal($('#searchResults')._el.innerHTML, '<table id="resulttable">ok</table>');
	});

	it('falls back to the content table when #searchResults is missing', () => {
		const { $, nodes } = createEnv();
		nodes['#searchResults'] = null;
		assert.equal(search.mountResultsHtml($, '<table id="resulttable">ok</table>'), 'fallback');
		assert.equal(
			nodes['#content > table:not(.hack), content > table:not(.hack)'].afterHTML,
			'<table id="resulttable">ok</table>'
		);
	});
});

describe('HiveNovaSearch actions', () => {
	it('shows #searchEmpty on submit with a blank term', () => {
		const { $ } = createEnv({ query: '   ' });
		const gets = [];
		assert.equal(search.submitSearch($, { preventDefault() {} }, (url) => gets.push(url)), false);
		assert.equal($('#searchEmpty').prop('hidden'), false);
		assert.equal(gets.length, 0);
	});

	it('fetches and mounts results for a non-empty term', () => {
		const { $ } = createEnv({ query: 'TideFen98', type: 'playername' });
		const gets = [];
		assert.equal(search.runSearch($, (url, cb) => {
			gets.push(url);
			cb('<table id="resulttable"><tr><td>TideFen98</td></tr></table>');
		}), true);
		assert.equal(gets[0], search.resultUrl('playername', 'TideFen98'));
		assert.equal($('#searchEmpty').prop('hidden'), true);
		assert.match($('#searchResults')._el.innerHTML, /TideFen98/);
		assert.equal($('#loading')._el.hidden, true);
	});

	it('binds search button click and Enter to submit immediately', () => {
		const { $, nodes } = createEnv({ query: 'TideFen98' });
		const bound = search.bind($);
		assert.ok(bound);
		assert.equal(typeof nodes['#searchbutton'].listeners.click, 'function');
		assert.equal(typeof nodes['#searchtext'].listeners.keyup, 'function');

		nodes['#searchbutton'].listeners.click({ preventDefault() {} });
		assert.equal($.gets.length, 1);
		assert.equal($.gets[0].url, search.resultUrl('playername', 'TideFen98'));

		$.gets.length = 0;
		nodes['#searchtext'].listeners.keyup({ keyCode: 13, preventDefault() {} });
		assert.equal($.gets.length, 1);
		assert.equal($.gets[0].url, search.resultUrl('playername', 'TideFen98'));
	});

	it('debounces ordinary typing instead of ignoring it', () => {
		const { $, nodes } = createEnv({ query: 'Ti' });
		search.bind($);
		nodes['#searchtext'].listeners.keyup({ keyCode: 73 });
		assert.equal($.gets.length, 0);
		assert.equal(search.DEBOUNCE_MS, 300);
	});
});

describe('HiveNovaSearch wiring', () => {
	it('keeps the template mount, button, and empty-term hint', () => {
		assert.match(searchTpl, /id="searchbutton"/);
		assert.match(searchTpl, /id="searchEmpty"/);
		assert.match(searchTpl, /id="searchResults"/);
	});

	it('stays on the AssetRevision fingerprint list', () => {
		assert.match(assetRevision, /scripts\/game\/search\.js/);
		assert.match(searchJs, /#searchbutton/);
		assert.match(searchJs, /#searchResults/);
		assert.equal(/13,\s*\/\/ ENTER/.test(searchJs), false);
	});
});
