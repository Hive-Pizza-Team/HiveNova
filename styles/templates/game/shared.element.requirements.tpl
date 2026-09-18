{if !empty($requirementRows)}
<p class="element-requirements">
	{$LNG.in_needed}:
	{foreach $requirementRows as $Req}
		{if !$Req@first}<br>{/if}
		{if $Req.href}
			<a href="{$Req.href}" class="requirement-link{if $Req.met} requirement-link--met{else} requirement-link--unmet{/if}"{if !empty($requirementLinkTarget)} target="{$requirementLinkTarget}"{/if}>{$Req.name}</a>
		{else}
			<span class="requirement-link{if $Req.met} requirement-link--met{else} requirement-link--unmet{/if}">{$Req.name}</span>
		{/if}
		<span class="requirement-level">({$LNG.tt_lvl}{$Req.own}/{$Req.count})</span>
	{/foreach}
</p>
{/if}
