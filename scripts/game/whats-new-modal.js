/**
 * One-time What's New release dialog: open, focus trap, dismiss once.
 */
(function (root) {
	'use strict';

	var FOCUSABLE =
		'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])';

	function getFocusable(dialog) {
		if (!dialog || typeof dialog.querySelectorAll !== 'function') {
			return [];
		}
		return Array.prototype.slice.call(dialog.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return !el.disabled && el.getAttribute('aria-hidden') !== 'true';
		});
	}

	function trapFocusKeydown(event, dialog) {
		if (!event || event.key !== 'Tab' || !dialog) {
			return false;
		}
		var nodes = getFocusable(dialog);
		if (nodes.length === 0) {
			return false;
		}
		var first = nodes[0];
		var last = nodes[nodes.length - 1];
		var active = dialog.ownerDocument ? dialog.ownerDocument.activeElement : null;
		if (event.shiftKey) {
			if (active === first || !dialog.contains(active)) {
				last.focus();
				if (typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
				return true;
			}
		} else if (active === last) {
			first.focus();
			if (typeof event.preventDefault === 'function') {
				event.preventDefault();
			}
			return true;
		}
		return false;
	}

	function createDismissSender(url, token, postFn) {
		var sent = false;
		return function sendDismiss() {
			if (sent) {
				return false;
			}
			sent = true;
			var body = 'token=' + encodeURIComponent(token || '');
			if (typeof postFn === 'function') {
				postFn(url, body);
				return true;
			}
			if (typeof root.fetch === 'function') {
				root.fetch(url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body
				});
				return true;
			}
			if (root.jQuery && typeof root.jQuery.post === 'function') {
				root.jQuery.post(url, { token: token || '' });
				return true;
			}
			return true;
		};
	}

	function openDialog(dialog) {
		if (!dialog) {
			return 'none';
		}
		if (typeof dialog.showModal === 'function') {
			try {
				dialog.showModal();
				return 'modal';
			} catch (err) {
				/* fall through */
			}
		}
		dialog.setAttribute('open', 'open');
		if (dialog.style) {
			dialog.style.display = 'block';
		}
		return 'fallback';
	}

	function closeDialog(dialog) {
		if (!dialog) {
			return;
		}
		if (typeof dialog.close === 'function' && dialog.open) {
			dialog.close();
			return;
		}
		dialog.removeAttribute('open');
		if (dialog.style) {
			dialog.style.display = 'none';
		}
	}

	function bind(dialog, options) {
		options = options || {};
		if (!dialog) {
			return null;
		}

		var url = options.url || dialog.getAttribute('data-dismiss-url') || '';
		var token = options.token || dialog.getAttribute('data-token') || '';
		var postFn = options.postFn;
		var sendDismiss = options.sendDismiss || createDismissSender(url, token, postFn);
		var previousFocus = options.previousFocus;
		var doc = dialog.ownerDocument || (typeof document !== 'undefined' ? document : null);
		if (!previousFocus && doc) {
			previousFocus = doc.activeElement || null;
		}

		var closed = false;
		function dismissAndClose() {
			if (closed) {
				sendDismiss();
				return;
			}
			closed = true;
			sendDismiss();
			closeDialog(dialog);
			if (previousFocus && typeof previousFocus.focus === 'function') {
				try {
					previousFocus.focus();
				} catch (err) {
					/* ignore */
				}
			}
		}

		function onKeydown(event) {
			if (!event) {
				return;
			}
			if (event.key === 'Escape' || event.key === 'Esc') {
				dismissAndClose();
				return;
			}
			trapFocusKeydown(event, dialog);
		}

		if (typeof dialog.addEventListener === 'function') {
			dialog.addEventListener('cancel', function (event) {
				if (event && typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
				dismissAndClose();
			});
			dialog.addEventListener('click', function (event) {
				if (event && event.target === dialog) {
					dismissAndClose();
				}
			});
			dialog.addEventListener('keydown', onKeydown);
		}

		var closeBtn = dialog.querySelector
			? dialog.querySelector('[data-whats-new-close]')
			: null;
		var gotItBtn = dialog.querySelector
			? dialog.querySelector('[data-whats-new-got-it]')
			: null;
		if (closeBtn && typeof closeBtn.addEventListener === 'function') {
			closeBtn.addEventListener('click', function (event) {
				if (event && typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
				dismissAndClose();
			});
		}
		if (gotItBtn && typeof gotItBtn.addEventListener === 'function') {
			gotItBtn.addEventListener('click', function (event) {
				if (event && typeof event.preventDefault === 'function') {
					event.preventDefault();
				}
				dismissAndClose();
			});
		}

		var mode = openDialog(dialog);
		var focusTarget = closeBtn || gotItBtn;
		if (focusTarget && typeof focusTarget.focus === 'function') {
			try {
				focusTarget.focus();
			} catch (err) {
				/* ignore */
			}
		}

		return {
			dismissAndClose: dismissAndClose,
			sendDismiss: sendDismiss,
			mode: mode,
			onKeydown: onKeydown
		};
	}

	function init(doc) {
		var host = doc || (typeof document !== 'undefined' ? document : null);
		if (!host || typeof host.getElementById !== 'function') {
			return null;
		}
		return bind(host.getElementById('whats-new-dialog'));
	}

	var api = {
		FOCUSABLE: FOCUSABLE,
		getFocusable: getFocusable,
		trapFocusKeydown: trapFocusKeydown,
		createDismissSender: createDismissSender,
		openDialog: openDialog,
		closeDialog: closeDialog,
		bind: bind,
		init: init
	};

	root.HiveNovaWhatsNewModal = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined') {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				init(document);
			});
		} else {
			init(document);
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
