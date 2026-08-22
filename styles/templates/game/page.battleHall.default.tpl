{block name="title" prepend}{$LNG.lm_topkb}{/block}
{block name="content"}
<table class="table569">
<tbody>
<tr>
    <th colspan="4">
		<a href="game.php?page=battleHall">{$LNG.tkb_top}</a>
		&nbsp;|&nbsp;
		<a href="game.php?page=battleHall&amp;tab=feats">{$LNG.feat_tab}</a>
	</th>
</tr>
{if $battleHallTab == 'feats'}
<tr>
    <td colspan="4">{$LNG.feat_tab_intro}</td>
</tr>
<tr>
    <td>{$LNG.feat_col_name}</td>
	<td>{$LNG.feat_col_winner}</td>
    <td>{$LNG.feat_col_when}</td>
    <td>{$LNG.feat_col_status}</td>
</tr>
{foreach $FeatList as $row}
    <tr>
        <td>{$row.name}</td>
        <td>{if $row.status == 'claimed'}{$row.username}{else}—{/if}</td>
        <td>{if $row.status == 'claimed'}{$row.date}{else}—{/if}</td>
        <td>
			{if $row.status == 'claimed'}{$LNG.feat_status_claimed}
			{elseif $row.status == 'open'}{$LNG.feat_status_open}
			{else}{$LNG.feat_status_unknown}{/if}
		</td>
    </tr>
{/foreach}
{else}
<tr>
    <td colspan="4">{$LNG.tkb_gratz}</td>
</tr>
<tr>
    <td>{$LNG.tkb_platz}</td>
	<td>{$LNG.tkb_owners}</td>
    <td>{$LNG.tkb_datum}</td>
	<td>{$LNG.tkb_units}</td>
</tr>
{foreach $TopKBList as $row}
    <tr>
        <td>{$row@iteration}</td>
        <td><a href="game.php?page=raport&amp;mode=battlehall&amp;raport={$row.rid}" target="_blank">
        {if $row.result == "a"}
        <span style="color:#00FF00">{$row.attacker}</span> VS <span style="color:#FF0000">{$row.defender}</span>
        {elseif $row.result == "r"}
        <span style="color:#FF0000">{$row.attacker}</span> VS <span style="color:#00FF00">{$row.defender}</span>
        {else}
        {$row.attacker} VS {$row.defender}
        {/if}
        </a></td>
        <td>{$row.date}</td>
        <td>{$row.units|number}</td>
    </tr>
{/foreach}
<tr>
<td colspan="4">{$LNG.tkb_legende}<span style="color:#00FF00">{$LNG.tkb_gewinner}</span><span style="color:#FF0000">{$LNG.tkb_verlierer}</span></td></tr>
{/if}
</tbody>
</table>
{/block}
