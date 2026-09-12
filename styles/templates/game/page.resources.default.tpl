{block name="title" prepend}{$LNG.lm_resources}{/block}
{block name="content"}
<form class="resources-form" action="?page=resources" method="post">
<input type="hidden" name="mode" value="send">
<table class="resources-table">
<tbody>
<tr class="resources-table__title">
	<th colspan="5">{$header}</th>
</tr>
<tr class="resources-table__cols no-mobile">
	<td>&nbsp;</td>
    <td><a href='#' onclick='return Dialog.info(901)'>{$LNG.tech.901}</a></td>
    <td><a href='#' onclick='return Dialog.info(902)'>{$LNG.tech.902}</a></td>
    <td><a href='#' onclick='return Dialog.info(903)'>{$LNG.tech.903}</a></td>
    <td><a href='#' onclick='return Dialog.info(911)'>{$LNG.tech.911}</a></td>
</tr>
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_basic_income}</td>
	<td data-label="{$LNG.tech.901}"><span class="resources-table__value">{$basicProduction.901|number} <span class="res-prod-pos">/h</span></span></td>
	<td data-label="{$LNG.tech.902}"><span class="resources-table__value">{$basicProduction.902|number} <span class="res-prod-pos">/h</span></span></td>
	<td data-label="{$LNG.tech.903}"><span class="resources-table__value">{$basicProduction.903|number} <span class="res-prod-pos">/h</span></span></td>
	<td data-label="{$LNG.tech.911}"><span class="resources-table__value">{$basicProduction.911|number} <span class="res-prod-pos">/h</span></span></td>
</tr>
{foreach $productionList as $productionID => $productionRow}
<tr class="resources-table__row resources-table__prod">
	<td class="resources-table__label"><a href='#' onclick='return Dialog.info({$productionID});'>{$LNG.tech.$productionID }</a> ({if $productionID  > 200}{$LNG.rs_amount}{else}{$LNG.rs_lvl}{/if} {$productionRow.elementLevel})</td>
	<td data-label="{$LNG.tech.901}"><span class="{if $productionRow.production.901 > 0}res-prod-pos{elseif $productionRow.production.901 < 0}res-prod-neg{else}res-prod-zero{/if}">{$productionRow.production.901|number} /h</span></td>
	<td data-label="{$LNG.tech.902}"><span class="{if $productionRow.production.902 > 0}res-prod-pos{elseif $productionRow.production.902 < 0}res-prod-neg{else}res-prod-zero{/if}">{$productionRow.production.902|number} /h</span></td>
	<td data-label="{$LNG.tech.903}"><span class="{if $productionRow.production.903 > 0}res-prod-pos{elseif $productionRow.production.903 < 0}res-prod-neg{else}res-prod-zero{/if}">{$productionRow.production.903|number} /h</span></td>
	<td data-label="{$LNG.tech.911}"><span class="{if $productionRow.production.911 > 0}res-prod-pos{elseif $productionRow.production.911 < 0}res-prod-neg{else}res-prod-zero{/if}">{$productionRow.production.911|number} /h</span></td>
	<td class="resources-table__factor" data-label="{$LNG.rs_prod_factor}">
		{html_options name="prod[{$productionID}]" options=$prodSelector selected=$productionRow.prodLevel}
	</td>
</tr>
{/foreach}
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_ress_bonus}</td>
	<td data-label="{$LNG.tech.901}"><span class="{if $bonusProduction.901 > 0}res-prod-pos{elseif $bonusProduction.901 < 0}res-prod-neg{else}res-prod-zero{/if}">{$bonusProduction.901|number} /h</span></td>
	<td data-label="{$LNG.tech.902}"><span class="{if $bonusProduction.902 > 0}res-prod-pos{elseif $bonusProduction.902 < 0}res-prod-neg{else}res-prod-zero{/if}">{$bonusProduction.902|number} /h</span></td>
	<td data-label="{$LNG.tech.903}"><span class="{if $bonusProduction.903 > 0}res-prod-pos{elseif $bonusProduction.903 < 0}res-prod-neg{else}res-prod-zero{/if}">{$bonusProduction.903|number} /h</span></td>
	<td data-label="{$LNG.tech.911}"><span class="{if $bonusProduction.911 > 0}res-prod-pos{elseif $bonusProduction.911 < 0}res-prod-neg{else}res-prod-zero{/if}">{$bonusProduction.911|number} /h</span></td>
	<td class="resources-table__apply-cell no-mobile"><input class="resources-apply-inline" value="{$LNG.rs_calculate}" type="submit"></td>
</tr>
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_storage_capacity}</td>
	<td data-label="{$LNG.tech.901}"><span class="res-prod-pos">{$storage.901}</span></td>
	<td data-label="{$LNG.tech.902}"><span class="res-prod-pos">{$storage.902}</span></td>
	<td data-label="{$LNG.tech.903}"><span class="res-prod-pos">{$storage.903}</span></td>
	<td data-label="{$LNG.tech.911}">-</td>
</tr>
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_sum}:</td>
	<td data-label="{$LNG.tech.901}"><span class="{if $totalProduction.901 > 0}res-prod-pos{elseif $totalProduction.901 < 0}res-prod-neg{else}res-prod-zero{/if}">{$totalProduction.901|number} /h</span></td>
	<td data-label="{$LNG.tech.902}"><span class="{if $totalProduction.902 > 0}res-prod-pos{elseif $totalProduction.902 < 0}res-prod-neg{else}res-prod-zero{/if}">{$totalProduction.902|number} /h</span></td>
	<td data-label="{$LNG.tech.903}"><span class="{if $totalProduction.903 > 0}res-prod-pos{elseif $totalProduction.903 < 0}res-prod-neg{else}res-prod-zero{/if}">{$totalProduction.903|number} /h</span></td>
	<td data-label="{$LNG.tech.911}"><span class="{if $totalProduction.911 > 0}res-prod-pos{elseif $totalProduction.911 < 0}res-prod-neg{else}res-prod-zero{/if}">{$totalProduction.911|number} /h</span></td>
</tr>
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_daily}</td>
	<td data-label="{$LNG.tech.901}"><span class="{if $dailyProduction.901 > 0}res-prod-pos{elseif $dailyProduction.901 < 0}res-prod-neg{else}res-prod-zero{/if}">{$dailyProduction.901|number} /day</span></td>
	<td data-label="{$LNG.tech.902}"><span class="{if $dailyProduction.902 > 0}res-prod-pos{elseif $dailyProduction.902 < 0}res-prod-neg{else}res-prod-zero{/if}">{$dailyProduction.902|number} /day</span></td>
	<td data-label="{$LNG.tech.903}"><span class="{if $dailyProduction.903 > 0}res-prod-pos{elseif $dailyProduction.903 < 0}res-prod-neg{else}res-prod-zero{/if}">{$dailyProduction.903|number} /day</span></td>
	<td data-label="{$LNG.tech.911}"><span class="{if $dailyProduction.911 > 0}res-prod-pos{elseif $dailyProduction.911 < 0}res-prod-neg{else}res-prod-zero{/if}">{$dailyProduction.911|number} /day</span></td>
</tr>
<tr class="resources-table__row">
	<td class="resources-table__label">{$LNG.rs_weekly}</td>
	<td data-label="{$LNG.tech.901}"><span class="{if $weeklyProduction.901 > 0}res-prod-pos{elseif $weeklyProduction.901 < 0}res-prod-neg{else}res-prod-zero{/if}">{$weeklyProduction.901|number} /week</span></td>
	<td data-label="{$LNG.tech.902}"><span class="{if $weeklyProduction.902 > 0}res-prod-pos{elseif $weeklyProduction.902 < 0}res-prod-neg{else}res-prod-zero{/if}">{$weeklyProduction.902|number} /week</span></td>
	<td data-label="{$LNG.tech.903}"><span class="{if $weeklyProduction.903 > 0}res-prod-pos{elseif $weeklyProduction.903 < 0}res-prod-neg{else}res-prod-zero{/if}">{$weeklyProduction.903|number} /week</span></td>
	<td data-label="{$LNG.tech.911}"><span class="{if $weeklyProduction.911 > 0}res-prod-pos{elseif $weeklyProduction.911 < 0}res-prod-neg{else}res-prod-zero{/if}">{$weeklyProduction.911|number} /week</span></td>
</tr>
</tbody>
</table>
<div class="resources-apply-bar mobile">
	<input value="{$LNG.rs_calculate}" type="submit">
</div>
</form>
{/block}
