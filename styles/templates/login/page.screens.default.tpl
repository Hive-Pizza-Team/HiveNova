{block name="title" prepend}{$LNG.siteTitleScreens}{/block}
{block name="content"}
<table>
	{foreach $screenshots as $screenshot}
		{if ($screenshot@iteration % 2) === 1}<tr>{/if}
		<td style="padding-top:13px;">
			<a href="{$screenshot.path}" target="_blank" rel="noopener noreferrer">
				<img src="{$screenshot.thumbnail}" alt="">
			</a>
		</td>
		{if ($screenshot@iteration % 2) === 0}</tr>{/if}
	{/foreach}
</table>
{/block}
