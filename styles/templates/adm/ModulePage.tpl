{include file="overall_header.tpl"}
<center>
<table width="500">
<tr>
    <th colspan="3">{$mod_module}</th>
</tr>
<tr>
    <td colspan="3"><strong>{$mod_info}</strong></td>
</tr>
{foreach key=ID item=Info from=$Modules}
<tr>
	<td>{$Info.name}</td>
	{if $Info.state == 1}
		<td style="color:green"><b>{$mod_active}</b></td>
		<td>
			<form action="?page=module" method="post" style="display:inline">
				<input type="hidden" name="admin_csrf" value="{$admin_csrf}">
				<input type="hidden" name="mode" value="deaktiv">
				<input type="hidden" name="id" value="{$ID}">
				<button type="submit">{$mod_change_deactive}</button>
			</form>
		</td>
	{else}
		<td style="color:red"><b>{$mod_deactive}</b></td>
		<td>
			<form action="?page=module" method="post" style="display:inline">
				<input type="hidden" name="admin_csrf" value="{$admin_csrf}">
				<input type="hidden" name="mode" value="aktiv">
				<input type="hidden" name="id" value="{$ID}">
				<button type="submit">{$mod_change_active}</button>
			</form>
		</td>
	{/if}
	</tr>
{/foreach}
</table>
</center>
{include file="overall_footer.tpl"}
