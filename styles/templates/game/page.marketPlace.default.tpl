{block name="title" prepend}{$LNG.lm_marketplace}{/block}
{block name="content"}
<div class="market" id="market"
	data-tab="{$tab}"
	data-confirm-buy="{$LNG.market_confirm_buy|escape:'html'}"
	data-confirm-cancel="{$LNG.market_cancel_confirm|escape:'html'}"
	data-deal-good="{$LNG.market_deal_good|escape:'html'}"
	data-deal-fair="{$LNG.market_deal_fair|escape:'html'}"
	data-deal-bad="{$LNG.market_deal_bad|escape:'html'}"
	data-fuel-label="{$LNG.tech.903|escape:'html'}"
	data-expired="{$LNG.market_expired|escape:'html'}">

	<header class="market__head">
		<h2 class="market__title">{$LNG.lm_marketplace}</h2>
		<p class="market__intro">{$LNG.market_intro}</p>
		<div class="market__cta">
			<a class="btn btn--primary market__sell" href="{$sellUrl|escape:'html'}">{$LNG.market_sell_cta}</a>
			<span class="market__cta-hint">{$LNG.market_sell_cta_hint|sprintf:"[`$tradeZone.galaxy`:`$tradeZone.system`:`$tradeZone.planet`]"}</span>
		</div>
		<details class="market__how">
			<summary>{$LNG.market_how_title}</summary>
			<ol class="market__steps">
				<li>{$LNG.market_how_step_1|sprintf:$tradeZone.planet}</li>
				<li>{$LNG.market_how_step_2}</li>
				<li>{$LNG.market_how_step_3}</li>
				<li>{$LNG.market_how_step_4}</li>
			</ol>
		</details>
	</header>

	{if $flash}
	<div class="market__notice market__notice--{if $flash.ok}ok{else}error{/if}" role="status">{$flash.message}</div>
	{/if}

	{if $myOfferCount > 0}
	<p class="market__mine">{$LNG.market_my_offers|sprintf:$myOfferCount}</p>
	{/if}

	<ul class="simple-tabs__nav market__tabs" role="tablist">
		<li class="{if $tab == 'resources'}is-active{/if}"><a href="game.php?page=marketPlace&amp;tab=resources" data-market-tab="resources" role="tab" aria-selected="{if $tab == 'resources'}true{else}false{/if}">{$LNG.market_tab_resources} <span class="market__count">{$resourceOffers|@count}</span></a></li>
		<li class="{if $tab == 'fleet'}is-active{/if}"><a href="game.php?page=marketPlace&amp;tab=fleet" data-market-tab="fleet" role="tab" aria-selected="{if $tab == 'fleet'}true{else}false{/if}">{$LNG.market_tab_fleet} <span class="market__count">{$fleetOffers|@count}</span></a></li>
	</ul>

	<div class="market__toolbar">
		<label class="market__field">
			<span>{$LNG.market_pay_with}</span>
			<select id="marketPayWith" title="{$LNG.market_pay_with_help|escape:'html'}">
				<option value="1"{if $payWith == 1} selected{/if}>{$LNG.tech.202}</option>
				<option value="2"{if $payWith == 2} selected{/if}>{$LNG.tech.203}</option>
			</select>
		</label>
		<label class="market__field" data-market-only="resources">
			<span>{$LNG.market_filter_get}</span>
			<select id="marketFilterGet">
				<option value="">{$LNG.market_filter_any}</option>
				<option value="901">{$LNG.tech.901}</option>
				<option value="902">{$LNG.tech.902}</option>
				<option value="903">{$LNG.tech.903}</option>
			</select>
		</label>
		<label class="market__field">
			<span>{$LNG.market_filter_pay}</span>
			<select id="marketFilterPay">
				<option value="">{$LNG.market_filter_any}</option>
				<option value="1">{$LNG.tech.901}</option>
				<option value="2">{$LNG.tech.902}</option>
				<option value="3">{$LNG.tech.903}</option>
			</select>
		</label>
		<label class="market__field">
			<span>{$LNG.market_sort}</span>
			<select id="marketSort">
				<option value="deal" data-market-only="resources">{$LNG.market_sort_deal}</option>
				<option value="delivery">{$LNG.market_sort_delivery}</option>
				<option value="expires" selected>{$LNG.market_sort_expires}</option>
			</select>
		</label>
		<label class="market__check">
			<input type="checkbox" id="marketOnlyBuyable">
			<span>{$LNG.market_only_buyable}</span>
		</label>
	</div>
	<p class="market__pay-help">{$LNG.market_pay_with_help}</p>

	<details class="market__ratio" data-market-only="resources">
		<summary>{$LNG.market_ref_ratio}: <span class="js-ratio-label">{$refRatio.0}:{$refRatio.1}:{$refRatio.2}</span></summary>
		<p>{$LNG.market_ref_ratio_help}</p>
		<div class="market__ratio-inputs">
			<label><span>{$LNG.tech.901}</span><input type="number" name="ratio-metal" value="{$refRatio.0}" min="0.1" step="0.1" inputmode="decimal"></label>
			<label><span>{$LNG.tech.902}</span><input type="number" name="ratio-crystal" value="{$refRatio.1}" min="0.1" step="0.1" inputmode="decimal"></label>
			<label><span>{$LNG.tech.903}</span><input type="number" name="ratio-deuterium" value="{$refRatio.2}" min="0.1" step="0.1" inputmode="decimal"></label>
		</div>
	</details>

	{foreach ['resources' => $resourceOffers, 'fleet' => $fleetOffers] as $panel => $offers}
	<section class="market__panel" id="market-panel-{$panel}" data-market-panel="{$panel}"{if $tab != $panel} hidden{/if}>
		{if $offers|@count == 0}
		<div class="market-empty">
			<p class="market-empty__title">{if $panel == 'fleet'}{$LNG.market_empty_fleet}{else}{$LNG.market_empty_offers}{/if}</p>
			<p>{$LNG.market_empty_offers_hint}</p>
			<a class="btn btn--secondary" href="{$sellUrl|escape:'html'}">{$LNG.market_sell_cta}</a>
		</div>
		{else}
		<table class="market-table" id="{if $panel == 'fleet'}tradeFleetList{else}tradeList{/if}">
			<thead>
				<tr>
					<th>{$LNG.market_col_seller}</th>
					<th>{$LNG.market_col_get}</th>
					<th>{$LNG.market_col_pay}</th>
					{if $panel == 'resources'}<th>{$LNG.market_col_deal}</th>{/if}
					<th>{$LNG.market_col_delivery}</th>
					<th>{$LNG.market_col_expires}</th>
					<th>{$LNG.market_col_cost}</th>
					<th><span class="market-sr">{$LNG.market_p_buy}</span></th>
				</tr>
			</thead>
			<tbody>
			{foreach $offers as $o}
				<tr class="market-offer {$o.diploClass}{if $o.own} market-offer--own{/if}"
					data-offer="{$o.id}"
					data-get-901="{$o.get.901}" data-get-902="{$o.get.902}" data-get-903="{$o.get.903}"
					data-pay-type="{$o.wantedType}" data-pay-amount="{$o.wantedAmount}"
					data-delivery="{$o.delivery}" data-end="{$o.end}"
					data-get-label="{$o.getLabel|escape:'html'}" data-pay-label="{$o.payLabel|escape:'html'}"
					data-own="{if $o.own}1{else}0{/if}"
					data-block-lc="{$o.pay.lc.block|escape:'html'}" data-block-hc="{$o.pay.hc.block|escape:'html'}"
					data-ships-lc="{$o.pay.lc.ships|escape:'html'}" data-ships-hc="{$o.pay.hc.ships|escape:'html'}"
					data-fuel-lc="{$o.pay.lc.fuel|number_plain}" data-fuel-hc="{$o.pay.hc.fuel|number_plain}">
					<td class="table_username" data-label="{$LNG.market_col_seller|escape:'html'}">{$o.username|escape:'html'}</td>
					<td data-label="{$LNG.market_col_get|escape:'html'}">
						<ul class="market-goods">
						{if $panel == 'resources'}
							{foreach $resImages as $resId => $img}{if $o.get.$resId > 0}
							<li><img src="{$dpath}images/{$img}.gif" alt="" width="20" height="20"><span>{$o.get.$resId|number}</span> <span class="market-goods__name">{$LNG.tech.$resId}</span></li>
							{/if}{/foreach}
						{else}
							{foreach $o.ships as $ship}
							<li><span>{$ship.count|number}</span> <span class="market-goods__name">{$ship.name}</span></li>
							{/foreach}
						{/if}
						</ul>
					</td>
					<td data-label="{$LNG.market_col_pay|escape:'html'}">
						<span class="market-price">{if $o.wantedTech}<img src="{$dpath}images/{$resImages[$o.wantedTech]}.gif" alt="" width="20" height="20">{/if}<span>{$o.wantedAmount|number}</span> <span class="market-goods__name">{$o.wantedName}</span></span>
					</td>
					{if $panel == 'resources'}
					<td data-label="{$LNG.market_col_deal|escape:'html'}">
						<span class="market-deal market-deal--{$o.rating}" title="{$LNG.market_deal_help|escape:'html'}"><b class="js-deal-ratio">{if $o.ratio !== null}{$o.ratio|string_format:"%.2f"}&times;{else}&ndash;{/if}</b> <span class="js-deal-label">{if $o.rating == 'good'}{$LNG.market_deal_good}{elseif $o.rating == 'bad'}{$LNG.market_deal_bad}{elseif $o.rating == 'fair'}{$LNG.market_deal_fair}{/if}</span></span>
					</td>
					{/if}
					<td data-label="{$LNG.market_col_delivery|escape:'html'}">{$o.delivery|pretty_fly_time}</td>
					<td data-label="{$LNG.market_col_expires|escape:'html'}"><span data-time="{$o.end}">{$o.end|pretty_fly_time}</span></td>
					<td data-label="{$LNG.market_col_cost|escape:'html'}" class="market-cost">
						{if $o.own}&ndash;{else}
						<span class="js-cost">{if $o.pay.$payKey.ships}{$o.pay.$payKey.ships}<br><small>{$LNG.tech.903}: {$o.pay.$payKey.fuel|number}</small>{else}&ndash;{/if}</span>
						{/if}
					</td>
					<td class="market-action">
						{if $o.own}
						<span class="market-badge">{$LNG.market_your_offer}</span>
						<form class="market-cancel" method="post" action="game.php?page=marketPlace&amp;action=cancel&amp;tab={$panel}">
							<input type="hidden" name="fleetID" value="{$o.id}">
							<input type="hidden" name="csrf" value="{$csrf}">
							<input type="hidden" name="shipType" value="{$payWith}" class="js-ship-type">
							<button type="submit" class="btn btn--secondary market-cancel__btn">{$LNG.market_cancel}</button>
						</form>
						{else}
						<form class="market-buy" method="post" action="game.php?page=marketPlace&amp;action=buy&amp;tab={$panel}">
							<input type="hidden" name="fleetID" value="{$o.id}">
							<input type="hidden" name="csrf" value="{$csrf}">
							<input type="hidden" name="shipType" value="{$payWith}" class="js-ship-type">
							<button type="submit" class="btn btn--primary market-buy__btn"{if $o.pay.$payKey.block} disabled{/if}>{$LNG.market_p_submit}</button>
							<small class="market-offer__reason js-reason">{$o.pay.$payKey.block}</small>
						</form>
						{/if}
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		<p class="market-empty market-empty--filtered js-empty-filtered" hidden>{$LNG.market_empty_filtered}</p>
		{/if}

		<h3 class="market__subtitle">{$LNG.market_history_title}</h3>
		{if $panel == 'resources'}{$history = $resourceHistory}{else}{$history = $fleetHistory}{/if}
		{if $history|@count == 0}
		<p class="market-empty market-empty--history">{$LNG.market_history_empty}</p>
		{else}
		<table class="market-table market-table--history" id="{if $panel == 'fleet'}fleetHistoryList{else}resourceHistoryList{/if}">
			<thead>
				<tr>
					<th>{$LNG.tkb_datum}</th>
					<th>{$LNG.market_col_sold}</th>
					<th>{$LNG.market_col_price}</th>
				</tr>
			</thead>
			<tbody>
			{foreach $history as $row}
				<tr>
					<td data-label="{$LNG.tkb_datum|escape:'html'}">{$row.time_label}</td>
					<td data-label="{$LNG.market_col_sold|escape:'html'}">
						<ul class="market-goods">
						{if $panel == 'resources'}
							{foreach $resImages as $resId => $img}{if $row[$img] > 0}
							<li><img src="{$dpath}images/{$img}.gif" alt="" width="20" height="20"><span>{$row[$img]|number}</span> <span class="market-goods__name">{$LNG.tech.$resId}</span></li>
							{/if}{/foreach}
						{else}
							{foreach $row.ships as $ship}
							<li><span>{$ship.count|number}</span> <span class="market-goods__name">{$ship.name}</span></li>
							{/foreach}
						{/if}
						</ul>
					</td>
					<td data-label="{$LNG.market_col_price|escape:'html'}">
						{if $row.res_type >= 1 && $row.res_type <= 3}{$tech = 900 + $row.res_type}
						<span class="market-price"><img src="{$dpath}images/{$resImages[$tech]}.gif" alt="" width="20" height="20"><span>{$row.amount|number}</span> <span class="market-goods__name">{$LNG.tech.$tech}</span></span>
						{else}{$row.amount|number}{/if}
					</td>
				</tr>
			{/foreach}
			</tbody>
		</table>
		{/if}
	</section>
	{/foreach}
</div>
{/block}
