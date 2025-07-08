<!DOCTYPE html>
<!--[if lt IE 7 ]> <html lang="{$lang}" class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <html lang="{$lang}" class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <html lang="{$lang}" class="no-js ie8"> <![endif]-->
<!--[if IE 9 ]>    <html lang="{$lang}" class="no-js ie9"> <![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--> <html lang="{$lang}" class="no-js"> <!--<![endif]-->
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="styles/theme/{$dpath|default:'nova'}/formate.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/main.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/base/jquery.fancybox.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/icon-font/style.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/hivekeychain_button.css?v={$REV}">
	<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
	<title>{block name="title"} - {$gameName}{/block}</title>
	<meta name="keywords" content="{$gameName}, Hive, Browsergame, MMOSG, MMOG, Strategy, SteemNova, XNova, 2Moons, Space">
	<meta name="description" content="Multiplayer Orbiting Optimization Network (MOON) game for Hivers. Space themed empire building game in the browser. Free-to-play. Come get mooned!">
	<!-- open graph protocol -->
	<meta property="og:title" content="{$gameName}">
	<meta property="og:type" content="website">
	<meta property="og:description" content="Multiplayer Orbiting Optimization Network (MOON) game for Hivers. Space themed empire building game in the browser. Free-to-play. Come get mooned!">
	<meta property="og:image" content="https://moon.hive.pizza/styles/resource/images/login/HiveNova.png">
	<!--[if lt IE 9]>
	<script src="scripts/base/html5.js"></script>
	<![endif]-->
	<script src="scripts/base/jquery.js?v={$REV}"></script>
	<script src="scripts/base/jquery.cookie.js?v={$REV}"></script>
	<script src="scripts/base/jquery.fancybox.js?v={$REV}"></script>
	<script src="scripts/login/main.js"></script>
	<script>{if isset($code)}var loginError = {$code|json};{/if}</script>
	{block name="script"}{/block}	
</head>
<body id="{$smarty.get.page|htmlspecialchars|default:'overview'}" class="{$bodyclass}">
	<div id="page">
