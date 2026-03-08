{include file="overall_header.tpl"}
<form method="get" action="admin.php" style="margin-bottom:8px;">
	<input type="hidden" name="page" value="transactions">
	<input type="text" name="q" value="{$search|escape}" placeholder="{$LNG.tx_search_placeholder}" style="width:260px;">
	<input type="submit" value="{$LNG.tx_search}">
	{if $search != ''}<a href="?page=transactions">{$LNG.tx_clear}</a>{/if}
</form>
<table width="100%">
<tr>
	<th>{$LNG.tx_id}</th>
	<th>{$LNG.tx_timestamp}</th>
	<th>{$LNG.tx_user}</th>
	<th>{$LNG.tx_amount_spent}</th>
	<th>{$LNG.tx_amount_received}</th>
	<th>{$LNG.tx_item}</th>
	<th>{$LNG.tx_memo}</th>
</tr>
{foreach item=row from=$rows}
<tr>
	<td>{$row.id}</td>
	<td nowrap>{$row.timestamp}</td>
	<td><a href="?page=accounteditor&amp;edit=personal&amp;id={$row.user_id}" target="Hauptframe">{$row.username}</a> <small>({$row.user_id})</small></td>
	<td>{$row.amount_spent|default:'—'}</td>
	<td>{$row.amount_received|default:'—'}</td>
	<td>{$row.item_purchased_id|default:'—'}</td>
	<td>{$row.memo|default:''|escape}</td>
</tr>
{foreachelse}
<tr><td colspan="7">{$LNG.tx_no_results}</td></tr>
{/foreach}
<tr>
	<td colspan="7">{$LNG.tx_total}: {$total}</td>
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
