{include file="overall_header.tpl"}
<form method="get" action="admin.php" style="margin-bottom:8px;">
	<input type="hidden" name="page" value="buildlog">
	<input type="text" name="q" value="{$search|escape}" placeholder="{$LNG.bl_search_placeholder}" style="width:220px;">
	<select name="type">
		<option value="all"{if $type == 'all'} selected{/if}>{$LNG.bl_type_all}</option>
		<option value="buildings"{if $type == 'buildings'} selected{/if}>{$LNG.bl_type_buildings}</option>
		<option value="research"{if $type == 'research'} selected{/if}>{$LNG.bl_type_research}</option>
		<option value="shipyard"{if $type == 'shipyard'} selected{/if}>{$LNG.bl_type_shipyard}</option>
	</select>
	<input type="submit" value="{$LNG.bl_search}">
	{if $search != '' || $type != 'all'}<a href="?page=buildlog">{$LNG.bl_clear}</a>{/if}
</form>
<table width="100%">
<tr>
	<th>{$LNG.bl_type}</th>
	<th>{$LNG.bl_element}</th>
	<th>{$LNG.bl_user}</th>
	<th>{$LNG.bl_planet}</th>
	<th>{$LNG.bl_universe}</th>
	<th>{$LNG.bl_count}</th>
	<th>{$LNG.bl_metal}</th>
	<th>{$LNG.bl_crystal}</th>
	<th>{$LNG.bl_deuterium}</th>
	<th>{$LNG.bl_queued_at}</th>
</tr>
{foreach item=row from=$rows}
<tr>
	<td>
		{if $row.log_type == 'building'}{$LNG.bl_type_buildings}
		{elseif $row.log_type == 'research'}{$LNG.bl_type_research}
		{else}{$LNG.bl_type_shipyard}{/if}
	</td>
	<td>{$row.element_name|escape} <small>({$row.element_id})</small></td>
	<td><a href="?page=accounteditor&amp;edit=personal&amp;id={$row.owner_id}" target="Hauptframe">{$row.username|escape}</a> <small>({$row.owner_id})</small></td>
	<td>{$row.planet_id}</td>
	<td>{$row.universe}</td>
	<td>{if $row.log_type == 'shipyard'}{$row.count}{else}—{/if}</td>
	<td>{$row.metal}</td>
	<td>{$row.crystal}</td>
	<td>{$row.deuterium}</td>
	<td nowrap>{$row.queued_at}</td>
</tr>
{foreachelse}
<tr><td colspan="10">{$LNG.bl_no_results}</td></tr>
{/foreach}
<tr>
	<td colspan="10">{$LNG.bl_total}: {$total}</td>
</tr>
</table>
{if $pages}
<div style="margin-top:6px;">
	{foreach item=p from=$pages}
		{if $p.current}<strong>[{$p.num}]</strong>{else}<a href="{$p.url}">{$p.num}</a>{/if}
		&nbsp;
	{/foreach}
</div>
{/if}
{include file="overall_footer.tpl"}
