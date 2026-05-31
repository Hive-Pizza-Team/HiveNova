{if $languages|count > 1}
<header>
	<nav>
		<ul id="language">
		{foreach $languages as $langKey => $langName}
		<li><a href="?lang={$langKey}" rel="alternate" hreflang="{$langKey}" title="{$langName}"><span class="flags {$langKey}">{$langName}</span></a></li>
		{/foreach}
		</ul>
	</nav>
</header>
{/if}