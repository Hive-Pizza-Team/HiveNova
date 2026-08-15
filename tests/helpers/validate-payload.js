#!/usr/bin/env node
'use strict';

const { validatePlanetVizPayload } = require('../../scripts/game/planet-viz-payload.js');

const payload = JSON.parse(process.argv[2] || '{}');
const options = JSON.parse(process.argv[3] || '{}');
const result = validatePlanetVizPayload(payload, options);

process.stdout.write(JSON.stringify(result));
