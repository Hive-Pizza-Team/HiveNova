{block name="title" prepend}{$LNG.siteTitleIndex}{/block}
{block name="content"}
<section class="hero-section">
	<h1>{$descHeader}</h1>
	<p class="hero-tagline">{$descText}</p>
	<ul id="desc_list">{foreach $gameInformations as $info}<li>{$info}</li>{/foreach}</ul>
</section>
<section>
	<div class="reg-tabs-wrapper">
		<div class="reg-tab-nav" role="tablist">
			<button class="reg-tab-btn active" data-tab="login-password" role="tab" aria-selected="true" aria-controls="login-password">
				&#128274;&nbsp; {$LNG.loginPassword}
			</button>
			<button class="reg-tab-btn" data-tab="login-hive" role="tab" aria-selected="false" aria-controls="login-hive">
				<img src="https://hive.io/favicon.ico" alt="" class="reg-tab-icon"> Hive Keychain
			</button>
		</div>

		<div class="reg-tab-panel active" id="login-password" role="tabpanel">
			<div class="contentbox">
				<h2>{$LNG.loginHeader} {$LNG.loginPassword}</h2>
				<form id="login" name="login" action="index.php?page=login" data-action="index.php?page=login" method="post">
					<div class="row">
					<select name="uni" id="universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$universeSelect|array_key_first}</select>
						<input name="username" id="username" type="text" placeholder="{$LNG.loginUsername}">
						<input name="password" id="password" type="password" placeholder="{$LNG.loginPassword}">
					{$verkeySafe = $verkey|default:[]}
					{if $verkeySafe.capaktiv == 1}
						<script src='https://www.google.com/recaptcha/api.js'></script>
						<script>function onSubmit() { document.getElementById("login").submit(); } </script>
						<input class="g-recaptcha" data-sitekey="{$verkeySafe.cappublic}" data-callback="onSubmit" type="submit" value="{$LNG.loginButton}">
						{else}
							<input type="submit" value="{$LNG.loginButton}">
						{/if}
					</div>
				</form>
				<br>
				<span class="small">{$loginInfo}</span>
				{if $facebookEnable|default:false}<a href="#" data-href="index.php?page=externalAuth&method=facebook" class="fb_login"><img src="styles/resource/images/facebook/fb-connect-large.png" alt="Log in with Facebook"></a>{/if}
			</div>
		</div>

		<div class="reg-tab-panel" id="login-hive" role="tabpanel">
			<div class="contentbox">
				<h2>{$LNG.loginHeader} {$LNG.loginHiveAccount}</h2>
				<form id="loginHive" action="index.php?page=login" data-action="index.php?page=login" method="post" onsubmit="return false;">
					<select name="uni" id="loginHive-universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$universeSelect|array_key_first}</select>
					<input name="username" id="loginHive-username" type="text" maxlength="16" placeholder="{$LNG.loginHiveAccount}">
					<input name="password" id="loginHive-password" type="hidden">
					<input name="hiveAccount" id="loginHive-hiveAccount" type="hidden">
					<button onclick="HiveKeychainLogin()" class="button_keychain" title="Log in with HiveKeychain"></button>
				</form>
				<br>
				<span class="small">{$loginInfo}</span>
			</div>
		</div>

		<div id="uni-stats" class="uni-stats">
			{foreach $universeStats as $uniId => $stats}
			<div class="uni-stats-row" data-uni="{$uniId}">
				<span class="uni-stat-item">&#9992;&nbsp; {$stats.fleets} fleets flying</span>
				<span class="uni-stat-item">&#128100;&nbsp; {$stats.players} players</span>
			</div>
			{/foreach}
		</div>

		<div class="contentbox">
			<h2>{$LNG.buttonRegister} First</h2>
			<a href="/index.php?page=register"><input value="{$LNG.buttonRegister}"></a>
		</div>
	</div>
</section>
<section>
<!-- 	<div class="button-box">
	<div class="button-box-inner">
		<div class="button-important">
			<a href="index.php?page=register">
				<span class="button-left"></span>
				<span class="button-center">{$LNG.buttonRegister}</span>
				<span class="button-right"></span>
			</a>
		</div>
	</div>
</div>
<div class="button-box">
	<div class="button-box-inner">
		{if $mailEnable}
		<div class="button multi">
			<a href="index.php?page=lostPassword">
				<span class="button-left"></span>
				<span class="button-center">{$LNG.buttonLostPassword}</span>
				<span class="button-right"></span>
			</a>
		</div>
		<div class="button multi">
		{else}
		<div class="button">
		{/if}
			<a href="index.php?page=screens">
				<span class="button-left"></span>
				<span class="button-center">{$LNG.buttonScreenshot}</span>
				<span class="button-right"></span>
			</a>
		</div>
	</div>
</div> -->
</section>
{/block}
{block name="script" append}
<script>{if $code}alert({$code|default:0|json});{/if}</script>
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/register.css?v={$REV}">
<style>
.uni-stats {
	width: 300px;
	margin: 0 auto 12px;
	text-align: center;
}
.uni-stats-row {
	display: none;
	gap: 20px;
	justify-content: center;
	flex-wrap: wrap;
	padding: 8px 12px;
	background: rgba(5, 20, 35, 0.7);
	border: 1px solid rgba(255,255,255,0.1);
	border-radius: 8px;
}
.uni-stats-row.active {
	display: flex;
}
.uni-stat-item {
	color: #aac4dd;
	font-size: 12px;
	letter-spacing: 0.3px;
}
</style>
<script>
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

	// Universe stats strip
	function showUniStats(uniId) {
		document.querySelectorAll('#uni-stats .uni-stats-row').forEach(function(row) {
			row.classList.toggle('active', row.getAttribute('data-uni') == uniId);
		});
	}

	// Sync both universe selects and stats strip
	function getActiveUniSelect() {
		var activePanel = document.querySelector('.reg-tab-panel.active');
		return activePanel ? activePanel.querySelector('.changeAction') : null;
	}

	document.querySelectorAll('.changeAction').forEach(function(sel) {
		sel.addEventListener('change', function() {
			// Mirror value to the other select
			var val = this.value;
			document.querySelectorAll('.changeAction').forEach(function(s) { s.value = val; });
			showUniStats(val);
		});
	});

	// Init stats for current universe
	var initSel = getActiveUniSelect();
	if (initSel) showUniStats(initSel.value);

	// If Hive Keychain is available, select that tab by default
	// Extensions inject after DOMContentLoaded, so we wait briefly
	setTimeout(function() {
		if (typeof hive_keychain !== 'undefined') {
			var keychainBtn = document.querySelector('.reg-tab-btn[data-tab="login-hive"]');
			if (keychainBtn) keychainBtn.click();
		}
		// Re-sync stats after possible tab switch
		var sel = getActiveUniSelect();
		if (sel) showUniStats(sel.value);
	}, 300);
});
</script>
{/block}
