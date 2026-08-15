{block name="title"}{$LNG.ach_page_title} - {$uni_name} - {$game_name}{/block}
{block name="content"}
<div id="achievementsPage" class="achievements-page">
	<h1>{$LNG.ach_page_title}</h1>
	<div class="achievements-summary">
		<span>{$LNG.ach_unlocked_count}: <strong>{$unlockedCount} / {$totalCount}</strong></span>
		<span>{$LNG.ach_points_total}: <strong>{$pointsTotal|number}</strong></span>
	</div>

	{foreach $achievementsByCategory as $category => $items}
	<section class="achievements-category">
		<h2>{$categoryLabels.$category|default:$category}</h2>
		<table class="table519">
			<tr>
				<th>{$LNG.ach_page_title}</th>
				<th>{$LNG.ach_progress}</th>
				<th>{$LNG.ach_reward}</th>
				<th></th>
			</tr>
			{foreach $items as $ach}
			<tr class="achievements-row{if $ach.unlocked} achievements-row--unlocked{/if}{if $ach.hidden} achievements-row--hidden{/if}">
				<td>
					<strong>{$ach.name}</strong>
					{if !$ach.hidden}<br><span class="achievements-desc">{$ach.description}</span>{/if}
				</td>
				<td>
					{if $ach.unlocked}
						{$LNG.ach_unlocked}
					{else}
						{$ach.progress|number} / {$ach.threshold|number}
					{/if}
				</td>
				<td>
					{if $ach.reward_type != 'none' && $ach.reward_amount > 0}
						{$ach.reward_amount|number} {$LNG.tech.921}
					{else}
						&mdash;
					{/if}
				</td>
				<td>{if $ach.unlocked}<span class="achievements-badge">&#10003;</span>{/if}</td>
			</tr>
			{/foreach}
		</table>
	</section>
	{/foreach}
</div>
{/block}
