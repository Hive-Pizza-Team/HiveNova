// Tab switching
document.addEventListener('DOMContentLoaded', function() {
	var btns = document.querySelectorAll('.reg-tab-btn');
	var panels = document.querySelectorAll('.reg-tab-panel');

	btns.forEach(function(btn) {
		btn.addEventListener('click', function() {
			var target = btn.getAttribute('data-tab');

			btns.forEach(function(b) {
				b.classList.remove('active');
				b.setAttribute('aria-selected', 'false');
			});
			panels.forEach(function(p) { p.classList.remove('active'); });

			btn.classList.add('active');
			btn.setAttribute('aria-selected', 'true');
			document.getElementById(target).classList.add('active');
		});
	});

	// If Hive Keychain is available, select that tab by default
	// Extensions inject after DOMContentLoaded, so we wait briefly
	setTimeout(function() {
		if (typeof hive_keychain !== 'undefined') {
			var keychainBtn = document.querySelector('.reg-tab-btn[data-tab="reg-hive"]');
			if (keychainBtn) keychainBtn.click();
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
