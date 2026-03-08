
{block name="title" prepend}{$LNG.siteTitleRegister}{/block}
{block name="content"}
<div class="reg-tabs-wrapper">
	<div class="reg-tab-nav" role="tablist">
		<button class="reg-tab-btn active" data-tab="reg-email" role="tab" aria-selected="true" aria-controls="reg-email">
			&#9993;&nbsp; {$LNG.registerTabEmail}
		</button>
		<button class="reg-tab-btn" data-tab="reg-hive" role="tab" aria-selected="false" aria-controls="reg-hive">
			<img src="https://hive.io/favicon.ico" alt="" class="reg-tab-icon"> {$LNG.registerTabHive}
		</button>
	</div>

	<div class="reg-tab-panel active" id="reg-email" role="tabpanel">
		<form id="registerForm" method="post" action="index.php?page=register" data-action="index.php?page=register">
		<input type="hidden" value="send" name="mode">
		<input type="hidden" value="{$externalAuth.account}" name="externalAuth[account]">
		<input type="hidden" value="{$externalAuth.method}" name="externalAuth[method]">
		<input type="hidden" value="{$referralData.id}" name="referralID">
			<div class="rowForm">
				<label for="universe">{$LNG.universe}</label>
				<select name="uni" id="universe" class="changeAction">{html_options options=$universeSelect selected=$universeSelect|array_key_first}</select>
				{if !empty($error.uni)}<span class="error errorUni"></span>{/if}
			</div>
			{if !empty($externalAuth.account)}
			{if $facebookEnable}
			<div class="rowForm">
				<label>{$LNG.registerFacebookAccount}</label>
				<span class="text fbname">{$accountName}</span>
			</div>
			{/if}
			{elseif empty($referralData.id)}
			{if $facebookEnable}
			<div class="rowForm">
				<label>{$LNG.registerFacebookAccount}</label>
				<a href="#" data-href="index.php?page=externalAuth&method=facebook" class="fb_login"><img src="styles/resource/images/facebook/fb-connect-large.png" alt=""></a>
			</div>
			{/if}
			{/if}
			<div class="rowForm">
				<label for="reg-email-username">{$LNG.registerUsername}</label>
				<input type="text" class="input" name="username" id="reg-email-username" maxlength="32">
				{if !empty($error.username)}<span class="error errorUsername"></span>{/if}
				<span class="inputDesc">{$LNG.registerUsernameDesc}</span>
			</div>
			<div class="rowForm">
				<label for="reg-email-password">{$LNG.registerPassword}</label>
				<input type="password" class="input" name="password" id="reg-email-password">
				{if !empty($error.password)}<span class="error errorPassword"></span>{/if}
				<span class="inputDesc">{$registerPasswordDesc}</span>
			</div>
			<div class="rowForm">
				<label for="reg-email-passwordReplay">{$LNG.registerPasswordReplay}</label>
				<input type="password" class="input" name="passwordReplay" id="reg-email-passwordReplay">
				{if !empty($error.passwordReplay)}<span class="error errorPasswordReplay"></span>{/if}
				<span class="inputDesc">{$LNG.registerPasswordReplayDesc}</span>
			</div>
			<div class="rowForm">
				<label for="reg-email-email">{$LNG.registerEmail}</label>
				<input type="email" class="input" name="email" id="reg-email-email">
				{if !empty($error.email)}<span class="error errorEmail"></span>{/if}
				<span class="inputDesc">{$LNG.registerEmailDesc}</span>
			</div>
			<div class="rowForm">
				<label for="reg-email-emailReplay">{$LNG.registerEmailReplay}</label>
				<input type="email" class="input" name="emailReplay" id="reg-email-emailReplay">
				{if !empty($error.emailReplay)}<span class="error errorEmailReplay"></span>{/if}
				<span class="inputDesc">{$LNG.registerEmailReplayDesc}</span>
			</div>
			{if $languages|count > 1}
			<div class="rowForm">
				<label for="reg-email-language">{$LNG.registerLanguage}</label>
				<select name="lang" id="reg-email-language">{html_options options=$languages selected=$lang}</select>
				{if !empty($error.language)}<span class="error errorLanguage"></span>{/if}
				<div class="clear"></div>
			</div>
			{/if}
			{if !empty($referralData.name)}
			<div class="rowForm">
				<label>{$LNG.registerReferral}</label>
				<span class="text">{$referralData.name}</span>
				<div class="clear"></div>
			</div>
			{/if}
			{if $recaptchaEnable}
			<div class="rowForm" id="captchaRow">
				<div>
					<label>{$LNG.registerCaptcha}</label>
					<div class="g-recaptcha" data-sitekey="{$recaptchaPublicKey}"></div>
				</div>
				<div class="clear"></div>
			</div>
			{/if}
			<div class="rowForm">
				<label for="reg-email-rules">{$LNG.registerRules}</label>
				<input type="checkbox" name="rules" id="reg-email-rules" value="1">
				{if !empty($error.rules)}<span class="error errorRules"></span>{/if}
				<span class="inputDesc">{$registerRulesDesc}</span>
			</div>
			<div class="rowForm">
				<input type="submit" class="submitButton" value="{$LNG.buttonRegister}">
			</div>
		</form>
	</div>

	<div class="reg-tab-panel" id="reg-hive" role="tabpanel">
		<form id="registerFormHive" method="post" action="index.php?page=register" data-action="index.php?page=register" onsubmit="return false;">
		<input type="hidden" value="send" name="mode">
		<input type="hidden" value="{$externalAuth.account}" name="externalAuth[account]">
		<input type="hidden" value="{$externalAuth.method}" name="externalAuth[method]">
		<input type="hidden" value="{$referralData.id}" name="referralID">
			<div class="rowForm reg-hive-info">
				<p>{$LNG.registerHiveKeychainInfo}</p>
			</div>
			<div class="rowForm">
				<label for="reg-hive-universe">{$LNG.universe}</label>
				<select name="uni" id="reg-hive-universe" class="changeAction">{html_options options=$universeSelect selected=$universeSelect|array_key_first}</select>
				{if !empty($error.uni)}<span class="error errorUni"></span>{/if}
			</div>
			<div class="rowForm">
				<label for="reg-hive-username">{$LNG.hiveAccount}</label>
				<input type="text" id="reg-hive-username" name="username" maxlength="16">
			</div>
			<input type="hidden" name="password" id="password">
			<input type="hidden" name="passwordReplay" id="passwordReplay">
			<input id="hiveAccount" name="hiveAccount" type="hidden">
			<input type="hidden" name="email" id="email">
			<input type="hidden" name="emailReplay" id="emailReplay">
			{if $languages|count > 1}
			<div class="rowForm">
				<label for="reg-hive-language">{$LNG.registerLanguage}</label>
				<select name="lang" id="reg-hive-language">{html_options options=$languages selected=$lang}</select>
				{if !empty($error.language)}<span class="error errorLanguage"></span>{/if}
				<div class="clear"></div>
			</div>
			{/if}
			<div class="rowForm">
				<label for="reg-hive-rules">{$LNG.registerRules}</label>
				<input type="checkbox" name="rules" id="reg-hive-rules" value="1">
				{if !empty($error.rules)}<span class="error errorRules"></span>{/if}
				<span class="inputDesc">{$registerRulesDesc}</span>
			</div>
			<div class="rowForm">
				<input type="submit" class="submitButton reg-hive-submit" value="{$LNG.buttonRegisterHive}" onclick="HiveKeychainRegister()">
			</div>
		</form>
	</div>
</div>
{/block}
{block name="script" append}
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/register.css?v={$REV}">
{if $recaptchaEnable}
<script type="text/javascript" src="https://www.google.com/recaptcha/api.js?hl={$lang}"></script>
{/if}
<script type="text/javascript" src="scripts/login/register.js"></script>
{/block}
