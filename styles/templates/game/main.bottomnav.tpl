{if !isset($navPage)}{assign var=navPage value=$smarty.get.page|default:'overview'}{/if}
<nav id="bottom-nav" aria-label="{$LNG.lm_overview}">
	<a href="game.php?page=overview" class="{if $navPage == 'overview'}active{/if}">
		<i class="fas fa-home" aria-hidden="true"></i>
		<span>{$LNG.lm_overview}</span>
	</a>
	{if $smarty.const.MODULE_BUILDING|isModuleAvailable}
	<a href="game.php?page=buildings" class="{if $navPage == 'buildings'}active{/if}">
		<i class="fas fa-industry" aria-hidden="true"></i>
		<span>{$LNG.lm_buildings}</span>
	</a>
	{/if}
	{if $smarty.const.MODULE_TECHTREE|isModuleAvailable}
	<a href="game.php?page=techtree" class="{if $navPage == 'techtree'}active{/if}">
		<i class="fas fa-sitemap" aria-hidden="true"></i>
		<span>{$LNG.lm_technology}</span>
	</a>
	{/if}
	{if $smarty.const.MODULE_TRADER|isModuleAvailable}
	<a href="game.php?page=fleetTable" class="{if $navPage == 'fleetTable' || $navPage == 'fleetStep1' || $navPage == 'fleetStep2' || $navPage == 'fleetStep3'}active{/if}">
		<i class="fas fa-rocket" aria-hidden="true"></i>
		<span>{$LNG.lm_fleet}</span>
	</a>
	{/if}
	{if $smarty.const.MODULE_GALAXY|isModuleAvailable}
	<a href="game.php?page=galaxy" class="{if $navPage == 'galaxy'}active{/if}">
		<i class="fas fa-star" aria-hidden="true"></i>
		<span>{$LNG.lm_galaxy}</span>
	</a>
	{/if}
	<label for="toggle-menu" class="bottom-nav-more">
		<i class="fas fa-ellipsis-h" aria-hidden="true"></i>
		<span>Menu</span>
	</label>
</nav>
