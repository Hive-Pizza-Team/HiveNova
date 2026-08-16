{block name="title" prepend}{$LNG.ef_title}{/block}
{block name="content"}
<table class="table519" id="event-firehose">
<tbody>
<tr>
	<th colspan="4">{$LNG.ef_title}</th>
</tr>
</tbody>
<tbody id="event-firehose-list">
{foreach $EventList as $row}
<tr data-event-id="{$row.id}">
	<td>{$row.time}</td>
	<td>{$row.eventType}</td>
	<td>{$row.size}</td>
	<td>{$row.outcome}</td>
</tr>
{foreachelse}
<tr class="event-firehose-empty">
	<td colspan="4">{$LNG.ef_empty}</td>
</tr>
{/foreach}
</tbody>
</table>
{/block}
