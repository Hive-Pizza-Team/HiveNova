'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const imperium = require('../../scripts/game/imperium.js');

function createEl(tag, attrs) {
	const el = {
		tagName: String(tag).toUpperCase(),
		attrs: Object.assign({}, attrs || {}),
		children: [],
		dataset: {},
		textContent: '',
		innerHTML: '',
		open: false,
		listeners: {},
		colSpan: 1,
		href: '',
		onclick: null,
		get id() { return this.attrs.id || ''; },
		set id(v) { this.attrs.id = v; },
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
		},
		setAttribute(name, value) {
			this.attrs[name] = String(value);
		},
		addEventListener(type, fn) {
			(this.listeners[type] || (this.listeners[type] = [])).push(fn);
		},
		appendChild(child) {
			if (child && Array.isArray(child.children) && child.tagName === undefined) {
				child.children.forEach((n) => this.children.push(n));
				return child;
			}
			this.children.push(child);
			if (child && child.tagName === 'A' && this.textContent === '') {
				this.textContent = child.textContent;
			}
			return child;
		}
	};
	if (attrs && attrs.id) {
		el.id = attrs.id;
	}
	return el;
}

function makeDoc(sections, configText, configUrl) {
	const nodes = {};
	(sections || []).forEach((section) => {
		nodes['empire-' + section] = createEl('tbody', { id: 'empire-' + section });
		nodes['empire-details-' + section] = createEl('details', { id: 'empire-details-' + section });
	});
	if (configText !== undefined) {
		const cfg = createEl('script', { id: 'empire-matrix-config' });
		if (configUrl) {
			cfg.attrs['data-url'] = configUrl;
		}
		cfg.textContent = configText;
		nodes['empire-matrix-config'] = cfg;
	}
	return {
		nodes,
		getElementById(id) {
			return nodes[id] || null;
		},
		createElement(tag) {
			return createEl(tag);
		},
		createDocumentFragment() {
			return {
				children: [],
				appendChild(child) {
					this.children.push(child);
					return child;
				}
			};
		}
	};
}

const samplePayload = {
	colspan: 3,
	planetIds: ['10'],
	sections: {
		build: [
			{ id: 1, name: 'Ore Extractor', total: 5, values: { '10': 5 } }
		],
		fleet: [],
		defense: [],
		missiles: [],
		tech: []
	}
};

describe('HiveNovaImperium', () => {
	it('rejects login HTML as a matrix payload', () => {
		assert.equal(imperium.parseMatrixPayload('<html>login</html>'), null);
		assert.equal(imperium.looksLikeJson('{"sections":{}}'), true);
		assert.ok(imperium.parseMatrixPayload(JSON.stringify(samplePayload)));
	});

	it('reads embedded matrix JSON from the config tag', () => {
		const doc = makeDoc(['build'], JSON.stringify(samplePayload), 'game.php?page=imperium&mode=matrix&ajax=1');
		const embedded = imperium.readEmbeddedPayload(doc);
		assert.equal(embedded.sections.build[0].name, 'Ore Extractor');
		assert.equal(imperium.matrixUrl(doc), 'game.php?page=imperium&mode=matrix&ajax=1');
	});

	it('renders building rows from embedded JSON when a section is already open', async () => {
		const doc = makeDoc(['build'], JSON.stringify(samplePayload));
		doc.nodes['empire-details-build'].open = true;
		const api = imperium.bindEmpireMatrix(doc);
		await api.loadMatrix();
		await Promise.resolve();
		const host = doc.nodes['empire-build'];
		assert.equal(host.dataset.filled, '1');
		assert.equal(host.children.length, 1);
		assert.equal(host.children[0].children[0].textContent, 'Ore Extractor');
		assert.equal(host.children[0].children[1].innerHTML, '5');
		assert.equal(host.children[0].children[2].innerHTML, '5');
	});

	it('looks up compacted values by string or numeric planet id', () => {
		assert.equal(imperium.cellAmount({ 10: 3 }, '10'), 3);
		assert.equal(imperium.cellAmount({ '10': 3 }, 10), 3);
		assert.equal(imperium.cellAmount({}, '10'), 0);
	});

	it('falls back to fetch when the config tag is empty', async () => {
		const doc = makeDoc(['build'], '');
		let called = 0;
		const fetchFn = async () => {
			called += 1;
			return { ok: true, text: async () => JSON.stringify(samplePayload) };
		};
		const api = imperium.bindEmpireMatrix(doc, { fetchFn });
		const cfg = await api.loadMatrix();
		assert.equal(called, 1);
		assert.equal(cfg.sections.build[0].id, 1);
		assert.equal(imperium.renderSection(cfg, 'build', doc), 1);
		assert.equal(doc.nodes['empire-build'].children.length, 1);
	});
});
