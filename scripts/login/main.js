/**
 * Login site scripts (no jQuery): language cookie, multi-universe form actions, Hive Keychain.
 */
(function () {
	'use strict';

	function setCookie(name, value, days) {
		var expires = '';
		if (typeof days === 'number') {
			var date = new Date();
			date.setTime(date.getTime() + days * 86400000);
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
	}

	function universeFromSelect(select) {
		return select && select.value ? String(select.value) : '';
	}

	function updateUrls(select) {
		if (!select || typeof LoginConfig === 'undefined') {
			return;
		}
		var universe = universeFromSelect(select);
		var basePath = LoginConfig.basePath || '';
		var form = select.closest('form');
		if (form) {
			var action = form.getAttribute('data-action') || form.getAttribute('action') || '';
			if (LoginConfig.unisWildcast) {
				var wildBase = basePath.replace('://', '://uni' + universe + '.');
				form.setAttribute('action', wildBase + action);
			} else {
				form.setAttribute('action', basePath + 'uni' + universe + '/' + action);
			}
		}
		document.querySelectorAll('.fb_login').forEach(function (el) {
			var href = el.getAttribute('data-href');
			if (!href) {
				return;
			}
			if (LoginConfig.unisWildcast) {
				el.setAttribute('href', basePath.replace('://', '://uni' + universe + '.') + href);
			} else {
				el.setAttribute('href', basePath + 'uni' + universe + '/' + href);
			}
		});
	}

	window.Login = {
		/** Explicit UI choice cookie (matches LoginLanguageResolver::EXPLICIT_COOKIE). */
		LANG_COOKIE: 'lang_choice',
		setLanguage: function (lang, query) {
			setCookie(Login.LANG_COOKIE, lang, 365);
			// Drop legacy auto-detection cookie so it cannot override Accept-Language.
			document.cookie = 'lang=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
			if (typeof query === 'undefined') {
				window.location.href = window.location.href;
			} else {
				window.location.href = window.location.href + query;
			}
		}
	};

	document.addEventListener('DOMContentLoaded', function () {
		// Flag links navigate with ?lang= (server persists lang_choice). Do not
		// intercept — a client-only legacy `lang` cookie used to stick forever.

		if (typeof LoginConfig !== 'undefined' && LoginConfig.isMultiUniverse) {
			document.querySelectorAll('.changeAction').forEach(function (select) {
				updateUrls(select);
				select.addEventListener('change', function () {
					updateUrls(select);
				});
			});
		} else {
			document.querySelectorAll('.fb_login').forEach(function (el) {
				var href = el.getAttribute('data-href');
				if (href && LoginConfig && LoginConfig.basePath) {
					el.setAttribute('href', LoginConfig.basePath + href);
				}
			});
		}
	});
})();

const HiveKeychainLogin = async () => {
	if (typeof hive_keychain === 'undefined') {
		alert('You must install Hive Keychain extension first.');
		return;
	}

	var usernameInput = document.getElementById('loginHive-username');
	if (!usernameInput || usernameInput.value.length === 0 || usernameInput.value.length > 16) {
		alert('You must enter a valid Hive account name first.');
		return;
	}

	const hiveaccount = usernameInput.value.toLowerCase().trim();

	try {
		await hive_keychain.requestSignBuffer(
			hiveaccount,
			hiveaccount + ' is my account.',
			'Posting',
			(response) => {
				if (response.success) {
					document.getElementById('loginHive-hiveAccount').value = hiveaccount;
					document.getElementById('loginHive-password').value = response.result;
					document.getElementById('loginHive').submit();
				} else {
					console.error('Keychain error', response.error);
				}
			},
			null,
			'Moon Login'
		);
	} catch (error) {
		console.error({ error });
	}
};
