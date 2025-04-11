<footer>
	<br>
	<a href="https://hive.io"><svg width="200" height="100" xmlns="http://www.w3.org/2000/svg">
		<image href="styles/resource/images/login/badge_powered-by-hive_dark.svg" height="100" width="200" />
	  </svg></a>
	<br>Project <a href="https://github.com/Hive-Pizza-Team/HiveNova" title="HiveNova" target="copy">HiveNova</a> 2025 | Brought to you by <a href="https://peakd.com/@open.mithril">Team Mithril</a> and <a href="https://peakd.com/@hive.pizz">Hive Pizza Team</a> <a href="https://discord.gg/BWqmGbtuDn" title="Discord" target="copy">Discord</a> guild
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