{block name="content"}

<div id="threejs-container">
<style>
	body { margin: 0; }
	#threejs-container { position: fixed; touch-action: none; }
</style>
</div>

<script type="application/json" id="viz-config">{$vizConfigJson nofilter}</script>
<script defer src="./scripts/game/viz.js?v={$REV}"></script>
{/block}
