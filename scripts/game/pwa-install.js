/**
 * PWA install prompts: platform-specific steps and beforeinstallprompt on Chromium.
 * "Not now" is stored in localStorage for DISMISS_MS; fleet dispatch pages never show the banner.
 */
(function (root) {
	'use strict';

	var DISMISS_KEY = 'hivenova_pwa_install_dismissed';
	var DISMISS_MS = 30 * 24 * 60 * 60 * 1000;
	var FLEET_BANNER_PAGES = {
		fleetTable: true,
		fleetStep1: true,
		fleetStep2: true,
		fleetStep3: true
	};
	var deferredPrompt = null;

	function getStorage() {
		try {
			return root.localStorage || null;
		} catch (e) {
			return null;
		}
	}

	function isStandalone() {
		return root.matchMedia('(display-mode: standalone)').matches
			|| root.navigator.standalone === true;
	}

	function isIos() {
		return (/iPad|iPhone|iPod/.test(root.navigator.userAgent) && !root.MSStream)
			|| (root.navigator.platform === 'MacIntel' && root.navigator.maxTouchPoints > 1);
	}

	function isAndroid() {
		return /Android/i.test(root.navigator.userAgent);
	}

	function isFirefox() {
		return /Firefox|FxiOS/i.test(root.navigator.userAgent);
	}

	function isEdge() {
		return /Edg/i.test(root.navigator.userAgent);
	}

	function isChromium() {
		return /Chrome|CriOS|Edg|OPR|SamsungBrowser/i.test(root.navigator.userAgent)
			&& !isFirefox();
	}

	function isSafari() {
		return /Safari/i.test(root.navigator.userAgent) && !isChromium();
	}

	function isMobileViewport() {
		return root.matchMedia('(max-width: 699px)').matches;
	}

	function isDismissed(now, storage) {
		storage = storage === undefined ? getStorage() : storage;
		now = now == null ? Date.now() : now;
		if (!storage || typeof storage.getItem !== 'function') {
			return false;
		}
		try {
			var raw = storage.getItem(DISMISS_KEY);
			if (!raw) {
				return false;
			}
			var ts = parseInt(raw, 10);
			if (!isFinite(ts)) {
				return false;
			}
			return now - ts < DISMISS_MS;
		} catch (e) {
			return false;
		}
	}

	function writeDismiss(now, storage) {
		storage = storage === undefined ? getStorage() : storage;
		now = now == null ? Date.now() : now;
		if (!storage || typeof storage.setItem !== 'function') {
			return false;
		}
		try {
			storage.setItem(DISMISS_KEY, String(now));
			return true;
		} catch (e) {
			return false;
		}
	}

	function pageFromSearch(search) {
		var query = String(search == null ? '' : search).replace(/^\?/, '');
		var parts = query.split('&');
		var i;
		for (i = 0; i < parts.length; i++) {
			var pair = parts[i].split('=');
			if (decodeURIComponent(pair[0] || '') === 'page') {
				return decodeURIComponent(pair[1] || '');
			}
		}
		return '';
	}

	function getGamePage(doc, search) {
		doc = doc || (typeof document !== 'undefined' ? document : null);
		if (doc && doc.body && doc.body.id) {
			return String(doc.body.id);
		}
		if (search == null && root.location) {
			search = root.location.search;
		}
		return pageFromSearch(search);
	}

	function isFleetDispatchPage(page) {
		return !!FLEET_BANNER_PAGES[page];
	}

	function shouldShowInstallBanner(opts) {
		opts = opts || {};
		if (opts.standalone) {
			return false;
		}
		if (opts.dismissed) {
			return false;
		}
		if (isFleetDispatchPage(opts.page)) {
			return false;
		}
		return true;
	}

	function platformToDatasetKey(platform) {
		return 'hint' + platform.split('-').map(function (part) {
			return part.charAt(0).toUpperCase() + part.slice(1);
		}).join('');
	}

	function getInstallPlatform() {
		if (isIos()) {
			return 'ios';
		}
		if (isAndroid()) {
			if (deferredPrompt) {
				return 'android-chrome-prompt';
			}
			if (isChromium()) {
				return 'android-chrome-manual';
			}
			if (isFirefox()) {
				return 'android-firefox';
			}
			return 'android-other';
		}
		if (deferredPrompt) {
			return 'desktop-chrome-prompt';
		}
		if (isEdge()) {
			return 'desktop-edge';
		}
		if (isChromium()) {
			return 'desktop-chrome';
		}
		if (isSafari()) {
			return 'desktop-safari';
		}
		if (isFirefox()) {
			return 'desktop-firefox';
		}
		return 'fallback';
	}

	function getHintText(banner, platform) {
		var key = platformToDatasetKey(platform);
		return banner.dataset[key] || banner.dataset.hintFallback || '';
	}

	function hideBanner() {
		var banner = document.getElementById('pwa-install-banner');
		if (banner) {
			banner.hidden = true;
		}
	}

	function dismiss() {
		writeDismiss();
		hideBanner();
	}

	function updateInstructions() {
		var banner = document.getElementById('pwa-install-banner');
		if (!banner) {
			return;
		}
		var platform = getInstallPlatform();
		var text = getHintText(banner, platform);
		var instructions = banner.querySelector('[data-pwa-instructions]');
		if (instructions) {
			instructions.textContent = text;
		}
		var settingsHint = document.querySelector('[data-pwa-settings-instructions]');
		if (settingsHint) {
			settingsHint.textContent = text;
			settingsHint.hidden = !text;
		}
	}

	function showBanner() {
		if (!shouldShowInstallBanner({
			standalone: isStandalone(),
			dismissed: isDismissed(),
			page: getGamePage()
		})) {
			return;
		}
		var banner = document.getElementById('pwa-install-banner');
		if (!banner) {
			return;
		}

		updateInstructions();

		var installBtn = banner.querySelector('[data-pwa-install]');
		if (installBtn) {
			installBtn.hidden = isIos() || !deferredPrompt;
		}

		banner.hidden = false;
	}

	function install() {
		if (!deferredPrompt) {
			return Promise.reject();
		}
		deferredPrompt.prompt();
		return deferredPrompt.userChoice.then(function (result) {
			deferredPrompt = null;
			updateSettingsInstallButton();
			if (result.outcome === 'accepted') {
				hideBanner();
			}
			return result.outcome;
		});
	}

	function initBanner() {
		var banner = document.getElementById('pwa-install-banner');
		if (!banner) {
			return;
		}

		banner.querySelector('[data-pwa-dismiss]')?.addEventListener('click', dismiss);
		banner.querySelector('[data-pwa-install]')?.addEventListener('click', function () {
			install().catch(function () {});
		});

		if (isIos() || deferredPrompt || isMobileViewport()) {
			showBanner();
		}
	}

	function updateSettingsInstallButton() {
		var btn = document.getElementById('pwa-install-settings-btn');
		if (!btn) {
			return;
		}
		btn.hidden = isStandalone() || isIos() || !deferredPrompt;
	}

	function initSettings() {
		var row = document.getElementById('pwa-install-settings');
		if (!row) {
			return;
		}
		if (isStandalone()) {
			row.hidden = true;
			return;
		}
		row.hidden = false;
		updateInstructions();

		var installBtn = document.getElementById('pwa-install-settings-btn');
		if (installBtn) {
			installBtn.hidden = isIos() || !deferredPrompt;
			installBtn.addEventListener('click', function () {
				install().catch(function () {});
			});
		}
	}

	var api = {
		DISMISS_KEY: DISMISS_KEY,
		DISMISS_MS: DISMISS_MS,
		install: install,
		isStandalone: isStandalone,
		dismiss: dismiss,
		isDismissed: isDismissed,
		writeDismiss: writeDismiss,
		getGamePage: getGamePage,
		pageFromSearch: pageFromSearch,
		isFleetDispatchPage: isFleetDispatchPage,
		shouldShowInstallBanner: shouldShowInstallBanner,
		getInstallPlatform: getInstallPlatform
	};

	root.HiveNovaPwaInstall = api;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}

	if (typeof root.addEventListener === 'function') {
		root.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			showBanner();
			updateSettingsInstallButton();
		});
	}

	if (typeof root.$ === 'function') {
		root.$(function () {
			initBanner();
			initSettings();
		});
	}
})(typeof window !== 'undefined' ? window : globalThis);
