{include file="overall_header.tpl"}
<table width="760px">
	<tr>
		<th>{$LNG.uvs_id}</th>
		<th>{$LNG.uvs_name}</th>
		<th colspan="5" title="{$LNG.uvs_speeds_full}">{$LNG.uvs_speeds}</th>
		<th>{$LNG.uvs_players}</th>
		<th>{$LNG.uvs_planets}</th>
		<th>{$LNG.uvs_inactive}</th>
		<th>{$LNG.uvs_open}</th>
		<th>{$LNG.uvs_actions}</th>
	</tr>
	{foreach $uniList as $uniID => $uniRow}
	<tr style="height:23px;">
		<td>{$uniID}</td>
		<td>{$uniRow.uni_name|number}</td>
		<td>{($uniRow.game_speed / 2500)|number}</td>
		<td>{($uniRow.fleet_speed / 2500)|number}</td>
		<td>{$uniRow.resource_multiplier|number}</td>
		<td>{$uniRow.halt_speed|number}</td>
		<td>{$uniRow.energySpeed|number}</td>
		<td>{$uniRow.users_amount|number}</td>
		<td>{$uniRow.planet|number}</td>
		<td>{$uniRow.inactive|number}</td>
		<td>{if $uniRow.game_disable == 1}<span style="color:lime;">{$LNG.uvs_on}</span>{else}<span style="color:red;">{$LNG.uvs_off}</span>{/if}</td>
		<td>
			{if $uniRow.game_disable == 1}
			<form action="?page=universe&amp;sid={$SID}&amp;reload=t" method="post" style="display:inline;">
				<input type="hidden" name="action" value="closed">
				<input type="hidden" name="uniID" value="{$uniID}">
				<button type="submit" style="background:none;border:0;padding:0;cursor:pointer;"><img src="styles/resource/images/icons/closed.png" alt=""></button>
			</form>
			{else}
			<form action="?page=universe&amp;sid={$SID}&amp;reload=t" method="post" style="display:inline;">
				<input type="hidden" name="action" value="open">
				<input type="hidden" name="uniID" value="{$uniID}">
				<button type="submit" style="background:none;border:0;padding:0;cursor:pointer;"><img src="styles/resource/images/icons/open.png" alt=""></button>
			</form>
			{/if}
			{if $uniID != $smarty.const.ROOT_UNI}
			<form action="?page=universe&amp;sid={$SID}&amp;reload=t" method="post" style="display:inline;" onsubmit="return confirm('{$LNG.uvs_delete}');">
				<input type="hidden" name="action" value="delete">
				<input type="hidden" name="uniID" value="{$uniID}">
				<button type="submit" title="{$LNG.uvs_delete}" style="background:none;border:0;padding:0;cursor:pointer;"><img src="styles/resource/images/false.png" alt=""></button>
			</form>
			{/if}
		</td>
	</tr>
	{/foreach}
	<tr>
		<td colspan="12">
			<form action="?page=universe&amp;sid={$SID}&amp;reload=t" method="post">
				<input type="hidden" name="action" value="create">
				<input type="submit" value="{$LNG.uvs_new}">
			</form>
		</td>
	</tr>
</table>
{include file="overall_footer.tpl"}
