{block name="title" prepend}{$LNG.lm_medals}{/block}
{block name="content"}
<div class="medals-page">
	<table class="medals-header table519">
		<tr>
			<th colspan="2">{$LNG.md_title}</th>
		</tr>
		<tr>
			<td style="width:35%">{$LNG.md_commander}</td>
			<td>
				{if $medalsView.owner.username}{$medalsView.owner.username|escape:'html'}{/if}
				{include file="shared.prestige.badges.tpl" badges=$prestige.badges}
				{if $medalsView.owner.isSelf}<span class="medals-self">{$LNG.md_you}</span>{/if}
			</td>
		</tr>
		{if $prestige.memberSinceDate || $prestige.hiveLine}
		<tr>
			<td>{$LNG.pl_member_since_label}</td>
			<td>
				{if $prestige.memberSinceDate}{$prestige.memberSinceDate|escape:'html'}{/if}
				{if $prestige.hiveLine}<span class="prestige-hive">{$prestige.hiveLine|escape:'html'}</span>{/if}
			</td>
		</tr>
		{/if}
		<tr>
			<td>{$LNG.md_lookup}</td>
			<td>
				<form method="get" action="game.php" class="medals-lookup">
					<input type="hidden" name="page" value="medals">
					<label>{$LNG.md_lookup_id} <input type="number" name="id" min="1" value="{if $lookupId}{$lookupId}{/if}"></label>
					<label>{$LNG.md_lookup_hive} <input type="text" name="hive" maxlength="16" value="{$lookupHive|escape:'html'}" placeholder="@hive"></label>
					<button type="submit">{$LNG.md_lookup_go}</button>
				</form>
			</td>
		</tr>
	</table>

	<table class="medals-legend table519">
		<tr>
			<th colspan="2">{$LNG.md_legend_title}</th>
		</tr>
		{foreach $medalsView.legend as $item}
		<tr>
			<td style="width:48px" class="medals-legend-glyph">
				{include file="shared.prestige.badges.tpl" badges=$item.badges prestigeCap=1}
			</td>
			<td>{$item.label|escape:'html'}</td>
		</tr>
		{/foreach}
	</table>

	<table class="medals-inventory table519">
		<tr>
			<th colspan="5">{$LNG.md_inventory}</th>
		</tr>
		{if $medalsView.medals|@count == 0}
		<tr>
			<td colspan="5" class="medals-empty">{$medalsView.emptyReason|escape:'html'}</td>
		</tr>
		{else}
		<tr>
			<th>{$LNG.md_col_medal}</th>
			<th>{$LNG.md_col_season}</th>
			<th>{$LNG.md_col_tier}</th>
			<th>{$LNG.md_col_hive}</th>
			<th>{$LNG.md_col_links}</th>
		</tr>
		{foreach $medalsView.medals as $medal}
		<tr>
			<td class="medals-art">
				{if $medal.imageUrl}
					<img src="{$medal.imageUrl|escape:'html'}" alt="{$medal.title|escape:'html'}" width="48" height="48">
				{else}
					{include file="shared.prestige.badges.tpl" badges=$medal.badges prestigeCap=1}
				{/if}
				<div class="medals-title">{$medal.title|escape:'html'}</div>
				{if $medal.retroactive}<span class="medals-retro">{$LNG.md_retro}</span>{/if}
			</td>
			<td>{$medal.seasonLabel|escape:'html'}</td>
			<td>
				{$medal.tierLabel|escape:'html'}
				{if $medal.rank} #{$medal.rank}{/if}
				{if $medal.featId} ({$medal.featId|escape:'html'}){/if}
			</td>
			<td>
				{if $medal.hiveUser}@{$medal.hiveUser|escape:'html'}{/if}
				{if $medal.gameName}<div class="medals-game-name">{$medal.gameName|escape:'html'}</div>{/if}
			</td>
			<td>
				{if $medal.recapUrl}<a href="{$medal.recapUrl|escape:'html'}" target="_blank" rel="noopener noreferrer">{$LNG.md_recap}</a>{else}—{/if}
			</td>
		</tr>
		{/foreach}
		{/if}
	</table>

	<p class="medals-note">{$LNG.md_cosmetic_note}</p>
</div>
{/block}
