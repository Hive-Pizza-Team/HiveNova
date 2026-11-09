'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const historyNav = require('../../scripts/game/history-nav.js');

describe('HiveNovaHistoryNav hash guard', () => {
	it('treats empty and bare hash hrefs as history no-ops', () => {
		assert.equal(historyNav.isHashOnlyHref(undefined), true);
		assert.equal(historyNav.isHashOnlyHref(''), true);
		assert.equal(historyNav.isHashOnlyHref('#'), true);
		assert.equal(historyNav.isHashOnlyHref(' # '), true);
		assert.equal(historyNav.isHashOnlyHref('game.php?page=buildings'), false);
		assert.equal(historyNav.isHashOnlyHref('#panel'), false);
		assert.equal(historyNav.isHashOnlyHref('javascript:void(0)'), false);
	});

	it('prevents default for hash-only anchor clicks', () => {
		const prevented = [];
		const event = {
			defaultPrevented: false,
			metaKey: false,
			ctrlKey: false,
			shiftKey: false,
			altKey: false,
			button: 0,
			target: {
				closest: function () {
					return {
						getAttribute: function () {
							return '#';
						}
					};
				}
			},
			preventDefault: function () {
				prevented.push(true);
				this.defaultPrevented = true;
			}
		};
		historyNav.suppressHashOnlyNavigation(event);
		assert.equal(prevented.length, 1);
	});

	it('leaves real navigation links alone', () => {
		let prevented = false;
		const event = {
			defaultPrevented: false,
			button: 0,
			target: {
				closest: function () {
					return {
						getAttribute: function () {
							return 'game.php?page=statistics';
						}
					};
				}
			},
			preventDefault: function () {
				prevented = true;
			}
		};
		historyNav.suppressHashOnlyNavigation(event);
		assert.equal(prevented, false);
	});
});

describe('HiveNovaHistoryNav messages history', () => {
	it('builds message URLs and replaces until a messages state exists', () => {
		assert.equal(
			historyNav.messagesHistoryUrl(3, 1),
			'game.php?page=messages&messcat=3'
		);
		assert.equal(
			historyNav.messagesHistoryUrl(3, 2),
			'game.php?page=messages&messcat=3&site=2'
		);
		assert.equal(historyNav.shouldReplaceMessagesHistory(null), true);
		assert.equal(historyNav.shouldReplaceMessagesHistory({}), true);
		assert.equal(
			historyNav.shouldReplaceMessagesHistory(
				historyNav.buildMessagesHistoryState(1, 1)
			),
			false
		);
	});

	it('records replace then push for successive AJAX message views', () => {
		const calls = [];
		const historyObj = {
			state: null,
			replaceState: function (state, title, url) {
				calls.push(['replace', state, url]);
				this.state = state;
			},
			pushState: function (state, title, url) {
				calls.push(['push', state, url]);
				this.state = state;
			}
		};
		historyNav.recordMessagesHistory(historyObj, 3, 1);
		historyNav.recordMessagesHistory(historyObj, 3, 2);
		assert.deepEqual(calls.map(function (c) { return c[0]; }), ['replace', 'push']);
		assert.equal(calls[0][2], 'game.php?page=messages&messcat=3');
		assert.equal(calls[1][2], 'game.php?page=messages&messcat=3&site=2');
	});

	it('reads message state from popstate events only', () => {
		assert.equal(historyNav.messagesStateFromPopstate({ state: null }), null);
		assert.equal(historyNav.messagesStateFromPopstate({ state: { foo: 1 } }), null);
		assert.deepEqual(
			historyNav.messagesStateFromPopstate({
				state: historyNav.buildMessagesHistoryState(5, 4)
			}),
			{ messcat: 5, site: 4 }
		);
	});
});

describe('history-nav wiring', () => {
	it('is loaded from the game header and used by message.js', () => {
		const header = fs.readFileSync(
			path.join(__dirname, '../../styles/templates/game/main.header.tpl'),
			'utf8'
		);
		const messageSrc = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/message.js'),
			'utf8'
		);
		assert.match(header, /scripts\/game\/history-nav\.js/);
		assert.match(messageSrc, /HiveNovaHistoryNav\.recordMessagesHistory/);
		assert.match(messageSrc, /messagesStateFromPopstate/);
		assert.match(messageSrc, /skipHistory/);
	});
});
