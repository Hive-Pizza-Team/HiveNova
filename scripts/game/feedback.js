(function () {
	'use strict';

	var fab = document.getElementById('feedback-fab');
	var modal = document.getElementById('feedback-modal');
	var form = document.getElementById('feedback-form');
	var configEl = document.getElementById('feedback-config-json');
	if (!fab || !modal || !form || !configEl) {
		return;
	}

	var config;
	try {
		config = JSON.parse(configEl.textContent || '{}');
	} catch (e) {
		config = {};
	}

	var csrfInput = document.getElementById('feedback-csrf');
	var pageUrlInput = document.getElementById('feedback-page-url');
	var viewportInput = document.getElementById('feedback-viewport');
	var contextPreview = document.getElementById('feedback-context-preview');
	var statusEl = document.getElementById('feedback-status');
	var submitBtn = document.getElementById('feedback-submit');
	var captureCheckbox = document.getElementById('feedback-capture-screenshot');
	var fileInput = document.getElementById('feedback-screenshot');
	var descriptionInput = document.getElementById('feedback-description');
	var capturedBlob = null;
	var captureFailed = false;

	function sanitizeClientUrl(href) {
		try {
			var url = new URL(href, window.location.origin);
			var blocked = {
				token: 1, csrf: 1, admin_csrf: 1, password: 1, passwd: 1, pass: 1,
				session: 1, sessionid: 1, sid: 1, access_token: 1, refresh_token: 1,
				api_key: 1, apikey: 1, auth: 1, authorization: 1, keychain: 1, code: 1, state: 1
			};
			Array.from(url.searchParams.keys()).forEach(function (key) {
				if (blocked[String(key).toLowerCase()]) {
					url.searchParams.delete(key);
				}
			});
			url.hash = '';
			return url.pathname + url.search;
		} catch (err) {
			return window.location.pathname;
		}
	}

	function buildContextLines() {
		var ctx = config.context || {};
		var page = sanitizeClientUrl(window.location.href);
		var viewport = window.innerWidth + 'x' + window.innerHeight;
		var ua = navigator.userAgent || 'n/a';
		var account = (ctx.userId && Number(ctx.userId) > 0)
			? ((ctx.username || '') + ' (#' + ctx.userId + ')').trim()
			: 'guest';
		var planet = 'n/a';
		if (ctx.planetName || ctx.planetCoords || ctx.planetId) {
			planet = [ctx.planetName, ctx.planetCoords ? '[' + ctx.planetCoords + ']' : '', ctx.planetId ? '#' + ctx.planetId : '']
				.filter(Boolean).join(' ');
		}
		return [
			'Page: ' + page,
			'Universe: ' + (ctx.universe || 'n/a'),
			'Account: ' + account,
			'Planet: ' + planet,
			'Browser/OS: ' + ua,
			'Viewport: ' + viewport,
			'Language: ' + (ctx.language || 'n/a'),
			'Version: ' + (ctx.version || 'n/a'),
			'Time: ' + new Date().toISOString().replace('T', ' ').replace(/\.\d+Z$/, ' UTC')
		].join('\n');
	}

	function refreshContext() {
		if (pageUrlInput) {
			pageUrlInput.value = sanitizeClientUrl(window.location.href);
		}
		if (viewportInput) {
			viewportInput.value = window.innerWidth + 'x' + window.innerHeight;
		}
		if (contextPreview) {
			contextPreview.textContent = buildContextLines();
		}
	}

	function setStatus(message, isError) {
		if (!statusEl) {
			return;
		}
		if (!message) {
			statusEl.hidden = true;
			statusEl.textContent = '';
			statusEl.classList.remove('is-error', 'is-success');
			return;
		}
		statusEl.hidden = false;
		statusEl.textContent = message;
		statusEl.classList.toggle('is-error', !!isError);
		statusEl.classList.toggle('is-success', !isError);
	}

	function openModal() {
		refreshContext();
		setStatus('', false);
		capturedBlob = null;
		captureFailed = false;
		if (captureCheckbox) {
			captureCheckbox.checked = false;
		}
		if (typeof modal.showModal === 'function') {
			modal.showModal();
		} else {
			modal.setAttribute('open', 'open');
		}
		if (descriptionInput) {
			descriptionInput.focus();
		}
	}

	function closeModal() {
		if (typeof modal.close === 'function') {
			modal.close();
		} else {
			modal.removeAttribute('open');
		}
	}

	function loadHtml2Canvas() {
		return new Promise(function (resolve, reject) {
			if (window.html2canvas) {
				resolve(window.html2canvas);
				return;
			}
			var existing = document.querySelector('script[data-feedback-html2canvas]');
			if (existing) {
				existing.addEventListener('load', function () {
					resolve(window.html2canvas);
				});
				existing.addEventListener('error', reject);
				return;
			}
			var script = document.createElement('script');
			script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
			script.async = true;
			script.dataset.feedbackHtml2canvas = '1';
			script.onload = function () {
				if (window.html2canvas) {
					resolve(window.html2canvas);
				} else {
					reject(new Error('html2canvas missing'));
				}
			};
			script.onerror = reject;
			document.head.appendChild(script);
		});
	}

	function capturePageScreenshot() {
		return loadHtml2Canvas().then(function (html2canvas) {
			return html2canvas(document.body, {
				useCORS: true,
				allowTaint: true,
				logging: false,
				scale: Math.min(window.devicePixelRatio || 1, 1.5),
				windowWidth: document.documentElement.clientWidth,
				windowHeight: document.documentElement.clientHeight
			});
		}).then(function (canvas) {
			return new Promise(function (resolve, reject) {
				canvas.toBlob(function (blob) {
					if (blob) {
						resolve(blob);
					} else {
						reject(new Error('blob failed'));
					}
				}, 'image/png');
			});
		});
	}

	fab.addEventListener('click', openModal);
	modal.querySelectorAll('[data-feedback-close]').forEach(function (el) {
		el.addEventListener('click', closeModal);
	});
	modal.addEventListener('cancel', function (event) {
		event.preventDefault();
		closeModal();
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		setStatus('', false);
		captureFailed = false;
		if (submitBtn) {
			submitBtn.disabled = true;
		}

		var run = Promise.resolve(null);
		if (captureCheckbox && captureCheckbox.checked && !(fileInput && fileInput.files && fileInput.files.length)) {
			run = capturePageScreenshot().then(function (blob) {
				capturedBlob = blob;
				captureFailed = false;
				return blob;
			}).catch(function () {
				capturedBlob = null;
				captureFailed = true;
				return null;
			});
		}

		run.then(function () {
			refreshContext();
			var body = new FormData(form);
			if (capturedBlob && !(fileInput && fileInput.files && fileInput.files.length)) {
				body.set('screenshot', capturedBlob, 'page-screenshot.png');
			}
			var maxBytes = Number(config.maxScreenshotBytes) || (2 * 1024 * 1024);
			var shot = body.get('screenshot');
			if (shot && shot.size && shot.size > maxBytes) {
				setStatus('Screenshot must be 2 MB or smaller.', true);
				if (submitBtn) {
					submitBtn.disabled = false;
				}
				return null;
			}
			var submitUrl = config.submitUrl || form.action;
			if (submitUrl.indexOf('ajax=1') === -1) {
				submitUrl += (submitUrl.indexOf('?') >= 0 ? '&' : '?') + 'ajax=1';
			}
			return fetch(submitUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			}).then(function (response) {
				return response.json().then(function (data) {
					return { okHttp: response.ok, data: data || {} };
				}).catch(function () {
					return { okHttp: response.ok, data: {} };
				});
			});
		}).then(function (result) {
			if (!result) {
				return;
			}
			if (result.data && result.data.csrf && csrfInput) {
				csrfInput.value = result.data.csrf;
				config.csrf = result.data.csrf;
			}
			if (result.data && result.data.ok) {
				var successMsg = result.data.message || ('Thanks! Reference: ' + (result.data.reference || ''));
				if (captureFailed) {
					var tpl = (config.messages && config.messages.successNoScreenshot)
						|| 'Thanks! Your report was sent without a screenshot. Reference: %s';
					successMsg = tpl.replace('%s', result.data.reference || '');
				}
				setStatus(successMsg, false);
				form.reset();
				if (csrfInput && config.csrf) {
					csrfInput.value = config.csrf;
				}
				capturedBlob = null;
				captureFailed = false;
				refreshContext();
				return;
			}
			setStatus((result.data && result.data.message) || 'Could not send feedback. Please try again.', true);
		}).catch(function () {
			setStatus('Could not send feedback. Please try again.', true);
		}).then(function () {
			if (submitBtn) {
				submitBtn.disabled = false;
			}
		});
	});
}());
