{block name="title"}{$documentTitle}{/block}
{block name="content"}
<div class="public-battle">
	<div class="public-battle-cta">
		<p class="public-battle-cta__blurb">{$signupCtaBlurb|escape}</p>
		<p class="public-battle-cta__actions">
			<a class="public-battle-cta__btn" href="{$signupCtaUrl|escape}">{$signupCtaLabel|escape}</a>
			<a class="public-battle-cta__link" href="{$hallListUrl|escape}">{$backToHallLabel|escape}</a>
		</p>
	</div>

	<div class="battle-report">
	{if isset($Info)}
	<div class="battle-report__players">
		<div class="battle-report__player {if $Raport.result == "a"}battle-report__player--attacker{elseif $Raport.result == "r"}battle-report__player--defender{/if}">{$Info.0|escape}</div>
		<div class="battle-report__versus">VS</div>
		<div class="battle-report__player {if $Raport.result == "r"}battle-report__player--attacker{elseif $Raport.result == "a"}battle-report__player--defender{/if}">{$Info.1|escape}</div>
	</div>
	{/if}

	<div class="battle-report__summary">
		<div class="battle-report__summary-item">
			<div class="battle-report__summary-label">{$LNG.sys_br_time}</div>
			<div class="battle-report__summary-value">{$Raport.time|escape}</div>
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
			<div class="battle-report__summary-label">{$LNG.tkb_units}</div>
			<div class="battle-report__summary-value">{$hallUnits|number}</div>
		</div>
		<div class="battle-report__summary-item">
			<div class="battle-report__summary-label">{$LNG.sys_attacker_lostunits}</div>
			<div class="battle-report__summary-value">{$Raport['units'][0]|number}</div>
		</div>
		<div class="battle-report__summary-item">
			<div class="battle-report__summary-label">{$LNG.sys_defender_lostunits}</div>
			<div class="battle-report__summary-value">{$Raport['units'][1]|number}</div>
		</div>
		{if isset($Raport.debris)}
		<div class="battle-report__summary-item">
			<div class="battle-report__summary-label">{$LNG.debree_field_1}</div>
			<div class="battle-report__summary-value">{foreach $Raport.debris as $elementID => $amount}{$amount|number} {$LNG.tech.$elementID}{if ($amount@index + 2) == $Raport.debris|count} {$LNG.sys_and} {elseif !$amount@last}, {/if}{/foreach}</div>
		</div>
		{/if}
	</div>
	</div>

	<div class="public-battle-cta public-battle-cta--footer">
		<p class="public-battle-cta__blurb">{$signupCtaBlurb|escape}</p>
		<p class="public-battle-cta__actions">
			<a class="public-battle-cta__btn" href="{$signupCtaUrl|escape}">{$signupCtaLabel|escape}</a>
		</p>
	</div>
</div>
{/block}
{block name="script"}
<link rel="stylesheet" type="text/css" href="styles/resource/css/login/battle-public.css?v={$REV}">
{/block}
