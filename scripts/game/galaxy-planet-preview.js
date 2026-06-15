/**
 * Galaxy page — animated planet preview inside sticky tooltips on hover.
 */
(function ($) {
	'use strict';

	var assetLoader = (typeof GalaxyPlanetPreviewUtils !== 'undefined')
		? GalaxyPlanetPreviewUtils.createAssetLoader(loadScript)
		: null;
	var activeTrigger = null;
	var previewCanvas = null;
	var vizPayloadCache = Object.create(null);
	var vizPayloadInflight = Object.create(null);

	function getConfig() {
		var tag = document.getElementById('galaxy-planet-viz-loader')
			|| document.querySelector('script[data-three-src][data-planet-src]');
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

	function ensureAssets() {
		if (window.HiveNovaOverviewPlanet && typeof THREE !== 'undefined') {
			return Promise.resolve();
		}
		if (!assetLoader) {
			return Promise.reject(new Error('Missing planet viz asset loader'));
		}
		return assetLoader.ensureAssets(getConfig());
	}

	function cleanupPreview() {
		if (window.HiveNovaOverviewPlanet) {
			window.HiveNovaOverviewPlanet.unmountPreview();
		}
		if (previewCanvas) {
			previewCanvas.classList.remove('galaxy-planet-viz-canvas--ready');
		}
		if (previewCanvas && previewCanvas.parentNode) {
			previewCanvas.parentNode.removeChild(previewCanvas);
		}
		var tip = $('#tooltip');
		if (activeTrigger) {
			tip.find('.galaxy-viz-fallback').show();
		}
		activeTrigger = null;
	}

	function getOrCreatePreviewCanvas(host) {
		if (!previewCanvas) {
			previewCanvas = document.createElement('canvas');
			previewCanvas.className = 'galaxy-planet-viz-canvas';
			previewCanvas.setAttribute('aria-hidden', 'true');
		}
		host.empty().append(previewCanvas);
		return previewCanvas;
	}

	function hasSharedPlanetIntel(data) {
		return !!(data && data.shareIntel);
	}

	function fetchVizPayload(ref) {
		if (vizPayloadCache[ref]) {
			return Promise.resolve(vizPayloadCache[ref]);
		}
		if (vizPayloadInflight[ref]) {
			return vizPayloadInflight[ref];
		}

		vizPayloadInflight[ref] = fetch(
			'game.php?page=galaxy&mode=planetViz&ref=' + encodeURIComponent(ref),
			{ credentials: 'same-origin' }
		).then(function (response) {
			if (!response.ok) {
				throw new Error('Viz payload request failed');
			}
			return response.json();
		}).then(function (data) {
			if (!data || data.error) {
				throw new Error('Viz payload unavailable');
			}
			vizPayloadCache[ref] = data;
			delete vizPayloadInflight[ref];
			return data;
		}).catch(function (err) {
			delete vizPayloadInflight[ref];
			throw err;
		});

		return vizPayloadInflight[ref];
	}

	function startPreview(trigger) {
		var vizRef = $(trigger).attr('data-planet-viz-ref');
		if (!vizRef || isMobileTooltip()) {
			return;
		}

		var tip = $('#tooltip');
		var host = tip.find('.galaxy-viz-host');
		if (!host.length) {
			return;
		}

		cleanupPreview();
		activeTrigger = trigger;

		var canvas = getOrCreatePreviewCanvas(host);
		canvas.classList.remove('galaxy-planet-viz-canvas--ready');

		fetchVizPayload(vizRef).then(function (data) {
			if (activeTrigger !== trigger) {
				return null;
			}
			return ensureAssets().then(function () {
				return data;
			});
		}).then(function (data) {
			if (!data || activeTrigger !== trigger) {
				if (activeTrigger === trigger) {
					cleanupPreview();
				}
				return;
			}
			return window.HiveNovaOverviewPlanet.mountPreview(canvas, data, {
				size: 75,
				lite: !hasSharedPlanetIntel(data)
			});
		}).then(function (ok) {
			if (!ok || activeTrigger !== trigger) {
				if (activeTrigger === trigger) {
					cleanupPreview();
				}
				return;
			}
			host.find('.galaxy-viz-fallback').hide();
			canvas.classList.add('galaxy-planet-viz-canvas--ready');
		}).catch(function () {
			if (activeTrigger === trigger) {
				cleanupPreview();
			}
		});
	}

	function isMobileTooltip() {
		return window.matchMedia('(max-width: 699px)').matches;
	}

	$(function () {
		if (isMobileTooltip()) {
			return;
		}

		window.addEventListener('hiveNovaPlanetPreviewContextLost', function () {
			cleanupPreview();
		});

		$(document).on('mouseenter', '.galaxy-planet-preview', function () {
			var self = this;
			window.setTimeout(function () {
				if (!$('#tooltip').is(':visible')) {
					return;
				}
				startPreview(self);
			}, 0);
		});

		$('#tooltip').on('mouseleave', function () {
			cleanupPreview();
		});
	});
}(jQuery));
