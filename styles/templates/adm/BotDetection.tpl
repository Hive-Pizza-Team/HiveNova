{include file="overall_header.tpl"}
<table style="width:760px">
	<tr>
		<th>{$LNG.se_name}</th>
		<th>{$LNG.mu_universe}</th>
		<th>Total Actions</th>
		<th>Fleets</th>
		<th>Buildings</th>
		<th>Research</th>
		<th>Longest Break</th>
	</tr>
	{if $suspects}
		{foreach $suspects as $s}
		<tr>
			<td class="left" style="padding:3px;"><a href="admin.php?page=accountdata&id_u={$s.id}">{$s.username}</a></td>
			<td class="center" style="padding:3px;">{$s.universe}</td>
			<td class="center" style="padding:3px;">{$s.total_actions}</td>
			<td class="center" style="padding:3px;">{$s.fleet_count}</td>
			<td class="center" style="padding:3px;">{$s.building_count}</td>
			<td class="center" style="padding:3px;">{$s.research_count}</td>
			<td class="center" style="padding:3px;">{$s.max_gap_human}</td>
		</tr>
		{/foreach}
	{else}
	<tr>
		<td colspan="7" class="center" style="padding:6px;">No suspicious players detected.</td>
	</tr>
	{/if}
</table>
{include file="overall_footer.tpl"}
