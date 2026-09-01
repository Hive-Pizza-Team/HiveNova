'use strict';

const { describe, it, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');

function syncGameClockToWall(serverTime, relativeTimeRef) {
	var nowSec = Math.floor(Date.now() / 1000);
	var drift = nowSec - relativeTimeRef.value;
	if (drift > 0) {
		serverTime.setTime(serverTime.getTime() + drift * 1000);
		relativeTimeRef.value = nowSec;
	}
}

function makeElement(initialHtml) {
	return {
		classes: {},
		attrs: {},
		_html: initialHtml,
		hasClass(name) {
			return !!this.classes[name];
		},
		addClass(name) {
			this.classes[name] = true;
		},
		attr(name, value) {
			if (value !== undefined) {
				this.attrs[name] = value;
			}
			return this.attrs[name];
		},
		html(value) {
			if (value !== undefined) {
				this._html = value;
			}
			return this._html;
		},
		data(name) {
			return this.attrs['data-' + name];
		}
	};
}

function mockJQueryCollection(nodes) {
	var collection = {
		length: nodes.length,
		_nodes: nodes,
		hasClass(name) {
			return nodes.some(function (node) {
				return node.hasClass(name);
			});
		},
		addClass(name) {
			nodes.forEach(function (node) {
				node.addClass(name);
			});
			return collection;
		},
		attr(name, value) {
			if (value !== undefined) {
				nodes.forEach(function (node) {
					node.attr(name, value);
				});
			}
			return nodes[0] ? nodes[0].attr(name) : undefined;
		},
		html(value) {
			if (value !== undefined) {
				nodes.forEach(function (node) {
					node.html(value);
				});
			}
			return nodes[0] ? nodes[0].html() : '';
		},
		filter(selector) {
			if (selector === '.res_current_max') {
				return mockJQueryCollection(nodes.filter(function (node) {
					return node.hasClass('res_current_max');
				}));
			}
			return mockJQueryCollection([]);
		},
		each(fn) {
			nodes.forEach(function (node, index) {
				fn.call(node, index, node);
			});
			return collection;
		},
		first() {
			return nodes[0] || makeElement('');
		}
	};
	return collection;
}

describe('syncGameClockToWall', () => {
	it('advances serverTime by full wall-clock drift', () => {
		var serverTime = new Date(2020, 0, 1, 12, 0, 0);
		var relativeTimeRef = { value: Math.floor(Date.now() / 1000) - 120 };
		var before = serverTime.getTime();
		syncGameClockToWall(serverTime, relativeTimeRef);
		assert.equal(serverTime.getTime() - before, 120000);
		assert.equal(relativeTimeRef.value, Math.floor(Date.now() / 1000));
	});

	it('does nothing when already in sync', () => {
		var serverTime = new Date(2020, 0, 1, 12, 0, 0);
		var relativeTimeRef = { value: Math.floor(Date.now() / 1000) };
		var before = serverTime.getTime();
		syncGameClockToWall(serverTime, relativeTimeRef);
		assert.equal(serverTime.getTime(), before);
	});
});

describe('HiveNovaTopnav', () => {
	var topnav;
	var savedGlobals;

	beforeEach(() => {
		savedGlobals = {
			$: global.$,
			window: global.window,
			document: global.document,
			serverTime: global.serverTime,
			startTime: global.startTime,
			viewShortlyNumber: global.viewShortlyNumber,
			NumberGetHumanReadable: global.NumberGetHumanReadable,
			shortly_number: global.shortly_number,
			syncGameClockToWall: global.syncGameClockToWall
		};

		delete global.window;
		delete global.document;
		delete global.syncGameClockToWall;

		global.serverTime = new Date(2020, 0, 1, 12, 0, 0);
		global.startTime = global.serverTime.getTime();
		global.viewShortlyNumber = true;
		global.NumberGetHumanReadable = function (value) { return String(value); };
		global.shortly_number = function (value) { return 'S' + value; };

		delete require.cache[require.resolve('../../scripts/game/topnav.js')];
		topnav = require('../../scripts/game/topnav.js');
		topnav._resetForTests();
	});

	afterEach(() => {
		Object.assign(global, savedGlobals);
		delete require.cache[require.resolve('../../scripts/game/topnav.js')];
	});

	it('computeResource uses elapsed server time', () => {
		global.serverTime = new Date(global.startTime + 3600 * 1000);
		var value = topnav.computeResource({
			available: 1000,
			production: 3600,
			limit: [0, 100000]
		});
		assert.equal(value, 4600);
	});

	it('updateElements updates every node with the same id', () => {
		var desktop = makeElement('0');
		var mobile = makeElement('0');
		global.$ = function (arg) {
			if (typeof arg === 'string') {
				assert.equal(arg, '[id="current_metal"]');
				return mockJQueryCollection([desktop, mobile]);
			}
			return mockJQueryCollection([arg]);
		};

		topnav.updateElements({
			available: 1000,
			production: 0,
			limit: [0, 100000],
			valueElem: 'current_metal'
		});

		assert.equal(desktop._html, 'S1000');
		assert.equal(mobile._html, 'S1000');
	});

	it('resyncAll runs every registered ticker', () => {
		var calls = 0;
		global.$ = function () {
			calls += 1;
			return mockJQueryCollection([]);
		};

		topnav._registerTickerForTests({ valueElem: 'current_metal' });
		topnav._registerTickerForTests({ valueElem: 'current_crystal' });
		topnav.resyncAll();
		assert.equal(calls, 2);
	});

	it('onResume syncs clock then resyncs tickers', () => {
		var clockCalls = 0;
		var updateCalls = 0;
		global.syncGameClockToWall = function () { clockCalls += 1; };
		global.$ = function () {
			updateCalls += 1;
			return mockJQueryCollection([]);
		};
		topnav._registerTickerForTests({ valueElem: 'current_metal' });
		topnav.onResume();
		assert.equal(clockCalls, 1);
		assert.equal(updateCalls, 1);
	});

	it('initVisibilityResync triggers onResume when tab becomes visible', () => {
		var clockCalls = 0;
		var updateCalls = 0;
		var syncFn = function () { clockCalls += 1; };
		global.syncGameClockToWall = syncFn;
		global.$ = function () {
			updateCalls += 1;
			return mockJQueryCollection([]);
		};

		var listeners = {};
		global.document = {
			hidden: true,
			addEventListener(name, fn) {
				listeners[name] = fn;
			}
		};
		global.window = {
			addEventListener() {},
			syncGameClockToWall: syncFn
		};

		delete require.cache[require.resolve('../../scripts/game/topnav.js')];
		topnav = require('../../scripts/game/topnav.js');
		topnav._registerTickerForTests({ valueElem: 'current_metal' });
		topnav.initVisibilityResync();
		topnav.initVisibilityResync();
		assert.equal(clockCalls, 0);

		global.document.hidden = false;
		listeners.visibilitychange();
		assert.equal(clockCalls, 1);
		assert.equal(updateCalls, 1);
	});
});
