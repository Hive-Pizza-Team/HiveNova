<footer>
	<a href="https://discord.gg/BWqmGbtuDn" title="Discord" target="copy">Discord</a> community server
	<br>
	Project <a href="https://github.com/Hive-Pizza-Team/HiveNova" title="HiveNova" target="copy">HiveNova</a> 2025
	<br>
	<img src="styles/resources/images/badge_powered-by-hive_dark.svg" type="image/svg+xml">
</footer>
</div>
<div id="dialog" style="display:none;"></div>
<script>
var LoginConfig = {
    'isMultiUniverse': {$isMultiUniverse|json},
	'unisWildcast': {$unisWildcast|json},
	'referralEnable' : {$referralEnable|json},
	'basePath' : {$basepath|json}
};
</script>
{if $analyticsEnable}
<script type="text/javascript" src="http://www.google-analytics.com/ga.js"></script>
<script type="text/javascript">
try{
var pageTracker = _gat._getTracker("{$analyticsUID}");
pageTracker._trackPageview();
} catch(err) {}</script>
{/if}
</body>
</html>