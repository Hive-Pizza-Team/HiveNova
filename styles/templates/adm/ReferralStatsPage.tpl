{include file="overall_header.tpl"}
<h2>{$LNG.ref_stats_title}</h2>
{if !$ref_active}
<p><strong>{$LNG.ref_stats_disabled}</strong></p>
{/if}
<p>{$LNG.ref_stats_universe}: {$universe}</p>

<table width="100%" style="margin-bottom:12px;">
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

<h3>{$LNG.ref_stats_referrer_heading}</h3>
<table width="100%">
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
	<td><a href="?page=accounteditor&amp;edit=personal&amp;id={$row.referrer_id}" target="Hauptframe">{$row.referrer_username|escape}</a> <small>({$row.referrer_id})</small></td>
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
<div style="margin-top:6px;">
	{foreach item=p from=$pages}
		{if $p.current}<strong>[{$p.num}]</strong>{else}<a href="{$p.url}">{$p.num}</a>{/if}
		&nbsp;
	{/foreach}
</div>
{/if}

<h3 style="margin-top:16px;">{$LNG.ref_stats_recent_heading}</h3>
<table width="100%">
<tr>
	<th>{$LNG.ref_stats_recruit}</th>
	<th>{$LNG.ref_stats_referrer}</th>
	<th>{$LNG.ref_stats_registered}</th>
	<th>{$LNG.ref_stats_points}</th>
	<th>{$LNG.ref_stats_bonus_status}</th>
</tr>
{foreach item=row from=$recentRecruits}
<tr>
	<td><a href="?page=accounteditor&amp;edit=personal&amp;id={$row.recruit_id}" target="Hauptframe">{$row.recruit_username|escape}</a> <small>({$row.recruit_id})</small></td>
	<td><a href="?page=accounteditor&amp;edit=personal&amp;id={$row.referrer_id}" target="Hauptframe">{$row.referrer_username|escape}</a> <small>({$row.referrer_id})</small></td>
	<td nowrap>{$row.register_time_fmt}</td>
	<td>{$row.total_points|number}</td>
	<td>{$row.bonus_status_label|escape}</td>
</tr>
{foreachelse}
<tr><td colspan="5">{$LNG.ref_stats_no_recruits}</td></tr>
{/foreach}
</table>

<p style="margin-top:12px;font-size:12px;opacity:0.85;">{$LNG.ref_stats_note}</p>
{include file="overall_footer.tpl"}
