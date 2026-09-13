{assign var=navPage value=$smarty.get.page|default:'overview'}
{assign var=navMode value=$smarty.get.mode|default:''}
<ul id="menu">
    <li class="menu-separator menu-section">{$LNG.lm_menu_section_overview}</li>
    <li><a href="game.php?page=overview"{if $navPage == 'overview'} class="active"{/if}>{$LNG.lm_overview}</a></li>
    {if $smarty.const.MODULE_BUILDING|isModuleAvailable}<li><a href="game.php?page=buildings"{if $navPage == 'buildings'} class="active"{/if}>{$LNG.lm_buildings}</a></li>{/if}
    {if $smarty.const.MODULE_SHIPYARD_FLEET|isModuleAvailable}<li><a href="game.php?page=shipyard&amp;mode=fleet"{if $navPage == 'shipyard' && $navMode != 'defense'} class="active"{/if}>{$LNG.lm_shipshard}</a></li>{/if}
    {if $smarty.const.MODULE_SHIPYARD_DEFENSIVE|isModuleAvailable}<li><a href="game.php?page=shipyard&amp;mode=defense"{if $navPage == 'shipyard' && $navMode == 'defense'} class="active"{/if}>{$LNG.lm_defenses}</a></li>{/if}
    {if $smarty.const.MODULE_RESEARCH|isModuleAvailable}<li><a href="game.php?page=research"{if $navPage == 'research'} class="active"{/if}>{$LNG.lm_research}</a></li>{/if}
    {if $smarty.const.MODULE_TRADER|isModuleAvailable}<li><a href="game.php?page=fleetTable"{if $navPage == 'fleetTable' || $navPage == 'fleetStep1' || $navPage == 'fleetStep2' || $navPage == 'fleetStep3'} class="active"{/if}>{$LNG.lm_fleet}</a></li>{/if}
    {if $smarty.const.MODULE_GALAXY|isModuleAvailable}<li><a href="game.php?page=galaxy"{if $navPage == 'galaxy'} class="active"{/if}>{$LNG.lm_galaxy}</a></li>{/if}
    <li><a href="game.php?page=viz"{if $navPage == 'viz'} class="active"{/if}>{$LNG.lm_viz}</a></li>
    <li><a href="game.php?page=eventFirehose"{if $navPage == 'eventFirehose'} class="active"{/if}>{$LNG.ef_title}</a></li>
    {if $smarty.const.MODULE_IMPERIUM|isModuleAvailable}<li><a href="game.php?page=imperium"{if $navPage == 'imperium'} class="active"{/if}>{$LNG.lm_empire}</a></li>{/if}
    {if $smarty.const.MODULE_MESSAGES|isModuleAvailable}<li><a href="game.php?page=messages"{if $navPage == 'messages'} class="active"{/if}>{$LNG.lm_messages}{nocache}{if $new_message > 0}<span id="newmes"> (<span id="newmesnum">{$new_message}</span>)</span>{/if}{/nocache}</a></li>{/if}
    {if $smarty.const.MODULE_TECHTREE|isModuleAvailable}<li><a href="game.php?page=techtree"{if $navPage == 'techtree'} class="active"{/if}>{$LNG.lm_technology}</a></li>{/if}
    {if $smarty.const.MODULE_RESSOURCE_LIST|isModuleAvailable}<li><a href="game.php?page=resources"{if $navPage == 'resources'} class="active"{/if}>{$LNG.lm_resources}</a></li>{/if}
    {if $smarty.const.MODULE_OFFICIER|isModuleAvailable || $smarty.const.MODULE_DMEXTRAS|isModuleAvailable}<li><a href="game.php?page=officier"{if $navPage == 'officier'} class="active"{/if}>{$LNG.lm_officiers}</a></li>{/if}
    {if $smarty.const.MODULE_TRADER|isModuleAvailable}<li><a href="game.php?page=trader"{if $navPage == 'trader'} class="active"{/if}>{$LNG.lm_trader}</a></li>{/if}
    {if $smarty.const.MODULE_FLEET_TRADER|isModuleAvailable}<li><a href="game.php?page=fleetDealer"{if $navPage == 'fleetDealer'} class="active"{/if}>{$LNG.lm_fleettrader}</a></li>{/if}

    <li class="menu-separator menu-section">{$LNG.lm_menu_section_empire}</li>
    {if $smarty.const.MODULE_ALLIANCE|isModuleAvailable}<li><a href="game.php?page=alliance"{if $navPage == 'alliance'} class="active"{/if}>{$LNG.lm_alliance}</a></li>{/if}
    {if !empty($hasBoard)}<li><a href="game.php?page=board" target="forum">{$LNG.lm_forums}</a></li>{/if}
    {if $smarty.const.MODULE_STATISTICS|isModuleAvailable}<li><a href="game.php?page=statistics"{if $navPage == 'statistics'} class="active"{/if}>{$LNG.lm_statistics}</a></li>{/if}
    {if $smarty.const.MODULE_RECORDS|isModuleAvailable}<li><a href="game.php?page=records"{if $navPage == 'records'} class="active"{/if}>{$LNG.lm_records}</a></li>{/if}
    {if $smarty.const.MODULE_ACHIEVEMENTS|isModuleAvailable}<li><a href="game.php?page=achievements"{if $navPage == 'achievements'} class="active"{/if}>{$LNG.lm_achievements}</a></li>{/if}
    {if $smarty.const.MODULE_BATTLEHALL|isModuleAvailable}<li><a href="game.php?page=battleHall"{if $navPage == 'battleHall'} class="active"{/if}>{$LNG.lm_topkb}</a></li>{/if}
    {if $smarty.const.MODULE_SEARCH|isModuleAvailable}<li><a href="game.php?page=search"{if $navPage == 'search'} class="active"{/if}>{$LNG.lm_search}</a></li>{/if}
    <!--{if $smarty.const.MODULE_CHAT|isModuleAvailable}<li><a href="game.php?page=chat">{$LNG.lm_chat}</a></li>{/if}-->
    <li><a href="{$discordUrl}" target="copy">Discord</a></li>
    {if $smarty.const.MODULE_SUPPORT|isModuleAvailable}<li><a href="game.php?page=ticket"{if $navPage == 'ticket'} class="active"{/if}>{$LNG.lm_support}</a></li>{/if}
    <li><a href="game.php?page=questions"{if $navPage == 'questions'} class="active"{/if}>{$LNG.lm_faq}</a></li>
    {if $smarty.const.MODULE_BANLIST|isModuleAvailable}<li><a href="game.php?page=banList"{if $navPage == 'banList'} class="active"{/if}>{$LNG.lm_banned}</a></li>{/if}
    {if false}
    <li><a href="index.php?page=rules" target="rules">{$LNG.lm_rules}</a></li>{/if}
    {if $smarty.const.MODULE_SIMULATOR|isModuleAvailable}<li><a href="game.php?page=battleSimulator"{if $navPage == 'battleSimulator'} class="active"{/if}>{$LNG.lm_battlesim}</a></li>{/if}

    <li class="menu-separator menu-section">{$LNG.lm_menu_section_account}</li>
    {if $smarty.const.MODULE_NOTICE|isModuleAvailable}<li><a href="javascript:OpenPopup('?page=notes', 'notes', 720, 300);"{if $navPage == 'notes'} class="active"{/if}>{$LNG.lm_notes}</a></li>{/if}
    {if $smarty.const.MODULE_BUDDYLIST|isModuleAvailable}<li><a href="game.php?page=buddyList"{if $navPage == 'buddyList'} class="active"{/if}>{$LNG.lm_buddylist}</a></li>{/if}
    <li><a href="game.php?page=settings"{if $navPage == 'settings'} class="active"{/if}>{$LNG.lm_options}</a></li>
    {if $showReferralDashboard}<li><a href="game.php?page=referrals"{if $navPage == 'referrals'} class="active"{/if}>{$LNG.lm_referrals}</a></li>{/if}
    <li><a href="game.php?page=logout"{if $navPage == 'logout'} class="active"{/if}>{$LNG.lm_logout}</a></li>
    {if $showAdminLink}<li><a href="./admin.php" style="color:lime">{$LNG.lm_administration} ({$VERSION})</a></li>{/if}
</ul>
<div id="disclamer" class="no-mobile">
    {if $commit != ''}<a href="https://github.com/Hive-Pizza-Team/HiveNova/tree/{$commit}" target="copy">HiveNova engine {$commitShort}</a>{/if}
</div>
