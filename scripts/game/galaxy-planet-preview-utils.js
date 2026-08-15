/**
 * Testable asset-loading state for galaxy-planet-preview.js.
 */
(function (root, factory) {
	'use strict';

	var api = factory();

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	root.GalaxyPlanetPreviewUtils = api;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function () {
	'use strict';

	function createAssetLoader(loadScript) {
		var state = { promise: null };

		function ensureAssets(config) {
			if (!config || !config.threeSrc || !config.planetSrc) {
				return Promise.reject(new Error('Missing planet viz asset config'));
			}
			if (state.promise) {
				return state.promise;
			}
			state.promise = loadScript(config.threeSrc).then(function () {
				return loadScript(config.planetSrc);
			}).catch(function (err) {
				state.promise = null;
				throw err;
			});
			return state.promise;
		}

		function resetAssets() {
			state.promise = null;
		}

		function hasPendingAssets() {
			return !!state.promise;
		}

		return {
			ensureAssets: ensureAssets,
			resetAssets: resetAssets,
			hasPendingAssets: hasPendingAssets
		};
	}

	return {
		createAssetLoader: createAssetLoader
	};
}));
