(function (root) {
	'use strict';

	function isBlank(text) {
		return String(text == null ? '' : text).replace(/^\s+|\s+$/g, '').length === 0;
	}

	function composeHost(win) {
		if (!win) {
			return win;
		}
		return win.parent && win.parent !== win ? win.parent : win;
	}

	function showOnHost(host, message) {
		if (!host) {
			return false;
		}
		if (typeof host.showGameToast === 'function') {
			host.showGameToast(message, 'notify-toast notify-success', 2500);
			return true;
		}
		if (typeof host.NotifyBox === 'function') {
			host.NotifyBox(message);
			return true;
		}
		return false;
	}

	function closeHostDialog(host) {
		if (host && host.$ && host.$.fancybox && typeof host.$.fancybox.close === 'function') {
			host.$.fancybox.close();
		}
	}

	function setFormError(el, message) {
		if (!el) {
			return;
		}
		if (!message) {
			el.hidden = true;
			el.textContent = '';
			return;
		}
		el.hidden = false;
		el.textContent = message;
	}

	function payloadMessage(data) {
		if (data && typeof data === 'object' && typeof data.message === 'string') {
			return data.message;
		}
		if (typeof data === 'string') {
			return data;
		}
		return '';
	}

	function payloadOk(data) {
		return !!(data && typeof data === 'object' && data.ok === true);
	}

	function init(doc, win, postFn) {
		if (!doc) {
			return null;
		}
		var form = doc.getElementById('message');
		if (!form || form.getAttribute('data-message-write') !== '1') {
			return null;
		}
		var submit = doc.getElementById('submit');
		var text = doc.getElementById('text');
		var errorEl = doc.getElementById('message-write-error');
		var emptyMsg = form.getAttribute('data-empty') || '';
		var url = form.getAttribute('data-url') || form.getAttribute('action') || '';
		var post = postFn;

		function onSubmit(ev) {
			if (ev && typeof ev.preventDefault === 'function') {
				ev.preventDefault();
			}
			if (isBlank(text ? text.value : '')) {
				setFormError(errorEl, emptyMsg);
				return false;
			}
			setFormError(errorEl, '');
			if (submit) {
				submit.disabled = true;
			}
			if (typeof post !== 'function') {
				return false;
			}
			return Promise.resolve(post(url, form)).then(function (data) {
				var msg = payloadMessage(data);
				if (!payloadOk(data)) {
					setFormError(errorEl, msg || emptyMsg);
					if (submit) {
						submit.disabled = false;
					}
					return data;
				}
				var host = composeHost(win);
				showOnHost(host, msg);
				closeHostDialog(host);
				return data;
			}).catch(function () {
				if (submit) {
					submit.disabled = false;
				}
			});
		}

		form.addEventListener('submit', onSubmit);
		if (submit) {
			submit.addEventListener('click', function (ev) {
				if (submit.type === 'button') {
					onSubmit(ev);
				}
			});
		}

		return { onSubmit: onSubmit };
	}

	var api = {
		isBlank: isBlank,
		composeHost: composeHost,
		showOnHost: showOnHost,
		closeHostDialog: closeHostDialog,
		setFormError: setFormError,
		payloadMessage: payloadMessage,
		payloadOk: payloadOk,
		init: init
	};

	root.HiveNovaMessageWrite = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof document !== 'undefined' && typeof window !== 'undefined') {
		var defaultPost = typeof window.jQuery === 'function'
			? function (url, form) {
				return window.jQuery.post(url, window.jQuery(form).serialize(), null, 'json');
			}
			: null;
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				init(document, window, defaultPost);
			});
		} else {
			init(document, window, defaultPost);
		}
	}
})(typeof window !== 'undefined' ? window : globalThis);
