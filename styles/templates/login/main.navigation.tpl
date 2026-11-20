{if $languages|count > 1}
<header>
	<nav aria-label="{$LNG.registerLanguage|escape}">
		<ul id="language">
		{foreach $languages as $langKey => $langName}
		<li><a href="{if isset($hreflangUrls[$langKey])}{$hreflangUrls[$langKey]}{else}?lang={$langKey}{/if}" title="{$langName}"{if $langKey == $lang} aria-current="true"{/if}><span class="flags {$langKey}">{$langName}</span></a></li>
		{/foreach}
		</ul>
		<label class="language-compact" for="language-compact">
			<span class="language-compact-label">{$LNG.registerLanguage}</span>
			<select id="language-compact" class="language-compact-select" onchange="if (this.value) window.location.href = this.value;">
			{foreach $languages as $langKey => $langName}
				<option value="{if isset($hreflangUrls[$langKey])}{$hreflangUrls[$langKey]|escape}{else}?lang={$langKey|escape}{/if}"{if $langKey == $lang} selected{/if}>{$langName|escape}</option>
			{/foreach}
			</select>
		</label>
	</nav>
</header>
{/if}
