<!DOCTYPE html>
<!--[if lt IE 7 ]> <html lang="{$lang}" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="{$lang}" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="{$lang}" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="{$lang}" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!-->
<html lang="{$lang}" class="no-js"> <!--<![endif]-->
<head>
	<title>{block name="title"} - {$uni_name} - {$game_name}{/block}</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
	<link rel="manifest" href="manifest.php?uni={$USER.universe}">
	<meta name="theme-color" content="#1a1a2e">
	<meta name="robots" content="noindex, nofollow">
	{if !empty($goto)}
	<meta http-equiv="refresh" content="{$gotoinsec};URL={$goto}">
	{/if}
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	{if $bodyclass == 'popup'}
	<style>{literal}html,body{background:var(--color-bg-page,#0d0d0d);color-scheme:dark}{/literal}</style>
	{/if}
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/base/boilerplate.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/ingame/main.css?v={$REV}">
	{if $loadAchievementsCss|default:false}
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/ingame/achievements.css?v={$REV}">
	{/if}
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/tokens.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="{$dpath}formate.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/ingame/buttons.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="./styles/resource/css/fontawesome/css/ingame-icons.css?v={$REV}">
	<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
	{assign var="ingamePage" value=$smarty.get.page|default:'overview'}
	<script type="text/javascript">
	var ServerTimezoneOffset = {$Offset};
	var serverTime 	= new Date({$date.0}, {$date.1 - 1}, {$date.2}, {$date.3}, {$date.4}, {$date.5});
	var startTime	= serverTime.getTime();
	var localTime 	= serverTime;
	var localTS 	= startTime;
	var Gamename	= document.title;
	var Ready		= "{$LNG.ready}";
	var Skin		= "{$dpath}";
	var Lang		= "{$lang}";
	var head_info	= "{$LNG.fcm_info}";
	var auth		= {$authlevel|default:'0'};
	var days 		= {$LNG.week_day|json|default:'[]'} 
	var months 		= {$LNG.months|json|default:'[]'} ;
	var tdformat	= "{$LNG.js_tdformat}";
	var queryString	= "{$queryString|escape:'javascript'}";
	var isPlayerCardActive	= "{$isPlayerCardActive|json}";
	var numberFormat	= "{$USER.number_format|default:'auto'}";
	var needHiveForDeposit = "{$LNG.js_need_hive_for_deposit|escape:'javascript'}";
	var relativeTime = Math.floor(Date.now() / 1000);

	setInterval(function() {
		if(relativeTime < Math.floor(Date.now() / 1000)) {
		serverTime.setSeconds(serverTime.getSeconds()+1);
		relativeTime++;
		}
	}, 1000);
	</script>
	<script type="text/javascript" src="./scripts/base/jquery.js?v={$REV}"></script>
	<script type="text/javascript" src="./scripts/base/jquery.cookie.js?v={$REV}"></script>
	<script type="text/javascript" src="./scripts/base/tooltip.js?v={$REV}"></script>
	<script type="text/javascript" src="./scripts/game/base.js?v={$REV}"></script>
	<script type="text/javascript" src="./scripts/game/pwa-install.js?v={$REV}" defer></script>
	{foreach item=scriptname from=$scripts}
	<script type="text/javascript" src="./scripts/game/{$scriptname}.js?v={$REV}"></script>
	{/foreach}
	{block name="script"}{/block}
	<script type="text/javascript">
	$(window).scroll(function(){
		// affix
		windowHeight = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;
		lastScroll = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;
		
		// menu
		elementHeight = document.getElementsByTagName("menu")[0].getElementsByClassName("fixed")[0].clientHeight;
		element = document.getElementsByTagName("menu")[0].getElementsByClassName("fixed")[0];
		if (elementHeight > windowHeight - 100){
			a = 100 - lastScroll;
			b = windowHeight - elementHeight;
			scrollTo = Math.max(a, b);
			element.style.top = scrollTo + 'px';
		}
	});
	$(function() {
		{$execscript}
	});
	</script>
</head>
<body id="{$smarty.get.page|htmlspecialchars|default:'overview'}" class="{$bodyclass}">
	<div id="tooltip" class="tip"></div>