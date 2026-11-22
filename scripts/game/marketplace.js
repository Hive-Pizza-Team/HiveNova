/**
 * Marketplace page (game.php?page=marketPlace).
 *
 * Pure helpers are exported for node tests; the DOM wiring only runs in the
 * browser with jQuery. Keep JS out of the Smarty template: literal braces there
 * are parsed as tags (#748).
 */
(function (root) {
	'use strict';

	var RES_IDS = [901, 902, 903];
	var PAY_TYPE_TO_RES = { 1: 901, 2: 902, 3: 903 };
	var DEAL_GOOD_AT = 1.05;
	var DEAL_BAD_BELOW = 0.95;

	function toNumber(value) {
		var n = parseFloat(value);
		return isFinite(n) ? n : 0;
	}

	/** Value of a bundle keyed by 901/902/903 at a metal:crystal:deuterium ratio. */
	function referenceValue(bundle, ratio) {
		var value = 0;
		for (var i = 0; i < RES_IDS.length; i++) {
			var weight = toNumber(ratio[i]);
			if (weight <= 0) {
				continue;
			}
			value += toNumber(bundle[RES_IDS[i]]) / weight;
		}
		return value;
	}

	/** What you get / what you pay at the reference ratio; null when the price has no value. */
	function dealRatio(offer, payType, payAmount, ratio) {
		var resId = PAY_TYPE_TO_RES[payType];
		if (!resId) {
			return null;
		}
		var price = {};
		price[resId] = toNumber(payAmount);
		var priceValue = referenceValue(price, ratio);
		if (priceValue <= 0) {
			return null;
		}
		return referenceValue(offer, ratio) / priceValue;
	}

	function dealRating(ratio) {
		if (ratio === null || ratio === undefined || !isFinite(ratio)) {
			return 'none';
		}
		if (ratio >= DEAL_GOOD_AT) {
			return 'good';
		}
		if (ratio < DEAL_BAD_BELOW) {
			return 'bad';
		}
		return 'fair';
	}

	/** Replace %1$s, %2$s, ... (and bare %s in order) with args. */
	function fillTemplate(template, args) {
		var next = 0;
		return String(template)
			.replace(/\\n/g, '\n')
			.replace(/%(\d+)\$s|%s/g, function (match, index) {
				var i = index ? parseInt(index, 10) - 1 : next++;
				return args[i] !== undefined ? String(args[i]) : '';
			});
	}

	/** offer: { getIds: [901..], payType, own, block } filters: { get, pay, onlyBuyable } */
	function matchesFilters(offer, filters) {
		if (filters.get && offer.getIds.indexOf(parseInt(filters.get, 10)) === -1) {
			return false;
		}
		if (filters.pay && parseInt(filters.pay, 10) !== offer.payType) {
			return false;
		}
		if (filters.onlyBuyable && (offer.own || offer.block)) {
			return false;
		}
		return true;
	}

	/** Comparator for offers: { deal, delivery, end }. Own offers stay on top. */
	function compareOffers(sortKey) {
		return function (a, b) {
			if (a.own !== b.own) {
				return a.own ? -1 : 1;
			}
			if (sortKey === 'deal') {
				var da = a.deal === null ? -Infinity : a.deal;
				var db = b.deal === null ? -Infinity : b.deal;
				if (db !== da) {
					return db > da ? 1 : -1;
				}
			} else if (sortKey === 'delivery' && a.delivery !== b.delivery) {
				return a.delivery - b.delivery;
			}
			return a.end - b.end;
		};
	}

	function formatSeconds(secs) {
		secs = Math.max(0, Math.floor(secs));
		var h = Math.floor(secs / 3600);
		var m = Math.floor(secs / 60) % 60;
		var s = secs % 60;
		var pad = function (n) {
			return n < 10 ? '0' + n : String(n);
		};
		return pad(h) + ':' + pad(m) + ':' + pad(s);
	}

	var api = {
		referenceValue: referenceValue,
		dealRatio: dealRatio,
		dealRating: dealRating,
		fillTemplate: fillTemplate,
		matchesFilters: matchesFilters,
		compareOffers: compareOffers,
		formatSeconds: formatSeconds
	};

	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	root.HiveNovaMarketplace = api;

	var $ = root.jQuery;
	if (!$) {
		return;
	}

	function payKey() {
		return $('#marketPayWith').val() === '1' ? 'lc' : 'hc';
	}

	function readRatio() {
		return [
			toNumber($('input[name=ratio-metal]').val()),
			toNumber($('input[name=ratio-crystal]').val()),
			toNumber($('input[name=ratio-deuterium]').val())
		];
	}

	function rowOffer($row) {
		var getIds = [];
		var bundle = {};
		for (var i = 0; i < RES_IDS.length; i++) {
			var v = toNumber($row.attr('data-get-' + RES_IDS[i]));
			bundle[RES_IDS[i]] = v;
			if (v > 0) {
				getIds.push(RES_IDS[i]);
			}
		}
		var deal = $row.data('deal');
		return {
			row: $row,
			bundle: bundle,
			getIds: getIds,
			payType: parseInt($row.attr('data-pay-type'), 10),
			payAmount: toNumber($row.attr('data-pay-amount')),
			own: $row.attr('data-own') === '1',
			block: $row.attr('data-block-' + payKey()) || $row.data('expired') || '',
			delivery: toNumber($row.attr('data-delivery')),
			end: toNumber($row.attr('data-end')),
			deal: deal === undefined ? null : deal
		};
	}

	function calculateRatios() {
		var ratio = readRatio();
		var $market = $('#market');
		$('.js-ratio-label').text(ratio.join(':'));
		$('table#tradeList tbody tr').each(function () {
			var $row = $(this);
			var offer = rowOffer($row);
			var deal = dealRatio(offer.bundle, offer.payType, offer.payAmount, ratio);
			var rating = dealRating(deal);
			$row.data('deal', deal);
			var $deal = $row.find('.market-deal');
			$deal.removeClass('market-deal--good market-deal--fair market-deal--bad market-deal--none')
				.addClass('market-deal--' + rating);
			$deal.find('.js-deal-ratio').text(deal === null ? '\u2013' : deal.toFixed(2) + '\u00d7');
			$deal.find('.js-deal-label').text(rating === 'none' ? '' : ($market.attr('data-deal-' + rating) || ''));
		});
		applyView();
	}

	function updatePayWith() {
		var key = payKey();
		var shipType = $('#marketPayWith').val();
		var fuelLabel = $('#market').attr('data-fuel-label') || '';
		$('.js-ship-type').val(shipType);
		$('.market-offer').each(function () {
			var $row = $(this);
			if ($row.attr('data-own') === '1') {
				return;
			}
			var block = $row.data('expired') || $row.attr('data-block-' + key) || '';
			var ships = $row.attr('data-ships-' + key) || '';
			var fuel = $row.attr('data-fuel-' + key) || '0';
			$row.find('.market-buy__btn').prop('disabled', block !== '');
			$row.find('.js-reason').text(block);
			var $cost = $row.find('.js-cost').empty();
			if (ships) {
				$cost.append(document.createTextNode(ships), $('<br>'),
					$('<small>').text(fuelLabel + ': ' + fuel));
			} else {
				$cost.text('\u2013');
			}
		});
		if (root.history && root.history.replaceState) {
			syncUrl();
		}
		applyView();
	}

	function currentTab() {
		return $('#market').attr('data-tab') === 'fleet' ? 'fleet' : 'resources';
	}

	function syncUrl() {
		try {
			var url = 'game.php?page=marketPlace&tab=' + currentTab() + '&shipType=' + $('#marketPayWith').val();
			root.history.replaceState(null, '', url);
		} catch (e) { /* ignore */ }
	}

	function applyView() {
		var tab = currentTab();
		var filters = {
			get: tab === 'resources' ? $('#marketFilterGet').val() : '',
			pay: $('#marketFilterPay').val(),
			onlyBuyable: $('#marketOnlyBuyable').is(':checked')
		};
		var sortKey = $('#marketSort').val();
		if (tab === 'fleet' && sortKey === 'deal') {
			sortKey = 'expires';
		}
		$('[data-market-panel]').each(function () {
			var $panel = $(this);
			var $tbody = $panel.find('.market-table:not(.market-table--history) tbody');
			if (!$tbody.length) {
				return;
			}
			var offers = $tbody.children('tr').map(function () {
				return rowOffer($(this));
			}).get();
			offers.sort(compareOffers(sortKey));
			var shown = 0;
			for (var i = 0; i < offers.length; i++) {
				var visible = matchesFilters(offers[i], filters);
				offers[i].row.prop('hidden', !visible);
				if (visible) {
					shown++;
				}
				$tbody.append(offers[i].row);
			}
			$panel.find('.js-empty-filtered').prop('hidden', shown > 0);
		});
	}

	function showTab(tab) {
		$('#market').attr('data-tab', tab);
		$('[data-market-panel]').each(function () {
			$(this).prop('hidden', $(this).attr('data-market-panel') !== tab);
		});
		$('[data-market-tab]').each(function () {
			var active = $(this).attr('data-market-tab') === tab;
			$(this).attr('aria-selected', active ? 'true' : 'false').parent().toggleClass('is-active', active);
		});
		$('[data-market-only]').each(function () {
			var only = $(this).attr('data-market-only') === tab;
			if (this.tagName === 'OPTION') {
				$(this).prop('disabled', !only).prop('hidden', !only);
			} else {
				$(this).prop('hidden', !only);
			}
		});
		var $sort = $('#marketSort');
		if ($sort.find('option:selected').prop('disabled')) {
			$sort.val('expires');
		}
		$('.market-buy, .market-cancel').each(function () {
			var action = $(this).attr('action').replace(/&tab=[a-z]+/, '') + '&tab=' + tab;
			$(this).attr('action', action);
		});
		if (root.history && root.history.replaceState) {
			syncUrl();
		}
		applyView();
	}

	var start = Date.now() / 1000;

	function tick() {
		var elapsed = Date.now() / 1000 - start;
		var expiredText = $('#market').attr('data-expired') || '';
		$('[data-time]').each(function () {
			var left = toNumber($(this).attr('data-time')) - elapsed;
			$(this).text(formatSeconds(left));
			if (left <= 0) {
				var $row = $(this).closest('.market-offer');
				if ($row.length && !$row.data('expired')) {
					$row.data('expired', expiredText).addClass('market-offer--expired');
					$row.find('.market-buy__btn').prop('disabled', true);
					$row.find('.js-reason').text(expiredText);
				}
			}
		});
	}

	$(function () {
		var $market = $('#market');
		if (!$market.length) {
			return;
		}

		$('[data-market-tab]').on('click', function (e) {
			e.preventDefault();
			showTab($(this).attr('data-market-tab'));
		});
		$('#marketPayWith').on('change', updatePayWith);
		$('#marketFilterGet, #marketFilterPay, #marketSort, #marketOnlyBuyable').on('change', applyView);
		$('input[name=ratio-metal], input[name=ratio-crystal], input[name=ratio-deuterium]').on('change input', calculateRatios);

		$('.market-buy').on('submit', function () {
			var $row = $(this).closest('.market-offer');
			var key = payKey();
			if ($row.data('expired') || $row.attr('data-block-' + key)) {
				return false;
			}
			var text = fillTemplate($market.attr('data-confirm-buy') || '', [
				$row.attr('data-get-label'),
				$row.find('.table_username').text(),
				$row.attr('data-pay-label'),
				$row.attr('data-ships-' + key),
				($row.attr('data-fuel-' + key) || '0') + ' ' + ($market.attr('data-fuel-label') || ''),
				formatSeconds(toNumber($row.attr('data-delivery')))
			]);
			if (!root.confirm(text)) {
				return false;
			}
			$(this).find('button').prop('disabled', true);
			return true;
		});
		$('.market-cancel').on('submit', function () {
			if (!root.confirm($market.attr('data-confirm-cancel') || '')) {
				return false;
			}
			$(this).find('button').prop('disabled', true);
			return true;
		});

		showTab(currentTab());
		calculateRatios();
		root.setInterval(tick, 1000);
		tick();
	});
})(typeof window !== 'undefined' ? window : globalThis);
