{block name="title" prepend}{$LNG.write_message}{/block}
{block name="content"}
<form name="message" id="message" method="post" action="game.php?page=messages&amp;mode=send&amp;id={$id}&amp;ajax=1" data-message-write="1" data-empty="{$LNG.mg_empty_text|escape:'html'}" data-url="game.php?page=messages&amp;mode=send&amp;id={$id}&amp;ajax=1">
	<table style="width:95%;">
		<tr>
			<th colspan="2">{$LNG.mg_send_new}</th>
		</tr><tr>
			<td style="width:30%">{$LNG.mg_send_to}</td>
			<td style="width:70%"><input type="text" name="to" size="40" value="{$OwnerRecord.username} [{$OwnerRecord.galaxy}:{$OwnerRecord.system}:{$OwnerRecord.planet}]"></td>
		</tr><tr>
			<td style="width:30%">{$LNG.mg_subject}</td>
			<td style="width:70%"><input type="text" name="subject" id="subject" size="40" maxlength="40" value="{if !empty($subject)}{$subject}{else}{$LNG.mg_no_subject}{/if}"></td>
		</tr><tr>
			<td style="width:30%">{$LNG.mg_message}<br>(<span id="cntChars">0</span>&nbsp;/&nbsp;5.000&nbsp;{$LNG.mg_characters})</th>
			<td style="width:70%"><textarea name="text" id="text" cols="40" rows="10" onkeyup="$('#cntChars').text($(this).val().length);"></textarea></td>
		</tr>
		<tr id="message-write-error-row">
			<td colspan="2"><p id="message-write-error" class="message-write-error" hidden></p></td>
		</tr>
		<tr>
			<td colspan="2"><input id="submit" type="submit" name="button" value="{$LNG.mg_send}"></td>
		</tr>
	</table>
</form>
{/block}
