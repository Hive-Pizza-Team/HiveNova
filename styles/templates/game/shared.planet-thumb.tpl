{* Static planet thumbnail. preferLite=true uses .jpg (mobile/galaxy); otherwise HQ with .jpg fallback. *}
{if $preferLite}
<img{if $class} class="{$class}"{/if}{if $width} width="{$width}"{/if}{if $height} height="{$height}"{/if}{if $alt} alt="{$alt|escape:'html'}"{/if}{if $style} style="{$style}"{/if}{if $border !== null} border="{$border}"{/if}{if $loading} loading="{$loading}"{/if} src="{$dpath}planeten/{$texture}.jpg">
{else}
<img{if $class} class="{$class}"{/if}{if $width} width="{$width}"{/if}{if $height} height="{$height}"{/if}{if $alt} alt="{$alt|escape:'html'}"{/if}{if $style} style="{$style}"{/if}{if $border !== null} border="{$border}"{/if}{if $loading} loading="{$loading}"{/if} src="{$dpath}planeten/{$texture}_hq.jpg" onerror="this.onerror=null;this.src='{$dpath}planeten/{$texture}.jpg'">
{/if}
