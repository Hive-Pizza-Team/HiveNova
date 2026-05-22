{include file="main.header.tpl" bodyclass="full"}

<div class="wrapper">

	<top>
		<div class="fixed">
		</div>
	</top>

	<logo>
		<div class="fixed">
			<a href="game.php?page=overview"><img src="styles/resource/images/HiveNova.png" /></a>
		</div>
	</logo>
	
	<header>
		<div class="fixed">
			{include file="main.topnav.tpl"}
		</div>
	</header>

	<input style="display:none;" type="checkbox" id="toggle-menu" role="button">
	<menu>
		<div class="fixed">
			{include file="main.navigation.tpl"}
		</div>
	</menu>
	
	<content>
		{if $hasAdminAccess}
		<div class="globalWarning">
		{$LNG.admin_access_1} <a id="drop-admin">{$LNG.admin_access_link}</a>{$LNG.admin_access_2}
		</div>
		{/if}
		{if $closed}
		<div class="infobox">{$LNG.ov_closed}</div>
		{elseif $delete}
		<div class="infobox">{$delete}</div>
		{elseif $vacation}
		<div class="infobox">{$LNG.tn_vacation_mode} {$vacation}</div>
		{/if}
		
		{block name="content"}{/block}
		<table class="hack"></table>
	</content>

	<footer>
		{foreach $cronjobs as $cronjob}<img src="cronjob.php?cronjobID={$cronjob}" alt="">{/foreach}
		
		{include file="main.footer.tpl" nocache}
	</footer>

</div>

<div id="pwa-install-banner" class="pwa-install-banner" hidden
	data-hint-ios="{$LNG.pwa_install_ios|sprintf:$game_name|escape:'html'}"
	data-hint-android-chrome-prompt="{$LNG.pwa_install_android_chrome_prompt|sprintf:$game_name|escape:'html'}"
	data-hint-android-chrome-manual="{$LNG.pwa_install_android_chrome_manual|sprintf:$game_name|escape:'html'}"
	data-hint-android-firefox="{$LNG.pwa_install_android_firefox|sprintf:$game_name|escape:'html'}"
	data-hint-android-other="{$LNG.pwa_install_android_other|sprintf:$game_name|escape:'html'}"
	data-hint-desktop-chrome-prompt="{$LNG.pwa_install_desktop_chrome_prompt|sprintf:$game_name|escape:'html'}"
	data-hint-desktop-chrome="{$LNG.pwa_install_desktop_chrome|sprintf:$game_name|escape:'html'}"
	data-hint-desktop-edge="{$LNG.pwa_install_desktop_edge|sprintf:$game_name|escape:'html'}"
	data-hint-desktop-safari="{$LNG.pwa_install_desktop_safari|sprintf:$game_name|escape:'html'}"
	data-hint-desktop-firefox="{$LNG.pwa_install_desktop_firefox|sprintf:$game_name|escape:'html'}"
	data-hint-fallback="{$LNG.pwa_install_fallback|sprintf:$game_name|escape:'html'}">
	<p class="pwa-install-banner__title">{$LNG.pwa_install_banner_title|sprintf:$game_name|escape:'html'}</p>
	<p class="pwa-install-banner__text" data-pwa-instructions></p>
	<div class="pwa-install-banner__actions">
		<button type="button" class="button" data-pwa-install hidden>{$LNG.pwa_install_button}</button>
		<button type="button" class="button" data-pwa-dismiss>{$LNG.pwa_install_dismiss}</button>
	</div>
</div>

{include file="main.bottomnav.tpl"}

</body>
</html>
