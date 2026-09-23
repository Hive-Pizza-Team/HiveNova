{if $maxPage > 1}
	<tr class="msg-pagination-row">
		<td class="right" colspan="4">{$LNG.mg_page}: {if $page != 1}<a href="game.php?page=messages&category={$MessID}{$filterQuery}&side=1">&laquo;</a>&nbsp;{/if}{if $page > 5}..&nbsp;{/if}{for $site=1 to $maxPage}<a href="game.php?page=messages&category={$MessID}{$filterQuery}&side={$site}">{if $site == $page}<b>[{$site}]&nbsp;</b>{elseif ($site > $page-5 && $site < $page+5)}[{$site}]&nbsp;{/if}</a>{/for}{if $page < $maxPage-4}..&nbsp;{/if}{if $page != $maxPage}&nbsp;<a href="game.php?page=messages&category={$MessID}{$filterQuery}&side={$maxPage}">&raquo;</a>{/if}</td>
	</tr>
{/if}
