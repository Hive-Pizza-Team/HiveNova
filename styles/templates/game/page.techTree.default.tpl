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
</style>
{if $messages}
	<div class="message"><a href="?page=messages">{$messages}</a></div>
{/if}
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
<script src="./scripts/game/techtree.js?v={$REV}"></script>
{/block}
