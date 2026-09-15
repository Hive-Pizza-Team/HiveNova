'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const check = require('../../scripts/login/register-username.js');

function createClassList(initial) {
	const classes = new Set(initial || []);
	return {
		add(name) { classes.add(name); },
		remove(name) { classes.delete(name); },
		toggle(name, force) {
			const on = typeof force === 'boolean' ? force : !classes.has(name);
			if (on) classes.add(name);
			else classes.delete(name);
			return on;
		},
		contains(name) { return classes.has(name); }
	};
}

function createEl(tag, attrs) {
	const el = {
		tagName: String(tag).toUpperCase(),
		attrs: Object.assign({}, attrs || {}),
		children: [],
		parentElement: null,
		classList: createClassList(attrs && attrs.class ? String(attrs.class).split(/\s+/).filter(Boolean) : []),
		listeners: {},
		textContent: '',
		innerHTML: '',
		value: attrs && attrs.value ? String(attrs.value) : '',
		hidden: false,
		get id() { return this.attrs.id || ''; },
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
		},
		setAttribute(name, value) {
			this.attrs[name] = String(value);
			if (name === 'hidden') {
				this.hidden = true;
			}
		},
		removeAttribute(name) {
			delete this.attrs[name];
			if (name === 'hidden') {
				this.hidden = false;
			}
		},
		addEventListener(type, fn) {
			(this.listeners[type] || (this.listeners[type] = [])).push(fn);
		},
		appendChild(child) {
			child.parentElement = this;
			this.children.push(child);
			return child;
		},
		querySelector(selector) {
			return queryAll(this, selector)[0] || null;
		},
		querySelectorAll(selector) {
			return queryAll(this, selector);
		}
	};
	return el;
}

function queryAll(root, selector) {
	const out = [];
	const visit = (node) => {
		if (matches(node, selector)) {
			out.push(node);
		}
		(node.children || []).forEach(visit);
	};
	(root.children || []).forEach(visit);
	out.forEach = Array.prototype.forEach;
	return out;
}

function matches(node, selector) {
	if (selector.startsWith('.')) {
		return node.classList && node.classList.contains(selector.slice(1));
	}
	if (selector.startsWith('#')) {
		return node.id === selector.slice(1);
	}
	if (selector.startsWith('[') && selector.endsWith(']')) {
		const body = selector.slice(1, -1);
		const eq = body.indexOf('=');
		if (eq === -1) {
			return node.getAttribute(body) !== null;
		}
		const name = body.slice(0, eq);
		const value = body.slice(eq + 1).replace(/^"|"$/g, '').replace(/^'|'$/g, '');
		return node.getAttribute(name) === value;
	}
	return node.tagName === selector.toUpperCase();
}

describe('register username live check', () => {
	it('builds the AJAX url with universe and hive flag', () => {
		const url = check.buildUrl(
			{ url: 'index.php?page=register&mode=checkUsername&ajax=1' },
			'Alice',
			'3',
			true
		);
		assert.match(url, /username=Alice/);
		assert.match(url, /uni=3/);
		assert.match(url, /hiveSignup=1/);
	});

	it('debounce waits before invoking', async () => {
		let n = 0;
		const fn = check.debounce(() => { n += 1; }, 20);
		fn();
		fn();
		assert.equal(n, 0);
		await new Promise((resolve) => setTimeout(resolve, 40));
		assert.equal(n, 1);
	});

	it('render shows unavailable mark, message, and clickable suggestions', () => {
		const mark = createEl('span', { class: 'reg-username-mark' });
		const message = createEl('p', { class: 'reg-username-message' });
		const suggestions = createEl('ul', { class: 'reg-username-suggestions' });
		suggestions.setAttribute('hidden', 'hidden');
		const picked = [];
		const doc = {
			createElement(tag) { return createEl(tag); }
		};

		check.render(
			{ mark, message, suggestions },
			{
				available: false,
				message: 'Taken on Hive',
				suggestions: ['Name2', 'xName']
			},
			{ suggestions: 'Try:' },
			(name) => picked.push(name),
			doc
		);

		assert.equal(mark.textContent, '❌');
		assert.ok(mark.classList.contains('is-unavailable'));
		assert.equal(message.textContent, 'Taken on Hive');
		assert.ok(message.classList.contains('is-visible'));
		assert.equal(suggestions.hidden, false);
		assert.equal(suggestions.children.length, 3);
		suggestions.children[1].children[0].listeners.click[0]();
		assert.deepEqual(picked, ['Name2']);
	});

	it('empty input clears the view and does not fetch', async () => {
		const row = createEl('div');
		const wrap = createEl('div', { class: 'reg-username-wrap' });
		const input = createEl('input', { 'data-username-check': 'email' });
		input.value = '';
		const mark = createEl('span', { class: 'reg-username-mark' });
		mark.textContent = 'x';
		const message = createEl('p', { class: 'reg-username-message' });
		wrap.appendChild(input);
		wrap.appendChild(mark);
		row.appendChild(wrap);
		row.appendChild(message);
		input.parentElement = wrap;
		wrap.parentElement = row;

		let fetches = 0;
		const bound = check.bindField(input, { url: '/check', debounceMs: 1 }, {
			documentObj: { querySelectorAll() { const empty = []; empty.forEach = Array.prototype.forEach; return empty; } },
			fetchFn() {
				fetches += 1;
				return Promise.resolve({ json: () => Promise.resolve({ available: true }) });
			}
		});

		await bound.runCheck();
		assert.equal(fetches, 0);
		assert.equal(mark.textContent, '');
	});

	it('parseConfig reads the json script tag', () => {
		const tag = { textContent: '{"url":"/check","debounceMs":300}' };
		const doc = {
			getElementById(id) { return id === 'reg-username-check-config' ? tag : null; }
		};
		assert.deepEqual(check.parseConfig(doc), { url: '/check', debounceMs: 300 });
		assert.equal(check.parseConfig({ getElementById() { return null; } }), null);
	});
});
