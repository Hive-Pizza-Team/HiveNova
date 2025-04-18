$(function() {
	$('form').on('submit', function(e) {
		
	});
});


const HiveKeychainRegister = async () => {
	if (typeof(hive_keychain) == "undefined") {
		alert('You must install Hive Keychain extension first.');
		return;
	}

	if (document.querySelector('#loginHive > input#username').value.length == 0 || document.querySelector('#loginHive > input#username').value.length > 16) {
		alert('You must enter a valid Hive account name first.');
		return;
	}

	const hiveaccount = document.querySelector('#loginHive > input#username').value;

	try
  	{
		await hive_keychain.requestSignBuffer(
			hiveaccount,
			`${hiveaccount} is my account.`,
			"Posting",
			(response) => {
				if (response.success) {
					document.querySelector('#loginHive > input#hiveAccount').value = hiveaccount;
					document.querySelector('#loginHive > input#hivesign').value = response.result;
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
}