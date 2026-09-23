{if $showTechTreeNudge|default:false}
<aside class="techtree-nudge" data-techtree-nudge role="status">
	<p class="techtree-nudge__text">{$LNG.tt_nudge}</p>
	<a class="btn btn--secondary btn--compact" href="game.php?page=techtree">{$LNG.tt_nudge_open}</a>
	<button type="button" class="btn btn--secondary btn--compact" data-techtree-nudge-dismiss>{$LNG.tt_nudge_dismiss}</button>
</aside>
<script defer src="./scripts/game/techtree-nudge.js?v={$REV}"></script>
{/if}
