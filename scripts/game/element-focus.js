/**
 * Focus an element card from a URL hash (t115 / s202 / g21 / o603).
 * Turns on the page "All" filter so category toggles cannot hide the target.
 */
(function (root) {
	'use strict';

	var ALL_FILTER_IDS = ['lab5', 'ship3', 'btn3'];
	var FRAGMENT_RE = /^[gsto]\d+$/;

	function fragmentId(hash) {
		if (!hash) {
			return '';
		}
		var id = String(hash).replace(/^#/, '');
		return FRAGMENT_RE.test(id) ? id : '';
	}

	function showAllFilters(doc) {
		if (!doc) {
			return;
		}
		for (var i = 0; i < ALL_FILTER_IDS.length; i++) {
			var btn = doc.getElementById(ALL_FILTER_IDS[i]);
			if (btn && typeof btn.click === 'function') {
				btn.click();
			}
		}
	}

	function focusById(doc, id) {
		if (!doc || !id) {
			return false;
		}
		showAllFilters(doc);
		var el = doc.getElementById(id);
		if (!el) {
			return false;
		}
		if (el.classList && typeof el.classList.add === 'function') {
			el.classList.add('element-focus');
		}
		if (typeof el.scrollIntoView === 'function') {
			el.scrollIntoView({ block: 'center' });
		}
		return true;
	}

	function boot(doc, loc) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		loc = loc || (typeof location !== 'undefined' ? location : null);
		if (!doc || !loc) {
			return false;
		}
		var id = fragmentId(loc.hash);
		if (!id) {
			return false;
		}
		return focusById(doc, id);
	}

	function scheduleBoot() {
		if (typeof root.jQuery === 'function') {
			root.jQuery(function () {
				boot();
			});
			return;
		}
		if (typeof document !== 'undefined' && document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				boot();
			});
			return;
		}
		boot();
	}

	var api = {
		fragmentId: fragmentId,
		showAllFilters: showAllFilters,
		focusById: focusById,
		boot: boot
	};

	root.HiveNovaElementFocus = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		scheduleBoot();
	}
})(typeof window !== 'undefined' ? window : globalThis);
