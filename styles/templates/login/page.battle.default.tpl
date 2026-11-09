{block name="title"}{$documentTitle}{/block}
{block name="content"}
{if !$reportFound}
<p>{$LNG.sys_raport_not_found|default:'Battle report not found.'}</p>
<p><a href="index.php?page=battleHall">{$LNG.siteTitleBattleHall|default:'Battle Hall'}</a></p>
{else}
<section class="battlehall-cta">
	<p class="battlehall-cta-hook">{$LNG.battleHallCtaHook|default:'Frontier fights. Room to explore. Space to claim.'}</p>
	<p><a class="battlehall-cta-btn" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a></p>
</section>
<article class="public-battle-report">
	<h1>
	{if $hall.result == "a"}
	<span style="color:#00FF00">{$attacker|escape}</span> VS <span style="color:#FF0000">{$defender|escape}</span>
	{elseif $hall.result == "r"}
	<span style="color:#FF0000">{$attacker|escape}</span> VS <span style="color:#00FF00">{$defender|escape}</span>
	{else}
	{$attacker|escape} VS {$defender|escape}
	{/if}
	</h1>
	<p>{$battleTime} · {$unitsCompact} {$LNG.tkb_units|default:'units'}</p>
	{if $combat}
	<p class="public-battle-report-note">{$LNG.battlePublicReportSummary|default:'Full combat detail is available in-game after you join.'}</p>
	{/if}
</article>
<p><a href="{$hallHref}">{$LNG.siteTitleBattleHall|default:'Battle Hall'}</a></p>
<p class="battlehall-cta-footer"><a class="battlehall-cta-btn" href="{$ctaHref}">{$LNG.battleHallCtaButton|default:'Play Moon free'}</a></p>
{/if}
{/block}
