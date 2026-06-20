/**
 * Static planet JPG catalog — five variants per biome family (01–05).
 * Temp sub-ranges match HiveNova\Core\PlanetImageUtil.
 * Regenerate: bash scripts/dev/render-all-planets.sh
 */
(function (root) {
	'use strict';

	var VARIANT_COUNT = 5;

	var FAMILY_TEMP_RANGES = {
		trocken: [120, 260],
		wuesten: [120, 260],
		dschjungel: [50, 110],
		normaltemp: [-10, 80],
		wasser: [-10, 60],
		eis: [-130, -10]
	};

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function catalogTempRangeForVariant(variant, rangeMin, rangeMax) {
		var lowBand = VARIANT_COUNT - variant;
		var span = rangeMax - rangeMin;
		var tempMin = rangeMin + Math.floor(span * lowBand / VARIANT_COUNT);
		var tempMax = variant === 1
			? rangeMax
			: rangeMin + Math.floor(span * (lowBand + 1) / VARIANT_COUNT) - 1;
		return { tempMin: tempMin, tempMax: tempMax };
	}

	function makeFamilyEntries(texturePrefix, familyKey, planetOffset) {
		var range = FAMILY_TEMP_RANGES[familyKey];
		var list = [];
		for (var variant = 1; variant <= VARIANT_COUNT; variant++) {
			var temps = catalogTempRangeForVariant(variant, range[0], range[1]);
			list.push({
				texture: texturePrefix + pad2(variant),
				type: 1,
				tempMin: temps.tempMin,
				tempMax: temps.tempMax,
				galaxy: 1,
				system: 88,
				planet: planetOffset + variant
			});
		}
		return list;
	}

	var ENTRIES = [].concat(
		makeFamilyEntries('trockenplanet', 'trocken', 0),
		makeFamilyEntries('wuestenplanet', 'wuesten', 10),
		makeFamilyEntries('dschjungelplanet', 'dschjungel', 20),
		makeFamilyEntries('normaltempplanet', 'normaltemp', 30),
		makeFamilyEntries('wasserplanet', 'wasser', 40),
		makeFamilyEntries('eisplanet', 'eis', 50),
		[{
			texture: 'mond',
			type: 3,
			diameter: 5000,
			tempMin: -20,
			tempMax: 20,
			galaxy: 1,
			system: 88,
			planet: 99
		}, {
			texture: 'moon-small',
			type: 3,
			diameter: 2500,
			tempMin: -38,
			tempMax: 2,
			galaxy: 1,
			system: 88,
			planet: 101
		}, {
			texture: 'moon-large',
			type: 3,
			diameter: 8945,
			tempMin: -42,
			tempMax: -5,
			galaxy: 1,
			system: 88,
			planet: 102
		}, {
			texture: 'moon-base-2',
			type: 3,
			diameter: 4800,
			tempMin: -35,
			tempMax: 8,
			moonBaseLevel: 2,
			fields: { current: 6, max: 13 },
			galaxy: 1,
			system: 88,
			planet: 103
		}, {
			texture: 'moon-base-5',
			type: 3,
			diameter: 7200,
			tempMin: -28,
			tempMax: 12,
			moonBaseLevel: 5,
			fields: { current: 18, max: 25 },
			buildings: { 41: 5, 1: 10, 2: 8, 12: 1, 22: 3 },
			fleet: { 202: 6, 204: 4 },
			queue: { building: 1, hangar: 0 },
			galaxy: 1,
			system: 88,
			planet: 104
		}, {
			texture: 'unknown',
			vizState: 'unknown',
			type: 1,
			galaxy: 0,
			system: 0,
			planet: 91
		}, {
			texture: 'destroyed',
			vizState: 'destroyed',
			type: 1,
			tempMin: 12,
			tempMax: 28,
			galaxy: 1,
			system: 88,
			planet: 92
		}]
	);

	var TEXTURE_MAP = {};
	for (var e = 0; e < ENTRIES.length; e++) {
		TEXTURE_MAP[ENTRIES[e].texture] = ENTRIES[e];
	}

	var STATIC_BAND_KEEP_TEXTURES = {
		wuestenplanet02: true,
		trockenplanet05: true,
		trockenplanet01: true
	};

	function buildMoonPayload(entry) {
		var buildings = {};
		if (entry.buildings) {
			for (var id in entry.buildings) {
				if (Object.prototype.hasOwnProperty.call(entry.buildings, id)) {
					buildings[id] = entry.buildings[id];
				}
			}
		}
		if (entry.moonBaseLevel) {
			buildings[41] = entry.moonBaseLevel;
		}
		var fields = entry.fields;
		if (!fields) {
			var baseLvl = entry.moonBaseLevel || buildings[41] || 0;
			fields = {
				current: baseLvl * 3,
				max: Math.max(1, 1 + baseLvl * 3)
			};
		}
		return {
			texture: 'mond',
			type: 3,
			tempMin: entry.tempMin != null ? entry.tempMin : -20,
			tempMax: entry.tempMax != null ? entry.tempMax : 20,
			diameter: entry.diameter || 5000,
			fields: fields,
			galaxy: entry.galaxy || 1,
			system: entry.system || 88,
			planet: entry.planet,
			buildings: buildings,
			fleet: entry.fleet || {},
			defense: entry.defense || {},
			queue: entry.queue || { building: 0, hangar: 0 },
			moon: null,
			debris: entry.debris || null,
			dpath: '../../styles/theme/hive/'
		};
	}

	function buildPayload(entry, mode) {
		if (entry.vizState === 'unknown') {
			return {
				vizState: 'unknown',
				texture: '',
				type: 1,
				diameter: 0,
				fields: { current: 0, max: 0 },
				galaxy: entry.galaxy || 0,
				system: entry.system || 0,
				planet: entry.planet || 91,
				buildings: {},
				fleet: {},
				defense: {},
				queue: { building: 0, hangar: 0 },
				moon: null,
				debris: { metal: 0, crystal: 0 },
				dpath: '../../styles/theme/hive/'
			};
		}
		if (entry.vizState === 'destroyed') {
			return {
				vizState: 'destroyed',
				texture: entry.baseTexture || 'normaltempplanet03',
				type: 1,
				tempMin: entry.tempMin,
				tempMax: entry.tempMax,
				diameter: 11800,
				fields: { current: 0, max: 163 },
				galaxy: entry.galaxy || 1,
				system: entry.system || 88,
				planet: entry.planet || 92,
				buildings: {},
				fleet: {},
				defense: {},
				queue: { building: 0, hangar: 0 },
				moon: null,
				debris: { metal: 2500000, crystal: 1200000 },
				dpath: '../../styles/theme/hive/'
			};
		}
		if (entry.type === 3) {
			return buildMoonPayload(entry);
		}
		var payload = {
			texture: entry.texture,
			type: entry.type || 1,
			tempMin: entry.tempMin,
			tempMax: entry.tempMax,
			diameter: 12767,
			fields: {
				current: 0,
				max: 163
			},
			galaxy: entry.galaxy,
			system: entry.system,
			planet: entry.planet,
			buildings: {},
			fleet: {},
			defense: {},
			queue: { building: 0, hangar: 0 },
			moon: null,
			debris: null,
			dpath: '../../styles/theme/hive/'
		};
		var bandOverride = bandOverrideForTexture(entry.texture, entry.type);
		if (bandOverride) {
			payload.bandOverride = bandOverride;
		}
		return payload;
	}

	function getByTexture(texture) {
		return TEXTURE_MAP[texture] || null;
	}

	function getByVizState(vizState) {
		for (var i = 0; i < ENTRIES.length; i++) {
			if (ENTRIES[i].vizState === vizState) {
				return ENTRIES[i];
			}
		}
		return null;
	}

	function classifyBiomeFromTexture(texture, type) {
		if (type === 3) { return 'moon'; }
		texture = (texture || '').toLowerCase();
		if (texture.indexOf('mond') === 0 || texture.indexOf('moon') >= 0) { return 'moon'; }
		if (texture.indexOf('gas') >= 0) { return 'gas'; }
		if (texture.indexOf('eis') >= 0) { return 'ice'; }
		if (texture.indexOf('wasser') >= 0) { return 'water'; }
		if (texture.indexOf('wuest') >= 0 || texture.indexOf('trocken') >= 0) { return 'desert'; }
		if (texture.indexOf('dschjungel') >= 0 || texture.indexOf('jungel') >= 0 || texture.indexOf('jungle') >= 0) { return 'jungle'; }
		return 'terra';
	}

	function bandOverrideForTexture(texture, type) {
		if (!texture || STATIC_BAND_KEEP_TEXTURES[texture]) {
			return null;
		}
		if (type === 3) {
			return null;
		}
		var biome = classifyBiomeFromTexture(texture, type);
		if (biome === 'desert') { return 'desert'; }
		if (biome === 'jungle') { return 'savanna'; }
		if (biome === 'water') { return 'terra'; }
		if (biome === 'ice') { return 'ice'; }
		return null;
	}

	var PlanetImageCatalog = {
		entries: ENTRIES,
		map: TEXTURE_MAP,
		buildPayload: buildPayload,
		getByTexture: getByTexture,
		getByVizState: getByVizState,
		classifyBiomeFromTexture: classifyBiomeFromTexture,
		bandOverrideForTexture: bandOverrideForTexture,
		staticBandKeepTextures: STATIC_BAND_KEEP_TEXTURES,
		familyTempRanges: FAMILY_TEMP_RANGES,
		catalogTempRangeForVariant: catalogTempRangeForVariant,
		proposedBandOverride: function (entry) {
			return entry ? bandOverrideForTexture(entry.texture, entry.type) : null;
		}
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = PlanetImageCatalog;
	}
	root.PlanetImageCatalog = PlanetImageCatalog;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this));
