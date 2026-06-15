/**
 * Static planet JPG catalog — one entry per legacy planeten texture name.
 * Regenerate assets when overview-planet.js or this file changes:
 *   bash scripts/dev/render-all-planets.sh
 *
 * Coords are canonical (1:88:N) so each texture always renders identically.
 * Temp ranges follow includes/PlanetData.php slot positions.
 * Special entries: unknown, destroyed, moon variants (size + lunar base).
 */
(function (root) {
	'use strict';

	var SLOT_TEMPS = {
		1: { tempMin: 220, tempMax: 260 },
		2: { tempMin: 170, tempMax: 210 },
		3: { tempMin: 120, tempMax: 160 },
		4: { tempMin: 70, tempMax: 110 },
		5: { tempMin: 60, tempMax: 100 },
		6: { tempMin: 50, tempMax: 90 },
		7: { tempMin: 40, tempMax: 80 },
		8: { tempMin: 30, tempMax: 70 },
		9: { tempMin: 20, tempMax: 60 },
		10: { tempMin: 10, tempMax: 50 },
		11: { tempMin: 0, tempMax: 40 },
		12: { tempMin: -10, tempMax: 30 },
		13: { tempMin: -50, tempMax: -10 },
		14: { tempMin: -90, tempMax: -50 },
		15: { tempMin: -130, tempMax: -90 }
	};

	function slotForIndex(count, index, slotStart, slotEnd) {
		if (count <= 1) {
			return slotStart;
		}
		var t = (index - 1) / (count - 1);
		var slot = Math.round(slotStart + t * (slotEnd - slotStart));
		return Math.max(slotStart, Math.min(slotEnd, slot));
	}

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function makeEntries(prefix, count, slotStart, slotEnd, planetOffset) {
		var list = [];
		for (var i = 1; i <= count; i++) {
			var slot = slotForIndex(count, i, slotStart, slotEnd);
			var temps = SLOT_TEMPS[slot];
			list.push({
				texture: prefix + pad2(i),
				type: 1,
				tempMin: temps.tempMin,
				tempMax: temps.tempMax,
				galaxy: 1,
				system: 88,
				planet: planetOffset + i
			});
		}
		return list;
	}

	var ENTRIES = [].concat(
		makeEntries('trockenplanet', 10, 1, 3, 0),
		makeEntries('wuestenplanet', 4, 1, 3, 20),
		makeEntries('dschjungelplanet', 10, 4, 6, 30),
		makeEntries('normaltempplanet', 7, 7, 12, 50),
		makeEntries('wasserplanet', 9, 9, 12, 70),
		makeEntries('eisplanet', 10, 13, 15, 90),
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
		// Static catalog: pristine planets only — no fields fill, buildings, or fleet.
		// mode=lite|full affects renderer richness (clouds/atmosphere), not urbanization.
		return {
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

	/** Mirrors overview-planet.js classifyBiome — used by static preview tooling. */
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

	/**
	 * Option #2 preview: pick color band from texture family instead of temperature alone.
	 * Returns null to keep current temp-driven band (normaltemp*, etc.).
	 */
	function proposedBandOverride(entry) {
		if (!entry || entry.vizState || entry.type === 3) {
			return null;
		}
		var biome = classifyBiomeFromTexture(entry.texture, entry.type);
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
		proposedBandOverride: proposedBandOverride
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = PlanetImageCatalog;
	}
	root.PlanetImageCatalog = PlanetImageCatalog;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this));
