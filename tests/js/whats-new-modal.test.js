'use strict';

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');

const modal = require('../../scripts/game/whats-new-modal.js');

function createEl(tag, attrs) {
	const listeners = {};
	const el = {
		tagName: String(tag).toUpperCase(),
		attrs: Object.assign({}, attrs || {}),
		children: [],
		disabled: false,
		style: {},
		open: false,
		ownerDocument: null,
		parent: null,
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
		},
		setAttribute(name, value) {
			this.attrs[name] = String(value);
			if (name === 'open') {
				this.open = true;
			}
		},
		removeAttribute(name) {
			delete this.attrs[name];
			if (name === 'open') {
				this.open = false;
			}
		},
		addEventListener(type, fn) {
			(listeners[type] || (listeners[type] = [])).push(fn);
		},
		dispatch(type, event) {
			(listeners[type] || []).forEach((fn) => fn(event || { type }));
		},
		querySelector(sel) {
			if (sel === '[data-whats-new-close]') {
				return this.children.find((c) => c.attrs['data-whats-new-close'] !== undefined) || null;
			}
			if (sel === '[data-whats-new-got-it]') {
				return this.children.find((c) => c.attrs['data-whats-new-got-it'] !== undefined) || null;
			}
			return null;
		},
		querySelectorAll(sel) {
			if (sel !== modal.FOCUSABLE) {
				return [];
			}
			return this.children.filter((c) => {
				const tag = c.tagName;
				return tag === 'BUTTON' || tag === 'A' || tag === 'INPUT';
			});
		},
		contains(node) {
			return this.children.indexOf(node) !== -1 || node === this;
		},
		focus() {
			if (this.ownerDocument) {
				this.ownerDocument.activeElement = this;
			}
			this.focused = true;
		},
		showModal() {
			this.open = true;
			this.attrs.open = 'open';
		},
		close() {
			this.open = false;
			delete this.attrs.open;
		},
		_listeners: listeners
	};
	return el;
}

function buildDialog() {
	const doc = { activeElement: null };
	const dialog = createEl('dialog', {
		'data-dismiss-url': 'game.php?page=whatsNew&mode=dismiss&ajax=1',
		'data-token': 'tok'
	});
	dialog.ownerDocument = doc;
	const closeBtn = createEl('button', { 'data-whats-new-close': '' });
	closeBtn.ownerDocument = doc;
	closeBtn.parent = dialog;
	const gotIt = createEl('button', { 'data-whats-new-got-it': '' });
	gotIt.ownerDocument = doc;
	gotIt.parent = dialog;
	const link = createEl('a', { href: 'game.php?page=whatsNew' });
	link.ownerDocument = doc;
	link.parent = dialog;
	dialog.children = [closeBtn, link, gotIt];
	return { dialog, closeBtn, gotIt, link, doc };
}

describe('HiveNovaWhatsNewModal', () => {
	let posts;

	beforeEach(() => {
		posts = [];
	});

	it('closes and posts once on Got it', () => {
		const { dialog, gotIt } = buildDialog();
		const ctl = modal.bind(dialog, {
			postFn(url, body) {
				posts.push([url, body]);
			}
		});
		assert.equal(dialog.open, true);
		gotIt.dispatch('click', { preventDefault() {} });
		assert.equal(dialog.open, false);
		assert.equal(posts.length, 1);
		assert.match(posts[0][1], /token=tok/);
		ctl.dismissAndClose();
		assert.equal(posts.length, 1);
	});

	it('closes and posts once on Esc / cancel', () => {
		const { dialog } = buildDialog();
		modal.bind(dialog, {
			postFn(url, body) {
				posts.push([url, body]);
			}
		});
		dialog.dispatch('cancel', { preventDefault() {} });
		assert.equal(dialog.open, false);
		assert.equal(posts.length, 1);
		dialog.dispatch('keydown', { key: 'Escape' });
		assert.equal(posts.length, 1);
	});

	it('closes and posts once on backdrop click', () => {
		const { dialog } = buildDialog();
		modal.bind(dialog, {
			postFn(url, body) {
				posts.push([url, body]);
			}
		});
		dialog.dispatch('click', { target: dialog });
		assert.equal(dialog.open, false);
		assert.equal(posts.length, 1);
		dialog.dispatch('click', { target: dialog });
		assert.equal(posts.length, 1);
	});

	it('createDismissSender never posts twice', () => {
		const send = modal.createDismissSender('/x', 'abc', (url, body) => posts.push([url, body]));
		assert.equal(send(), true);
		assert.equal(send(), false);
		assert.equal(posts.length, 1);
		assert.equal(posts[0][1], 'token=abc');
	});

	it('focus trap wraps Tab and Shift+Tab', () => {
		const { dialog, closeBtn, gotIt, link, doc } = buildDialog();
		doc.activeElement = gotIt;
		const tabEvent = {
			key: 'Tab',
			shiftKey: false,
			prevented: false,
			preventDefault() {
				this.prevented = true;
			}
		};
		assert.equal(modal.trapFocusKeydown(tabEvent, dialog), true);
		assert.equal(doc.activeElement, closeBtn);
		assert.equal(tabEvent.prevented, true);

		doc.activeElement = closeBtn;
		const shiftEvent = {
			key: 'Tab',
			shiftKey: true,
			prevented: false,
			preventDefault() {
				this.prevented = true;
			}
		};
		assert.equal(modal.trapFocusKeydown(shiftEvent, dialog), true);
		assert.equal(doc.activeElement, gotIt);
		assert.equal(shiftEvent.prevented, true);
		assert.equal(link.tagName, 'A');
	});
});
