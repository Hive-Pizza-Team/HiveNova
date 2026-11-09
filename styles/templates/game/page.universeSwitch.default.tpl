{block name="title" prepend}{$LNG.lm_universe_switch}{/block}
{block name="content"}
<table class="table519">
	<tr>
		<th colspan="3">{$LNG.us_title}</th>
	</tr>
	<tr>
		<td colspan="3">{$LNG.us_intro}</td>
	</tr>
	{if $hasLinks}
		<tr>
			<th>{$LNG.us_title}</th>
			<th>{$LNG.us_username}</th>
			<th></th>
		</tr>
		{foreach $accounts as $account}
		<tr>
			<td>{$account.universeName|escape}{if $account.isCurrent} ({$LNG.us_current}){/if}</td>
			<td>{$account.username|escape}</td>
			<td>
				{if $account.isCurrent}
					—
				{else}
					<form action="game.php?page=universeSwitch" method="post" style="margin:0">
						<input type="hidden" name="mode" value="switchUniverse">
						<input type="hidden" name="universe" value="{$account.universeId}">
						<button type="submit" class="button">{$LNG.us_switch}</button>
					</form>
				{/if}
			</td>
		</tr>
		{/foreach}
	{else}
		<tr>
			<td colspan="3">{$LNG.us_empty}</td>
		</tr>
		{foreach $accounts as $account}
		<tr>
			<td>{$account.universeName|escape} ({$LNG.us_current})</td>
			<td>{$account.username|escape}</td>
			<td>—</td>
		</tr>
		{/foreach}
	{/if}
</table>
{/block}
