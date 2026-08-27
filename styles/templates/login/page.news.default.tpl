{block name="title"}{$documentTitle}{/block}
{block name="content"}
{foreach $newsList as $newsRow}
{if !$newsRow@first}<hr>{/if}
<h2>{$newsRow.title}</h2><br>
<div class="info">{$newsRow.from}</div>
<br><div><p>{$newsRow.text}</p></div>
{foreachelse}
<p>{$LNG.news_does_not_exist}</p>
{/foreach}
{/block}