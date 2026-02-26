{include file="ins_header.tpl"}
<tr>
	<td colspan="2">
		<div id="main" align="left">
		{if $update}
			<p>{$LNG.upgrade_success|sprintf:$revision}</p>
		{else}
			<p>{$LNG.upgrade_nothingtodo|sprintf:$revision}</p>
		{/if}
		</div><br><a href="../index.php"><button style="cursor: pointer;">{$LNG.upgrade_back}</button></a>
	</td>
</tr>
{include file="ins_footer.tpl"}