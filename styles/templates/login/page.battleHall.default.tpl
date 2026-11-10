{block name="title"}{$documentTitle}{/block}
{block name="content"}
<section class="public-battle public-battle--hall">
	<p class="public-battle-hook">{$LNG.battleHallCtaHook|default:'Frontier fights. Room to explore. Space to claim.'}</p>
	<div class="public-battle-actions">
		<a class="public-battle-btn public-battle-btn--primary" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a>
	</div>
</section>
{if $isMultiUniverse}<p>
{html_options options=$universeSelect selected=$UNI class="changeUni" id="universe" name="universe"}
</p>{/if}
<table class="public-battle-table">
<tr>
	<th>{$LNG.tkb_platz}</th>
	<th>{$LNG.tkb_owners}</th>
	<th>{$LNG.tkb_datum}</th>
	<th>{$LNG.tkb_units}</th>
</tr>
{foreach $hallList as $hallRow}
<tr>
	<td>{$hallRow@iteration}</td>
	<td>
	<a class="public-battle-rowlink" href="{$hallRow.reportUrl}">
	{if $hallRow.result == "a"}
	<span class="public-battle-side public-battle-side--win">{$hallRow.attacker}</span><span class="public-battle-vs-sep"> VS </span><span class="public-battle-side public-battle-side--lose">{$hallRow.defender}</span>
	{elseif $hallRow.result == "r"}
	<span class="public-battle-side public-battle-side--lose">{$hallRow.attacker}</span><span class="public-battle-vs-sep"> VS </span><span class="public-battle-side public-battle-side--win">{$hallRow.defender}</span>
	{else}
	{$hallRow.attacker}<span class="public-battle-vs-sep"> VS </span>{$hallRow.defender}
	{/if}
	</a>
	</td>
	<td>{$hallRow.time}</td>
	<td>{$hallRow.units|number}</td>
</tr>
{/foreach}
<tr>
<td colspan="4"><p class="public-battle-legend">{$LNG.tkb_legende}<span class="public-battle-side public-battle-side--win">{$LNG.tkb_gewinner}</span><span class="public-battle-side public-battle-side--lose">{$LNG.tkb_verlierer}</span></p></td>
</tr>
</table>
{/block}
