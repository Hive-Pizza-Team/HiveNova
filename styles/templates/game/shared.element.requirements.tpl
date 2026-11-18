{if !empty($requirementRows)}
<div class="element-requirements"><span class="element-requirements__label">{$LNG.in_needed}:</span>
	<span class="element-requirements__list">
	{foreach $requirementRows as $Req}
		<span class="element-requirement">
		{if $Req.href}
			<a href="{$Req.href}" class="requirement-link{if $Req.met} requirement-link--met{else} requirement-link--unmet{/if}"{if !empty($requirementLinkTarget)} target="{$requirementLinkTarget}"{/if}>{$Req.name}</a>
		{else}
			<span class="requirement-link{if $Req.met} requirement-link--met{else} requirement-link--unmet{/if}">{$Req.name}</span>
		{/if}
		<span class="requirement-level">({$LNG.tt_lvl}{$Req.own}/{$Req.count})</span>
		</span>
	{/foreach}
	</span>
</div>
{/if}
