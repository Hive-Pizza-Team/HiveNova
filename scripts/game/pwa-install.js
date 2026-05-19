/**
 * PWA install prompts: beforeinstallprompt on Chromium, Share instructions on iOS.
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
		return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
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

	function showBanner() {
		if (isStandalone() || isDismissed()) {
			return;
		}
		var banner = document.getElementById('pwa-install-banner');
		if (!banner) {
			return;
		}

		var installBtn = banner.querySelector('[data-pwa-install]');
		var iosBlock = banner.querySelector('[data-pwa-ios]');
		var androidBlock = banner.querySelector('[data-pwa-android]');

		if (isIos()) {
			if (iosBlock) {
				iosBlock.hidden = false;
			}
			if (installBtn) {
				installBtn.hidden = true;
			}
		} else {
			if (androidBlock) {
				androidBlock.hidden = false;
			}
			if (installBtn) {
				installBtn.hidden = !deferredPrompt;
			}
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

		if (isIos()) {
			showBanner();
		} else if (deferredPrompt) {
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

		var iosHint = row.querySelector('[data-pwa-settings-ios]');
		var installBtn = document.getElementById('pwa-install-settings-btn');
		if (iosHint) {
			iosHint.hidden = !isIos();
		}
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
		dismiss: dismiss
	};

	$(function () {
		initBanner();
		initSettings();
	});
})();
