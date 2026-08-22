// Tab switching
document.addEventListener('DOMContentLoaded', function() {
	var btns = document.querySelectorAll('.reg-tab-btn');
	var panels = document.querySelectorAll('.reg-tab-panel');
	var seasonalUnis = {};
	var seasonalNode = document.getElementById('reg-seasonal-unis');
	if (seasonalNode) {
		try {
			seasonalUnis = JSON.parse(seasonalNode.textContent || '{}') || {};
		} catch (e) {
			seasonalUnis = {};
		}
	}

	function activateTab(target) {
		btns.forEach(function(b) {
			b.classList.remove('active');
			b.setAttribute('aria-selected', 'false');
		});
		panels.forEach(function(p) { p.classList.remove('active'); });
		var btn = document.querySelector('.reg-tab-btn[data-tab="' + target + '"]');
		var panel = document.getElementById(target);
		if (btn) {
			btn.classList.add('active');
			btn.setAttribute('aria-selected', 'true');
		}
		if (panel) {
			panel.classList.add('active');
		}
	}

	function isSeasonalUni(uniId) {
		return seasonalUnis[uniId] == 1 || seasonalUnis[String(uniId)] == 1;
	}

	function currentUniId() {
		var activePanel = document.querySelector('.reg-tab-panel.active');
		var sel = activePanel ? activePanel.querySelector('.changeAction') : document.querySelector('.changeAction');
		return sel ? sel.value : '';
	}

	function applySeasonalRegister(uniId) {
		var seasonal = isSeasonalUni(uniId);
		document.querySelectorAll('.reg-season-hive-notice').forEach(function(el) {
			if (seasonal) {
				el.removeAttribute('hidden');
			} else {
				el.setAttribute('hidden', 'hidden');
			}
		});
		var emailBtn = document.querySelector('.reg-tab-btn[data-tab="reg-email"]');
		var emailSubmit = document.querySelector('#registerForm .submitButton');
		if (emailBtn) {
			emailBtn.disabled = seasonal;
			emailBtn.classList.toggle('disabled', seasonal);
			emailBtn.setAttribute('aria-disabled', seasonal ? 'true' : 'false');
		}
		if (emailSubmit) {
			emailSubmit.disabled = seasonal;
		}
		if (seasonal) {
			activateTab('reg-hive');
		}
	}

	btns.forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (btn.disabled || (btn.getAttribute('data-tab') === 'reg-email' && isSeasonalUni(currentUniId()))) {
				return;
			}
			activateTab(btn.getAttribute('data-tab'));
		});
	});

	document.querySelectorAll('.changeAction').forEach(function(sel) {
		sel.addEventListener('change', function() {
			var val = this.value;
			document.querySelectorAll('.changeAction').forEach(function(s) { s.value = val; });
			applySeasonalRegister(val);
		});
	});

	applySeasonalRegister(currentUniId());

	// If Hive Keychain is available, select that tab by default
	// Extensions inject after DOMContentLoaded, so we wait briefly
	setTimeout(function() {
		if (isSeasonalUni(currentUniId())) {
			applySeasonalRegister(currentUniId());
			return;
		}
		if (typeof hive_keychain !== 'undefined') {
			activateTab('reg-hive');
		}
	}, 300);
});


const HiveKeychainRegister = async () => {
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install Hive Keychain extension first.');
		return;
	}

	var usernameInput = document.querySelector('#registerFormHive #reg-hive-username');
	if (!usernameInput || usernameInput.value.length === 0 || usernameInput.value.length > 16) {
		alert('You must enter a valid Hive account name first.');
		return;
	}

	const hiveaccount = usernameInput.value.toLowerCase().trim();

	try {
		await hive_keychain.requestSignBuffer(
			hiveaccount,
			`${hiveaccount} is my account.`,
			"Posting",
			(response) => {
				if (response.success) {
					document.querySelector('#registerFormHive #hiveAccount').value = hiveaccount;
					document.querySelector('#registerFormHive #password').value = response.result;
					document.querySelector('#registerFormHive #passwordReplay').value = response.result;
					document.querySelector('#registerFormHive #email').value = `${hiveaccount}@hive.blog`;
					document.querySelector('#registerFormHive #emailReplay').value = `${hiveaccount}@hive.blog`;
					document.getElementById('registerFormHive').submit();
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
