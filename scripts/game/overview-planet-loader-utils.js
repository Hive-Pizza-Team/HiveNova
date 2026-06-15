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
	var VISUAL_SIZE_CAP = 280;

	function getVisualSlotSide(wrap, cap) {
		cap = cap || VISUAL_SIZE_CAP;
		if (!wrap) {
			return cap;
		}

		var available = wrap.clientWidth;
		if (!(available > 0)) {
			available = wrap.getBoundingClientRect().width;
		}
		if (!(available > 0) && wrap.parentElement) {
			var parentWidth = wrap.parentElement.clientWidth;
			if (parentWidth > 0) {
				available = parentWidth;
			}
		}
		if (!(available > 0)) {
			return cap;
		}

		return Math.max(120, Math.round(Math.min(available, cap)));
	}

	function reserveOverviewCanvasSlot(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc) {
			return 0;
		}

		var canvas = doc.getElementById('overview-planet-canvas');
		if (!canvas) {
			return 0;
		}

		var wrap = canvas.parentElement;
		if (!wrap || !wrap.classList.contains('overview-planet-visual')) {
			return 0;
		}

		var side = getVisualSlotSide(wrap);
		canvas.width = side;
		canvas.height = side;
		if (side > 0 && wrap.clientWidth > 0 && wrap.style) {
			wrap.style.width = side + 'px';
			wrap.style.height = side + 'px';
		}
		return side;
	}

	function watchOverviewVisualSlot(doc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (!doc || typeof ResizeObserver === 'undefined') {
			return;
		}

		var canvas = doc.getElementById('overview-planet-canvas');
		var wrap = canvas && canvas.parentElement;
		if (!wrap || !wrap.classList.contains('overview-planet-visual')) {
			return;
		}

		var observer = new ResizeObserver(function () {
			if (wrap.clientWidth > 0) {
				reserveOverviewCanvasSlot(doc);
			}
		});
		observer.observe(wrap);
		if (wrap.parentElement) {
			observer.observe(wrap.parentElement);
		}
	}

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
			var alt = fallbackImg.getAttribute('data-alt');
			if (alt) {
				fallbackImg.setAttribute('alt', alt);
			}
			fallbackImg.removeAttribute('aria-hidden');
		}
	}

	function applyLoadingVisual(wrap, fallbackImg) {
		if (wrap) {
			wrap.classList.add(LOADING);
			wrap.classList.remove(READY, FALLBACK);
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
		VISUAL_SIZE_CAP: VISUAL_SIZE_CAP,
		getVisualSlotSide: getVisualSlotSide,
		reserveOverviewCanvasSlot: reserveOverviewCanvasSlot,
		watchOverviewVisualSlot: watchOverviewVisualSlot,
		resolveFallbackSrc: resolveFallbackSrc,
		applyFallbackVisual: applyFallbackVisual,
		applyLoadingVisual: applyLoadingVisual,
		hasOverviewPlanetDom: hasOverviewPlanetDom,
		isLoaderConfigValid: isLoaderConfigValid,
		isHiveThemePath: isHiveThemePath
	};
}));
