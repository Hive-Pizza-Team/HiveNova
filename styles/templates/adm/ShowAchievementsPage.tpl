{include file="overall_header.tpl"}
<h1>Achievements</h1>
{if $message}<p class="successBox">{$message}</p>{/if}
<p>
	<a href="?page=achievements&amp;run_backfill=1" class="button" onclick="return confirm('Re-enable backfill cron for all users?');">Run backfill (enable cron)</a>
</p>
<table class="table519">
	<tr>
		<th>Key</th>
		<th>Category</th>
		<th>Trigger</th>
		<th>Params</th>
		<th>Reward</th>
		<th>Tier</th>
		<th>Active</th>
		<th>Hidden</th>
		<th></th>
	</tr>
	{foreach $achievements as $ach}
	<tr>
		<form method="post" action="?page=achievements">
		<td>{$ach.key}</td>
		<td>{$ach.category}</td>
		<td>{$ach.trigger_type}</td>
		<td><input type="text" name="trigger_params" value="{$ach.trigger_params|escape:'html'}" size="24"></td>
		<td>
			<select name="reward_type">
				<option value="none"{if $ach.reward_type == 'none'} selected{/if}>none</option>
				<option value="darkmatter"{if $ach.reward_type == 'darkmatter'} selected{/if}>darkmatter</option>
			</select>
			<input type="number" name="reward_amount" value="{$ach.reward_amount}" size="8">
		</td>
		<td>
			<select name="celebration_tier">
				<option value="normal"{if $ach.celebration_tier == 'normal'} selected{/if}>normal</option>
				<option value="epic"{if $ach.celebration_tier == 'epic'} selected{/if}>epic</option>
				<option value="legendary"{if $ach.celebration_tier == 'legendary'} selected{/if}>legendary</option>
			</select>
		</td>
		<td><input type="checkbox" name="active" value="1"{if $ach.active} checked{/if}></td>
		<td><input type="checkbox" name="hidden" value="1"{if $ach.hidden} checked{/if}></td>
		<td>
			<input type="hidden" name="id" value="{$ach.id}">
			<input type="hidden" name="save" value="1">
			<input type="submit" value="Save">
		</td>
		</form>
	</tr>
	{/foreach}
</table>
{include file="overall_footer.tpl"}
