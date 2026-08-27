<!DOCTYPE html>
<html lang="{$lang}" class="no-js">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="{$robotsContent}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/tokens.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/theme/{$dpath|default:'nova'}/formate.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/main.css?v={$REV}">
	<link rel="stylesheet" type="text/css" href="styles/resource/css/login/hivekeychain_button.css?v={$REV}">
	<link rel="shortcut icon" href="./favicon.ico" type="image/x-icon">
	<title>{block name="title"}{$documentTitle}{/block}</title>
	<meta name="description" content="{block name='description'}{$metaDescription}{/block}">
	<link rel="canonical" href="{$canonicalUrl}">
	{foreach $hreflangUrls as $hreflangCode => $hreflangHref}
	<link rel="alternate" hreflang="{$hreflangCode}" href="{$hreflangHref}">
	{/foreach}
	<!-- open graph -->
	<meta property="og:title" content="{block name="og_title"}{$documentTitle}{/block}">
	<meta property="og:type" content="website">
	<meta property="og:url" content="{$canonicalUrl}">
	<meta property="og:site_name" content="{$gameName}">
	<meta property="og:description" content="{block name='og_description'}{$metaDescription}{/block}">
	<meta property="og:image" content="{$ogImageUrl}">
	<meta property="og:image:width" content="{$ogImageWidth}">
	<meta property="og:image:height" content="{$ogImageHeight}">
	<meta property="og:locale" content="{$lang}">
	<!-- Twitter card -->
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="{block name="twitter_title"}{$documentTitle}{/block}">
	<meta name="twitter:description" content="{block name='twitter_description'}{$metaDescription}{/block}">
	<meta name="twitter:image" content="{$ogImageUrl}">
	{if $seoPage == 'index' && $jsonLd}
	<script type="application/ld+json">{$jsonLd nofilter}</script>
	{/if}
	<script src="scripts/login/main.js" defer></script>
	<script>{if isset($code)}var loginError = {$code|json};{/if}</script>
	{block name="script"}{/block}
</head>
<body id="{$smarty.get.page|htmlspecialchars|default:'overview'}" class="{$bodyclass}">
	<div id="page">
