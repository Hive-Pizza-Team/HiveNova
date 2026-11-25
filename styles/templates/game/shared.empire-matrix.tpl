<table class="empire-matrix">
	{include file="shared.empire-cols.tpl"}
	<thead>
		<tr>
			<th></th>
			<th>{$LNG.lv_total}</th>
			{if $techSection}
			<th colspan="{$planetList.image|count}"></th>
			{else}
			{foreach $planetList.name as $name}
			<th>{$name}</th>
			{/foreach}
			{/if}
		</tr>
	</thead>
	<tbody id="empire-{$sectionId}"></tbody>
</table>
