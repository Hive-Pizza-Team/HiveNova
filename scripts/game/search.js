(function (root) {
	'use strict';

	var DEBOUNCE_MS = 300;
	var ENTER = 13;
	var NUMPAD_ENTER = 108;
	var IGNORED_KEY_CODES = [
		91, // WINDOWS / COMMAND / COMMAND_LEFT
		18, // ALT
		20, // CAPS_LOCK
		188, // COMMA
		93, // COMMAND_RIGHT / MENU
		17, // CONTROL
		40, // DOWN
		35, // END
		27, // ESCAPE
		36, // HOME
		45, // INSERT
		37, // LEFT
		107, // NUMPAD_ADD
		110, // NUMPAD_DECIMAL
		111, // NUMPAD_DIVIDE
		106, // NUMPAD_MULTIPLY
		109, // NUMPAD_SUBTRACT
		34, // PAGE_DOWN
		33, // PAGE_UP
		190, // PERIOD
		39, // RIGHT
		16, // SHIFT
		32, // SPACE
		9, // TAB
		38 // UP
	];

	function searchQuery(raw) {
		return String(raw == null ? '' : raw).replace(/^\s+|\s+$/g, '');
	}

	function isSubmitSearchKey(event) {
		if (!event) {
			return false;
		}
		var code = event.keyCode;
		return code === ENTER || code === NUMPAD_ENTER;
	}

	function isIgnoredSearchKey(event) {
		if (!event || isSubmitSearchKey(event)) {
			return false;
		}
		return IGNORED_KEY_CODES.indexOf(event.keyCode) !== -1;
	}

	function resultsMountSelector() {
		return '#searchResults';
	}

	function fallbackMountSelector() {
		// Full layout uses a <content> tag; popup/bare use #content.
		return '#searchResults, #content > table:not(.hack), content > table:not(.hack)';
	}

	function resultUrl(type, term) {
		return 'game.php?page=search&mode=result&type=' + encodeURIComponent(type || '') +
			'&search=' + encodeURIComponent(term) + '&ajax=1';
	}

	function readSearchTerm($) {
		return searchQuery($('#searchtext').val());
	}

	function clearResults($) {
		$('#resulttable').remove();
		$('#searchResults').empty();
	}

	function mountResultsHtml($, data) {
		$('#resulttable').remove();
		var $mount = $('#searchResults');
		if ($mount.length) {
			$mount.html(data);
			return 'searchResults';
		}
		$('#content > table:not(.hack), content > table:not(.hack)').first().after(data);
		return 'fallback';
	}

	function runSearch($, ajaxGet) {
		var term = readSearchTerm($);
		if (!term) {
			clearResults($);
			$('#loading').hide();
			return false;
		}

		$('#searchEmpty').prop('hidden', true);
		$('#loading').show();
		var url = resultUrl($('#type').val(), term);
		ajaxGet(url, function (data) {
			mountResultsHtml($, data);
			$('#loading').hide();
		});
		return true;
	}

	function submitSearch($, event, ajaxGet) {
		if (event && typeof event.preventDefault === 'function') {
			event.preventDefault();
		}

		if (!readSearchTerm($)) {
			clearResults($);
			$('#searchEmpty').prop('hidden', false);
			return false;
		}

		return runSearch($, ajaxGet);
	}

	function defaultAjaxGet($) {
		return function (url, callback) {
			$.get(url, callback);
		};
	}

	function bind($, ajaxGet) {
		if (!$ || typeof $.fn === 'undefined') {
			return null;
		}

		var get = ajaxGet || defaultAjaxGet($);
		var debounceTimer = null;

		function scheduleTypedSearch() {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				runSearch($, get);
			}, DEBOUNCE_MS);
		}

		$('#searchtext').on('keyup', function (event) {
			if (isSubmitSearchKey(event)) {
				if (event && typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
				clearTimeout(debounceTimer);
				submitSearch($, event, get);
				return;
			}
			if (isIgnoredSearchKey(event)) {
				return;
			}
			scheduleTypedSearch();
		});

		$('#searchbutton').on('click', function (event) {
			clearTimeout(debounceTimer);
			submitSearch($, event, get);
		});

		$('#type').on('change', function () {
			clearTimeout(debounceTimer);
			if (readSearchTerm($)) {
				runSearch($, get);
			}
		});

		return {
			runSearch: function () { return runSearch($, get); },
			submitSearch: function (event) { return submitSearch($, event, get); }
		};
	}

	var api = {
		DEBOUNCE_MS: DEBOUNCE_MS,
		searchQuery: searchQuery,
		isSubmitSearchKey: isSubmitSearchKey,
		isIgnoredSearchKey: isIgnoredSearchKey,
		resultsMountSelector: resultsMountSelector,
		fallbackMountSelector: fallbackMountSelector,
		resultUrl: resultUrl,
		mountResultsHtml: mountResultsHtml,
		runSearch: runSearch,
		submitSearch: submitSearch,
		bind: bind
	};

	root.HiveNovaSearch = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof $ !== 'undefined' && $ && typeof $.fn !== 'undefined') {
		$(function () {
			api.bind($);
		});
	}
})(typeof window !== 'undefined' ? window : globalThis);
