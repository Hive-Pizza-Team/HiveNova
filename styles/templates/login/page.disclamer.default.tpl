{block name="title"}{$documentTitle}{/block}
{block name="content"}
<table id="disclamerTable">
	{if $disclamerAddress != ''}
	<tr>
		<td style="width:50%;text-align:left;">{$LNG.disclamerLabelAddress}</td><td style="width:50%;text-align:left;">{$disclamerAddress}</td>
	</tr>
	{/if}
	{if $disclamerPhone != ''}
	<tr>
		<td style="width:50%;text-align:left;">{$LNG.disclamerLabelPhone}</td><td style="width:50%;text-align:left;">{$disclamerPhone}</td>
	</tr>
	{/if}
	{if $disclamerMail != ''}
	<tr>
		<td style="width:50%;text-align:left;">{$LNG.disclamerLabelMail}</td><td style="width:50%;text-align:left;"><a href="{$disclamerMailHref}">{$disclamerMail}</a></td>
	</tr>
	{/if}
	{if $disclamerDiscordUrl != ''}
	<tr>
		<td style="width:50%;text-align:left;">{$LNG.disclamerLabelDiscord}</td><td style="width:50%;text-align:left;"><a href="{$disclamerDiscordUrl}" target="_blank" rel="noopener noreferrer">{$disclamerDiscordUrl}</a></td>
	</tr>
	{/if}
	{if $disclamerNotice != ''}
	<tr>
		<td colspan="2" style="text-align:left;"><p><br></p></td>
	</tr>
	<tr>
		<td colspan="2">{$LNG.disclamerLabelNotice}</td>
	</tr>
	<tr>
		<td colspan="2" style="text-align:left;">{$disclamerNotice}</td>
	</tr>
	{/if}
</table>
{/block}
