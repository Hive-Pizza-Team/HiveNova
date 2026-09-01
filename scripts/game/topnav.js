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

	function computeResource(config) {
		return Math.max(0, Math.floor(
			parseFloat(config.available) +
			parseFloat(config.production) / 3600 * (serverTime.getTime() - startTime) / 1000
		));
	}

	function updateElements(config) {
		var elements = resourceElements(config.valueElem);
		if (!elements.length) {
			return false;
		}
		if (elements.filter('.res_current_max').length === elements.length) {
			return false;
		}

		var nrResource = computeResource(config);
		var atMax = nrResource >= config.limit[1];

		elements.each(function () {
			var element = $(this);
			if (element.hasClass('res_current_max')) {
				return;
			}
			if (!atMax) {
				if (!element.hasClass('res_current_warn') && nrResource >= config.limit[1] * 0.9) {
					element.addClass('res_current_warn');
				}
				if (viewShortlyNumber) {
					element.attr('data-tooltip-content', NumberGetHumanReadable(nrResource));
					element.html(shortly_number(nrResource));
				} else {
					element.html(NumberGetHumanReadable(nrResource));
				}
			} else {
				element.addClass('res_current_max');
			}
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
})(typeof window !== 'undefined' ? window : globalThis);
