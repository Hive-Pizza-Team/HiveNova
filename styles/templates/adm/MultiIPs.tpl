{include file="overall_header.tpl"}
<table style="width:760px">
	<tr>
		<th>IP:</th>
		<th>{$LNG.se_id_owner}</th>
		<th>{$LNG.se_name}</th>
		<th>{$LNG.se_email}</th>
		<th>{$LNG.ac_register_time}</th>
		<th>{$LNG.ac_act_time}</th>
		<th>{$LNG.mip_known}</th>
	</tr>
	{foreach $multiGroups as $IP => $Users}
	<tr>
		<td rowspan="{$Users|count}">{$IP}</td>
		{foreach $Users as $ID => $User}
		<td class="left" style="padding:3px;">{$ID}</td>
		<td class="left" style="padding:3px;"><a href="admin.php?page=accountdata&id_u={$ID}">{$User.username}</a></td>
		<td class="left" style="padding:3px;">{$User.email}</td>
		<td class="left" style="padding:3px;">{$User.register_time}</td>
		<td class="left" style="padding:3px;">{$User.onlinetime}</td>
		<td class="center" style="padding:3px;">
			<form method="post" action="admin.php?page=multiips" style="display:inline;margin:0">
				<input type="hidden" name="admin_csrf" value="{$admin_csrf|escape:'html'}">
				<input type="hidden" name="id" value="{$ID}">
				{if ($User.isKnown != 0)}
				<input type="hidden" name="action" value="unknown">
				<button type="submit" style="background:none;border:0;padding:0;cursor:pointer" aria-label="{$LNG.mip_known}"><img src="styles/resource/images/true.png" alt=""></button>
				{else}
				<input type="hidden" name="action" value="known">
				<button type="submit" style="background:none;border:0;padding:0;cursor:pointer" aria-label="{$LNG.mip_known}"><img src="styles/resource/images/false.png" alt=""></button>
				{/if}
			</form>
		</td>
		</tr>{if !$User@last}<tr>{/if}
		{/foreach}
	{/foreach}
</table>
{include file="overall_footer.tpl"}