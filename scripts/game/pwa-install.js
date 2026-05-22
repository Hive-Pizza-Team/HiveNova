/**
 * PWA install prompts: platform-specific steps and beforeinstallprompt on Chromium.
 */
(function () {
	var DISMISS_KEY = 'hivenova_pwa_install_dismissed';
	var DISMISS_MS = 30 * 24 * 60 * 60 * 1000;
	var deferredPrompt = null;

	function isStandalone() {
		return window.matchMedia('(display-mode: standalone)').matches
			|| window.navigator.standalone === true;
	}

	function isIos() {
		return (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream)
			|| (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	}

	function isAndroid() {
		return /Android/i.test(navigator.userAgent);
	}

	function isFirefox() {
		return /Firefox|FxiOS/i.test(navigator.userAgent);
	}

	function isEdge() {
		return /Edg/i.test(navigator.userAgent);
	}

	function isChromium() {
		return /Chrome|CriOS|Edg|OPR|SamsungBrowser/i.test(navigator.userAgent)
			&& !isFirefox();
	}

	function isSafari() {
		return /Safari/i.test(navigator.userAgent) && !isChromium();
	}

	function isMobileViewport() {
		return window.matchMedia('(max-width: 699px)').matches;
	}

	function isDismissed() {
		try {
			var raw = localStorage.getItem(DISMISS_KEY);
			if (!raw) {
				return false;
			}
			return Date.now() - parseInt(raw, 10) < DISMISS_MS;
		} catch (e) {
			return false;
		}
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

	function dismiss() {
		try {
			localStorage.setItem(DISMISS_KEY, String(Date.now()));
		} catch (e) {}
		hideBanner();
	}

	function hideBanner() {
		var banner = document.getElementById('pwa-install-banner');
		if (banner) {
			banner.hidden = true;
		}
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
		if (isStandalone() || isDismissed()) {
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

	window.addEventListener('beforeinstallprompt', function (e) {
		e.preventDefault();
		deferredPrompt = e;
		showBanner();
		updateSettingsInstallButton();
	});

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

	window.HiveNovaPwaInstall = {
		install: install,
		isStandalone: isStandalone,
		dismiss: dismiss,
		getInstallPlatform: getInstallPlatform
	};

	$(function () {
		initBanner();
		initSettings();
	});
})();
