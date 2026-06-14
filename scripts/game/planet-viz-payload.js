/**
 * Shared contract for overview / galaxy planet visualization JSON payloads.
 * Used by overview-planet.js consumers and Node tests (tests/js/).
 */
(function (root, factory) {
	'use strict';

	var api = factory();

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	root.PlanetVizPayload = api;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function () {
	'use strict';

	var REQUIRED_TOP = [
		'texture', 'type', 'tempMin', 'tempMax', 'diameter', 'fields',
		'galaxy', 'system', 'planet', 'buildings', 'fleet', 'defense',
		'queue', 'moon', 'debris', 'dpath'
	];

	var REQUIRED_QUEUE = ['building', 'hangar'];
	var REQUIRED_FIELDS = ['current', 'max'];

	function isPlainObject(value) {
		return value !== null && typeof value === 'object' && !Array.isArray(value);
	}

	function isIntLike(value) {
		return typeof value === 'number' && Number.isFinite(value) && Math.floor(value) === value;
	}

	function isCountMap(value, label, errors) {
		if (!isPlainObject(value)) {
			errors.push(label + ' must be an object');
			return;
		}
		Object.keys(value).forEach(function (key) {
			if (!/^\d+$/.test(key)) {
				errors.push(label + ' keys must be numeric element IDs');
				return;
			}
			if (!isIntLike(value[key]) || value[key] < 0) {
				errors.push(label + '[' + key + '] must be a non-negative integer');
			}
		});
	}

	/**
	 * @param {object} data Parsed planet viz payload
	 * @param {{ sparse?: boolean }} options sparse=true allows empty buildings/fleet/defense (galaxy spy view)
	 * @returns {{ valid: boolean, errors: string[] }}
	 */
	function validatePlanetVizPayload(data, options) {
		options = options || {};
		var errors = [];

		if (!isPlainObject(data)) {
			return { valid: false, errors: ['payload must be a plain object'] };
		}

		REQUIRED_TOP.forEach(function (key) {
			if (!(key in data)) {
				errors.push('missing required key: ' + key);
			}
		});

		if (errors.length) {
			return { valid: false, errors: errors };
		}

		if (typeof data.texture !== 'string') {
			errors.push('texture must be a string');
		}
		if (!isIntLike(data.type) || data.type < 1 || data.type > 3) {
			errors.push('type must be an integer 1–3');
		}
		if (!isIntLike(data.tempMin) || !isIntLike(data.tempMax)) {
			errors.push('tempMin and tempMax must be integers');
		}
		if (!isIntLike(data.diameter) || data.diameter < 0) {
			errors.push('diameter must be a non-negative integer');
		}
		if (!isPlainObject(data.fields)) {
			errors.push('fields must be an object');
		} else {
			REQUIRED_FIELDS.forEach(function (key) {
				if (!isIntLike(data.fields[key]) || data.fields[key] < 0) {
					errors.push('fields.' + key + ' must be a non-negative integer');
				}
			});
			if (data.fields.max < 1) {
				var allowZeroFields = options.sparse && data.vizState === 'unknown';
				if (!allowZeroFields) {
					errors.push('fields.max must be at least 1');
				}
			}
		}
		['galaxy', 'system', 'planet'].forEach(function (key) {
			if (!isIntLike(data[key]) || data[key] < 0) {
				errors.push(key + ' must be a non-negative integer');
			}
		});

		isCountMap(data.buildings, 'buildings', errors);
		isCountMap(data.fleet, 'fleet', errors);
		isCountMap(data.defense, 'defense', errors);

		if (!isPlainObject(data.queue)) {
			errors.push('queue must be an object');
		} else {
			REQUIRED_QUEUE.forEach(function (key) {
				var val = data.queue[key];
				if (val !== 0 && val !== 1) {
					errors.push('queue.' + key + ' must be 0 or 1');
				}
			});
		}

		if (data.moon !== null) {
			if (!isPlainObject(data.moon)) {
				errors.push('moon must be null or an object');
			} else {
				['id', 'name', 'diameter'].forEach(function (key) {
					if (!(key in data.moon)) {
						errors.push('moon.' + key + ' is required when moon is set');
					}
				});
				if (data.moon.id !== undefined && (!isIntLike(data.moon.id) || data.moon.id < 1)) {
					errors.push('moon.id must be a positive integer');
				}
				if (data.moon.diameter !== undefined && (!isIntLike(data.moon.diameter) || data.moon.diameter < 0)) {
					errors.push('moon.diameter must be a non-negative integer');
				}
			}
		}

		if (data.debris !== null) {
			if (!isPlainObject(data.debris)) {
				errors.push('debris must be null or an object');
			} else {
				['metal', 'crystal'].forEach(function (key) {
					if (!isIntLike(data.debris[key]) || data.debris[key] < 0) {
						errors.push('debris.' + key + ' must be a non-negative integer');
					}
				});
			}
		}

		if (typeof data.dpath !== 'string' || data.dpath.indexOf('./styles/theme/') !== 0) {
			errors.push('dpath must be a theme path starting with ./styles/theme/');
		}

		if (data.vizState !== undefined && typeof data.vizState !== 'string') {
			errors.push('vizState must be a string when present');
		}

		if (options.sparse) {
			['buildings', 'fleet', 'defense'].forEach(function (key) {
				if (!isPlainObject(data[key])) {
					return;
				}
				Object.keys(data[key]).forEach(function (mapKey) {
					if (data[key][mapKey] <= 0) {
						return;
					}
					errors.push(key + ' must be empty in sparse galaxy payloads');
				});
			});
		}

		return { valid: errors.length === 0, errors: errors };
	}

	return {
		REQUIRED_TOP: REQUIRED_TOP,
		validatePlanetVizPayload: validatePlanetVizPayload
	};
}));
