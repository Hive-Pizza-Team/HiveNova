{include file="overall_header.tpl"}
<table width="500">
<tr><th>{$mu_clear_cache}</th></tr>
<tr>
	<td>
		<form action="?page=clearcache" method="post">
			<input type="hidden" name="admin_csrf" value="{$admin_csrf}">
			<button type="submit">{$button_submit}</button>
		</form>
	</td>
</tr>
</table>
{include file="overall_footer.tpl"}
