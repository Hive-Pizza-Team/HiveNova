{block name="title" prepend}{$LNG.lm_messages}{/block}
{block name="content"}
<table class="msg-overview">
	<tr>
		<th colspan="6">{$LNG.mg_overview}<span id="loading" class="msg-loading"> ({$LNG.loading})</span></th>
	</tr>
	{foreach $CategoryList as $CategoryID => $CategoryRow}
	{if ($CategoryRow@iteration % 6) === 1}<tr>{/if}
	{if $CategoryRow@last && ($CategoryRow@iteration % 6) !== 0}<td>&nbsp;</td>{/if}
	<td class="msg-cat-cell"><a href="game.php?page=messages&category={$CategoryID}" class="msg-cat-link" style="color:{$CategoryRow.color};">{$LNG.mg_type.{$CategoryID}}</a>
	<br><span class="msg-cat-counts"><span id="unread_{$CategoryID}">{$CategoryRow.unread}</span>/<span id="total_{$CategoryID}">{$CategoryRow.total}</span></span>
	</td>
	{if $CategoryRow@last || ($CategoryRow@iteration % 6) === 0}</tr>{/if}
	{/foreach}
</table>
<form action="game.php?page=messages" method="post">
<input type="hidden" name="mode" value="action">
<input type="hidden" name="ajax" value="1">
<input type="hidden" name="messcat" value="{$MessID}">
<input type="hidden" name="side" value="{$page}">
<table id="messagestable" class="msg-table">
	<tr>
		<th colspan="4">{$LNG.mg_message_title}</th>
	</tr>
	{if $MessID != 999}
	<tr>
		<td colspan="4" class="msg-action-bar">
			<select name="actionTop">
				<option value="readmarked">{$LNG.mg_read_marked}</option>
				<option value="readtypeall">{$LNG.mg_read_type_all}</option>
				<option value="readall">{$LNG.mg_read_all}</option>
				<option value="deletemarked">{$LNG.mg_delete_marked}</option>
				<option value="deleteunmarked">{$LNG.mg_delete_unmarked}</option>
				<option value="deletetypeall">{$LNG.mg_delete_type_all}</option>
				<option value="deleteall">{$LNG.mg_delete_all}</option>
			</select>
			<input value="{$LNG.mg_confirm}" type="submit" name="submitTop">
		</td>
	</tr>
	{/if}
	<tr class="msg-pagination-row">
		<td class="right" colspan="4">{$LNG.mg_page}: {if $page != 1}<a href="game.php?page=messages&category={$MessID}&side=1">&laquo;</a>&nbsp;{/if}{if $page > 5}..&nbsp;{/if}{for $site=1 to $maxPage}<a href="game.php?page=messages&category={$MessID}&side={$site}">{if $site == $page}<b>[{$site}]&nbsp;</b>{elseif ($site > $page-5 && $site < $page+5)}[{$site}]&nbsp;{/if}</a>{/for}{if $page < $maxPage-4}..&nbsp;{/if}{if $page != $maxPage}&nbsp;<a href="game.php?page=messages&category={$MessID}&side={$maxPage}">&raquo;</a>{/if}</td>
	</tr>
	<tr class="msg-col-header">
		<td class="msg-action-col">{$LNG.mg_action}</td>
		<td class="msg-date-col">{$LNG.mg_date}</td>
		<td class="msg-from-col">{if $MessID != 999}{$LNG.mg_from}{else}{$LNG.mg_to}{/if}</td>
		<td>{$LNG.mg_subject}</td>
	</tr>
	{foreach $MessageList as $Message}
	<tr id="message_{$Message.id}" class="message_{$Message.id} message_head{if $MessID != 999 && $Message.unread == 1} mes_unread{/if}">
		<td rowspan="2" class="msg-action-col">
		{if $MessID != 999}<input name="messageID[{$Message.id}]" value="1" type="checkbox">{/if}
		</td>
		<td class="msg-date-col">{$Message.time}</td>
		<td class="msg-from-col">{$Message.from}</td>
		<td class="msg-subject-col">
			{$Message.subject}
			{if $Message.type == 1 && $MessID != 999}
			<a href="#" onclick="return Dialog.PM({$Message.sender}, Message.CreateAnswer('{$Message.subject}'));" title="{$LNG.mg_answer_to} {$Message.from|strip_tags}" class="msg-icon-btn"><i class="fas fa-reply"></i></a>
			{/if}
			{if $MessID != 999}<a href="#" onclick="Message.delMessage({$Message.id});return false;" class="msg-icon-btn msg-delete-btn"><i class="fas fa-trash"></i></a>{/if}
		</td>
	</tr>
	<tr class="message_{$Message.id} messages_body{if $MessID != 999 && $Message.unread == 1} mes_unread{/if}">
		<td colspan="3" class="left msg-body-cell">
		{$Message.text}
		</td>
	</tr>
	{/foreach}
	<tr class="msg-pagination-row">
		<td class="right" colspan="4">{$LNG.mg_page}: {if $page != 1}<a href="game.php?page=messages&category={$MessID}&side=1">&laquo;</a>&nbsp;{/if}{if $page > 5}..&nbsp;{/if}{for $site=1 to $maxPage}<a href="game.php?page=messages&category={$MessID}&side={$site}">{if $site == $page}<b>[{$site}]&nbsp;</b>{elseif ($site > $page-5 && $site < $page+5)}[{$site}]&nbsp;{/if}</a>{/for}{if $page < $maxPage-4}..&nbsp;{/if}{if $page != $maxPage}&nbsp;<a href="game.php?page=messages&category={$MessID}&side={$maxPage}">&raquo;</a>{/if}</td>
	</tr>
	{if $MessID != 999}
	<tr>
		<td colspan="4" class="msg-action-bar">
			<select name="actionBottom">
				<option value="readmarked">{$LNG.mg_read_marked}</option>
				<option value="readtypeall">{$LNG.mg_read_type_all}</option>
				<option value="readall">{$LNG.mg_read_all}</option>
				<option value="deletemarked">{$LNG.mg_delete_marked}</option>
				<option value="deleteunmarked">{$LNG.mg_delete_unmarked}</option>
				<option value="deletetypeall">{$LNG.mg_delete_type_all}</option>
				<option value="deleteall">{$LNG.mg_delete_all}</option>
			</select>
			<input value="{$LNG.mg_confirm}" type="submit" name="submitBottom">
		</td>
	</tr>
	{/if}
</table>
</form>
{/block}
