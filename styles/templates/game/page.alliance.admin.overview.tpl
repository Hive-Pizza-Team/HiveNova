{block name="title" prepend}{$LNG.lm_alliance}{/block}
{block name="content"}
<table>
	<tr>
		<th>{$LNG.al_manage_alliance}</th>
	</tr>
	<tr>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;action=permissions">{$LNG.al_manage_ranks}</a></td>
	</tr>
	<tr>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;action=members">{$LNG.al_manage_members}</a></td>
	</tr>
	{if $rights.DIPLOMATIC}
	<tr>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;action=diplomacy">{$LNG.al_manage_diplo}</a></td>
	</tr>
	{/if}
</table>
<form action="game.php?page=alliance&mode=admin" method="post">
<input type="hidden" name="textMode" value="{$textMode}">
<input type="hidden" name="send" value="1">
<table>
	<tr>
		<th colspan="3">{$LNG.al_texts}</th>
	</tr>
	<tr>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;textMode=external">{$LNG.al_outside_text}</a></td>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;textMode=internal">{$LNG.al_inside_text}</a></td>
		<td><a href="game.php?page=alliance&amp;mode=admin&amp;textMode=apply">{$LNG.al_request_text}</a></td>
	</tr>
	<tr>
		<td colspan="3">
			<textarea name="text" id="text" cols="70" rows="15">{$text}</textarea>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<input type="reset" value="{$LNG.al_circular_reset}"> 
			<input type="submit" value="{$LNG.al_save}">
		</td>
	</tr>
</table>
<table>
	<tr>
		<th colspan="2">{$LNG.al_manage_options}</th>
	</tr>
	<tr>
		<td>{$LNG.al_tag}</td>
		<td><input type="text" name="ally_tag" value="{$ally_tag}" size="8" maxlength="8" required></td>
	</tr>
	<tr>
		<td>{$LNG.al_name}</td>
		<td><input type="text" name="ally_name" value="{$ally_name}" size="20" maxlength="30" required></td>
	</tr>
	<tr>
		<td>{$LNG.al_manage_founder_rank}</td>
		<td><input type="text" name="owner_range" value="{$ally_owner_range}" size="30"></td>
	</tr>
	<tr>
		<td>{$LNG.al_web_site}</td>
		<td><input type="text" name="web" value="{$ally_web}" size="70"></td>
	</tr>
	<tr>
		<td>{$LNG.al_manage_image}</td>
		<td><input type="text" name="image" value="{$ally_image}" size="70"></td>
	</tr>
	<tr>
		<td>{$LNG.al_view_stats}</td>
		<td>{html_options name=stats options=$YesNoSelector selected=$ally_stats_data}</td>
	</tr>
	<tr>
		<td>{$LNG.al_view_diplo}</td>
		<td>{html_options name=diplo options=$YesNoSelector selected=$ally_diplo_data}</td>
	</tr>
	<tr>
		<td>{$LNG.al_view_events}</td>
		<td>
			<select name="events[]" size="{$available_events|@count}" multiple>
				{foreach $available_events as $id => $mission}
					{foreach $ally_events as $selected_events}
						{if $selected_events == $id}
							{assign var=selected value=selected}
							{break}
						{else}
							{assign var=selected value=''}
						{/if}
					{/foreach}
					<option value="{$id}" {$selected}>{$mission}</option>
				{/foreach}
			</select>
		</td>
	</tr>
	<tr>
		<th colspan="2">{$LNG.al_manage_requests}</th>
	</tr>
	<tr>
		<td>{$LNG.al_manage_requests}</td>
		<td>{html_options name=request_notallow options=$RequestSelector selected=$ally_request_notallow}</td>
	</tr>
	<tr>
		<td>{$LNG.al_set_max_members}</th>
		<td>{$ally_members} / <input type="number" min="1" name="ally_max_members" value="{$ally_max_members}" size="8"></th>
    </tr>
	<tr>
		<td>{$LNG.al_manage_request_min_points}</td>
		<td><input type="number" min="0" name="request_min_points" value="{$ally_request_min_points}" size="30"></td>
	</tr>
	<tr>
		<td colspan="3">
			<input type="reset" value="{$LNG.al_circular_reset}">
			<input type="submit" value="{$LNG.al_save}">
		</td>
	</tr>
</table>
</form>
{if $AllianceOwner}
<table>
	<tr>
		<th>{$LNG.al_disolve_alliance}</th>
	</tr>
	<tr>
		<td><form action="game.php?page=alliance&amp;mode=admin&amp;action=close" method="post"><input type="submit" value="{$LNG.al_continue}" onclick="return confirm('{$LNG.al_close_ally}');"></form></td>
	</tr>  
</table>
<table>
	<tr>
		<th>{$LNG.al_transfer_alliance}</th>
	</tr>
	<tr>
		<td><form action="game.php?page=alliance&amp;mode=admin&amp;action=transfer" method="post"><input type="submit" value="{$LNG.al_continue}"></form></td>
	</tr>  
</table>
{/if}
{/block}
