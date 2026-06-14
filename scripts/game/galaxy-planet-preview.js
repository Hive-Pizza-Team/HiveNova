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

	function startPreview(trigger) {
		var vizJson = $(trigger).attr('data-planet-viz');
		if (!vizJson || isMobileTooltip()) {
			return;
		}

		var data;
		try {
			data = JSON.parse(vizJson);
		} catch (e) {
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

		ensureAssets().then(function () {
			if (activeTrigger !== trigger) {
				return;
			}
			window.HiveNovaOverviewPlanet.mountPreview(canvas, data, { size: 75 }).then(function (ok) {
				if (!ok || activeTrigger !== trigger) {
					cleanupPreview();
					return;
				}
				host.find('.galaxy-viz-fallback').hide();
				canvas.classList.add('galaxy-planet-viz-canvas--ready');
			});
		}).catch(function () {
			cleanupPreview();
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
