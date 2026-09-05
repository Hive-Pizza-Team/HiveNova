{block name="title" prepend}{$LNG.lm_referrals}{/block}
{block name="content"}
<table class="table519">
	<tr>
		<th colspan="5">{$LNG.ref_stats_title}</th>
	</tr>
	{if !$ref_active}
	<tr>
		<td colspan="5"><strong>{$LNG.ref_stats_disabled}</strong></td>
	</tr>
	{/if}
	<tr>
		<td colspan="5">{$LNG.ref_stats_universe}: {$universe}</td>
	</tr>
	<tr>
		<th>{$LNG.ref_stats_total_recruits}</th>
		<th>{$LNG.ref_stats_active_referrers}</th>
		<th>{$LNG.ref_stats_pending_bonus}</th>
		<th>{$LNG.ref_stats_bonus_paid}</th>
		<th>{$LNG.ref_stats_min_points}</th>
	</tr>
	<tr>
		<td>{$summary.total_recruits}</td>
		<td>{$summary.active_referrers}</td>
		<td>{$summary.pending_bonus}</td>
		<td>{$summary.bonus_paid}</td>
		<td>{$ref_minpoints|number}</td>
	</tr>
</table>

<table class="table519">
	<tr>
		<th colspan="7">{$LNG.ref_stats_referrer_heading}</th>
	</tr>
	<tr>
		<th>{$LNG.ref_stats_referrer}</th>
		<th>{$LNG.ref_stats_hive}</th>
		<th>{$LNG.ref_stats_recruits}</th>
		<th>{$LNG.ref_stats_qualified}</th>
		<th>{$LNG.ref_stats_pending_bonus}</th>
		<th>{$LNG.ref_stats_bonus_paid}</th>
		<th>{$LNG.ref_stats_link}</th>
	</tr>
	{foreach item=row from=$referrers}
	<tr>
		<td>{$row.referrer_username|escape} <small>({$row.referrer_id})</small></td>
		<td>{if $row.referrer_hive != ''}@{$row.referrer_hive|escape}{else}—{/if}</td>
		<td>{$row.recruit_count}</td>
		<td>{$row.qualified_count}</td>
		<td>{$row.pending_bonus}</td>
		<td>{$row.bonus_paid}</td>
		<td><code>{$row.referral_link|escape}</code></td>
	</tr>
	{foreachelse}
	<tr><td colspan="7">{$LNG.ref_stats_no_referrers}</td></tr>
	{/foreach}
</table>
{if $pages}
<p>
	{foreach item=p from=$pages}
		{if $p.current}<strong>[{$p.num}]</strong>{else}<a href="{$p.url}">{$p.num}</a>{/if}
		&nbsp;
	{/foreach}
</p>
{/if}

<table class="table519">
	<tr>
		<th colspan="5">{$LNG.ref_stats_recent_heading}</th>
	</tr>
	<tr>
		<th>{$LNG.ref_stats_recruit}</th>
		<th>{$LNG.ref_stats_referrer}</th>
		<th>{$LNG.ref_stats_registered}</th>
		<th>{$LNG.ref_stats_points}</th>
		<th>{$LNG.ref_stats_bonus_status}</th>
	</tr>
	{foreach item=row from=$recentRecruits}
	<tr>
		<td>{$row.recruit_username|escape} <small>({$row.recruit_id})</small></td>
		<td>{$row.referrer_username|escape} <small>({$row.referrer_id})</small></td>
		<td>{$row.register_time_fmt}</td>
		<td>{$row.total_points|number}</td>
		<td>{$row.bonus_status_label|escape}</td>
	</tr>
	{foreachelse}
	<tr><td colspan="5">{$LNG.ref_stats_no_recruits}</td></tr>
	{/foreach}
</table>

<p>{$LNG.ref_stats_note}</p>
{/block}
