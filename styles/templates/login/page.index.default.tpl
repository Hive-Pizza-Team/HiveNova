{block name="title"}{$documentTitle}{/block}
{block name="content"}
<div class="lobby">
	<section class="lobby-hero" aria-label="{$gameName|escape}">
		<div class="lobby-hero-brand">
			<p class="lobby-kicker">{$LNG.lobby_kicker}</p>
			<h1 class="lobby-title">{$gameName|escape}</h1>
			<p class="lobby-hero-hook">{$lobbyHook} <span class="lobby-hero-hook-em">{$lobbyHookEm}</span></p>
			<p class="lobby-tagline">{$descText}</p>
			<div class="lobby-cta-row">
				<a class="lobby-cta lobby-cta--primary" href="index.php?page=register">{$LNG.buttonRegister}</a>
				<a class="lobby-cta lobby-cta--ghost" href="#lobby-login">{$LNG.loginHeader}</a>
			</div>
			<ul class="lobby-bullets" id="desc_list">{foreach $gameInformations as $info}<li>{$info}</li>{/foreach}</ul>
		</div>
		<figure class="lobby-hero-visual" aria-label="{$LNG.lobby_viz_label|escape}">
			<div class="lobby-viz-stage">
				<div id="lobby-viz" class="lobby-viz" role="img" aria-hidden="true"></div>
				<script type="application/json" id="lobby-viz-config">{$lobbyVizConfigJson nofilter}</script>
			</div>
			<figcaption class="lobby-viz-caption">
				<p class="lobby-viz-caption-title">{$lobbyVizCaptionTitle|escape}</p>
				<ul class="lobby-viz-legend" aria-hidden="true">
					<li class="lobby-viz-legend-item lobby-viz-legend-item--galaxy">{$LNG.lobby_viz_legend_galaxy}</li>
					<li class="lobby-viz-legend-item lobby-viz-legend-item--fleet">{$LNG.lobby_viz_legend_fleet}</li>
					<li class="lobby-viz-legend-item lobby-viz-legend-item--attack">{$LNG.lobby_viz_legend_attack}</li>
				</ul>
			</figcaption>
		</figure>
	</section>

	<section class="lobby-alive" aria-labelledby="lobby-feed-heading">
		<div class="lobby-alive-head">
			<h2 id="lobby-feed-heading">{$lobbyFeedTitle}</h2>
			<p class="lobby-alive-sub">{$LNG.lobby_feed_sub}</p>
			<span class="lobby-pulse" aria-hidden="true"><span class="lobby-pulse-dot"></span> {$LNG.lobby_feed_live}</span>
		</div>
		<ol class="lobby-feed" id="lobby-feed" data-poll-url="{$activityPollUrl|escape}" data-empty="{$LNG.lobby_feed_empty|escape}">
			{foreach $activityEvents as $event}
			<li class="lobby-feed-item lobby-feed-item--{$event.eventType|lower|escape}" data-event-id="{$event.id}" data-ts="{$event.ts}">
				<span class="lobby-feed-uni">{$event.universe|escape}</span>
				{if $event.headline}<span class="lobby-feed-headline">{$event.headline|escape}</span>{/if}
				<span class="lobby-feed-size">{$event.size|escape}</span>
				<span class="lobby-feed-type">{$event.eventType|escape}</span>
				<span class="lobby-feed-outcome">{$event.outcome|escape}</span>
				<time class="lobby-feed-time" datetime="{$event.ts}">{$event.time|escape}</time>
			</li>
			{foreachelse}
			<li class="lobby-feed-empty">{$LNG.lobby_feed_empty}</li>
			{/foreach}
		</ol>
	</section>

	<section class="lobby-gate" id="lobby-login">
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
						<select name="uni" id="universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$defaultEmailUniverse}</select>
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
							<select name="uni" id="loginHive-universe" class="changeAction">{html_options options=$universeSelect|default:[] selected=$defaultHiveUniverse}</select>
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
				<div class="contentbox uni-stats-row{if $uniId == $defaultEmailUniverse} active{/if}{if $stats.seasonal} uni-stats-row--season{/if}" data-uni="{$uniId}">
					<h2>{$stats.name|escape}</h2>
					{if $stats.seasonal || !$stats.open || !$stats.reg_open}
					<ul class="uni-stats-badges">
						{if $stats.seasonal}
						<li class="uni-badge uni-badge--season">{$LNG.uni_info_season_badge}</li>
						{if $stats.season_number != ''}
						<li class="uni-badge">{$stats.season_number|escape}</li>
						{/if}
						{if !$stats.season_can_enter}
						<li class="uni-badge uni-badge--warn">{$LNG.uni_info_season_entries_closed}</li>
						{/if}
						{/if}
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
							{if $stats.seasonal}
							<div class="uni-stats-config-item uni-stats-config-item--paired">
								<div class="uni-stats-config-term">{$LNG.uni_info_wipe}</div>
								<div class="uni-stats-config-desc uni-stats-wipe{if $stats.wipe_live} uni-stats-wipe--live{/if}{if $stats.wipe_urgent} uni-stats-wipe--urgent{/if}"{if $stats.wipe_live} data-closes-at="{$stats.closes_at}" data-closed-label="{$LNG.uni_info_wipe_now|escape}"{/if}>{$stats.wipe_label|escape}</div>
							</div>
							<div class="uni-stats-config-item uni-stats-config-item--paired">
								<div class="uni-stats-config-term">{$LNG.uni_info_entry}</div>
								<div class="uni-stats-config-desc">{$stats.entry_label|escape}</div>
							</div>
							<div class="uni-stats-config-item uni-stats-config-item--wide uni-stats-config-item--paired">
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
							{else}
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
							{/if}
						</div>
					</div>
					<div class="uni-stats-field uni-stats-live">
						{$stats.players|number_format} {$LNG.uni_info_players} · {$stats.fleets|number_format} {$LNG.uni_info_fleets}
					</div>
				</div>
				{/foreach}
			</div>

			<div class="contentbox lobby-register-box">
				<h2>{$LNG.buttonRegister}</h2>
				<a href="index.php?page=register"><input value="{$LNG.buttonRegister}"></a>
			</div>
		</div>
	</section>
</div>
{/block}
{block name="script" append}
<script>{if $code}alert({$code|default:0|json});{/if}</script>
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/register.css?v={$REV}">
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/lobby.css?v={$REV}.{$lobbyCssMtime}">
<script type="application/json" id="prefetch-assets-data">{$prefetchUrls|default:[]|json}</script>
<script src="scripts/login/prefetch-assets.js?v={$REV}" defer></script>
<script src="scripts/login/lobby-feed.js?v={$REV}" defer></script>
<script src="scripts/login/lobby-viz.js?v={$REV}.{$lobbyVizMtime}" defer></script>
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
			var sel = document.querySelector('#' + target + ' .changeAction');
			if (sel) showUniStats(sel.value);
		});
	});

	function showUniStats(uniId) {
		document.querySelectorAll('#uni-stats .uni-stats-row').forEach(function(row) {
			row.classList.toggle('active', row.getAttribute('data-uni') == uniId);
		});
	}

	function getActiveUniSelect() {
		var activePanel = document.querySelector('.reg-tab-panel.active');
		return activePanel ? activePanel.querySelector('.changeAction') : null;
	}

	document.querySelectorAll('.changeAction').forEach(function(sel) {
		sel.addEventListener('change', function() {
			var val = this.value;
			showUniStats(val);
		});
	});

	function padWipe(n) {
		return (n < 10 ? '0' : '') + n;
	}
	function formatWipeCountdown(seconds) {
		seconds = Math.max(0, Math.floor(seconds));
		var d = Math.floor(seconds / 86400);
		var h = Math.floor(seconds / 3600) % 24;
		var m = Math.floor(seconds / 60) % 60;
		var s = seconds % 60;
		var time = padWipe(h) + {$LNG.short_hour|json} + ' ' + padWipe(m) + {$LNG.short_minute|json} + ' ' + padWipe(s) + {$LNG.short_second|json};
		return d > 0 ? d + {$LNG.short_day|json} + ' ' + time : time;
	}
	function tickSeasonWipes() {
		var now = Math.floor(Date.now() / 1000);
		document.querySelectorAll('.uni-stats-wipe--live').forEach(function(el) {
			var closes = parseInt(el.getAttribute('data-closes-at'), 10);
			if (!closes) {
				return;
			}
			var left = closes - now;
			if (left <= 0) {
				el.textContent = el.getAttribute('data-closed-label') || '';
				el.classList.remove('uni-stats-wipe--live');
				return;
			}
			el.textContent = formatWipeCountdown(left);
		});
	}
	tickSeasonWipes();
	setInterval(tickSeasonWipes, 1000);

	var initSel = getActiveUniSelect();
	if (initSel) showUniStats(initSel.value);

	setTimeout(function() {
		if (typeof hive_keychain !== 'undefined') {
			var keychainBtn = document.querySelector('.reg-tab-btn[data-tab="login-hive"]');
			if (keychainBtn) keychainBtn.click();
		}
		var sel = getActiveUniSelect();
		if (sel) showUniStats(sel.value);
	}, 300);
});
</script>
{/block}
