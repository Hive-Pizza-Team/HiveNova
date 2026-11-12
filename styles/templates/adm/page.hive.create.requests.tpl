{include file="overall_header.tpl"}
{if $message}<div class="message-success">{$message|escape:'html'}</div>{/if}
<table width="90%">
<tr>
	<th colspan="6">{$LNG.hive_create_admin_title} (uni {$universe})</th>
</tr>
<tr>
	<td colspan="6"><small>{$LNG.hive_create_admin_help}</small></td>
</tr>
<tr>
	<th>ID</th>
	<th>{$LNG.hive_create_admin_player}</th>
	<th>{$LNG.hive_create_admin_desired}</th>
	<th>{$LNG.hive_create_admin_requested}</th>
	<th>{$LNG.hive_create_admin_approve}</th>
	<th>{$LNG.hive_create_admin_reject}</th>
</tr>
{foreach $pendingRequests as $row}
<tr>
	<td>{$row.id}</td>
	<td>{$row.username|escape:'html'} (#{$row.user_id})</td>
	<td>@{$row.desired_name|escape:'html'}</td>
	<td>{$row.created_at_fmt}</td>
	<td>
		<form method="post" action="?page=hivecreaterequests">
			<input type="hidden" name="action" value="approve">
			<input type="hidden" name="id" value="{$row.id}">
			<input type="submit" value="{$LNG.hive_create_admin_approve}">
		</form>
	</td>
	<td>
		<form method="post" action="?page=hivecreaterequests">
			<input type="hidden" name="action" value="reject">
			<input type="hidden" name="id" value="{$row.id}">
			<input type="text" name="reason" size="24" maxlength="255" placeholder="{$LNG.hive_create_admin_reason_ph|escape:'html'}">
			<input type="submit" value="{$LNG.hive_create_admin_reject}">
		</form>
	</td>
</tr>
{foreachelse}
<tr>
	<td colspan="6">{$LNG.hive_create_admin_empty}</td>
</tr>
{/foreach}
</table>
{include file="overall_footer.tpl"}
