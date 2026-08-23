<div id="commander" class="infos commander-briefing{if $commanderBriefing.directive} commander-briefing--active{/if}">
	<div class="commander-briefing__header">
		<button type="button" class="commander-briefing__toggle" aria-expanded="true" aria-controls="commander-briefing-body">
			<span class="commander-briefing__title">{$LNG.cm_title}</span>
		</button>
		<p class="commander-briefing__timer">
			<span class="commander-briefing__timer-label">{$LNG.cm_period_remaining}</span>
			<span class="timer" data-time="{$commanderBriefing.remaining}">{$commanderBriefing.remaining}</span>
		</p>
	</div>
	<div id="commander-briefing-body" class="commander-briefing__body">
		{if $commanderBriefing.directive}
			<div class="commander-briefing__directive" data-key="{$commanderBriefing.directive.key}">
				<div class="commander-briefing__directive-head">
					<h3 class="commander-briefing__directive-title">{$LNG[$commanderBriefing.directive.title_key]}</h3>
					{if $commanderBriefing.directive.claimed}
						<span class="commander-briefing__badge commander-briefing__badge--done">{$LNG.cm_claimed}</span>
					{elseif $commanderBriefing.directive.completed}
						<span class="commander-briefing__badge commander-briefing__badge--ready">{$LNG.cm_completed}</span>
					{else}
						<span class="commander-briefing__badge">{$LNG.cm_locked}</span>
					{/if}
				</div>
				<p class="commander-briefing__directive-desc">{$LNG[$commanderBriefing.directive.desc_key]}</p>
				{if $commanderBriefing.directive.reward}
					<p class="commander-briefing__reward">{$LNG.tech.901}: {$commanderBriefing.directive.reward.metal} · {$LNG.tech.902}: {$commanderBriefing.directive.reward.crystal} · {$LNG.tech.903}: {$commanderBriefing.directive.reward.deuterium}</p>
				{/if}
				<p class="commander-briefing__suggestion">{$LNG.cm_suggestion}: {$LNG[$commanderBriefing.directive.suggestion_key]} ({$LNG["cm_stance_{$commanderBriefing.directive.recommended_stance}"]})</p>
				<ul class="commander-briefing__progress">
					{foreach $commanderBriefing.directive.targets as $counter => $need}
						{assign var="have" value=$commanderBriefing.directive.progress[$counter]|default:0}
						{if $need > 0}
							{$_v=$have/$need*100}
							{if $_v > 100}{assign var="pct" value=100}{else}{assign var="pct" value=$_v|round}{/if}
						{else}
							{assign var="pct" value=0}
						{/if}
						<li>
							<div class="commander-briefing__progress-meta">
								<span>{$LNG["cm_counter_{$counter}"]|default:$counter}</span>
								<span>{$have} / {$need}</span>
							</div>
							<div class="commander-briefing__bar" role="progressbar" aria-valuemin="0" aria-valuenow="{$have}" aria-valuemax="{$need}">
								<span style="width: {$pct}%"></span>
							</div>
						</li>
					{/foreach}
				</ul>
				{if $commanderBriefing.directive.completed && !$commanderBriefing.directive.claimed}
					<button type="button" class="commander-briefing__claim" data-token="{$commanderBriefing.csrf}">{$LNG.cm_claim}</button>
				{/if}
			</div>
		{else}
			<div class="commander-briefing__picker">
				<p class="commander-briefing__prompt">{$LNG.cm_select_directive}</p>
				<div class="commander-briefing__options">
					{foreach $commanderBriefing.options as $option}
						<button type="button" class="commander-briefing__select" data-key="{$option.key}" data-token="{$commanderBriefing.csrf}">
							<strong class="commander-briefing__select-title">{$LNG[$option.title_key]}</strong>
							<span class="commander-briefing__select-desc">{$LNG[$option.desc_key]}</span>
						</button>
					{/foreach}
				</div>
			</div>
		{/if}

		{if $commanderBriefing.expeditions}
			<div class="commander-briefing__expeditions">
				<h4 class="commander-briefing__section-title">{$LNG.cm_expeditions}</h4>
				<ul>
					{foreach $commanderBriefing.expeditions as $expe}
						<li><span class="commander-briefing__fleet">#{$expe.fleet_id}</span> {$LNG.cm_stance}: {$LNG["cm_stance_{$expe.stance}"]}</li>
					{/foreach}
				</ul>
			</div>
		{/if}

		{if $commanderBriefing.pending_choices}
			<div class="commander-briefing__choices">
				<h4 class="commander-briefing__section-title">{$LNG.cm_pending_choice}</h4>
				{foreach $commanderBriefing.pending_choices as $pending}
					<div class="commander-briefing__choice" data-fleet="{$pending.fleet_id}">
						{foreach $pending.branches as $branch}
							<button type="button" class="commander-briefing__branch" data-fleet="{$pending.fleet_id}" data-branch="{$branch.key}" data-token="{$commanderBriefing.csrf}">
								<span class="commander-briefing__branch-title">{$LNG["cm_branch_{$branch.key}"]}</span>
								<span class="commander-briefing__branch-loot">{$branch.metal|default:0} / {$branch.crystal|default:0} / {$branch.deuterium|default:0}</span>
							</button>
						{/foreach}
					</div>
				{/foreach}
			</div>
		{/if}
	</div>
</div>
