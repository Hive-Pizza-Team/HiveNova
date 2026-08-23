{include file="overall_header.tpl"}
<table width="80%">
<tr>
	<th>{$LNG.cronjob_id}</th>
	<th>{$LNG.cronjob_name}</th>
	<th>{$LNG.cronjob_min}</th>
	<th>{$LNG.cronjob_hours}</th>
	<th>{$LNG.cronjob_dom}</th>
	<th>{$LNG.cronjob_month}</th>
	<th>{$LNG.cronjob_dow}</th>
	<th>{$LNG.cronjob_class}</th>
	<th>{$LNG.cronjob_nextTime}</th>
	<th>{$LNG.cronjob_inActive}</th>
	<th>{$LNG.cronjob_lock}</th>
	<th>{$LNG.cronjob_edit}</th>
	<th>{$LNG.cronjob_delete}</th>
</tr>
{foreach item=CronjobInfo from=$CronjobArray}
<tr>
	<td>{$CronjobInfo.id}</td>
	<td>{$LNG["cronName_{$CronjobInfo.name}"]}</td>
	<td>{$CronjobInfo.min}</td>
	<td>{$CronjobInfo.hours}</td>
	<td>{$CronjobInfo.dom}</td>
	<td>{if $CronjobInfo.month == '*'}{$CronjobInfo.month}{else}{foreach item=month from=$CronjobInfo.month}{$LNG.months.{$month-1}}{/foreach}{/if}</td>
	<td>{if $CronjobInfo.dow == '*'}{$CronjobInfo.dow}{else}{foreach item=d from=$CronjobInfo.dow}{$LNG.week_day.{$d}} {/foreach}{/if}</td>
	<td>{$CronjobInfo.class}</td>
	<td>{if $CronjobInfo.isActive}{$CronjobInfo.nextTime|php_date:$LNG.php_tdformat}{else}-{/if}</td>
	<td>
		<form method="post" action="admin.php?page=cronjob" style="display:inline">
			<input type="hidden" name="action" value="enable">
			<input type="hidden" name="id" value="{$CronjobInfo.id}">
			{if $CronjobInfo.isActive}
			<input type="hidden" name="enable" value="0">
			<button type="submit" style="color:lime;background:none;border:0;padding:0;cursor:pointer">{$LNG.cronjob_inactive}</button>
			{else}
			<input type="hidden" name="enable" value="1">
			<button type="submit" style="color:red;background:none;border:0;padding:0;cursor:pointer">{$LNG.cronjob_active}</button>
			{/if}
		</form>
	</td>
	<td>
		<form method="post" action="admin.php?page=cronjob" style="display:inline">
			<input type="hidden" name="id" value="{$CronjobInfo.id}">
			{if $CronjobInfo.lock}
			<input type="hidden" name="action" value="unlock">
			<button type="submit" style="color:red;background:none;border:0;padding:0;cursor:pointer">{$LNG.cronjob_is_lock}</button>
			{else}
			<input type="hidden" name="action" value="lock">
			<button type="submit" style="color:lime;background:none;border:0;padding:0;cursor:pointer">{$LNG.cronjob_is_unlock}</button>
			{/if}
		</form>
	</td>
	<td><a href="admin.php?page=cronjob&amp;action=detail&amp;id={$CronjobInfo.id}"><img src="./styles/resource/images/admin/GO.png"></a></td>
	<td>
		<form method="post" action="admin.php?page=cronjob" style="display:inline" onsubmit="return confirm('{$LNG.cronjob_delete}');">
			<input type="hidden" name="action" value="delete">
			<input type="hidden" name="id" value="{$CronjobInfo.id}">
			<button type="submit" style="background:none;border:0;padding:0;cursor:pointer"><img src="./styles/resource/images/false.png" width="16" height="16" alt="{$LNG.cronjob_delete}"></button>
		</form>
	</td>
</tr>
{/foreach}
<tr>
<td colspan="13"><a href="admin.php?page=cronjob&amp;action=detail">{$LNG.cronjob_new}</a></td>
</tr>
</table>
</body>
{include file="overall_footer.tpl"}