'use strict';

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadMessageScript() {
	const source = fs.readFileSync(
		path.join(__dirname, '../../scripts/game/message.js'),
		'utf8'
	);
	const unread = {
		1: { text: '1' },
		100: { text: '1' },
	};
	const newmes = { text: '(1)' };
	const newmesnum = { text: '1' };
	const jqueryMap = {
		'#unread_1': {
			length: 1,
			text(value) {
				if (typeof value === 'undefined') {
					return unread[1].text;
				}
				unread[1].text = String(value);
				return this;
			},
		},
		'#unread_100': {
			length: 1,
			text(value) {
				if (typeof value === 'undefined') {
					return unread[100].text;
				}
				unread[100].text = String(value);
				return this;
			},
		},
		'#newmes': {
			text(value) {
				if (typeof value === 'undefined') {
					return newmes.text;
				}
				newmes.text = String(value);
				return this;
			},
		},
		'#newmesnum': {
			text(value) {
				if (typeof value === 'undefined') {
					return newmesnum.text;
				}
				newmesnum.text = String(value);
				return this;
			},
		},
	};

	const row = {
		id: 'message_10',
		className: 'message_10 message_head mes_unread',
	};
	const body = {
		className: 'message_10 messages_body mes_unread',
	};

	const sandbox = {
		Message: undefined,
		document: {
			getElementById(id) {
				return id === 'message_10' ? row : null;
			},
			querySelectorAll(selector) {
				if (selector === '.message_10') {
					return [row, body];
				}
				return [];
			},
		},
		$: function (sel) {
			return jqueryMap[sel] || { length: 0, text() { return this; } };
		},
	};
	sandbox.$.fn = {};
	vm.runInNewContext(source.replace(/\$\(function[\s\S]*$/m, ''), sandbox);
	return { Message: sandbox.Message, unread, newmes, row, body };
}

describe('Message.applyMarkedRead', () => {
	it('decrements unread counters and clears mes_unread', () => {
		const { Message, unread, newmes, row, body } = loadMessageScript();
		Message.MessID = 1;
		Message.applyMarkedRead([10]);
		assert.equal(unread[1].text, '0');
		assert.equal(unread[100].text, '0');
		assert.equal(newmes.text, '');
		assert.equal(row.className.includes('mes_unread'), false);
		assert.equal(body.className.includes('mes_unread'), false);
	});

	it('ignores ids that were already read', () => {
		const { Message, unread } = loadMessageScript();
		Message.MessID = 1;
		Message.applyMarkedRead([99]);
		assert.equal(unread[1].text, '1');
		assert.equal(unread[100].text, '1');
	});
});
