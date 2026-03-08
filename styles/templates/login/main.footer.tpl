<footer>
	<div class="footer-inner">
		<span class="footer-powered">{$LNG.footerPoweredBy} <a href="https://hive.io" target="_blank"><img src="styles/resource/images/login/badge_powered-by-hive_dark.svg" alt="Hive" class="footer-hive-logo"></a></span>
		<span class="footer-sep">&bull;</span>
		<a href="https://github.com/Hive-Pizza-Team/HiveNova" title="HiveNova" target="_blank">HiveNova</a> 2025
		<span class="footer-sep">&bull;</span>
		<a href="https://thecrazygm.com/hivetools/utility/tipjar/mithril.pizza/1/hbd/support%20moon" target="_blank">Team Mithril</a> &amp; <a href="https://peakd.com/@hive.pizza" target="_blank">Hive Pizza Team</a>
		<span class="footer-sep">&bull;</span>
		<a href="https://discord.gg/BWqmGbtuDn" title="Discord" target="_blank">Discord</a>
	</div>
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