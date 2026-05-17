(function () {
	function urlBase64ToUint8Array(base64String) {
		var padding = '='.repeat((4 - base64String.length % 4) % 4);
		var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		var rawData = window.atob(base64);
		var outputArray = new Uint8Array(rawData.length);
		for (var i = 0; i < rawData.length; ++i) {
			outputArray[i] = rawData.charCodeAt(i);
		}
		return outputArray;
	}

	function registerServiceWorker() {
		if (!('serviceWorker' in navigator)) {
			return Promise.resolve(null);
		}
		return navigator.serviceWorker.register('sw.js').catch(function () {
			return null;
		});
	}

	window.HiveNovaPush = {
		enable: function () {
			if (!('Notification' in window) || !('PushManager' in window)) {
				return Promise.reject(new Error('unsupported'));
			}
			return fetch('game.php?page=push&mode=vapidPublicKey', { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (cfg) {
					if (!cfg.configured || !cfg.publicKey) {
						throw new Error('not_configured');
					}
					return Notification.requestPermission().then(function (perm) {
						if (perm !== 'granted') {
							throw new Error('denied');
						}
						return registerServiceWorker().then(function (reg) {
							if (!reg) {
								throw new Error('no_sw');
							}
							return reg.pushManager.subscribe({
								userVisibleOnly: true,
								applicationServerKey: urlBase64ToUint8Array(cfg.publicKey)
							});
						});
					}).then(function (subscription) {
						return fetch('game.php?page=push&mode=subscribe', {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify(subscription.toJSON())
						});
					});
				});
		},
		disable: function () {
			return registerServiceWorker().then(function (reg) {
				if (!reg || !reg.pushManager) {
					return;
				}
				return reg.pushManager.getSubscription().then(function (sub) {
					if (!sub) {
						return;
					}
					return fetch('game.php?page=push&mode=unsubscribe', {
						method: 'POST',
						credentials: 'same-origin',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ endpoint: sub.endpoint })
					}).then(function () {
						return sub.unsubscribe();
					});
				});
			});
		}
	};

	$(function () {
		$('#push-notifications-enable').on('click', function (e) {
			e.preventDefault();
			HiveNovaPush.enable().then(function () {
				alert('Notifications enabled');
			}).catch(function (err) {
				if (err && err.message === 'not_configured') {
					alert('Push notifications are not configured on this server yet.');
				}
			});
		});
		$('#push-notifications-disable').on('click', function (e) {
			e.preventDefault();
			HiveNovaPush.disable();
		});
		registerServiceWorker();
	});
})();
