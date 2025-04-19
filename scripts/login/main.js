$(function() {
	$('.flags').on('click', function(e) {
		e.preventDefault();
		var langKey = $(this).attr('class').replace(/flags(.*)/, "$1").trim();
		Login.setLanguage(langKey);
		return false;
	});
	
	$('.fancybox').fancybox({
		'type' : 'iframe',
		'padding' : 1,
	});
	
	if(LoginConfig.isMultiUniverse)
	{
		$('.changeAction')
		.each(function() {
			updateUrls($(this));
		})
		.on('change', function() {
			updateUrls($(this));
		});
		
		// $('.changeUni').on('change', function() {
		// 	document.location.href = LoginConfig.basePath+'uni'+$(this).val()+'/index.php'+document.location.search;
		// });
	}
	else
	{
		$('.fb_login').attr('href', function(i, old) {
			return LoginConfig.basePath+$(this).data('href');
		});
	}
});

var updateUrls = function(that, universe) {
	var universe = that.val();
	if (LoginConfig.unisWildcast) {
		var basePathWithSubdomain = LoginConfig.basePath.replace('://', '://uni' + universe + '.');
		that.parents('form').attr('action', function(i, old) {
			return basePathWithSubdomain+$(this).data('action');
		});
		$('.fb_login').attr('href', function(i, old) {
			return basePathWithSubdomain+$(this).data('href');
		});
	} else {
		that.parents('form').attr('action', function(i, old) {
			return LoginConfig.basePath+'uni'+universe+'/'+$(this).data('action');
		});
		$('.fb_login').attr('href', function(i, old) {
			return LoginConfig.basePath+'uni'+universe+'/'+$(this).data('href');
		});
	}
}

var Login = {
	setLanguage : function (LNG, Query) {
		$.cookie('lang', LNG);
		if(typeof Query === "undefined")
			document.location.href = document.location.href
		else
			document.location.href = document.location.href+Query;
	}
};

const HiveKeychainLogin = async () => {
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
					document.querySelector('#loginHive > input#password').value = response.result;
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