{* Compact nameplate glyphs. Cap is 4 unless the caller passes prestigeCap. *}
{if !isset($prestigeCap)}{assign var="prestigeCap" value=4}{/if}
{if $badges|@count > 0}
<span class="prestige-badges">
{assign var="prestigeShown" value=0}
{foreach $badges as $badge}
{if $prestigeShown < $prestigeCap}
<span class="prestige-badge prestige-badge--{$badge.id|escape:'html'} tooltip" role="img" aria-label="{$badge.label|escape:'html'}" title="{$badge.tooltip|escape:'html'}" data-tooltip-content="{$badge.tooltip|escape:'html'}">{if $badge.kind == 'pizza_stake'}<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M8 2.2 L13.6 12.4 A6.2 6.2 0 0 1 2.4 12.4 Z" fill="currentColor"/><circle cx="7.1" cy="8.2" r="0.7" fill="#1a1a1a"/><circle cx="9.2" cy="10.1" r="0.7" fill="#1a1a1a"/><circle cx="8.4" cy="6.4" r="0.55" fill="#1a1a1a"/></svg>{elseif $badge.kind == 'season'}<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="M5 1.6 L7.1 6.4 H2.9 Z" fill="currentColor" opacity="0.85"/><path d="M11 1.6 L13.1 6.4 H8.9 Z" fill="currentColor" opacity="0.85"/><circle cx="8" cy="10.2" r="4" fill="currentColor"/><circle cx="8" cy="10.2" r="1.8" fill="#1a1a1a" opacity="0.35"/></svg>{else}<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><ellipse cx="3.1" cy="8" rx="2.1" ry="1.25" fill="currentColor" opacity="0.45"/><ellipse cx="12.9" cy="8" rx="2.1" ry="1.25" fill="currentColor" opacity="0.45"/><ellipse cx="8" cy="8.3" rx="3.1" ry="4.1" fill="currentColor"/><rect x="5.2" y="6.15" width="5.6" height="1.05" fill="#1a1a1a"/><rect x="5.4" y="8.65" width="5.2" height="1.05" fill="#1a1a1a"/><circle cx="8" cy="3.35" r="1.45" fill="currentColor"/></svg>{/if}</span>
{assign var="prestigeShown" value=$prestigeShown+1}
{/if}
{/foreach}
</span>
{/if}
