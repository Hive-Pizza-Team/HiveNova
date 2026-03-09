{block name="title" prepend}{$LNG.fcm_info}{/block}
{block name="content"}
<div class="contentbox error-message">
	<p>{$message|default:''}</p>
	{if !empty($redirectButtons)}<p>{foreach $redirectButtons as $button}<a href="{$button.url|default:''}"><button>{$button.label|default:''}</button></a>{/foreach}</p>{/if}
</div>
{/block}