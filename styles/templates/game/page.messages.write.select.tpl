{block name="title" prepend}{$LNG.write_message}{/block}
{block name="content"}
<form name="message-compose-select" method="get" action="game.php">
	<input type="hidden" name="page" value="messages">
	<input type="hidden" name="mode" value="write">
	<table style="width:95%;">
		<tr>
			<th colspan="2">{$LNG.mg_send_new}</th>
		</tr>
		<tr>
			<td colspan="2" class="left">{$LNG.mg_compose_hint}</td>
		</tr>
		{if $composeError}
		<tr>
			<td colspan="2"><p class="message-write-error">{$composeError}</p></td>
		</tr>
		{/if}
		<tr>
			<td style="width:30%">{$LNG.mg_compose_recipient}</td>
			<td style="width:70%"><input type="text" name="username" size="40" maxlength="32" value="{$composeUsername}" autofocus required></td>
		</tr>
		<tr>
			<td colspan="2"><input type="submit" value="{$LNG.mg_send_new}"></td>
		</tr>
	</table>
</form>
{/block}
