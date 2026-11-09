{block name="title"}{$documentTitle}{/block}
{block name="content"}
<section class="battlehall-cta">
	<p class="battlehall-cta-hook">{$LNG.battleHallCtaHook|default:'Frontier fights. Room to explore. Space to claim.'}</p>
	<p><a class="battlehall-cta-btn" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a></p>
</section>
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
	<a href="{$hallRow.reportUrl}">
	{if $hallRow.result == "a"}
	<span style="color:#00FF00">{$hallRow.attacker}</span><span style="color:#FFFFFF"><b> VS </b></span><span style="color:#FF0000">{$hallRow.defender}</span>
	{elseif $hallRow.result == "r"}
	<span style="color:#FF0000">{$hallRow.attacker}</span><span style="color:#FFFFFF"><b> VS </b></span><span style="color:#00FF00">{$hallRow.defender}</span>
	{else}
	{$hallRow.attacker}<b> VS </b>{$hallRow.defender}
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
<p class="battlehall-cta-footer"><a class="battlehall-cta-btn" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a></p>
{/block}
