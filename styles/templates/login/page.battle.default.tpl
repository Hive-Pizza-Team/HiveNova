{block name="title"}{$documentTitle}{/block}
{block name="content"}
{if !$reportFound}
<div class="public-battle public-battle--missing">
	<p class="public-battle-note">{$LNG.sys_raport_not_found|default:'Battle report not found.'}</p>
	<p class="public-battle-back"><a href="{$hallHref|default:'index.php?page=battleHall'}">{$LNG.siteTitleBattleHall|default:'Battle Hall'}</a></p>
</div>
{else}
<article class="public-battle">
	<div class="public-battle-match{if $hall.result == "a"} public-battle-match--attacker{elseif $hall.result == "r"} public-battle-match--defender{/if}">
		<h2 class="public-battle-vs">
			<span class="public-battle-side public-battle-side--attacker">{$attacker|escape}</span>
			<span class="public-battle-vs-sep" aria-hidden="true">VS</span>
			<span class="public-battle-side public-battle-side--defender">{$defender|escape}</span>
		</h2>
		<p class="public-battle-meta"><span>{$battleTime}</span><span><strong>{$unitsCompact}</strong> {$LNG.tkb_units|default:'units'}</span>		</p>
	</div>
	{if $combat}
	<p class="public-battle-note">{$LNG.battlePublicReportSummary|default:'Full combat detail is available in-game after you join.'}</p>
	{/if}
	<p class="public-battle-hook">{$LNG.battleHallCtaHook|default:'Frontier fights. Room to explore. Space to claim.'}</p>
	<div class="public-battle-actions">
		<a class="public-battle-btn public-battle-btn--primary" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a>
	</div>
	<p class="public-battle-back"><a href="{$hallHref}">{$LNG.siteTitleBattleHall|default:'Battle Hall'}</a></p>
</article>
{/if}
{/block}
