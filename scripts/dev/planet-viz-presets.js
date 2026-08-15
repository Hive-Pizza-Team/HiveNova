/**
 * Gallery presets aligned with overview-planet.js tempBand() thresholds:
 *   lava    avg > 110
 *   desert  55 < avg <= 110
 *   savanna 25 < avg <= 55
 *   terra   0 < avg <= 25
 *   tundra  -45 < avg <= 0
 *   ice     avg <= -45
 *
 * Texture name sets biome modifiers (wetness, sea level); color palette follows temp band.
 * Special vizState presets: unknown (undiscovered), destroyed (demolished colony).
 * Moon presets (type 3): diameter, coords seed, building 41 (lunar base) level.
 */
(function (root) {
	'use strict';

	var PRESET_LIST = [
		{
			id: 'lava',
			label: 'Lava (trocken v3)',
			texture: 'trockenplanet03',
			tempMin: 176,
			tempMax: 203,
			planet: 3,
			band: 'desert',
			biome: 'desert'
		},
		{
			id: 'desert',
			label: 'Desert (wüste v2)',
			texture: 'wuestenplanet02',
			tempMin: 204,
			tempMax: 231,
			planet: 12,
			band: 'desert',
			biome: 'desert'
		},
		{
			id: 'savanna',
			label: 'Savanna (dschungel v3)',
			texture: 'dschjungelplanet03',
			tempMin: 74,
			tempMax: 85,
			planet: 23,
			band: 'savanna',
			biome: 'jungle'
		},
		{
			id: 'jungle',
			label: 'Jungle (dschungel v1)',
			texture: 'dschjungelplanet01',
			tempMin: 98,
			tempMax: 110,
			planet: 21,
			band: 'savanna',
			biome: 'jungle'
		},
		{
			id: 'terra',
			label: 'Terran (normal v3)',
			texture: 'normaltempplanet03',
			tempMin: 18,
			tempMax: 35,
			planet: 33,
			band: 'terra',
			biome: 'temperate'
		},
		{
			id: 'water',
			label: 'Ocean (wasser v3)',
			texture: 'wasserplanet03',
			tempMin: 10,
			tempMax: 27,
			planet: 43,
			band: 'terra',
			biome: 'ocean'
		},
		{
			id: 'tundra',
			label: 'Tundra (normal v5)',
			texture: 'normaltempplanet05',
			tempMin: -10,
			tempMax: 7,
			planet: 35,
			band: 'tundra',
			biome: 'temperate'
		},
		{
			id: 'ice',
			label: 'Ice (eis v2)',
			texture: 'eisplanet02',
			tempMin: -98,
			tempMax: -74,
			planet: 52,
			band: 'ice',
			biome: 'ice'
		},
		{
			id: 'moon-small',
			label: 'Moon',
			type: 3,
			texture: 'mond',
			diameter: 2500,
			tempMin: -38,
			tempMax: 2,
			planet: 101,
			band: 'moon',
			biome: 'pristine'
		},
		{
			id: 'moon-large',
			label: 'Large Moon',
			type: 3,
			texture: 'mond',
			diameter: 8945,
			tempMin: -42,
			tempMax: -5,
			planet: 102,
			band: 'moon',
			biome: 'pristine'
		},
		{
			id: 'moon-base-2',
			label: 'Moon + Base II',
			type: 3,
			texture: 'mond',
			diameter: 4800,
			tempMin: -35,
			tempMax: 8,
			planet: 103,
			band: 'moon',
			biome: 'lunar base',
			moonBaseLevel: 2,
			fields: { current: 6, max: 13 }
		},
		{
			id: 'moon-base-5',
			label: 'Moon + Base V',
			type: 3,
			texture: 'mond',
			diameter: 7200,
			tempMin: -28,
			tempMax: 12,
			planet: 104,
			band: 'moon',
			biome: 'developed',
			moonBaseLevel: 5,
			fields: { current: 18, max: 25 },
			buildings: { 41: 5, 1: 10, 2: 8, 12: 1, 22: 3 },
			fleet: { 202: 6, 204: 4 },
			queue: { building: 1, hangar: 0 }
		},
		{
			id: 'unknown',
			label: 'Unknown',
			vizState: 'unknown',
			planet: 91,
			band: 'unknown',
			biome: 'undiscovered'
		},
		{
			id: 'destroyed',
			label: 'Destroyed',
			vizState: 'destroyed',
			texture: 'normaltempplanet03',
			tempMin: 12,
			tempMax: 28,
			planet: 92,
			band: 'destroyed',
			biome: 'abandoned'
		}
	];

	var PRESET_MAP = {};
	for (var i = 0; i < PRESET_LIST.length; i++) {
		PRESET_MAP[PRESET_LIST[i].id] = PRESET_LIST[i];
	}

	function formatTempRange(min, max) {
		return min + '\u2013' + max + ' \u00B0C';
	}

	function formatDiameter(km) {
		if (km >= 1000) {
			return (km / 1000).toFixed(1).replace(/\.0$/, '') + 'k km';
		}
		return km + ' km';
	}

	function presetSubtitle(preset) {
		if (preset.type === 3) {
			var parts = [];
			if (preset.diameter) {
				parts.push('\u00D8 ' + formatDiameter(preset.diameter));
			}
			if (preset.moonBaseLevel) {
				parts.push('lunar base ' + preset.moonBaseLevel);
			} else if (preset.buildings && preset.buildings[41]) {
				parts.push('lunar base ' + preset.buildings[41]);
			} else {
				parts.push('no lunar base');
			}
			if (preset.biome && preset.biome !== 'pristine') {
				parts.push(preset.biome);
			}
			return parts.join(' \u00B7 ');
		}
		if (preset.vizState === 'unknown') {
			return 'undiscovered \u00B7 no survey data';
		}
		if (preset.vizState === 'destroyed') {
			return 'abandoned \u00B7 all structures demolished';
		}
		return formatTempRange(preset.tempMin, preset.tempMax)
			+ ' \u00B7 ' + preset.band + ' band'
			+ (preset.biome ? ' \u00B7 ' + preset.biome : '');
	}

	function buildMoonPayload(preset) {
		var buildings = {};
		if (preset.buildings) {
			for (var id in preset.buildings) {
				if (Object.prototype.hasOwnProperty.call(preset.buildings, id)) {
					buildings[id] = preset.buildings[id];
				}
			}
		}
		if (preset.moonBaseLevel) {
			buildings[41] = preset.moonBaseLevel;
		}
		var fields = preset.fields;
		if (!fields) {
			var baseLvl = preset.moonBaseLevel || buildings[41] || 0;
			fields = {
				current: baseLvl * 3,
				max: Math.max(1, 1 + baseLvl * 3)
			};
		}
		return {
			texture: 'mond',
			type: 3,
			tempMin: preset.tempMin != null ? preset.tempMin : -30,
			tempMax: preset.tempMax != null ? preset.tempMax : 5,
			diameter: preset.diameter || 4200,
			fields: fields,
			galaxy: 1,
			system: 88,
			planet: preset.planet,
			buildings: buildings,
			fleet: preset.fleet || {},
			defense: preset.defense || {},
			queue: preset.queue || { building: 0, hangar: 0 },
			moon: null,
			debris: preset.debris || null,
			dpath: '../../styles/theme/hive/'
		};
	}

	function buildPayload(preset, buildup) {
		if (preset.type === 3) {
			return buildMoonPayload(preset);
		}
		if (preset.vizState === 'unknown') {
			return {
				vizState: 'unknown',
				texture: '',
				type: 1,
				diameter: 0,
				fields: { current: 0, max: 0 },
				galaxy: 0,
				system: 0,
				planet: preset.planet,
				buildings: {},
				fleet: {},
				defense: {},
				queue: { building: 0, hangar: 0 },
				moon: null,
				debris: { metal: 0, crystal: 0 },
				dpath: '../../styles/theme/hive/'
			};
		}
		if (preset.vizState === 'destroyed') {
			return {
				vizState: 'destroyed',
				texture: preset.texture,
				type: 1,
				tempMin: preset.tempMin,
				tempMax: preset.tempMax,
				diameter: 11800,
				fields: { current: 0, max: 163 },
				galaxy: 1,
				system: 88,
				planet: preset.planet,
				buildings: {},
				fleet: {},
				defense: {},
				queue: { building: 0, hangar: 0 },
				moon: null,
				debris: { metal: 2500000, crystal: 1200000 },
				dpath: '../../styles/theme/hive/'
			};
		}
		return {
			texture: preset.texture,
			type: 1,
			tempMin: preset.tempMin,
			tempMax: preset.tempMax,
			diameter: 12800,
			fields: {
				current: Math.round(163 * buildup),
				max: 163
			},
			galaxy: 1,
			system: 88,
			planet: preset.planet,
			buildings: { 1: 12, 2: 10, 3: 8, 4: 6, 12: 1, 22: 4, 31: 2 },
			fleet: { 202: 8, 204: 15, 212: 10 },
			defense: { 401: 12, 402: 6 },
			queue: { building: 1, hangar: 0 },
			moon: { id: 9000 + preset.planet, name: 'Moon', diameter: 4200 },
			debris: { metal: 80000, crystal: 45000 },
			dpath: '../../styles/theme/hive/'
		};
	}

	root.PlanetVizPresets = {
		list: PRESET_LIST,
		map: PRESET_MAP,
		subtitle: presetSubtitle,
		buildPayload: buildPayload
	};
})(typeof window !== 'undefined' ? window : this);
