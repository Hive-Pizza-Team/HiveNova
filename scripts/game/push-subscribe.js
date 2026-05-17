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

	function fetchStatus() {
		return fetch('game.php?page=push&mode=status', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	function subscribeWithRegistration(reg, publicKey) {
		return reg.pushManager.getSubscription().then(function (existing) {
			if (existing) {
				return existing;
			}
			return reg.pushManager.subscribe({
				userVisibleOnly: true,
				applicationServerKey: urlBase64ToUint8Array(publicKey)
			});
		}).then(function (subscription) {
			return fetch('game.php?page=push&mode=subscribe', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(subscription.toJSON())
			});
		});
	}

	window.HiveNovaPush = {
		fetchStatus: fetchStatus,

		enable: function (options) {
			options = options || {};
			if (!('Notification' in window) || !('PushManager' in window)) {
				return Promise.reject(new Error('unsupported'));
			}
			return fetchStatus().then(function (cfg) {
				if (!cfg.configured || !cfg.publicKey) {
					throw new Error('not_configured');
				}
				if (!cfg.enabled) {
					throw new Error('disabled');
				}

				var permissionPromise;
				if (options.skipPermissionRequest && Notification.permission === 'granted') {
					permissionPromise = Promise.resolve('granted');
				} else if (Notification.permission === 'granted') {
					permissionPromise = Promise.resolve('granted');
				} else if (Notification.permission === 'denied') {
					permissionPromise = Promise.resolve('denied');
				} else {
					permissionPromise = Notification.requestPermission();
				}

				return permissionPromise.then(function (perm) {
					if (perm !== 'granted') {
						throw new Error('denied');
					}
					return registerServiceWorker().then(function (reg) {
						if (!reg) {
							throw new Error('no_sw');
						}
						return subscribeWithRegistration(reg, cfg.publicKey);
					});
				});
			});
		},

		disable: function () {
			return registerServiceWorker().then(function (reg) {
				var endpoint = null;
				if (reg && reg.pushManager) {
					return reg.pushManager.getSubscription().then(function (sub) {
						if (sub) {
							endpoint = sub.endpoint;
							return sub.unsubscribe();
						}
					}).then(function () {
						return fetch('game.php?page=push&mode=unsubscribe', {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({ endpoint: endpoint })
						});
					});
				}
				return fetch('game.php?page=push&mode=unsubscribe', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({})
				});
			});
		},

		maybeAutoSubscribe: function () {
			if (!('Notification' in window) || !('PushManager' in window)) {
				return Promise.resolve();
			}
			if (Notification.permission === 'denied') {
				return Promise.resolve();
			}
			return fetchStatus().then(function (cfg) {
				if (!cfg.configured || !cfg.enabled) {
					return;
				}
				return HiveNovaPush.enable({
					skipPermissionRequest: Notification.permission === 'granted'
				}).catch(function (err) {
					if (err && err.message === 'denied') {
						return;
					}
					if (err && err.message === 'not_configured') {
						return;
					}
				});
			});
		}
	};

	$(function () {
		registerServiceWorker().then(function () {
			HiveNovaPush.maybeAutoSubscribe();
		});

		$('#pushAlerts').on('change', function () {
			if (!this.checked) {
				HiveNovaPush.disable();
			} else {
				HiveNovaPush.enable().catch(function (err) {
					if (err && err.message === 'denied') {
						$('#pushAlerts').prop('checked', false);
					}
					if (err && err.message === 'not_configured') {
						$('#pushAlerts').prop('checked', false);
					}
				});
			}
		});
	});
})();
