{include file="overall_header.tpl"}
<table width="500">
<tr><th>{$mu_manual_points_update}</th></tr>
<tr>
	<td>
		<form action="?page=statsupdate" method="post" onsubmit="return confirm('{$mu_mpu_confirmation|escape:'javascript'}');">
			<input type="hidden" name="admin_csrf" value="{$admin_csrf}">
			<button type="submit">{$button_submit}</button>
		</form>
	</td>
</tr>
</table>
{include file="overall_footer.tpl"}
