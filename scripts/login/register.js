$(function() {
	$('form').on('submit', function(e) {
		
	});
});


const HiveKeychainRegister = async () => {
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install Hive Keychain extension first.');
		return;
	}

	if (document.querySelector('#registerFormHive input#username').value.length == 0 || document.querySelector('#registerFormHive input#username').value.length > 16) {
		alert('You must enter a valid Hive account name first.');
		return;
	}

	const hiveaccount = document.querySelector('#registerFormHive input#username').value.toLowerCase().trim();;

	try
  	{
		await hive_keychain.requestSignBuffer(
			hiveaccount,
			`${hiveaccount} is my account.`,
			"Posting",
			(response) => {
				if (response.success) {
					document.querySelector('#registerFormHive > input#hiveAccount').value = hiveaccount;
					document.querySelector('#registerFormHive > input#password').value = response.result;
					document.querySelector('#registerFormHive > input#passwordReplay').value = response.result;
					document.querySelector('#registerFormHive > input#email').value = `${hiveaccount}@hive.blog`;
					document.querySelector('#registerFormHive > input#emailReplay').value = `${hiveaccount}@hive.blog`;
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
}