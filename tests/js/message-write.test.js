'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const write = require('../../scripts/game/message-write.js');

function errorEl() {
	return { hidden: true, textContent: '' };
}

describe('HiveNovaMessageWrite', () => {
	it('treats whitespace as empty', () => {
		assert.equal(write.isBlank(''), true);
		assert.equal(write.isBlank('   '), true);
		assert.equal(write.isBlank('\n\t'), true);
		assert.equal(write.isBlank('hello'), false);
	});

	it('uses the parent window when composing in a fancybox iframe', () => {
		const parent = { name: 'parent' };
		const iframe = { parent, name: 'iframe' };
		assert.equal(write.composeHost(iframe), parent);
		assert.equal(write.composeHost(parent), parent);
	});

	it('toasts on the parent instead of alert()', () => {
		const calls = [];
		const host = {
			showGameToast(message, className) {
				calls.push([message, className]);
			}
		};
		assert.equal(write.showOnHost(host, 'Message Sent!'), true);
		assert.deepEqual(calls, [['Message Sent!', 'notify-toast notify-success']]);
	});

	it('closes the fancybox on the host', () => {
		let closed = 0;
		const host = { $: { fancybox: { close() { closed += 1; } } } };
		write.closeHostDialog(host);
		assert.equal(closed, 1);
	});

	it('shows and clears an inline form error', () => {
		const el = errorEl();
		write.setFormError(el, 'Write something first.');
		assert.equal(el.hidden, false);
		assert.equal(el.textContent, 'Write something first.');
		write.setFormError(el, '');
		assert.equal(el.hidden, true);
		assert.equal(el.textContent, '');
	});

	it('only treats structured ok:true as success', () => {
		assert.equal(write.payloadOk({ ok: true, message: 'Message Sent!' }), true);
		assert.equal(write.payloadOk({ ok: false, message: 'Error' }), false);
		assert.equal(write.payloadOk('Message Sent!'), false);
		assert.equal(write.payloadMessage({ ok: true, message: 'Message Sent!' }), 'Message Sent!');
	});

	it('does not post an empty compose and does not alert', async () => {
		const posts = [];
		const alerts = [];
		global.alert = (msg) => { alerts.push(msg); };
		const form = {
			id: 'message',
			getAttribute(name) {
				return name === 'data-message-write' ? '1' : (name === 'data-empty' ? 'empty' : '');
			},
			addEventListener(type, fn) { this.submit = fn; }
		};
		const error = errorEl();
		const doc = {
			getElementById(id) {
				if (id === 'message') return form;
				if (id === 'submit') return { type: 'submit', disabled: false, addEventListener() {} };
				if (id === 'text') return { value: '   ' };
				if (id === 'message-write-error') return error;
				return null;
			}
		};
		const bound = write.init(doc, {}, (url) => { posts.push(url); return Promise.resolve({ ok: true }); });
		assert.equal(bound.onSubmit({ preventDefault() {} }), false);
		assert.equal(posts.length, 0);
		assert.equal(error.hidden, false);
		assert.equal(error.textContent, 'empty');
		assert.equal(alerts.length, 0);
		delete global.alert;
	});

	it('toasts and closes the parent dialog after a successful send', async () => {
		const toast = [];
		let closed = 0;
		const parent = {
			showGameToast(message) { toast.push(message); },
			$: { fancybox: { close() { closed += 1; } } }
		};
		const form = {
			id: 'message',
			getAttribute(name) {
				if (name === 'data-message-write') return '1';
				if (name === 'data-url') return '/send';
				return '';
			},
			addEventListener(type, fn) { this.submit = fn; }
		};
		const submit = { type: 'submit', disabled: false, addEventListener() {} };
		const doc = {
			getElementById(id) {
				if (id === 'message') return form;
				if (id === 'submit') return submit;
				if (id === 'text') return { value: 'hello' };
				if (id === 'message-write-error') return errorEl();
				return null;
			}
		};
		const bound = write.init(doc, { parent }, () => Promise.resolve({ ok: true, message: 'Message Sent!' }));
		await bound.onSubmit({ preventDefault() {} });
		assert.deepEqual(toast, ['Message Sent!']);
		assert.equal(closed, 1);
		assert.equal(submit.disabled, true);
	});

	it('keeps the compose dialog open on send failure', async () => {
		let closed = 0;
		const error = errorEl();
		const form = {
			id: 'message',
			getAttribute(name) {
				return name === 'data-message-write' ? '1' : '';
			},
			addEventListener(type, fn) { this.submit = fn; }
		};
		const submit = { type: 'submit', disabled: false, addEventListener() {} };
		const doc = {
			getElementById(id) {
				if (id === 'message') return form;
				if (id === 'submit') return submit;
				if (id === 'text') return { value: 'hello' };
				if (id === 'message-write-error') return error;
				return null;
			}
		};
		const bound = write.init(doc, { parent: { $: { fancybox: { close() { closed += 1; } } } } }, () =>
			Promise.resolve({ ok: false, message: 'Error' })
		);
		await bound.onSubmit({ preventDefault() {} });
		assert.equal(closed, 0);
		assert.equal(submit.disabled, false);
		assert.equal(error.textContent, 'Error');
	});
});
