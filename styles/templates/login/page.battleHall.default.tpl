{block name="title"}{$documentTitle}{/block}
{block name="content"}
<div class="public-battle-cta">
	<p class="public-battle-cta__blurb">{$signupCtaBlurb|escape}</p>
	<p class="public-battle-cta__actions">
		<a class="public-battle-cta__btn" href="{$signupCtaUrl|escape}">{$signupCtaLabel|escape}</a>
	</p>
</div>
{if $isMultiUniverse}<p>
{html_options options=$universeSelect selected=$UNI class="changeUni" id="universe" name="universe"}
</p>{/if}
<table>
<tr>
	<th style="color:lime">{$LNG.tkb_platz}</th>
	<th style="color:lime">{$LNG.tkb_owners}</th>
	<th style="color:lime">{$LNG.tkb_datum}</th>
	<th style="color:lime">{$LNG.tkb_units}</th>
</tr>
{foreach $hallList as $hallRow}
<tr>
	<td>{$hallRow@iteration}</td>
	<td>
	<a href="{$hallRow.reportUrl|escape}">
	{if $hallRow.result == "a"}
	<span style="color:#00FF00">{$hallRow.attacker|escape}</span><span style="color:#FFFFFF"><b> VS </b></span><span style="color:#FF0000">{$hallRow.defender|escape}</span>
	{elseif $hallRow.result == "r"}
	<span style="color:#FF0000">{$hallRow.attacker|escape}</span><span style="color:#FFFFFF"><b> VS </b></span><span style="color:#00FF00">{$hallRow.defender|escape}</span>
	{else}
	{$hallRow.attacker|escape}<b> VS </b>{$hallRow.defender|escape}
	{/if}
	</a>
	</td>
	<td>{$hallRow.time}</td>
	<td>{$hallRow.units|number}</td>
</tr>
{/foreach}
<tr>
<td colspan="4"><p>{$LNG.tkb_legende}<span style="color:#00FF00">{$LNG.tkb_gewinner}</span><span style="color:#FF0000">{$LNG.tkb_verlierer}</span></p></td>
</tr>
</table>
<div class="public-battle-cta public-battle-cta--footer">
	<p class="public-battle-cta__actions">
		<a class="public-battle-cta__btn" href="{$signupCtaUrl|escape}">{$signupCtaLabel|escape}</a>
	</p>
</div>
{/block}
{block name="script"}
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/battle-public.css?v={$REV}">
{/block}
