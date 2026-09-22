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
			<p>{$payInstruction|escape:'html'}</p>
			<p><small>{$LNG.page_season_manual_intro}</small></p>
			<table>
				<tr>
					<th colspan="2">{$LNG.page_season_how_to}</th>
				</tr>
				<tr>
					<td>{$LNG.page_season_pay_from}</td>
					<td><input type="text" readonly value="{$hiveAccount|escape:'html'}" size="24" onclick="this.select();"></td>
				</tr>
				<tr>
					<td>{$LNG.page_season_pay_to}</td>
					<td><input type="text" readonly value="{$wallet|escape:'html'}" size="24" onclick="this.select();"></td>
				</tr>
				<tr>
					<td>{$LNG.page_season_pay_amount}</td>
					<td><input type="text" readonly value="{$entryAmount|escape:'html'} PIZZA" size="24" onclick="this.select();"></td>
				</tr>
				<tr>
					<td>{$LNG.page_season_pay_memo}</td>
					<td><input type="text" readonly value="{$memo|escape:'html'}" size="24" onclick="this.select();"></td>
				</tr>
			</table>
			<p><small>{$LNG.page_season_token_hint}<br>{$LNG.page_season_pay_memo_hint}</small></p>
			<p>
				<button type="button" class="button_standard" onclick="DepositSeasonPizza('{$hiveAccount|escape:'javascript'}', '{$wallet|escape:'javascript'}', '{$entryAmount}', '{$memo|escape:'javascript'}')">{$LNG.page_season_pay_button}</button>
				<a href="game.php?page=season" class="button_standard">{$LNG.page_season_sent_button}</a>
			</p>
			<p>{$LNG.page_season_confirm_hint}</p>
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
			{if !empty($claimDesk.show)}
			<hr>
			<h3>{$LNG.page_season_claim_title}</h3>
			<p>{$LNG.page_season_medal_blurb}</p>
			{if $claimSeconds != ''}
			<p>{$LNG.page_season_claim_window|sprintf:$claimSeconds}</p>
			{/if}
			{if $claimDesk.reason == 'unpaid'}
			<p>{$LNG.page_season_claim_unpaid}</p>
			<p>{$LNG.page_season_entry_before_wipe}</p>
			{elseif $claimDesk.reason == 'unlinked'}
			<p>{$LNG.page_season_claim_unlinked}</p>
			{elseif $claimDesk.reason == 'forfeited' || $claimDesk.reason == 'window_closed'}
			<p>{$LNG.page_season_claim_forfeit}</p>
			{elseif $claimDesk.reason == 'already'}
			<p>{$LNG.page_season_claim_ok}</p>
			{elseif $claimDesk.reason == 'auto'}
			<p>{$LNG.page_season_claim_auto}</p>
			{elseif $claimDesk.reason == 'not_eligible'}
			<p>{$LNG.page_season_claim_fail}</p>
			{/if}
			{if $claimDesk.can_claim}
			<p>{$LNG.page_season_claim_ready|sprintf:$claimAmount}</p>
			<form action="game.php?page=season" method="post">
				<input type="hidden" name="mode" value="claim">
				<input type="submit" value="{$LNG.page_season_claim_button}">
			</form>
			{/if}
			{if $claimDesk.medal_status == 'claimed'}
			<p>{$LNG.page_season_medal_claimed}</p>
			{elseif $claimDesk.medal_status == 'pending_claim'}
			<p>{$LNG.page_season_medal_pending}</p>
			{elseif $claimDesk.medal_status == 'forfeited'}
			<p>{$LNG.page_season_medal_forfeited}</p>
			{/if}
			{if $claimMeta != ''}
			<p><small>{$claimMeta|escape:'html'}</small></p>
			{/if}
			{/if}
		</td>
	</tr>
</table>
{/block}
