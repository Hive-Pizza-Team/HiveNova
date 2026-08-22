{block name="title" prepend}{$LNG.page_season_title}{/block}
{block name="content"}
<table>
	<tr>
		<th>{$LNG.page_season_title}</th>
	</tr>
	<tr>
		<td class="left">
			<p>{$seasonMessage}</p>
			{if $countdownLabel != ''}
			<p>{$LNG.page_season_countdown}: {$countdownLabel}</p>
			{/if}
			{if !$hasHive}
			<p><a href="game.php?page=settings">{$LNG.page_season_link_settings}</a></p>
			{/if}
			{if $hasHive && $canEnter && !$hasEntry}
			<p>
				<button type="button" class="button_standard" onclick="DepositSeasonPizza('{$hiveAccount|escape:'javascript'}', '{$wallet|escape:'javascript'}', '{$entryAmount}', '{$memo|escape:'javascript'}')">{$LNG.page_season_pay_button}</button>
			</p>
			<form action="game.php?page=season" method="post">
				<input type="hidden" name="mode" value="confirm">
				<label>{$LNG.page_season_confirm}</label>
				<input type="text" name="txid" maxlength="80" size="40" autocomplete="off">
				<input type="submit" value="{$LNG.page_season_confirm}">
			</form>
			{/if}
			{if $canPlay}
			<p><a href="game.php?page=overview">{$LNG.sys_forward}</a></p>
			{/if}
		</td>
	</tr>
</table>
{/block}
