'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const { sourceMatchesUrbanizationContract } = require('../../scripts/game/overview-planet-viz-contract.js');

describe('overview-planet regression guard', () => {
	it('uses base-material emissive for urbanization and avoids night overlay mesh', () => {
		const source = fs.readFileSync(
			path.join(__dirname, '../../scripts/game/overview-planet.js'),
			'utf8'
		);
		const result = sourceMatchesUrbanizationContract(source);
		assert.equal(result.ok, true, [
			result.missing.length ? 'missing: ' + result.missing.join(', ') : '',
			result.forbidden.length ? 'forbidden: ' + result.forbidden.join(', ') : ''
		].filter(Boolean).join('; '));
	});
});
