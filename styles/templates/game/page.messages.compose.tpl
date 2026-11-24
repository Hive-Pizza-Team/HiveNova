{block name="title" prepend}{$LNG.mg_send_new}{/block}
{block name="content"}
<form method="post" action="game.php?page=messages&amp;mode=compose">
<table style="width:100%;">
	<tr>
		<th colspan="2">{$LNG.mg_send_new}</th>
	</tr>
	{if $composeError}
	<tr>
		<td colspan="2" class="msg-compose-error">{$composeError}</td>
	</tr>
	{/if}
	<tr>
		<td>{$LNG.mg_compose_recipient}</td>
		<td><input type="text" name="username" value="{$composeUsername|escape:'html'}" maxlength="32" required autofocus></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit" value="{$LNG.mg_send_new}"></td>
	</tr>
</table>
</form>
{/block}
