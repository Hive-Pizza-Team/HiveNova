<nav id="bottom-nav" aria-label="{$LNG.lm_overview}">
	<a href="game.php?page=overview" class="{if $smarty.get.page|default:'overview' == 'overview'}active{/if}">
		<i class="fas fa-home" aria-hidden="true"></i>
		<span>{$LNG.lm_overview}</span>
	</a>
	{if $smarty.const.MODULE_BUILDING|isModuleAvailable}
	<a href="game.php?page=buildings" class="{if $smarty.get.page|default:'' == 'buildings'}active{/if}">
		<i class="fas fa-industry" aria-hidden="true"></i>
		<span>{$LNG.lm_buildings}</span>
	</a>
	{/if}
	{if $smarty.const.MODULE_TRADER|isModuleAvailable}
	<a href="game.php?page=fleetTable" class="{if $smarty.get.page|default:'' == 'fleetTable' || $smarty.get.page|default:'' == 'fleetStep1' || $smarty.get.page|default:'' == 'fleetStep2' || $smarty.get.page|default:'' == 'fleetStep3'}active{/if}">
		<i class="fas fa-rocket" aria-hidden="true"></i>
		<span>{$LNG.lm_fleet}</span>
	</a>
	{/if}
	{if $smarty.const.MODULE_GALAXY|isModuleAvailable}
	<a href="game.php?page=galaxy" class="{if $smarty.get.page|default:'' == 'galaxy'}active{/if}">
		<i class="fas fa-star" aria-hidden="true"></i>
		<span>{$LNG.lm_galaxy}</span>
	</a>
	{/if}
	<label for="toggle-menu" class="bottom-nav-more">
		<i class="fas fa-ellipsis-h" aria-hidden="true"></i>
		<span>Menu</span>
	</label>
</nav>
