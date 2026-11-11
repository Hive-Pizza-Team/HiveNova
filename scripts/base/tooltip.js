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
var TOOLTIP_TOUCH_HOVER_GUARD_MS = 800;
var mobileTooltipGuardUntil = 0;
var mobileTooltipTouch = null;
var lastTooltipTouchAt = 0;
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

function noteTooltipTouch(now) {
	lastTooltipTouchAt = now || Date.now();
	return lastTooltipTouchAt;
}

function isRecentTooltipTouch(now, lastTouchAt, guardMs) {
	if (!lastTouchAt) {
		return false;
	}
	return ((now || Date.now()) - lastTouchAt) < (guardMs || TOOLTIP_TOUCH_HOVER_GUARD_MS);
}

function shouldUseHoverTooltip(mobile, recentTouch) {
	return !mobile && !recentTouch;
}

function tooltipClickAction(suppressClick, guardActive, mobile) {
	if (suppressClick || guardActive) {
		return 'swallow';
	}
	if (!mobile) {
		return 'ignore';
	}
	return 'open';
}

function isHoverTooltipEnabled() {
	return shouldUseHoverTooltip(
		isMobileTooltip(),
		isRecentTooltipTouch(Date.now(), lastTooltipTouchAt, TOOLTIP_TOUCH_HOVER_GUARD_MS)
	);
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

function positionMobileTooltip(tip) {
	tip.css({
		position: 'fixed',
		top: Math.max(8, (window.innerHeight - tip.outerHeight()) / 2),
		left: Math.max(8, (window.innerWidth - tip.outerWidth()) / 2),
		right: 'auto',
		bottom: 'auto',
		transform: 'none'
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
	var content = $(el).attr('data-tooltip-content');
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
	var tip = $('#tooltip');
	if (!tip.hasClass('tooltip-mobile-active') && !isMobileTooltip()) {
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

	// A finger tap is the signal. Landscape phones are often ≥700px wide, and some
	// browsers still report a fine pointer there, so mouseenter never runs.
	$(".tooltip, .tooltip_sticky").live('touchstart', function (e) {
		noteTooltipTouch();
		var point = touchPointFromEvent(e);
		mobileTooltipTouch = point ? { x: point.x, y: point.y, el: this } : { el: this };
	});

	$(".tooltip, .tooltip_sticky").live('touchend', function (e) {
		noteTooltipTouch();
		var start = mobileTooltipTouch && mobileTooltipTouch.el === this ? mobileTooltipTouch : null;
		var end = touchPointFromEvent(e);
		mobileTooltipTouch = null;
		if (!isStationaryTooltipTouch(start, end)) {
			return;
		}
		openMobileTooltip(this, e);
	});

	$(".tooltip, .tooltip_sticky").live('click', function (e) {
		var action = tooltipClickAction(
			suppressNextMobileTooltipClick,
			isMobileTooltipGuardActive(),
			isMobileTooltip()
		);
		if (action === 'swallow') {
			suppressNextMobileTooltipClick = false;
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		if (action !== 'open') {
			return;
		}
		openMobileTooltip(this, e);
	});

	$(document).on('click touchend', function (e) {
		dismissMobileTooltipIfOutside(e.target);
	});

	$(".tooltip").live({
		mouseenter : function (e) {
			if (!isHoverTooltipEnabled()) {
				return;
			}
			var tip = $('#tooltip');
			tip.html($(this).attr('data-tooltip-content'));
			tip.show();
		},
		mouseleave : function () {
			var tip = $('#tooltip');
			if (tip.hasClass('tooltip-mobile-active') || !isHoverTooltipEnabled()) {
				return;
			}
			tip.hide();
		},
		mousemove : function (e) {
			if (!isHoverTooltipEnabled() || $('#tooltip').hasClass('tooltip-mobile-active')) {
				return;
			}
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
		if (!isHoverTooltipEnabled()) {
			return;
		}
		var tip = $('#tooltip');
		tip.html($(this).attr('data-tooltip-content'));
		tip.addClass('tooltip_sticky_div');
		tip.css({
			top : e.pageY - tip.outerHeight() / 2,
			left : e.pageX - tip.outerWidth() / 2
		});
		tip.show();
	});
	$(".tooltip_sticky_div").live('mouseleave', function () {
		var tip = $('#tooltip');
		if (tip.hasClass('tooltip-mobile-active')) {
			tip.removeClass('tooltip_sticky_div');
			return;
		}
		tip.removeClass('tooltip_sticky_div');
		tip.hide();
	});

	$(window).on('pageshow', function (e) {
		var persisted = !!(e.originalEvent && e.originalEvent.persisted);
		var overlay = $('#fancybox-overlay');
		var wrap = $('#fancybox-wrap');
		if (!persisted && !overlay.is(':visible') && !wrap.is(':visible')) {
			return;
		}
		if ($.fancybox && typeof $.fancybox.close === 'function') {
			$.fancybox.close();
		}
		overlay.hide();
		wrap.hide();
		resetTooltipOverlay($('#tooltip'));
		$('#tooltip').hide().removeData('mobile-source');
	});
});
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		MOBILE_TOOLTIP_INERT_HREF: MOBILE_TOOLTIP_INERT_HREF,
		MOBILE_TOOLTIP_GUARD_MS: MOBILE_TOOLTIP_GUARD_MS,
		MOBILE_TOOLTIP_TAP_SLOP: MOBILE_TOOLTIP_TAP_SLOP,
		TOOLTIP_TOUCH_HOVER_GUARD_MS: TOOLTIP_TOUCH_HOVER_GUARD_MS,
		mobileTooltipMediaQuery: mobileTooltipMediaQuery,
		normalizeTooltipTriggerHref: normalizeTooltipTriggerHref,
		armMobileTooltipGuard: armMobileTooltipGuard,
		isMobileTooltipGuardActive: isMobileTooltipGuardActive,
		isStationaryTooltipTouch: isStationaryTooltipTouch,
		shouldDismissMobileTooltip: shouldDismissMobileTooltip,
		isRecentTooltipTouch: isRecentTooltipTouch,
		shouldUseHoverTooltip: shouldUseHoverTooltip,
		tooltipClickAction: tooltipClickAction
	};
}
