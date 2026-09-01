{block name="title" prepend}{$pageTitle}{/block}
{block name="content"}
<div class="battle-report">
{if $canShareToHive}
<div class="battle-report__share">
	<button type="button" class="battle-report__share-btn" id="battleShareOpen">{$LNG.battle_share_button}</button>
	<div class="battle-report__share-status" id="battleShareStatus" hidden></div>
</div>
<div class="battle-report__share-modal" id="battleShareModal" hidden>
	<div class="battle-report__share-modal-inner">
		<h3 class="battle-report__share-modal-title">{$LNG.battle_share_modal_title}</h3>
		<p class="battle-report__share-preview"><strong>{$shareDraft.title|escape:'html'}</strong></p>
		<fieldset class="battle-report__share-dest">
			<label class="battle-report__share-option">
				<input type="radio" name="battleShareDest" value="blog" checked>
				<span>{$LNG.battle_share_dest_blog}</span>
			</label>
			<label class="battle-report__share-option">
				<input type="radio" name="battleShareDest" value="community">
				<span>{$LNG.battle_share_dest_community}</span>
			</label>
		</fieldset>
		<div class="battle-report__share-community" id="battleShareCommunityPanel" hidden>
			<label for="battleShareCommunitySelect">{$LNG.battle_share_community_hint}</label>
			<select id="battleShareCommunitySelect">
				{foreach $suggestedCommunities as $community}
				<option value="{$community.author}|{$community.permlink}">{$community.label|escape:'html'}</option>
				{/foreach}
				<option value="custom">{$LNG.battle_share_community_custom}</option>
			</select>
			<input type="text" id="battleShareCommunityCustom" placeholder="hive-123456 / community-id" hidden>
		</div>
		<div class="battle-report__share-actions">
			<button type="button" class="battle-report__share-cancel" id="battleShareCancel">{$LNG.battle_share_cancel}</button>
			<button type="button" class="battle-report__share-confirm" id="battleShareConfirm">{$LNG.battle_share_confirm}</button>
		</div>
	</div>
</div>
{/if}
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
	<button type="button" class="battle-report__round-tab{if $RoundInfo@first} is-active{/if}" data-round-tab="{$Round}">
		{$LNG.sys_attack_round} {$Round+1}
	</button>
	{/foreach}
</div>
{/if}

<div class="battle-report__rounds">
{foreach $Raport.rounds as $Round => $RoundInfo}
<section class="battle-report__round{if !$RoundInfo@first} is-hidden{/if}" data-round-panel="{$Round}">
	<div class="battle-report__round-meta">{$LNG.sys_attack_round} {$Round+1}</div>
	<div class="battle-report__table-wrap battle-report__table-wrap--players">
		{foreach $RoundInfo.attacker as $Player}
		{$PlayerInfo = $Raport.players[$Player.userID]}
		<div class="battle-report__player-block">
			<div class="battle-report__player-info">
				<div class="battle-report__player-role">{$LNG.sys_attack_attacker_pos} {$PlayerInfo.name} {if isset($Info)}([XX:XX:XX]){else}([{$PlayerInfo.koords[0]}:{$PlayerInfo.koords[1]}:{$PlayerInfo.koords[2]}]{if isset($PlayerInfo.koords[3])} ({$LNG["type_planet_short_{$PlayerInfo.koords[3]}"]}){/if}){/if}</div>
				<div class="battle-report__player-tech">{$LNG.sys_ship_weapon} {$PlayerInfo.tech[0]}% - {$LNG.sys_ship_shield} {$PlayerInfo.tech[1]}% - {$LNG.sys_ship_armour} {$PlayerInfo.tech[2]}%</div>
			</div>
			{if !empty($Player.ships)}
			<div class="battle-report__ships-desktop">
				<table class="battle-report__ships-table">
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
				</table>
			</div>
			<div class="battle-report__ships-mobile">
				{foreach $Player.ships as $ShipID => $ShipData}
				<div class="battle-report__ship-card">
					<div class="battle-report__ship-card-title">{$LNG.shortNames.{$ShipID}}</div>
					<dl class="battle-report__ship-stats">
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_count}</dt>
							<dd>{$ShipData[0]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_weapon}</dt>
							<dd>{$ShipData[1]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_shield}</dt>
							<dd>{$ShipData[2]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_armour}</dt>
							<dd>{$ShipData[3]|number}</dd>
						</div>
					</dl>
				</div>
				{/foreach}
			</div>
			{else}
			<div class="battle-report__destroyed">{$LNG.sys_destroyed}</div>
			{/if}
		</div>
		{/foreach}
	</div>
	<div class="battle-report__table-wrap battle-report__table-wrap--players">
		{foreach $RoundInfo.defender as $Player}
		{$PlayerInfo = $Raport.players[$Player.userID]}
		<div class="battle-report__player-block">
			<div class="battle-report__player-info">
				<div class="battle-report__player-role">{$LNG.sys_attack_defender_pos} {$PlayerInfo.name} {if isset($Info)}([XX:XX:XX]){else}([{$PlayerInfo.koords[0]}:{$PlayerInfo.koords[1]}:{$PlayerInfo.koords[2]}]{if isset($PlayerInfo.koords[3])} ({$LNG["type_planet_short_{$PlayerInfo.koords[3]}"]}){/if}){/if}</div>
				<div class="battle-report__player-tech">{$LNG.sys_ship_weapon} {$PlayerInfo.tech[0]}% - {$LNG.sys_ship_shield} {$PlayerInfo.tech[1]}% - {$LNG.sys_ship_armour} {$PlayerInfo.tech[2]}%</div>
			</div>
			{if !empty($Player.ships)}
			<div class="battle-report__ships-desktop">
				<table class="battle-report__ships-table">
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
				</table>
			</div>
			<div class="battle-report__ships-mobile">
				{foreach $Player.ships as $ShipID => $ShipData}
				<div class="battle-report__ship-card">
					<div class="battle-report__ship-card-title">{$LNG.shortNames.{$ShipID}}</div>
					<dl class="battle-report__ship-stats">
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_count}</dt>
							<dd>{$ShipData[0]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_weapon}</dt>
							<dd>{$ShipData[1]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_shield}</dt>
							<dd>{$ShipData[2]|number}</dd>
						</div>
						<div class="battle-report__ship-stat">
							<dt>{$LNG.sys_ship_armour}</dt>
							<dd>{$ShipData[3]|number}</dd>
						</div>
					</dl>
				</div>
				{/foreach}
			</div>
			{else}
			<div class="battle-report__destroyed">{$LNG.sys_destroyed}</div>
			{/if}
		</div>
		{/foreach}
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
{if $canShareToHive}
window.battleShareDraft = {$shareDraftJson nofilter};
window.battleShareMessages = {
	keychainMissing: "{$LNG.battle_share_keychain_missing|escape:'javascript'}",
	success: "{$LNG.battle_share_success|escape:'javascript'}",
	failure: "{$LNG.battle_share_failure|escape:'javascript'}",
	communityRequired: "{$LNG.battle_share_community_required|escape:'javascript'}"
};
{/if}
$(function() {
	var $tabs = $('.battle-report__round-tab');
	var $panels = $('.battle-report__round');

	if ($tabs.length) {
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
	}

{if $canShareToHive}
	var $modal = $('#battleShareModal');
	var $communityPanel = $('#battleShareCommunityPanel');
	var $communitySelect = $('#battleShareCommunitySelect');
	var $communityCustom = $('#battleShareCommunityCustom');
	var $status = $('#battleShareStatus');

	function setShareStatus(message, isError) {
		if (!message) {
			$status.prop('hidden', true).text('');
			return;
		}
		$status.prop('hidden', false).text(message).toggleClass('is-error', !!isError);
	}

	function toggleCommunityPanel() {
		var isCommunity = $('input[name="battleShareDest"]:checked').val() === 'community';
		$communityPanel.prop('hidden', !isCommunity);
	}

	$('#battleShareOpen').on('click', function() {
		if (typeof hive_keychain === 'undefined') {
			alert(window.battleShareMessages.keychainMissing);
			return;
		}
		setShareStatus('');
		$modal.prop('hidden', false);
	});

	$('#battleShareCancel').on('click', function() {
		$modal.prop('hidden', true);
	});

	$('input[name="battleShareDest"]').on('change', toggleCommunityPanel);
	$communitySelect.on('change', function() {
		$communityCustom.prop('hidden', $(this).val() !== 'custom');
	});

	$('#battleShareConfirm').on('click', function() {
		var destination = { type: 'blog' };
		if ($('input[name="battleShareDest"]:checked').val() === 'community') {
			var raw = $communitySelect.val();
			if (raw === 'custom') {
				raw = $.trim($communityCustom.val());
				if (!raw) {
					alert(window.battleShareMessages.communityRequired);
					return;
				}
				var parts = raw.split(/[\s/]+/);
				if (parts.length < 2) {
					alert(window.battleShareMessages.communityRequired);
					return;
				}
				destination = { type: 'community', parent_author: parts[0], parent_permlink: parts[1] };
			} else {
				var pair = raw.split('|');
				destination = { type: 'community', parent_author: pair[0], parent_permlink: pair[1] };
			}
		}

		HiveKeychainShareBattle(window.battleShareDraft, destination, function(err, txid) {
			if (err) {
				setShareStatus(window.battleShareMessages.failure + (err ? ' ' + err : ''), true);
				return;
			}
			$modal.prop('hidden', true);
			setShareStatus(window.battleShareMessages.success + (txid ? ' (' + txid + ')' : ''), false);
		});
	});
{/if}
});
</script>
{/block}
{/block} 
