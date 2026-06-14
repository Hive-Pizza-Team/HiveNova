'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { validatePlanetVizPayload, REQUIRED_TOP } = require('../../scripts/game/planet-viz-payload.js');

const fixturesDir = path.join(__dirname, '../fixtures');

function loadFixture(name) {
	return JSON.parse(fs.readFileSync(path.join(fixturesDir, name), 'utf8'));
}

describe('planet-viz-payload contract', () => {
	it('defines the expected top-level keys', () => {
		assert.deepEqual(REQUIRED_TOP, [
			'texture', 'type', 'tempMin', 'tempMax', 'diameter', 'fields',
			'galaxy', 'system', 'planet', 'buildings', 'fleet', 'defense',
			'queue', 'moon', 'debris', 'dpath'
		]);
	});

	it('accepts a full overview payload fixture', () => {
		const payload = loadFixture('planet-viz-overview-full.json');
		const result = validatePlanetVizPayload(payload);
		assert.equal(result.valid, true, result.errors.join('; '));
	});

	it('accepts sparse galaxy spy payloads with empty buildings/fleet/defense', () => {
		const payload = loadFixture('planet-viz-galaxy-sparse.json');
		const result = validatePlanetVizPayload(payload, { sparse: true });
		assert.equal(result.valid, true, result.errors.join('; '));
	});

	it('rejects payloads missing required keys', () => {
		const payload = loadFixture('planet-viz-overview-full.json');
		delete payload.dpath;
		const result = validatePlanetVizPayload(payload);
		assert.equal(result.valid, false);
		assert.ok(result.errors.some((e) => e.includes('dpath')));
	});

	it('rejects invalid queue flags and count maps', () => {
		const payload = loadFixture('planet-viz-overview-full.json');
		payload.queue.building = 2;
		payload.fleet['202'] = -1;
		const result = validatePlanetVizPayload(payload);
		assert.equal(result.valid, false);
		assert.ok(result.errors.some((e) => e.includes('queue.building')));
		assert.ok(result.errors.some((e) => e.includes('fleet[202]')));
	});

	it('rejects non-empty maps when sparse mode is enabled', () => {
		const payload = loadFixture('planet-viz-galaxy-sparse.json');
		payload.buildings = { '1': 5 };
		const result = validatePlanetVizPayload(payload, { sparse: true });
		assert.equal(result.valid, false);
		assert.ok(result.errors.some((e) => e.includes('buildings must be empty')));
	});

	it('rejects theme paths outside ./styles/theme/', () => {
		const payload = loadFixture('planet-viz-overview-full.json');
		payload.dpath = '/absolute/theme/hive/';
		const result = validatePlanetVizPayload(payload);
		assert.equal(result.valid, false);
		assert.ok(result.errors.some((e) => e.includes('dpath')));
	});
});
