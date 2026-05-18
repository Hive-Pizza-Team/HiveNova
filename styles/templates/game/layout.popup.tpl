{include file="main.header.tpl" bodyclass="popup"}

<input style="display:none;" type="checkbox" id="toggle-menu" role="button">
<menu>
	<div class="fixed">
		{include file="main.navigation.tpl"}
	</div>
</menu>

<div id="content">{block name="content"}{/block}</div>
{include file="main.footer.tpl" nocache}
{include file="main.bottomnav.tpl"}
</body>
</html>
