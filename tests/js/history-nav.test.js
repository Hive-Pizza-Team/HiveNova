'use strict';

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const historyNav = require('../../scripts/game/history-nav.js');

function fakeHistory(initialState) {
	const entries = [{ state: initialState || null, url: 'game.php?page=overview' }];
	let index = 0;
	return {
		get state() {
			return entries[index].state;
		},
		get length() {
			return entries.length;
		},
		pushState(state, _title, url) {
			entries.splice(index + 1);
			entries.push({
				state: state || null,
				url: url === undefined || url === null ? entries[index].url : url
			});
			index = entries.length - 1;
		},
		replaceState(state, _title, url) {
			entries[index] = {
				state: state || null,
				url: url === undefined || url === null ? entries[index].url : url
			};
		},
		back() {
			if (index > 0) {
				index -= 1;
			}
		},
		_url() {
			return entries[index].url;
		},
		_index() {
			return index;
		}
	};
}

function clickEvent(target, overrides) {
	return Object.assign({
		target,
		defaultPrevented: false,
		metaKey: false,
		ctrlKey: false,
		shiftKey: false,
		altKey: false,
		button: 0,
		preventDefault() {
			this.defaultPrevented = true;
		}
	}, overrides || {});
}

describe('HiveNovaHistoryNav hash guard', () => {
	beforeEach(() => {
		historyNav._resetForTests();
	});

	it('treats empty and bare hash hrefs as hash-only', () => {
		assert.equal(historyNav.isHashOnlyHref('#'), true);
		assert.equal(historyNav.isHashOnlyHref(''), true);
		assert.equal(historyNav.isHashOnlyHref('  #  '), true);
		assert.equal(historyNav.isHashOnlyHref('#g21'), false);
		assert.equal(historyNav.isHashOnlyHref('game.php?page=buildings'), false);
	});

	it('detects trailing empty hashes that should be stripped', () => {
		assert.equal(historyNav.shouldStripEmptyHash('https://x/game.php?page=buildings#', ''), true);
		assert.equal(historyNav.shouldStripEmptyHash('https://x/game.php?page=buildings#', '#'), true);
		assert.equal(historyNav.shouldStripEmptyHash('https://x/game.php?page=buildings#g1', '#g1'), false);
		assert.equal(historyNav.shouldStripEmptyHash('https://x/game.php?page=buildings', ''), false);
	});

	it('prevents default navigation for bare href="#"', () => {
		const anchor = {
			tagName: 'A',
			getAttribute(name) {
				return name === 'href' ? '#' : null;
			},
			closest(sel) {
				return sel === 'a[href]' ? this : null;
			}
		};
		const event = clickEvent(anchor);
		historyNav.suppressHashOnlyNavigation(event);
		assert.equal(event.defaultPrevented, true);
	});

	it('leaves real fragment and page links alone', () => {
		const fragment = {
			tagName: 'A',
			getAttribute(name) {
				return name === 'href' ? '#g21' : null;
			},
			closest(sel) {
				return sel === 'a[href]' ? this : null;
			}
		};
		const page = {
			tagName: 'A',
			getAttribute(name) {
				return name === 'href' ? 'game.php?page=buildings' : null;
			},
			closest(sel) {
				return sel === 'a[href]' ? this : null;
			}
		};
		const fragEvent = clickEvent(fragment);
		const pageEvent = clickEvent(page);
		historyNav.suppressHashOnlyNavigation(fragEvent);
		historyNav.suppressHashOnlyNavigation(pageEvent);
		assert.equal(fragEvent.defaultPrevented, false);
		assert.equal(pageEvent.defaultPrevented, false);
	});
});

describe('HiveNovaHistoryNav dialog history', () => {
	beforeEach(() => {
		historyNav._resetForTests();
	});

	it('pushes a dialog stub so Back can close the overlay', () => {
		const history = fakeHistory();
		assert.equal(historyNav.recordDialogOpen(history), true);
		assert.equal(historyNav._isDialogOpenForTests(), true);
		assert.equal(history.state[historyNav.DIALOG_STATE_KEY], true);
		assert.equal(history.length, 2);
		assert.equal(history._url(), 'game.php?page=overview');
	});

	it('closes the dialog on popstate without leaving the page URL', () => {
		const history = fakeHistory();
		historyNav.recordDialogOpen(history);
		let closed = 0;
		const result = historyNav.handlePopstate({ state: null }, function () {
			closed += 1;
		});
		assert.equal(result.handled, true);
		assert.equal(closed, 1);
		assert.equal(historyNav._isDialogOpenForTests(), false);
	});

	it('drops the stub entry when the dialog is closed via the UI', () => {
		const history = fakeHistory();
		historyNav.recordDialogOpen(history);
		assert.equal(history._index(), 1);
		historyNav.recordDialogClose(history);
		assert.equal(history._index(), 0);
		assert.equal(historyNav._isDialogOpenForTests(), false);
		const result = historyNav.handlePopstate({ state: history.state });
		assert.equal(result.handled, false);
		assert.equal(result.reason, 'ignore');
	});
});

describe('HiveNovaHistoryNav messages history helpers', () => {
	it('builds category/side URLs and records pushState after the first entry', () => {
		const history = fakeHistory();
		assert.equal(
			historyNav.messagesHistoryUrl(100, 1),
			'game.php?page=messages&category=100'
		);
		assert.equal(
			historyNav.messagesHistoryUrl(3, 2),
			'game.php?page=messages&category=3&side=2'
		);

		historyNav.recordMessagesHistory(history, 100, 1);
		assert.equal(history._url(), 'game.php?page=messages&category=100');
		assert.equal(history.state[historyNav.MESSAGE_STATE_KEY], true);

		historyNav.recordMessagesHistory(history, 3, 2);
		assert.equal(history._url(), 'game.php?page=messages&category=3&side=2');
		assert.equal(history.length, 2);
	});

	it('reads message state from popstate events', () => {
		assert.equal(historyNav.messagesStateFromPopstate({ state: null }), null);
		assert.deepEqual(
			historyNav.messagesStateFromPopstate({
				state: historyNav.buildMessagesHistoryState(15, 4)
			}),
			{ messcat: 15, site: 4 }
		);
	});
});

describe('history-nav wiring', () => {
	it('is loaded from the game header and used by Dialog/Message scripts', () => {
		const header = fs.readFileSync(
			path.join(__dirname, '../../styles/templates/game/main.header.tpl'),
			'utf8'
		);
		const messageSrc = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/message.js'),
			'utf8'
		);
		const baseSrc = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/base.js'),
			'utf8'
		);
		const galaxy = fs.readFileSync(
			path.join(__dirname, '../../styles/templates/game/page.galaxy.default.tpl'),
			'utf8'
		);
		const stats = fs.readFileSync(
			path.join(__dirname, '../../styles/templates/game/page.statistics.default.tpl'),
			'utf8'
		);
		assert.match(header, /scripts\/game\/history-nav\.js[\s\S]*scripts\/game\/base\.js/);
		assert.match(messageSrc, /HiveNovaHistoryNav\.recordMessagesHistory/);
		assert.match(messageSrc, /seedMessagesHistoryFromLocation/);
		assert.match(baseSrc, /HiveNovaHistoryNav\.recordDialogOpen/);
		assert.match(baseSrc, /HiveNovaHistoryNav\.recordDialogClose/);
		assert.doesNotMatch(baseSrc, /HiveNovaHistoryNav\.handlePopstate/);
		assert.match(galaxy, /method="get"/);
		assert.match(stats, /method="get"/);
	});
});
