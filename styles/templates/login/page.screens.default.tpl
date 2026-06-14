{block name="title" prepend}{$LNG.siteTitleScreens}{/block}
{block name="content"}
<table>
	{foreach $screenshots as $screenshot}
		{if ($screenshot@iteration % 2) === 1}<tr>{/if}
		<td style="padding-top:13px;">
			<a class="gallery" href="{$screenshot.path}" target="_blank" rel="gallery">
				<img src="{$screenshot.thumbnail}" alt="">
			</a>
		</td>
		{if ($screenshot@iteration % 2) === 0}</tr>{/if}
	{/foreach}
</table>
{/block}
{block name="script" append}
<link rel="stylesheet" type="text/css" href="styles/resource/css/base/jquery.fancybox.css?v={$REV}">
<script src="scripts/base/jquery.fancybox.js?v={$REV}" defer></script>
<script defer>
$(function() {
	$(".gallery").fancybox();
});
</script>
{/block}