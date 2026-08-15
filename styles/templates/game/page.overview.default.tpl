
{block name="title" prepend}{$LNG.lm_overview}{/block}
{block name="script" append}
    <script>

              $(function(){
            $("#chkbtn").on('click',function() {
                $(this).hide();
                $("#hidden-div").show();
            }); 
        });

 $(function(){
            $("#chkbtn2").on('click',function() {
                $("#chkbtn").show();
                $("#hidden-div").hide();
            }); 
        });
 $(function(){
            $("#chkbtn1").on('click',function() {
                $(this).hide();
                $("#hidden-div2").hide()
$("#tn3").show();
            }); 
        });
 $(function(){
            $("#chkbtn3").on('click',function() {
                $("#chkbtn1").show();
                $("#hidden-div2").show();
$("#tn3").hide();

            }); 
        });
    </script>

{/block}
{block name="content"}

   
<style>
	.hidden-div {
        display:none;	}
</style>	

<div>
{if $messages}
<div class="overview-mobile-shortcuts mobile">
	<a href="game.php?page=messages">{$LNG.lm_messages}</a>
</div>
{/if}
{if $buildInfo.buildings || $buildInfo.tech || $buildInfo.fleet}
<div class="overview-command-timers">
	{if $buildInfo.buildings}<div><a href="game.php?page=buildings">{$LNG.lm_buildings}:</a> {$LNG.tech[$buildInfo.buildings['id']]} <span class="timer" data-time="{$buildInfo.buildings['timeleft']}">{$buildInfo.buildings['starttime']}</span></div>{/if}
	{if $buildInfo.tech}<div><a href="game.php?page=research">{$LNG.lm_research}:</a> {$LNG.tech[$buildInfo.tech['id']]} <span class="timer" data-time="{$buildInfo.tech['timeleft']}">{$buildInfo.tech['starttime']}</span></div>{/if}
	{if $buildInfo.fleet}<div><a href="game.php?page=shipyard&amp;mode=fleet">{$LNG.lm_shipshard}:</a> {$LNG.tech[$buildInfo.fleet['id']]} <span class="timer" data-time="{$buildInfo.fleet['timeleft']}">{$buildInfo.fleet['starttime']}</span></div>{/if}
</div>
{/if}
	{if $messages}
	<div class="message"><a href="?page=messages">{$messages}</a></div>
	
	{/if}
<div class="infos">
<div class="planeto"><a href="#" onclick="return Dialog.PlanetAction();" title="{$LNG.ov_planetmenu}">{$LNG["type_planet_{$planet_type}"]} {$planetname}</a> ({$username})</div>

	{$LNG.ov_server_time}:
		<span class="servertime">{$servertime}</span>
	
	
</br>

{$LNG.ov_admins_online}:&nbsp;{foreach $AdminsOnline as $ID => $Name}{if !$Name@first}&nbsp;&bull;&nbsp;{/if}<a href="#" onclick="return Dialog.PM({$ID})"><a style="color:lime">{$Name}</a>{foreachelse}{/foreach} </br>
{$LNG.ov_online}

			<a style="color:lime">{$usersOnline}</a> {$LNG.ov_players}
		
			<a style="color:lime">{$fleetsOnline}</a> {$LNG.ov_moving_fleets}
<br>{$LNG.ov_points} {$rankInfo|default:''}
{if $is_news}

                <div class="hidden-div" id="hidden-div">

         
                 {$LNG.ov_news}:&nbsp;{$news|default:''} </br><span style="display:block; margin-top:10px;"><button id="chkbtn2">Hide News</button></span>
 
                </div>
                <span style="display:block; margin-top:10px;"><button id="chkbtn">Check News</button></span>
{/if}

    </div>
		
<div class="infos" >
<div class="planeto">
		{$LNG.ov_events} <button id="chkbtn1">Hide fleets</button> </div>

	<ul style="list-style-type:none;" id="hidden-div2">
	{foreach $fleets as $index => $fleet}


		<li style=" padding: 3px; "><span id="fleettime_{$index}" class="fleets" data-fleet-end-time="{$fleet.returntime|default:''}" data-fleet-time="{$fleet.resttime|default:''}">{$fleet.resttime|default:0|pretty_fly_time}
		</span> <td id="fleettime_{$index}">{$fleet.text|default:''}</td></li>
	
	{/foreach}
</ul>
 &nbsp;<span style="display:none" id="tn3"><button id="chkbtn3">Show fleets</button></span>
	</div>
<br>
<div class="infos overview-planet-panel">
{if $Moon}<div class="moon overview-planet-moon"><a href="game.php?page=overview&amp;cp={$Moon.id}&amp;re=0" title="{$Moon.name}">{include file="shared.planet-thumb.tpl" texture='mond' dpath=$dpath width=100 height=100 alt="{$Moon.name} ({$LNG.fcm_moon})"}</a><br>{$Moon.name} ({$LNG.fcm_moon})
</div>
{/if}
	<div class="overview-planet-main">
		{if $planetVizEnabled}
		<div class="planeth overview-planet-visual overview-planet-visual--loading">
			<img class="overview-planet-fallback" data-src="{$dpath}planeten/{$planetimage}.jpg" data-src-hq="{$dpath}planeten/{$planetimage}_hq.jpg" data-alt="{$planetname|escape:'html'}" alt="" aria-hidden="true">
			<canvas id="overview-planet-canvas" class="overview-planet-canvas" width="280" height="280" aria-label="{$planetname|escape:'html'} — animated planet view"></canvas>
		</div>
		{else}
		<div class="planeth overview-planet-visual overview-planet-visual--fallback">
			<img class="overview-planet-fallback" src="{$dpath}planeten/{$planetimage}_hq.jpg" onerror="this.onerror=null;this.src='{$dpath}planeten/{$planetimage}.jpg'" alt="{$planetname}">
		</div>
		{/if}
		<div class="planeth overview-planet-details">
			<div class="overview-planet-header">
				<span class="overview-planet-name">{$planetname}</span>
				<div class="overview-planet-actions">
					<button type="button" class="overview-planet-action-btn overview-rename-btn" onclick="return Dialog.PlanetAction();" title="{$LNG.ov_planet_rename}">{$LNG.ov_rename_short}</button>
					<button type="button" class="overview-planet-action-btn overview-delete-btn" onclick="return Dialog.PlanetAction('delete');" title="{$LNG.ov_delete_planet}">{$LNG.bu_delete|lower}</button>
				</div>
			</div>

			<div class="no-mobile overview-planet-queue">
			{if $buildInfo.buildings}<a href="game.php?page=buildings">{$LNG.lm_buildings}: </a>{$LNG.tech[$buildInfo.buildings['id']]} ({$buildInfo.buildings['level']})<br><div class="timer" data-time="{$buildInfo.buildings['timeleft']}">{$buildInfo.buildings['starttime']}</div>{else}<a href="game.php?page=buildings">{$LNG.lm_buildings}: {$LNG.ov_free}</a><br>{/if}
			{if $buildInfo.tech}<a href="game.php?page=research">{$LNG.lm_research}: </a>{$LNG.tech[$buildInfo.tech['id']]} ({$buildInfo.tech['level']})<br><div class="timer" data-time="{$buildInfo.tech['timeleft']}">{$buildInfo.tech['starttime']}</div>{else}<a href="game.php?page=research">{$LNG.lm_research}: {$LNG.ov_free}</a><br>{/if}
			{if $buildInfo.fleet}<a href="game.php?page=shipyard&amp;mode=fleet">{$LNG.lm_shipshard}: </a>{$LNG.tech[$buildInfo.fleet['id']]} ({$buildInfo.fleet['level']})<br><div class="timer" data-time="{$buildInfo.fleet['timeleft']}">{$buildInfo.fleet['starttime']}</div>{else}<a href="game.php?page=shipyard&amp;mode=fleet">{$LNG.lm_shipshard}: {$LNG.ov_free}</a><br>{/if}
			</div>

			<dl class="overview-planet-stats">
				<div class="overview-planet-stat">
					<dt>{$LNG.ov_diameter}</dt>
					<dd>{$planet_diameter} {$LNG.ov_distance_unit}</dd>
				</div>
				<div class="overview-planet-stat">
					<dt>{$LNG.ov_fields}</dt>
					<dd><a title="{$LNG.ov_developed_fields}">{$planet_field_current}</a> / <a title="{$LNG.ov_max_developed_fields}">{$planet_field_max}</a></dd>
				</div>
				<div class="overview-planet-stat">
					<dt>{$LNG.ov_temperature}</dt>
					<dd>{$planet_temp_min}{$LNG.ov_temp_unit} – {$planet_temp_max}{$LNG.ov_temp_unit}</dd>
				</div>
				<div class="overview-planet-stat">
					<dt>{$LNG.ov_position}</dt>
					<dd><a href="game.php?page=galaxy&amp;galaxy={$galaxy}&amp;system={$system}">[{$galaxy}:{$system}:{$planet}]</a></dd>
				</div>
			</dl>

		</div>
	</div>
</div>
<br>
<div class="infos">		
{if $AllPlanets}<div class="planeto">{$LNG.lv_planet}</div>




		
			{foreach $AllPlanets as $PlanetRow}
			{if ($PlanetRow@iteration % $themeSettings.PLANET_ROWS_ON_OVERVIEW) === 1}{/if}
			<div class="planetl"><a href="game.php?page=overview&amp;cp={$PlanetRow.id}" title="{$PlanetRow.name}">{include file="shared.planet-thumb.tpl" texture=$PlanetRow.image dpath=$dpath width=100 height=100 style='margin: 5px;' loading='lazy' alt=$PlanetRow.name}</a><br>{$PlanetRow.name}<br>{$PlanetRow.build|default:''}<br></div>
			{if $PlanetRow@last && $PlanetRow@total > 1 && ($PlanetRow@iteration % $themeSettings.PLANET_ROWS_ON_OVERVIEW) !== 0}
			{$to = $themeSettings.PLANET_ROWS_ON_OVERVIEW - ($PlanetRow@iteration % $themeSettings.PLANET_ROWS_ON_OVERVIEW)}
			{for $foo=1 to $to}
			
			{/for}
			{/if}
			{if ($PlanetRow@iteration % $themeSettings.PLANET_ROWS_ON_OVERVIEW) === 0}</tr>{/if}
			{/foreach}

		{else}&nbsp;{/if}
</div></div>
	
	
	
	
</div>

{if $planetVizEnabled}
<script type="application/json" id="overview-planet-data">{$planetVizJson nofilter}</script>
<script type="text/javascript"
	src="./scripts/game/overview-planet-loader-utils.js?v={$REV}"></script>
<script type="text/javascript"
	src="./scripts/game/overview-planet-loader.js?v={$REV}"
	data-three-src="./scripts/threejs/three.min.js?v={$REV}"
	data-planet-src="./scripts/game/overview-planet.js?v={$REV}"></script>
{/if}

{/block}
{block name="script" append}
    <script src="scripts/game/overview.js"></script>
{/block}
