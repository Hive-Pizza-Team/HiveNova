{block name="title" prepend}{$LNG.lm_universe_switch}{/block}
{block name="content"}
<table class="table519">
	<tr>
		<th colspan="4">{$LNG.us_title}</th>
	</tr>
	<tr>
		<td colspan="4">{$LNG.us_intro}<br><br>{$LNG.us_warning}</td>
	</tr>
	{if $hasLinks}
		<tr>
			<th>{$LNG.us_title}</th>
			<th>{$LNG.us_col_id}</th>
			<th>{$LNG.us_username}</th>
			<th></th>
		</tr>
		{foreach $accounts as $account}
		<tr{if $account.isCurrent} style="font-weight:bold"{/if}>
			<td>{$account.universeName|escape}{if $account.isCurrent} — {$LNG.us_current}{/if}</td>
			<td>{$account.universeIdLabel|escape}</td>
			<td>{$account.username|escape}</td>
			<td>
				{if $account.isCurrent}
					—
				{else}
					<form action="game.php?page=universeSwitch" method="post" style="margin:0" onsubmit="return confirm('{$account.confirmMessage|escape:'javascript'}');">
						<input type="hidden" name="mode" value="switchUniverse">
						<input type="hidden" name="universe" value="{$account.universeId}">
						<input type="hidden" name="csrf" value="{$csrfToken}">
						<button type="submit" class="button">{$LNG.us_switch}</button>
					</form>
				{/if}
			</td>
		</tr>
		{/foreach}
	{else}
		<tr>
			<td colspan="4">{$LNG.us_empty}</td>
		</tr>
		{foreach $accounts as $account}
		<tr style="font-weight:bold">
			<td>{$account.universeName|escape} — {$LNG.us_current}</td>
			<td>{$account.universeIdLabel|escape}</td>
			<td>{$account.username|escape}</td>
			<td>—</td>
		</tr>
		{/foreach}
	{/if}
</table>
{/block}
