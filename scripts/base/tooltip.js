/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

var MOBILE_TOOLTIP_INERT_HREF = 'javascript:void(0)';
var MOBILE_TOOLTIP_GUARD_MS = 500;
var MOBILE_TOOLTIP_TAP_SLOP = 16;
var mobileTooltipGuardUntil = 0;
var mobileTooltipTouch = null;
var suppressNextMobileTooltipClick = false;

function mobileTooltipMediaQuery() {
	return '(max-width: 699px), (hover: none), (pointer: coarse)';
}

function isMobileTooltip() {
	return window.matchMedia(mobileTooltipMediaQuery()).matches;
}

function normalizeTooltipTriggerHref(href) {
	if (!href || href === '#') {
		return MOBILE_TOOLTIP_INERT_HREF;
	}
	return href;
}

function armMobileTooltipGuard(now, durationMs) {
	return (now || Date.now()) + (durationMs || MOBILE_TOOLTIP_GUARD_MS);
}

function isMobileTooltipGuardActive(now, guardUntil) {
	return (now || Date.now()) < (guardUntil || mobileTooltipGuardUntil);
}

function isStationaryTooltipTouch(start, end, slop) {
	if (!start || !end) {
		return true;
	}
	var limit = slop || MOBILE_TOOLTIP_TAP_SLOP;
	var dx = end.x - start.x;
	var dy = end.y - start.y;
	return (dx * dx + dy * dy) <= (limit * limit);
}

function shouldDismissMobileTooltip(targetInsideTip, now, guardUntil) {
	if (targetInsideTip) {
		return false;
	}
	return !isMobileTooltipGuardActive(now, guardUntil);
}

function resetTooltipOverlay(tip) {
	tip = tip || $('#tooltip');
	tip.stop(true, true);
	tip.removeClass('tooltip-mobile-active tooltip_sticky_div notify notify-error notify-toast notify-success');
	tip.css({
		position: '',
		top: '',
		left: '',
		right: '',
		bottom: '',
		transform: '',
		zIndex: '',
		maxWidth: '',
		textAlign: ''
	});
}

/**
 * Leftover Fancybox/tooltip chrome after Stats → playercard → homeplanet
 * can leave #tooltip visible with empty content (tiny blank square).
 * Always safe to call on pageshow / galaxy ready.
 */
function scrubFancyboxAndTooltip(root$) {
	root$ = root$ || (typeof jQuery !== 'undefined' ? jQuery : null);
	if (!root$) {
		return false;
	}
	if (root$.fancybox && typeof root$.fancybox.close === 'function') {
		try {
			root$.fancybox.close();
		} catch (err) {
			/* ignore close races during navigation */
		}
	}
	root$('#fancybox-overlay').hide();
	root$('#fancybox-wrap').hide();
	var tip = root$('#tooltip');
	resetTooltipOverlay(tip);
	tip.hide().empty().removeData('mobile-source');
	return true;
}

function tooltipTriggerContent(raw) {
	if (raw == null) {
		return '';
	}
	var content = String(raw).replace(/^\s+|\s+$/g, '');
	return content;
}

function positionMobileTooltip(tip) {
	tip.css({
		top: Math.max(8, (window.innerHeight - tip.outerHeight()) / 2),
		left: Math.max(8, (window.innerWidth - tip.outerWidth()) / 2)
	});
}

function touchPointFromEvent(e) {
	var ev = e.originalEvent || e;
	var touch = ev.changedTouches && ev.changedTouches[0];
	if (!touch) {
		return null;
	}
	return { x: touch.clientX, y: touch.clientY };
}

function openMobileTooltip(el, e) {
	var tip = $('#tooltip');
	var content = tooltipTriggerContent($(el).attr('data-tooltip-content'));
	if (!content) {
		return false;
	}
	if (e) {
		e.preventDefault();
		e.stopPropagation();
	}
	if (e && e.type === 'touchend') {
		suppressNextMobileTooltipClick = true;
	}
	if (tip.is(':visible') && tip.data('mobile-source') === el) {
		resetTooltipOverlay(tip);
		tip.hide().removeData('mobile-source');
		mobileTooltipGuardUntil = armMobileTooltipGuard();
		return true;
	}
	resetTooltipOverlay(tip);
	tip.html(content).data('mobile-source', el).addClass('tooltip-mobile-active').show();
	positionMobileTooltip(tip);
	mobileTooltipGuardUntil = armMobileTooltipGuard();
	setTimeout(function () {
		positionMobileTooltip(tip);
	}, 0);
	return true;
}

function dismissMobileTooltipIfOutside(target) {
	if (!isMobileTooltip()) {
		return;
	}
	var inside = $(target).closest('.tooltip, .tooltip_sticky, #tooltip').length > 0;
	if (!shouldDismissMobileTooltip(inside, Date.now(), mobileTooltipGuardUntil)) {
		return;
	}
	resetTooltipOverlay($('#tooltip'));
	$('#tooltip').hide().removeData('mobile-source');
}

if (typeof jQuery !== 'undefined') {
$(document).ready(function () {
	$(".tooltip, .tooltip_sticky").each(function () {
		$(this).attr('href', normalizeTooltipTriggerHref($(this).attr('href')));
	});

	$(".tooltip, .tooltip_sticky").live('touchstart', function (e) {
		if (!isMobileTooltip()) {
			return;
		}
		var point = touchPointFromEvent(e);
		mobileTooltipTouch = point ? { x: point.x, y: point.y, el: this } : { el: this };
	});

	$(".tooltip, .tooltip_sticky").live('touchend', function (e) {
		if (!isMobileTooltip()) {
			return;
		}
		var start = mobileTooltipTouch && mobileTooltipTouch.el === this ? mobileTooltipTouch : null;
		var end = touchPointFromEvent(e);
		mobileTooltipTouch = null;
		if (!isStationaryTooltipTouch(start, end)) {
			return;
		}
		openMobileTooltip(this, e);
	});

	$(".tooltip, .tooltip_sticky").live('click', function (e) {
		if (!isMobileTooltip()) {
			return;
		}
		if (suppressNextMobileTooltipClick || isMobileTooltipGuardActive()) {
			suppressNextMobileTooltipClick = false;
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		openMobileTooltip(this, e);
	});

	$(document).on('click touchend', function (e) {
		if (!isMobileTooltip()) {
			return;
		}
		dismissMobileTooltipIfOutside(e.target);
	});

	$(".tooltip").live({
		mouseenter : function (e) {
			if (isMobileTooltip()) {
				return;
			}
			var content = tooltipTriggerContent($(this).attr('data-tooltip-content'));
			if (!content) {
				return;
			}
			var tip = $('#tooltip');
			resetTooltipOverlay(tip);
			tip.html(content);
			tip.show();
		},
		mouseleave : function () {
			var tip = $('#tooltip');
			tip.hide();
		},
		mousemove : function (e) {
			var tip = $('#tooltip');
			var mousex = e.pageX + 20;
			var mousey = e.pageY + 20;
			var tipWidth = tip.width();
			var tipHeight = tip.height();
			var tipVisX = $(window).width() - (mousex + tipWidth);
			var tipVisY = $(window).height() - (mousey + tipHeight);
			if (tipVisX < 20) {
				mousex = e.pageX - tipWidth - 20;
			};
			if (tipVisY < 20) {
				mousey = e.pageY - tipHeight - 20;
			};
			tip.css({
				top : mousey,
				left : mousex
			});
		}
	});
	$(".tooltip_sticky").live('mouseenter', function (e) {
		if (isMobileTooltip()) {
			return;
		}
		var content = tooltipTriggerContent($(this).attr('data-tooltip-content'));
		if (!content) {
			return;
		}
		var tip = $('#tooltip');
		resetTooltipOverlay(tip);
		tip.html(content);
		tip.addClass('tooltip_sticky_div');
		tip.css({
			top : e.pageY - tip.outerHeight() / 2,
			left : e.pageX - tip.outerWidth() / 2
		});
		tip.show();
	});
	$(".tooltip_sticky_div").live('mouseleave', function () {
		var tip = $('#tooltip');
		tip.removeClass('tooltip_sticky_div');
		tip.hide();
	});

	// Scrub on every pageshow (including first load and bfcache). The old
	// "only if fancybox :visible" gate missed empty leftover #tooltip chrome
	// after Stats → playercard → homeplanet navigation (#507/#522).
	$(window).on('pageshow', function () {
		scrubFancyboxAndTooltip($);
	});
});
}

if (typeof window !== 'undefined') {
	window.scrubFancyboxAndTooltip = scrubFancyboxAndTooltip;
	window.resetTooltipOverlay = resetTooltipOverlay;
	window.tooltipTriggerContent = tooltipTriggerContent;
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		MOBILE_TOOLTIP_INERT_HREF: MOBILE_TOOLTIP_INERT_HREF,
		MOBILE_TOOLTIP_GUARD_MS: MOBILE_TOOLTIP_GUARD_MS,
		MOBILE_TOOLTIP_TAP_SLOP: MOBILE_TOOLTIP_TAP_SLOP,
		mobileTooltipMediaQuery: mobileTooltipMediaQuery,
		normalizeTooltipTriggerHref: normalizeTooltipTriggerHref,
		armMobileTooltipGuard: armMobileTooltipGuard,
		isMobileTooltipGuardActive: isMobileTooltipGuardActive,
		isStationaryTooltipTouch: isStationaryTooltipTouch,
		shouldDismissMobileTooltip: shouldDismissMobileTooltip,
		tooltipTriggerContent: tooltipTriggerContent,
		scrubFancyboxAndTooltip: scrubFancyboxAndTooltip,
		resetTooltipOverlay: resetTooltipOverlay
	};
}
