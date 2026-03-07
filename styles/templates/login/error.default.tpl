{block name="title" prepend}{$LNG.fcm_info}{/block}
{block name="content"}
<table class="table519">
	<tr>
		<td><p>{$message|default:''}</p>{if !empty($redirectButtons)}<p>{foreach $redirectButtons as $button}<a href="{$button.url|default:''}"><button>{$button.label|default:''}</button></a>{/foreach}</p>{/if}</td>
	</tr>
</table>
{/block}