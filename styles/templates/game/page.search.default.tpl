{block name="title" prepend}{$LNG.lm_search}{/block}
{block name="content"}
<table>
	<tr>
		<th>{$LNG.sh_search_in_the_universe} <span id="loading" style="display:none;">{$LNG.sh_loading}</span></th>
	</tr>
	<tr>
		<td>
			{html_options options=$modeSelector name="type" id="type"}
			<input type="text" name="searchtext" id="searchtext">
			<input type="button" id="searchbutton" value="{$LNG.sh_search}">
			<p id="searchEmpty" class="text-danger" hidden>{$LNG.sh_enter_search_term}</p>
		</td>
	</tr>
</table>
{/block}