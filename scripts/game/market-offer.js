/**
 * Trade mission form (fleet step 2): live allowed price range and listing fee.
 *
 * Mirrors MarketAbuseRules (PHP). Pure helpers are exported for node tests; the
 * DOM wiring only runs in the browser with jQuery. The server re-checks both.
 */
(function (root) {
	'use strict';

	var RES_IDS = [901, 902, 903];

	function toNumber(value) {
		var n = parseFloat(String(value === undefined || value === null ? '' : value).replace(/[^0-9.\-]/g, ''));
		return isFinite(n) ? n : 0;
	}

	function referenceValue(bundle, ratio) {
		var value = 0;
		for (var i = 0; i < RES_IDS.length; i++) {
			var weight = toNumber(ratio[i]);
			if (weight > 0) {
				value += Math.max(0, toNumber(bundle[RES_IDS[i]])) / weight;
			}
		}
		return value;
	}

	/** Goods value: cargo for a resource offer (type 0), ships' build value for a fleet offer (type 1). */
	function goodsValue(marketType, cargo, shipValue, ratio) {
		return Number(marketType) === 1 ? toNumber(shipValue) : referenceValue(cargo, ratio);
	}

	/** Allowed asking amount in the wanted resource (1 metal, 2 crystal, 3 deuterium), or null. */
	function allowedRange(goods, wantedType, ratio, bandMin, bandMax) {
		var type = Number(wantedType);
		if (!(type >= 1 && type <= 3) || !(goods > 0)) {
			return null;
		}
		var weight = toNumber(ratio[type - 1]);
		if (!(weight > 0) || !(bandMin > 0) || !(bandMax > 0)) {
			return null;
		}
		return {
			min: Math.ceil(goods * weight / bandMax),
			max: Math.floor(goods * weight / bandMin)
		};
	}

	function listingFee(shipValue, cargo, ratio, rate) {
		var listed = toNumber(shipValue) + referenceValue(cargo, ratio);
		var deutWeight = toNumber(ratio[2]) > 0 ? toNumber(ratio[2]) : 1;
		return Math.max(1, Math.ceil(listed * toNumber(rate) * deutWeight));
	}

	function formatNumber(n) {
		return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	function fillTemplate(template, values) {
		return String(template).replace(/%(\d+)\$s/g, function (match, index) {
			var v = values[Number(index) - 1];
			return v === undefined ? match : String(v);
		});
	}

	var api = {
		referenceValue: referenceValue,
		goodsValue: goodsValue,
		allowedRange: allowedRange,
		listingFee: listingFee,
		formatNumber: formatNumber,
		fillTemplate: fillTemplate
	};

	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	root.HiveNovaMarketOffer = api;

	var $ = root.jQuery;
	if (!$) {
		return;
	}

	$(function () {
		var $box = $('#marketOffer');
		if (!$box.length) {
			return;
		}
		var ratio = String($box.attr('data-ratio') || '4,2,1').split(',').map(toNumber);
		var shipValue = toNumber($box.attr('data-ship-value'));
		var bandMin = toNumber($box.attr('data-band-min'));
		var bandMax = toNumber($box.attr('data-band-max'));
		var feeRate = toNumber($box.attr('data-fee-rate'));
		var names = {
			1: $box.attr('data-name-1') || '',
			2: $box.attr('data-name-2') || '',
			3: $box.attr('data-name-3') || ''
		};
		var $form = $box.closest('form');

		function cargo() {
			return {
				901: toNumber($form.find('input[name=metal]').val()),
				902: toNumber($form.find('input[name=crystal]').val()),
				903: toNumber($form.find('input[name=deuterium]').val())
			};
		}

		function update() {
			var type = $form.find('select[name=markettype]').val();
			var wanted = $form.find('select[name=resEx]').val();
			var load = Number(type) === 1 ? {} : cargo();
			var range = allowedRange(goodsValue(type, load, shipValue, ratio), wanted, ratio, bandMin, bandMax);
			var $range = $('#marketOfferRange');
			if (range) {
				$range.text(fillTemplate($box.attr('data-range-text') || '', [formatNumber(range.min), formatNumber(range.max), names[wanted] || '']));
				var asked = toNumber($form.find('input[name=exchange]').val());
				$range.toggleClass('market-offer__range--out', asked > 0 && (asked < range.min || asked > range.max));
			} else {
				$range.text($box.attr('data-range-empty') || '').removeClass('market-offer__range--out');
			}
			var fee = listingFee(shipValue, load, ratio, feeRate);
			$('#marketOfferFee').text(fillTemplate($box.attr('data-fee-text') || '', [formatNumber(fee), String(Math.round(feeRate * 1000) / 10), $box.attr('data-name-3') || '']));
		}

		$form.on('input change', 'input[name=metal], input[name=crystal], input[name=deuterium], input[name=exchange], select[name=resEx], select[name=markettype]', update);
		$form.on('click', 'a', function () {
			root.setTimeout(update, 0);
		});
		update();
	});
})(typeof window !== 'undefined' ? window : globalThis);
