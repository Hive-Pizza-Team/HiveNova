/**
 * Lazy-loads Three.js and overview-planet.js on the hive-theme overview page only.
 * Keeps ~600KB three.min.js off the critical path; shows a dark panel until WebGL is ready.
 */
(function () {
	'use strict';

	var utils = (typeof OverviewPlanetLoaderUtils !== 'undefined')
		? OverviewPlanetLoaderUtils
		: null;

	var started = false;
	var loadInFlight = false;
	var loadAttempt = 0;
	var MAX_LOAD_ATTEMPTS = 2;
	var RETRY_DELAY_MS = 400;

	function getConfig() {
		var tag = document.currentScript || document.querySelector('script[data-three-src]');
		if (!tag) {
			return null;
		}
		return {
			threeSrc: tag.getAttribute('data-three-src'),
			planetSrc: tag.getAttribute('data-planet-src')
		};
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var el = document.createElement('script');
			el.src = src;
			el.async = true;
			el.onload = function () { resolve(); };
			el.onerror = function () { reject(new Error('Failed to load ' + src)); };
			document.head.appendChild(el);
		});
	}

	function showFallback() {
		var canvas = document.getElementById('overview-planet-canvas');
		var wrap = canvas && canvas.parentElement;
		var img = wrap && wrap.querySelector('.overview-planet-fallback');

		if (utils) {
			utils.applyFallbackVisual(wrap, img);
			return;
		}

		if (wrap) {
			wrap.classList.remove('overview-planet-visual--loading', 'overview-planet-visual--ready');
			wrap.classList.add('overview-planet-visual--fallback');
		}
		if (img) {
			if (utils && typeof utils.resolveFallbackSrc === 'function') {
				utils.resolveFallbackSrc(img);
			} else {
				var hqSrc = img.getAttribute('data-src-hq');
				var stdSrc = img.getAttribute('data-src');
				if (hqSrc && !img.getAttribute('src')) {
					img.setAttribute('src', hqSrc);
					img.onerror = function () {
						if (stdSrc) {
							img.setAttribute('src', stdSrc);
						}
					};
				} else if (stdSrc && !img.getAttribute('src')) {
					img.setAttribute('src', stdSrc);
				}
			}
			img.removeAttribute('aria-hidden');
		}
	}

	function boot() {
		if (started || loadInFlight) {
			return;
		}
		if (utils ? !utils.hasOverviewPlanetDom() : (
			!document.getElementById('overview-planet-data')
			|| !document.getElementById('overview-planet-canvas')
		)) {
			return;
		}

		var config = getConfig();
		if (utils ? !utils.isLoaderConfigValid(config) : (!config || !config.threeSrc || !config.planetSrc)) {
			showFallback();
			return;
		}

		loadInFlight = true;

		loadScript(config.threeSrc)
			.then(function () {
				return loadScript(config.planetSrc);
			})
			.then(function () {
				loadInFlight = false;
				started = true;
			})
			.catch(function () {
				loadInFlight = false;
				if (loadAttempt < MAX_LOAD_ATTEMPTS) {
					loadAttempt += 1;
					window.setTimeout(boot, RETRY_DELAY_MS);
					return;
				}
				started = true;
				showFallback();
			});
	}

	function scheduleBoot() {
		if (utils && typeof utils.reserveOverviewCanvasSlot === 'function') {
			utils.reserveOverviewCanvasSlot();
		}
		if ('requestIdleCallback' in window) {
			requestIdleCallback(boot, { timeout: 500 });
		} else {
			setTimeout(boot, 0);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			if (utils && typeof utils.reserveOverviewCanvasSlot === 'function') {
				utils.reserveOverviewCanvasSlot();
			}
			if (utils && typeof utils.watchOverviewVisualSlot === 'function') {
				utils.watchOverviewVisualSlot();
			}
			scheduleBoot();
		});
	} else {
		if (utils && typeof utils.reserveOverviewCanvasSlot === 'function') {
			utils.reserveOverviewCanvasSlot();
		}
		if (utils && typeof utils.watchOverviewVisualSlot === 'function') {
			utils.watchOverviewVisualSlot();
		}
		scheduleBoot();
	}
})();
