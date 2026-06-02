{block name="title" prepend}{$pageTitle}{/block}
{block name="content"}
<div class="battle-report">
{if isset($Info)}
<div class="battle-report__players">
	<div class="battle-report__player {if $Raport.result == "a"}battle-report__player--attacker{elseif $Raport.result == "r"}battle-report__player--defender{/if}">{$Info.0}</div>
	<div class="battle-report__versus">VS</div>
	<div class="battle-report__player {if $Raport.result == "r"}battle-report__player--attacker{elseif $Raport.result == "a"}battle-report__player--defender{/if}">{$Info.1}</div>
</div>
{/if}

<div class="battle-report__summary">
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.sys_ship_type}</div>
		<div class="battle-report__summary-value">{if $Raport.mode == 1}{$LNG.type_mission_9}{else}{$LNG.type_mission_1}{/if}</div>
	</div>
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.sys_br_time}</div>
		<div class="battle-report__summary-value">{$Raport.time}</div>
	</div>
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.sys_br_result}</div>
		<div class="battle-report__summary-value">
			{if $Raport.result == "a"}
				<span class="battle-report__result battle-report__result--attacker">{$LNG.sys_attacker_won}</span>
			{elseif $Raport.result == "r"}
				<span class="battle-report__result battle-report__result--defender">{$LNG.sys_defender_won}</span>
			{else}
				<span class="battle-report__result battle-report__result--draw">{$LNG.sys_both_won}</span>
			{/if}
		</div>
	</div>
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.sys_attacker_lostunits}</div>
		<div class="battle-report__summary-value">{$Raport['units'][0]|number}</div>
	</div>
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.sys_defender_lostunits}</div>
		<div class="battle-report__summary-value">{$Raport['units'][1]|number}</div>
	</div>
	<div class="battle-report__summary-item">
		<div class="battle-report__summary-label">{$LNG.debree_field_1}</div>
		<div class="battle-report__summary-value">{foreach $Raport.debris as $elementID => $amount}{$amount|number} {$LNG.tech.$elementID}{if ($amount@index + 2) == $Raport.debris|count} {$LNG.sys_and} {elseif !$amount@last}, {/if}{/foreach}</div>
	</div>
	{if $Raport.result == "a"}
	<div class="battle-report__summary-item battle-report__summary-item--wide">
		<div class="battle-report__summary-label">{$LNG.sys_stealed_ressources}</div>
		<div class="battle-report__summary-value">
			{if $Raport.stealUnprofitable}<span style="color:var(--color-notice)" title="{$LNG.sys_steal_unprofitable_tooltip}">{/if}
			{foreach $Raport.steal as $elementID => $amount}{$amount|number} {$LNG.tech.$elementID}{if ($amount@index + 2) == $Raport.steal|count} {$LNG.sys_and} {elseif !$amount@last}, {/if}{/foreach}
			{if $Raport.stealUnprofitable}</span>{/if}
		</div>
	</div>
	{/if}
</div>

{if $Raport.rounds|count > 1}
<div class="battle-report__round-tabs" role="tablist">
	{foreach $Raport.rounds as $Round => $RoundInfo}
	<button type="button" class="battle-report__round-tab{if $RoundInfo@first} is-active{/if}" data-round-tab="{$Round@index}">
		{$LNG.sys_attack_round} {$Round+1}
	</button>
	{/foreach}
</div>
{/if}

<div class="battle-report__rounds">
{foreach $Raport.rounds as $Round => $RoundInfo}
<section class="battle-report__round{if !$RoundInfo@first} is-hidden{/if}" data-round-panel="{$Round@index}">
	<div class="battle-report__round-meta">{$LNG.sys_attack_round} {$Round+1}</div>
	<div class="battle-report__table-wrap">
		<table class="auto">
			<tr>
				{foreach $RoundInfo.attacker as $Player}
				{$PlayerInfo = $Raport.players[$Player.userID]}
				<td class="transparent">
					<table>
						<tr>
							<td>
								{$LNG.sys_attack_attacker_pos} {$PlayerInfo.name} {if isset($Info)}([XX:XX:XX]){else}([{$PlayerInfo.koords[0]}:{$PlayerInfo.koords[1]}:{$PlayerInfo.koords[2]}]{if isset($PlayerInfo.koords[3])} ({$LNG["type_planet_short_{$PlayerInfo.koords[3]}"]}){/if}){/if}<br>
								{$LNG.sys_ship_weapon} {$PlayerInfo.tech[0]}% - {$LNG.sys_ship_shield} {$PlayerInfo.tech[1]}% - {$LNG.sys_ship_armour} {$PlayerInfo.tech[2]}%
								<table class="battle-report__ships-table">
								{if !empty($Player.ships)}
									<tr>
										<td class="transparent">{$LNG.sys_ship_type}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$LNG.shortNames.{$ShipID}}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_count}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[0]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_weapon}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[1]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_shield}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[2]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_armour}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[3]|number}</td>
										{/foreach}
									</tr>
								{else}
									<tr>
										<td class="transparent">
											<br>{$LNG.sys_destroyed}<br><br>
										</td>
									</tr>
								{/if}
								</table>
							</td>
						</tr>
					</table>
				</td>
				{/foreach}
			</tr>
		</table>
	</div>
	<div class="battle-report__table-wrap">
		<table class="auto">
			<tr>
				{foreach $RoundInfo.defender as $Player}
				{$PlayerInfo = $Raport.players[$Player.userID]}
				<td class="transparent">
					<table>
						<tr>
							<td>
								{$LNG.sys_attack_defender_pos} {$PlayerInfo.name} {if isset($Info)}([XX:XX:XX]){else}([{$PlayerInfo.koords[0]}:{$PlayerInfo.koords[1]}:{$PlayerInfo.koords[2]}]{if isset($PlayerInfo.koords[3])} ({$LNG["type_planet_short_{$PlayerInfo.koords[3]}"]}){/if}){/if}<br>
								{$LNG.sys_ship_weapon} {$PlayerInfo.tech[0]}% - {$LNG.sys_ship_shield} {$PlayerInfo.tech[1]}% - {$LNG.sys_ship_armour} {$PlayerInfo.tech[2]}%
								<table class="battle-report__ships-table">
								{if !empty($Player.ships)}
									<tr>
										<td class="transparent">{$LNG.sys_ship_type}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$LNG.shortNames.{$ShipID}}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_count}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[0]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_weapon}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[1]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_shield}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[2]|number}</td>
										{/foreach}
									</tr>
									<tr>
										<td class="transparent">{$LNG.sys_ship_armour}</td>
										{foreach $Player.ships as $ShipID => $ShipData}
										<td class="transparent">{$ShipData[3]|number}</td>
										{/foreach}
									</tr>
								{else}
									<tr>
										<td class="transparent">
											<br>{$LNG.sys_destroyed}<br><br>
										</td>
									</tr>
								{/if}
								</table>
							</td>
						</tr>
					</table>
				</td>
				{/foreach}
			</tr>
		</table>
	</div>
	{if !$RoundInfo@last}
	<div class="battle-report__round-damage">
		{$LNG.fleet_attack_1} {$RoundInfo.info[0]|number} {$LNG.fleet_attack_2} {$RoundInfo.info[3]|number} {$LNG.damage}<br>
		{$LNG.fleet_defs_1} {$RoundInfo.info[2]|number} {$LNG.fleet_defs_2} {$RoundInfo.info[1]|number} {$LNG.damage}
	</div>
	{/if}
</section>
{/foreach}
</div>

<div class="battle-report__details">
{if $Raport.mode == 1}
	{* Destruction *}
	{if $Raport.moon.moonDestroySuccess == -1}
		{* Attack not win *}
		{$LNG.sys_destruc_stop}<br>
	{else}
		{* Attack win *}
		{$LNG.sys_destruc_lune|sprintf:$Raport.moon.moonDestroyChance}<br>{$LNG.sys_destruc_mess1}
		{if $Raport.moon.moonDestroySuccess == 1}
			{* Destroy success *}
			{$LNG.sys_destruc_reussi}
		{elseif $Raport.moon.moonDestroySuccess == 0}
			{* Destroy failed *}
			{$LNG.sys_destruc_null}			
		{/if}
		<br>
		{$LNG.sys_destruc_rip|sprintf:$Raport.moon.fleetDestroyChance}
		{if $Raport.moon.fleetDestroySuccess == 1}
			{* Fleet destroyed *}
			<br>{$LNG.sys_destruc_echec}
		{/if}			
	{/if}
{else}
	{* Normal Attack *}
	{$LNG.sys_moonproba} {$Raport.moon.moonChance} %<br>
	{if !empty($Raport.moon.moonName)}
		{if isset($Info)}
			{* Moon created (HoF Mode) *}
			{$LNG.sys_moonbuilt|sprintf:$Raport.moon.moonName:"XX":"XX":"XX"}
		{else}
			{* Moon created *}
			{$LNG.sys_moonbuilt|sprintf:$Raport.moon.moonName:$Raport.koords[0]:$Raport.koords[1]:$Raport.koords[2]}
		{/if}
	{/if}
{/if}

{$Raport.additionalInfo}
</div>
 </div>
{block name="script" append}
<script>
$(function() {
	var $tabs = $('.battle-report__round-tab');
	var $panels = $('.battle-report__round');

	if (!$tabs.length) {
		return;
	}

	$panels.hide().addClass('is-hidden');
	$panels.first().show().removeClass('is-hidden');
	$tabs.removeClass('is-active');
	$tabs.first().addClass('is-active');

	$tabs.on('click', function(event) {
		event.preventDefault();
		var index = $(this).data('round-tab');
		$tabs.removeClass('is-active');
		$(this).addClass('is-active');
		$panels.hide().addClass('is-hidden');
		$panels.filter('[data-round-panel="' + index + '"]').show().removeClass('is-hidden');
	});
});
</script>
{/block}
{/block} 
