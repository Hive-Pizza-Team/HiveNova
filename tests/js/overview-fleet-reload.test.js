'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const overviewJs = fs.readFileSync(
	path.join(__dirname, '../../scripts/game/overview.js'),
	'utf8'
);

describe('overview fleet timer reload (#455)', () => {
	it('reloads overview when fleet countdowns expire after counting down', () => {
		assert.match(overviewJs, /\$\('\.fleets'\)\.each/);
		assert.match(overviewJs, /data\('fleet-time'\)/);
		assert.match(overviewJs, /overviewTimersWereActive/);
		assert.match(overviewJs, /game\.php\?page=overview/);
		assert.doesNotMatch(
			overviewJs,
			/setInterval\(function\(\)\s*\{\s*\$\('\.fleets'\)/
		);
	});
});
