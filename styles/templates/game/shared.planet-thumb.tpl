{* Static planet thumbnail. preferLite=true uses the standard still; otherwise HQ with lite fallback. *}
{if $preferLite}
<img{if $class} class="{$class}"{/if}{if $width} width="{$width}"{/if}{if $height} height="{$height}"{/if}{if $alt} alt="{$alt|escape:'html'}"{/if}{if $style} style="{$style}"{/if}{if $border !== null} border="{$border}"{/if}{if $loading} loading="{$loading}"{/if} src="{planet_image texture=$texture lite=true}">
{else}
<img{if $class} class="{$class}"{/if}{if $width} width="{$width}"{/if}{if $height} height="{$height}"{/if}{if $alt} alt="{$alt|escape:'html'}"{/if}{if $style} style="{$style}"{/if}{if $border !== null} border="{$border}"{/if}{if $loading} loading="{$loading}"{/if} src="{planet_image texture=$texture}" onerror="this.onerror=null;this.src='{planet_image texture=$texture lite=true}'">
{/if}
