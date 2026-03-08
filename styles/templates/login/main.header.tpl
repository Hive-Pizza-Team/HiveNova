<!DOCTYPE html>
<html lang="{$lang}" class="no-js">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="index, follow">
	<link rel="stylesheet" type="text/css" href="styles/theme/{$dpath|default:'nova'}/formate.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/main.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/base/jquery.fancybox.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/icon-font/style.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/hivekeychain_button.css?v={$REV}">
	<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
	<title>{block name="title"} - {$gameName}{/block}</title>
	<meta name="keywords" content="{$gameName}, Hive, Browsergame, MMOSG, MMOG, Strategy, XNova, 2Moons, Space">
	<meta name="description" content="{block name='description'}Multiplayer Orbiting Optimization Network (MOON) game. Space themed empire building game in the browser. Free-to-play. Come get mooned!{/block}">
	{block name="canonical"}<link rel="canonical" href="{$basepath}">{/block}
	<!-- open graph -->
	<meta property="og:title" content="{$gameName}">
	<meta property="og:type" content="website">
	<meta property="og:url" content="{$basepath}">
	<meta property="og:site_name" content="{$gameName}">
	<meta property="og:description" content="Multiplayer Orbiting Optimization Network (MOON) game. Space themed empire building game in the browser. Free-to-play. Come get mooned!">
	<meta property="og:image" content="{$basepath}styles/resource/images/login/HiveNova.png">
	<!-- Twitter card -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="{$gameName}">
	<meta name="twitter:description" content="Multiplayer Orbiting Optimization Network (MOON) game. Space themed empire building game in the browser. Free-to-play. Come get mooned!">
	<meta name="twitter:image" content="{$basepath}styles/resource/images/login/HiveNova.png">
	<script src="scripts/base/jquery.js?v={$REV}" defer></script>
	<script src="scripts/base/jquery.cookie.js?v={$REV}" defer></script>
	<script src="scripts/base/jquery.fancybox.js?v={$REV}" defer></script>
	<script src="scripts/login/main.js" defer></script>
	<script>{if isset($code)}var loginError = {$code|json};{/if}</script>
	{block name="script"}{/block}
</head>
<body id="{$smarty.get.page|htmlspecialchars|default:'overview'}" class="{$bodyclass}">
	<div id="page">
