{block name="title" prepend}{$LNG.lm_empire}{/block}
{block name="content"}
<table>
	<tbody>
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_imperium_title}</th>
		</tr>
		<tr>
			<td style="width:100px">{$LNG.lv_planet}</td>
			<td style="width:100px;font-size: 50px;">&Sigma;</td>
			{foreach $planetList.image as $planetID => $image}
			<td style="width:100px"><a href="game.php?page=overview&amp;cp={$planetID}">{include file="shared.planet-thumb.tpl" texture=$image dpath=$dpath width=48 height=48 border=0 preferLite=true loading="lazy"}</a></td>
			{/foreach}
		</tr>
		<tr>
			<td>{$LNG.lv_name}</td>
			<td>{$LNG.lv_total}</td>
			{foreach $planetList.name as $name}
				<td>{$name}</td>
			{/foreach}
		</tr>
		<tr>
			<td>{$LNG.lv_coords}</td>
			<td>-</td>
			{foreach $planetList.coords as $coords}
				<td><a href="game.php?page=galaxy&amp;galaxy={$coords.galaxy}&amp;system={$coords.system}">[{$coords.galaxy}:{$coords.system}:{$coords.planet}]</a></td>
			{/foreach}
		</tr>
		<tr>
			<td>{$LNG.lv_fields}</td>
			<td>-</td>
			{foreach $planetList.field as $field}
				<td>{$field.current} / {$field.max}</td>
			{/foreach}
		</tr>
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_resources}</th>
		</tr>
		{foreach $planetList.resource as $elementID => $resourceArray}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID});'>{$LNG.tech.$elementID}</a></td>
			<td>{$resourceArray|array_sum|number} {if $elementID|in_array:[901,902,903]}<span style="color:lime">{$planetList.resourcePerHour[$elementID]|array_sum|number}/h</span>{/if}</td>
			{foreach $resourceArray as $planetID => $resource}
				<td>{$resource|number} {if $elementID|in_array:[901,902,903] && $planetList.planet_type[$planetID] == 1}<span style="color:lime">{$planetList.resourcePerHour[$elementID][$planetID]|number}/h</span>{/if}</td>
			{/foreach}
		</tr>
		{/foreach}
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_buildings}</th>
		</tr>
		{foreach $planetList.build as $elementID => $buildArray}
		{if ($buildArray|array_sum) > 0}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID})'>{$LNG.tech.$elementID}</a></td>
			<td>{$buildArray|array_sum|number}</td>
			{foreach $buildArray as $planetID => $build}
				<td>{$build|number}</td>
			{/foreach}
		</tr>
		{/if}
		{/foreach}
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_ships}</th>
		</tr>
		{foreach $planetList.fleet as $elementID => $fleetArray}
		{if ($fleetArray|array_sum) > 0}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID})'>{$LNG.tech.$elementID}</a></td>
			<td>{$fleetArray|array_sum|number}</td>
			{foreach $fleetArray as $planetID => $fleet}
				<td>{$fleet|number}</td>
			{/foreach}
		</tr>
		{/if}
		{/foreach}
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_defenses}</th>
		</tr>
		{foreach $planetList.defense as $elementID => $fleetArray}
		{if ($fleetArray|array_sum) > 0}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID})'>{$LNG.tech.$elementID}</a></td>
			<td>{$fleetArray|array_sum|number}</td>
			{foreach $fleetArray as $planetID => $fleet}
				<td>{$fleet|number}</td>
			{/foreach}
		</tr>
		{/if}
		{/foreach}
		<tr>
		    <th colspan="{$colspan}">{$LNG.tech.500}</th>
		</tr>
		{foreach $planetList.missiles as $elementID => $fleetArray}
		{if ($fleetArray|array_sum) > 0}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID})'>{$LNG.tech.$elementID}</a></td>
			<td>{$fleetArray|array_sum|number}</td>
			{foreach $fleetArray as $planetID => $fleet}
				<td>{$fleet|number}</td>
			{/foreach}
		</tr>
		{/if}
		{/foreach}
		<tr>
			<th colspan="{$colspan}">{$LNG.lv_technology}</th>
		</tr>
		{foreach $planetList.tech as $elementID => $tech}
		{if $tech > 0}
		<tr>
			<td><a href='#' onclick='return Dialog.info({$elementID})'>{$LNG.tech.$elementID}</a></td>
			<td>{$tech|number}</td>
			<td colspan="{$colspan-2}">{$tech|number}</td>
		</tr>
		{/if}
		{/foreach}
	</tbody>
</table>
{/block}
