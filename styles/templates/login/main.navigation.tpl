{if $languages|count > 1}
<header>
	<nav>
		<ul id="language">
		{foreach $languages as $langKey => $langName}
		<li><a href="{if isset($languageUrls[$langKey])}{$languageUrls[$langKey]}{else}?lang={$langKey}{/if}" title="{$langName}"{if $langKey == $lang} aria-current="true"{/if}><span class="flags {$langKey}">{$langName}</span></a></li>
		{/foreach}
		</ul>
	</nav>
</header>
{/if}
