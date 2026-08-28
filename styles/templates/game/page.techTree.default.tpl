{block name="title" prepend}
{$LNG.lm_technology}{/block}

{block name="content"}
<style>
.minus { display:none; }
.techtree-body .techi { display:none; }
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
