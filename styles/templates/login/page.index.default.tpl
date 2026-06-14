{block name="title"}{$gameName}{/block}
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
				<img src="styles/resource/images/login/keychain-round-logo.svg" alt="" class="reg-tab-icon reg-tab-icon--keychain"> Hive Keychain
			</button>
		</div>

		<div class="reg-tab-panel active" id="login-password" role="tabpanel">
			<div class="contentbox">
				<h2>{$LNG.loginPassword} {$LNG.loginHeader}</h2>
				<form id="login" name="login" action="index.php?page=login" data-action="index.php?page=login" method="post">
					<div class="login-form-fields">
					<select name="uni" id="universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$defaultUniverse}</select>
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
				<h2>{$LNG.loginHiveAccount} {$LNG.loginHeader}</h2>
				<form id="loginHive" action="index.php?page=login" data-action="index.php?page=login" method="post" onsubmit="return false;">
					<div class="login-form-fields">
						<select name="uni" id="loginHive-universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$defaultUniverse}</select>
						<input name="username" id="loginHive-username" type="text" maxlength="16" placeholder="{$LNG.loginHiveAccount}">
						<input name="password" id="loginHive-password" type="hidden">
						<input name="hiveAccount" id="loginHive-hiveAccount" type="hidden">
						<button type="button" onclick="HiveKeychainLogin()" class="button_keychain" title="{$LNG.loginKeychainButton}">
							<img src="styles/resource/images/login/keychain-round-logo.svg" alt="" class="button_keychain-icon" aria-hidden="true">
							<span class="button_keychain-label">{$LNG.loginKeychainButton}</span>
						</button>
					</div>
				</form>
				<br>
				<span class="small">{$loginInfo}</span>
			</div>
		</div>

		<div id="uni-stats" class="uni-stats">
			{foreach $universeStats as $uniId => $stats}
			<div class="contentbox uni-stats-row{if $uniId == $defaultUniverse} active{/if}" data-uni="{$uniId}">
				<h2>{$stats.name|escape}</h2>
				{if !$stats.open || !$stats.reg_open}
				<ul class="uni-stats-badges">
					{if !$stats.open}
					<li class="uni-badge uni-badge--warn">{$LNG.uni_info_status_closed}</li>
					{elseif !$stats.reg_open}
					<li class="uni-badge uni-badge--warn">{$LNG.uni_info_reg_closed}</li>
					{/if}
				</ul>
				{/if}
				<div class="uni-stats-field">
					<div class="uni-stats-config">
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_game_speed}</div>
							<div class="uni-stats-config-desc">{$stats.game_speed|number_format:1}</div>
						</div>
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_fleet_speed}</div>
							<div class="uni-stats-config-desc">{$stats.fleet_speed|number_format:1}</div>
						</div>
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_resources}</div>
							<div class="uni-stats-config-desc">{$stats.resource_multiplier|number_format}×</div>
						</div>
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_galaxy}</div>
							<div class="uni-stats-config-desc">{$stats.galaxy_size|escape}</div>
						</div>
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_debris}</div>
							<div class="uni-stats-config-desc">{$stats.debris_percent}%</div>
						</div>
						<div class="uni-stats-config-item">
							<div class="uni-stats-config-term">{$LNG.uni_info_moon_chance}</div>
							<div class="uni-stats-config-desc">{$stats.moon_chance}%</div>
						</div>
						<div class="uni-stats-config-item uni-stats-config-item--paired">
							<div class="uni-stats-config-term">{$LNG.uni_info_age}</div>
							<div class="uni-stats-config-desc">{$stats.age|escape}</div>
						</div>
						<div class="uni-stats-config-item uni-stats-config-item--paired">
							<div class="uni-stats-config-term">{$LNG.uni_info_fullness}</div>
							<div class="uni-stats-config-desc uni-stats-vacancy">
								<div class="uni-stats-capacity-row">
									<div class="uni-stats-capacity" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{$stats.vacancy_pct}" aria-label="{$stats.vacancy_label|escape}">
										<div class="uni-stats-capacity-bar uni-stats-capacity-bar--{$stats.vacancy_level|escape}" style="--fill: {$stats.vacancy_pct}%;"></div>
									</div>
									<span class="uni-stats-capacity-pct">{$stats.vacancy_pct}%</span>
								</div>
								<span class="uni-stats-capacity-label">{$stats.vacancy_label|escape}</span>
							</div>
						</div>
					</div>
				</div>
				<div class="uni-stats-field uni-stats-live">
					{$stats.players|number_format} {$LNG.uni_info_players} · {$stats.fleets|number_format} {$LNG.uni_info_fleets}
				</div>
			</div>
			{/foreach}
		</div>

		<div class="contentbox">
			<h2>{$LNG.buttonRegister}</h2>
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
