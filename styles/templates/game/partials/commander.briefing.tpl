<div id="commander" class="commander-briefing">
	<button type="button" class="commander-briefing__toggle" aria-expanded="true">{$LNG.cm_title}</button>
	<div class="commander-briefing__body">
		<p class="commander-briefing__timer">{$LNG.cm_period_remaining}: <span class="timer" data-time="{$commanderBriefing.remaining}">{$commanderBriefing.remaining}</span></p>

		{if $commanderBriefing.directive}
			<div class="commander-briefing__directive">
				<h3>{$LNG[$commanderBriefing.directive.title_key]}</h3>
				<p>{$LNG[$commanderBriefing.directive.desc_key]}</p>
				<p class="commander-briefing__suggestion">{$LNG[$commanderBriefing.directive.suggestion_key]} ({$LNG["fl_stance_{$commanderBriefing.directive.recommended_stance}"]})</p>
				<ul class="commander-briefing__progress">
					{foreach $commanderBriefing.directive.targets as $counter => $need}
						<li>{$counter}: {$commanderBriefing.directive.progress[$counter]|default:0} / {$need}</li>
					{/foreach}
				</ul>
				{if $commanderBriefing.directive.completed && !$commanderBriefing.directive.claimed}
					<button type="button" class="commander-briefing__claim" data-token="{$commanderBriefing.csrf}">{$LNG.cm_claim}</button>
				{elseif $commanderBriefing.directive.claimed}
					<p>{$LNG.cm_claimed}</p>
				{elseif $commanderBriefing.directive.completed}
					<p>{$LNG.cm_completed}</p>
				{/if}
			</div>
		{else}
			<div class="commander-briefing__picker">
				<p>{$LNG.cm_select_directive}</p>
				{foreach $commanderBriefing.options as $option}
					<button type="button" class="commander-briefing__select" data-key="{$option.key}" data-token="{$commanderBriefing.csrf}">
						<strong>{$LNG[$option.title_key]}</strong>
						<span>{$LNG[$option.desc_key]}</span>
					</button>
				{/foreach}
			</div>
		{/if}

		{if $commanderBriefing.expeditions}
			<div class="commander-briefing__expeditions">
				<h4>{$LNG.cm_expeditions}</h4>
				<ul>
					{foreach $commanderBriefing.expeditions as $expe}
						<li>#{$expe.fleet_id} · {$LNG["fl_stance_{$expe.stance}"]}</li>
					{/foreach}
				</ul>
			</div>
		{/if}

		{if $commanderBriefing.pending_choices}
			<div class="commander-briefing__choices">
				<h4>{$LNG.cm_pending_choice}</h4>
				{foreach $commanderBriefing.pending_choices as $pending}
					<div class="commander-briefing__choice" data-fleet="{$pending.fleet_id}">
						{foreach $pending.branches as $branch}
							<button type="button" class="commander-briefing__branch" data-fleet="{$pending.fleet_id}" data-branch="{$branch.key}" data-token="{$commanderBriefing.csrf}">
								{$LNG["cm_branch_{$branch.key}"]}
								({$branch.metal|default:0}/{$branch.crystal|default:0}/{$branch.deuterium|default:0})
							</button>
						{/foreach}
					</div>
				{/foreach}
			</div>
		{/if}
	</div>
</div>
