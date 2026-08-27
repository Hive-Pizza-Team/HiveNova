//topnav.js
//RealTimeRessisanzeige for 2Moons
// @version 1.0
// @copyright 2010 by ShadoX

function resourceTicker(config, init) {
	if(typeof init !== "undefined" && init === true)
		window.setInterval(function(){resourceTicker(config)}, 1000);

	var resourceName = String(config.valueElem || '').replace(/^current_/, '');
	// Include duplicate-id leftovers and mobile mirrors keyed by data-resource.
	var elements = $('[id="' + config.valueElem + '"], .res_current[data-resource="' + resourceName + '"]');
	if (!elements.length) {
		return false;
	}

	var nrResource = Math.max(0, Math.floor(parseFloat(config.available) + parseFloat(config.production) / 3600 * (serverTime.getTime() - startTime) / 1000));
	var limitMax = parseFloat(config.limit[1]);
	var atMax = nrResource >= limitMax;
	if (atMax) {
		nrResource = Math.floor(limitMax);
	}

	elements.each(function() {
		var element = $(this);
		// Keep data-real in sync so fleet Max / getRessource() match the live display.
		element.data('real', nrResource).attr('data-real', nrResource);

		if (atMax) {
			element.addClass('res_current_max');
		} else {
			element.removeClass('res_current_max');
			if (nrResource >= limitMax * 0.9) {
				element.addClass('res_current_warn');
			} else {
				element.removeClass('res_current_warn');
			}
		}

		var displayHtml;
		if (viewShortlyNumber) {
			element.attr('data-tooltip-content', NumberGetHumanReadable(nrResource));
			displayHtml = shortly_number(nrResource);
		} else {
			displayHtml = NumberGetHumanReadable(nrResource);
		}

		var span = element.children('span').first();
		if (span.length) {
			span.html(displayHtml);
			if (atMax) {
				span.css('color', 'red');
			} else {
				span.css('color', '');
			}
		} else {
			element.html(displayHtml);
		}
	});

	return true;
}

function getRessource(name) {
	var el = $('[id="current_' + name + '"], .res_current[data-resource="' + name + '"]').first();
	var val = parseInt(el.data('real'), 10);
	return isNaN(val) ? 0 : val;
}
