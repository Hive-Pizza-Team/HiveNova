/**
 * Testable markers for overview-planet.js urbanization rendering contract.
 */
(function (root, factory) {
	'use strict';

	var api = factory();

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	root.OverviewPlanetVizContract = api;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function () {
	'use strict';

	var FORBIDDEN_MARKERS = [
		'attachNightEmissiveOverlay',
		'NIGHT_EMISSIVE_FRAGMENT_SHADER',
		'nightEmissiveMesh'
	];

	var REQUIRED_MARKERS = [
		'generateBuildupLights',
		'combineEmissiveMaps',
		'matOpts.emissiveMap = buildupLights',
		'scheduleBuildScene',
		'abortPendingBuild'
	];

	function sourceMatchesUrbanizationContract(source) {
		var missing = [];
		var forbidden = [];

		REQUIRED_MARKERS.forEach(function (marker) {
			if (source.indexOf(marker) === -1) {
				missing.push(marker);
			}
		});
		FORBIDDEN_MARKERS.forEach(function (marker) {
			if (source.indexOf(marker) !== -1) {
				forbidden.push(marker);
			}
		});

		return {
			ok: missing.length === 0 && forbidden.length === 0,
			missing: missing,
			forbidden: forbidden
		};
	}

	return {
		FORBIDDEN_MARKERS: FORBIDDEN_MARKERS,
		REQUIRED_MARKERS: REQUIRED_MARKERS,
		sourceMatchesUrbanizationContract: sourceMatchesUrbanizationContract
	};
}));
