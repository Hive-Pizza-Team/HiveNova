'use strict';

const { describe, it, beforeEach } = require('node:test');
const assert = require('node:assert/strict');

const tabs = require('../../scripts/game/simple-tabs.js');
const overview = require('../../scripts/game/overview.actions.js');
const battlesim = require('../../scripts/game/battlesim.js');

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
		contains(name) { return classes.has(name); },
		has(name) { return classes.has(name); },
		toString() { return [...classes].join(' '); }
	};
}

function createEl(tag, attrs) {
	const el = {
		tagName: String(tag).toUpperCase(),
		attrs: Object.assign({}, attrs || {}),
		children: [],
		parentElement: null,
		classList: createClassList(
			attrs && attrs.class ? String(attrs.class).split(/\s+/).filter(Boolean) : []
		),
		style: {},
		hidden: false,
		listeners: {},
		textContent: '',
		get id() { return this.attrs.id || ''; },
		set id(v) { this.attrs.id = v; },
		getAttribute(name) {
			if (name === 'class') return this.classList.toString();
			return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
		},
		setAttribute(name, value) {
			this.attrs[name] = String(value);
			if (name === 'class') {
				this.classList = createClassList(String(value).split(/\s+/).filter(Boolean));
			}
		},
		addEventListener(type, fn) {
			(this.listeners[type] || (this.listeners[type] = [])).push(fn);
		},
		dispatchEvent(type, target) {
			const event = {
				type,
				target: target || this,
				defaultPrevented: false,
				preventDefault() { this.defaultPrevented = true; }
			};
			(this.listeners[type] || []).forEach((fn) => fn(event));
			return event;
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
		},
		contains(node) {
			if (node === this) return true;
			return this.children.some((c) => c.contains(node));
		},
		closest(selector) {
			let cur = this;
			while (cur) {
				if (matches(cur, selector)) return cur;
				cur = cur.parentElement;
			}
			return null;
		}
	};
	if (attrs && attrs.id) el.id = attrs.id;
	if (attrs && attrs.href) el.attrs.href = attrs.href;
	Object.defineProperty(el, 'nextElementSibling', {
		get() {
			if (!this.parentElement) return null;
			const sibs = this.parentElement.children;
			const idx = sibs.indexOf(this);
			return idx >= 0 ? sibs[idx + 1] || null : null;
		}
	});
	return el;
}

function matches(el, selector) {
	if (selector === 'ul') return el.tagName === 'UL';
	if (selector === 'tr') return el.tagName === 'TR';
	if (selector === 'td') return el.tagName === 'TD';
	if (selector === 'button.reset') {
		return el.tagName === 'BUTTON' && el.classList.contains('reset');
	}
	if (selector === 'a[href^="#"]') {
		const href = el.getAttribute('href');
		return el.tagName === 'A' && href && href.charAt(0) === '#';
	}
	if (selector.startsWith('[id="') && selector.endsWith('"]')) {
		const id = selector.slice(5, -2).replace(/\\"/g, '"');
		return el.id === id;
	}
	if (selector.startsWith('#')) return el.id === selector.slice(1);
	if (selector === ':scope > ul') return false; // handled by getNav via querySelector('ul')
	return false;
}

function walk(node, out) {
	out.push(node);
	node.children.forEach((c) => walk(c, out));
}

function queryAll(root, selector) {
	const all = [];
	walk(root, all);
	if (selector === ':scope > ul') {
		return root.children.filter((c) => c.tagName === 'UL');
	}
	if (selector === 'input') {
		return all.filter((n) => n.tagName === 'INPUT');
	}
	return all.filter((n) => n !== root && matches(n, selector));
}

function buildTabs(panelCount) {
	const root = createEl('div', { id: 'tabs' });
	const ul = createEl('ul');
	root.appendChild(ul);
	const links = [];
	const panels = [];
	for (let i = 0; i < panelCount; i++) {
		const li = createEl('li');
		const a = createEl('a', { href: '#tabs-' + i });
		a.textContent = 'Tab ' + (i + 1);
		li.appendChild(a);
		ul.appendChild(li);
		links.push(a);
		const panel = createEl('div', { id: 'tabs-' + i });
		panel.textContent = 'Panel ' + i;
		root.appendChild(panel);
		panels.push(panel);
	}
	const doc = {
		getElementById(id) {
			if (id === 'tabs') return root;
			return panels.find((p) => p.id === id) || null;
		}
	};
	root.ownerDocument = doc;
	return { root, links, panels, doc };
}

describe('HiveNovaSimpleTabs', () => {
	it('parses panel ids from hash hrefs', () => {
		assert.equal(tabs.panelIdFromHref('#tabs-1'), 'tabs-1');
		assert.equal(tabs.panelIdFromHref('/x#tabs-2'), 'tabs-2');
		assert.equal(tabs.panelIdFromHref('tabs-1'), null);
	});

	it('shows only the active panel and marks the nav link', () => {
		const { root, links, panels } = buildTabs(2);
		const ctl = tabs.init(root, { active: 0 });
		assert.ok(ctl);
		assert.equal(panels[0].hidden, false);
		assert.equal(panels[1].hidden, true);
		assert.equal(links[0].classList.contains('is-active'), true);
		assert.equal(links[1].classList.contains('is-active'), false);

		ctl.activate(1);
		assert.equal(panels[0].hidden, true);
		assert.equal(panels[1].hidden, false);
		assert.equal(links[1].classList.contains('is-active'), true);
		assert.equal(ctl.activeIndex(), 1);
	});

	it('switches tabs on nav click', () => {
		const { root, links, panels } = buildTabs(2);
		tabs.init(root, { active: 0 });
		root.dispatchEvent('click', links[1]);
		assert.equal(panels[1].hidden, false);
		assert.equal(panels[0].hidden, true);
	});
});

describe('HiveNovaOverviewActions tabs', () => {
	beforeEach(() => {
		globalThis.HiveNovaSimpleTabs = tabs;
	});

	it('opens delete tab when search has tab=delete', () => {
		assert.equal(overview.activeTabFromSearch('?page=overview&mode=actions'), 0);
		assert.equal(overview.activeTabFromSearch('?tab=delete'), 1);
	});

	it('initializes #tabs with the URL-selected panel', () => {
		const { root, panels, doc } = buildTabs(2);
		overview.initTabs(doc, '?tab=delete');
		assert.equal(panels[1].hidden, false);
		assert.equal(panels[0].hidden, true);
		assert.equal(root.getAttribute('data-active-tab'), '1');
	});
});

describe('HiveNovaBattleSim', () => {
	beforeEach(() => {
		globalThis.HiveNovaSimpleTabs = tabs;
	});

	it('initializes ACS slot tabs', () => {
		const { panels, doc } = buildTabs(3);
		battlesim.initTabs(doc);
		assert.equal(panels[0].hidden, false);
		assert.equal(panels[1].hidden, true);
		assert.equal(panels[2].hidden, true);
	});

	it('resets inputs in the same column for following rows', () => {
		const table = createEl('table');
		const header = createEl('tr');
		const h1 = createEl('td');
		const h2 = createEl('td');
		const btn = createEl('button', { class: 'reset' });
		h2.appendChild(btn);
		header.appendChild(h1);
		header.appendChild(h2);
		table.appendChild(header);

		const row = createEl('tr');
		const c1 = createEl('td');
		const c2 = createEl('td');
		const input = createEl('input');
		input.value = '42';
		c2.appendChild(input);
		row.appendChild(c1);
		row.appendChild(c2);
		table.appendChild(row);

		battlesim.resetColumn(btn);
		assert.equal(input.value, '0');
	});
});
