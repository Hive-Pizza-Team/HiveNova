{block name="title" prepend}
{$LNG.lm_technology}{/block}

{block name="content"}
<style>
.techb {
	cursor: pointer;
}
.techb .minus { display: none; }
.techb.is-open .minus { display: inline-block; }
.techb.is-open .plus { display: none; }
.techtree-body .techi { display: none; }
/* Contain floated .techi cards — without this the body height stays 0 (broken in WebKit). */
.techtree-body.is-open {
	overflow: hidden; /* BFC fallback for engines without flow-root */
	display: flow-root;
}
.techtree-body.is-open .techi {
	display: block;
}
.techtree-body.is-open .techi.is-filtered-out {
	display: none;
}
</style>
{if $messages}
	<div class="message"><a href="?page=messages">{$messages}</a></div>
{/if}
{if $nextUnlocks|@count > 0}
<section class="techtree-next" aria-labelledby="techtree-next-heading">
	<h2 id="techtree-next-heading" class="techtree-next__title">{$LNG.tt_next_unlock}</h2>
	<div class="techtree-next__cards">
	{foreach $nextUnlocks as $card}
		<article class="techtree-next__card">
			<h3 class="techtree-next__name">{$card.name}</h3>
			<p class="techtree-next__missing">{$card.missing}</p>
			{if $card.progress != ''}
			<p class="techtree-next__progress">{$card.progress}</p>
			{/if}
			<a class="btn btn--secondary btn--compact" href="{$card.href}">{$LNG.tt_go}</a>
		</article>
	{/foreach}
	</div>
</section>
{/if}
<div class="planeto techtree-tabs" role="tablist" aria-label="{$LNG.lm_technology}">
	<button type="button" class="btn btn--secondary btn--compact selected" data-techtree-filter="start" role="tab" aria-selected="true">{$LNG.tt_start_here}</button>
	<button type="button" class="btn btn--secondary btn--compact" data-techtree-filter="all" role="tab" aria-selected="false">{$LNG.tt_filter_all}</button>
	{foreach $TechCategories as $categoryId}
	<button type="button" class="btn btn--secondary btn--compact" data-techtree-filter="{$categoryId}" role="tab" aria-selected="false">{$LNG.tech.$categoryId}</button>
	{/foreach}
</div>
<div>
<div class="infos">
{foreach $TechCategories as $categoryId}
<div class="techb" id="{$categoryId}">
	<button type="button" class="btn btn--secondary btn--compact">
		<span class="plus" id="{$categoryId}s"><i class="fa fa-plus"></i></span>
		<span class="minus" id="{$categoryId}h"><i class="fa fa-minus"></i></span>
	</button>
	{$LNG.tech.$categoryId}
</div>
<div class="techtree-body" id="body{$categoryId}"></div>
{/foreach}
</div>
</div>
<script type="application/json" id="techtree-data">{$techTreeJson nofilter}</script>
<script defer src="./scripts/game/techtree.js?v={$REV}"></script>
{/block}
