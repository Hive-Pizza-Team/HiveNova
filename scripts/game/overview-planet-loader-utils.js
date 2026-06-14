/**
 * Testable helpers for overview-planet-loader.js (fallback DOM state + boot guards).
 */
(function (root, factory) {
	'use strict';

	var api = factory();

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	root.OverviewPlanetLoaderUtils = api;
}(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : this, function () {
	'use strict';

	var LOADING = 'overview-planet-visual--loading';
	var READY = 'overview-planet-visual--ready';
	var FALLBACK = 'overview-planet-visual--fallback';

	function resolveFallbackSrc(fallbackImg) {
		if (!fallbackImg) {
			return;
		}
		var hqSrc = fallbackImg.getAttribute('data-src-hq');
		var stdSrc = fallbackImg.getAttribute('data-src');
		if (hqSrc && !fallbackImg.getAttribute('src')) {
			fallbackImg.setAttribute('src', hqSrc);
			fallbackImg.onerror = function () {
				if (stdSrc) {
					fallbackImg.setAttribute('src', stdSrc);
				}
			};
			return;
		}
		if (stdSrc && !fallbackImg.getAttribute('src')) {
			fallbackImg.setAttribute('src', stdSrc);
		}
	}

	function applyFallbackVisual(wrap, fallbackImg) {
		if (wrap) {
			wrap.classList.remove(LOADING, READY);
			wrap.classList.add(FALLBACK);
		}
		if (fallbackImg) {
			resolveFallbackSrc(fallbackImg);
			fallbackImg.removeAttribute('aria-hidden');
		}
	}

	function applyLoadingVisual(wrap, fallbackImg) {
		if (wrap) {
			wrap.classList.add(LOADING);
			wrap.classList.remove(READY, FALLBACK);
		}
		if (fallbackImg) {
			resolveFallbackSrc(fallbackImg);
		}
	}

	function hasOverviewPlanetDom(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return false;
		}
		return !!(doc.getElementById('overview-planet-data')
			&& doc.getElementById('overview-planet-canvas'));
	}

	function isLoaderConfigValid(config) {
		return !!(config && config.threeSrc && config.planetSrc);
	}

	function isHiveThemePath(dpath) {
		return typeof dpath === 'string' && dpath.indexOf('/hive/') !== -1;
	}

	return {
		LOADING: LOADING,
		READY: READY,
		FALLBACK: FALLBACK,
		resolveFallbackSrc: resolveFallbackSrc,
		applyFallbackVisual: applyFallbackVisual,
		applyLoadingVisual: applyLoadingVisual,
		hasOverviewPlanetDom: hasOverviewPlanetDom,
		isLoaderConfigValid: isLoaderConfigValid,
		isHiveThemePath: isHiveThemePath
	};
}));
