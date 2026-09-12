//topnav.js
//RealTimeRessisanzeige for 2Moons
// @version 1.0
// @copyright 2010 by ShadoX

(function (root) {
	'use strict';

	var tickerConfigs = [];
	var visibilityBound = false;

	function resourceElements(valueElem) {
		return $('[id="' + valueElem + '"]');
	}

	function storageLimit(config) {
		return parseFloat(config.limit && config.limit[1]);
	}

	function computeResource(config) {
		var available = parseFloat(config.available);
		var produced = Math.max(0, Math.floor(
			available +
			parseFloat(config.production) / 3600 * (serverTime.getTime() - startTime) / 1000
		));
		var limit = storageLimit(config);
		if (!isFinite(limit)) {
			return produced;
		}
		if (available >= limit) {
			return Math.max(0, Math.floor(available));
		}
		return Math.min(produced, Math.floor(limit));
	}

	function renderAmount(element, nrResource) {
		if (viewShortlyNumber) {
			element.attr('data-tooltip-content', NumberGetHumanReadable(nrResource));
			element.html(shortly_number(nrResource));
		} else {
			element.html(NumberGetHumanReadable(nrResource));
		}
	}

	function updateElements(config) {
		var elements = resourceElements(config.valueElem);
		if (!elements.length) {
			return false;
		}

		var nrResource = computeResource(config);
		var limit = storageLimit(config);
		var atMax = isFinite(limit) && nrResource >= limit;

		elements.each(function () {
			var element = $(this);
			if (atMax) {
				element.addClass('res_current_max');
				element.addClass('is-over-capacity');
			} else if (!element.hasClass('res_current_warn') && isFinite(limit) && nrResource >= limit * 0.9) {
				element.addClass('res_current_warn');
			}
			renderAmount(element, nrResource);
		});
		return true;
	}

	function resourceTicker(config, init) {
		if (typeof init !== 'undefined' && init === true) {
			tickerConfigs.push(config);
			window.setInterval(function () { resourceTicker(config); }, 1000);
		}
		return updateElements(config);
	}

	function getRessource(name) {
		return parseInt(resourceElements('current_' + name).first().data('real'), 10);
	}

	function resyncAll() {
		for (var i = 0; i < tickerConfigs.length; i++) {
			updateElements(tickerConfigs[i]);
		}
	}

	function onResume() {
		if (typeof root.syncGameClockToWall === 'function') {
			root.syncGameClockToWall();
		}
		resyncAll();
	}

	function initVisibilityResync() {
		if (visibilityBound || typeof document === 'undefined') {
			return;
		}
		visibilityBound = true;
		document.addEventListener('visibilitychange', function () {
			if (!document.hidden) {
				onResume();
			}
		});
		if (typeof window !== 'undefined') {
			window.addEventListener('pageshow', function (event) {
				if (event.persisted) {
					onResume();
				}
			});
		}
	}

	root.resourceTicker = resourceTicker;
	root.getRessource = getRessource;

	var api = {
		resourceTicker: resourceTicker,
		getRessource: getRessource,
		computeResource: computeResource,
		updateElements: updateElements,
		resyncAll: resyncAll,
		onResume: onResume,
		initVisibilityResync: initVisibilityResync,
		_resetForTests: function () {
			tickerConfigs = [];
			visibilityBound = false;
		},
		_registerTickerForTests: function (config) {
			tickerConfigs.push(config);
		}
	};

	root.HiveNovaTopnav = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
	initVisibilityResync();
})(typeof window !== 'undefined' ? window : globalThis);
