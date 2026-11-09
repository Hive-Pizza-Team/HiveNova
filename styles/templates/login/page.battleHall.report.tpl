{block name="title"}{$documentTitle}{/block}
{block name="content"}
<nav class="bh-public-report-nav">
	<a href="{$hallIndexUrl}">← {$hallIndexLabel}</a>
</nav>
<section class="bh-public-cta" aria-label="{$registerCtaLabel|escape:'html'}">
	<p class="bh-public-cta__lead">{$registerCtaLead}</p>
	<p><a class="bh-public-cta__btn" href="{$registerCtaUrl}">{$registerCtaLabel}</a></p>
</section>
<article class="bh-public-report">
	<h1>{$pageTitle|escape:'html'}</h1>
	<p class="bh-public-report__meta">{$Raport.time|escape:'html'}</p>
	<p class="bh-public-report__vs">
		<span class="bh-public-report__attacker">{$Info[0]|escape:'html'}</span>
		<strong> VS </strong>
		<span class="bh-public-report__defender">{$Info[1]|escape:'html'}</span>
	</p>
	{if $fullReportUrl}
	<p><a class="bh-public-report__full" href="{$fullReportUrl}">{$fullReportLabel}</a></p>
	{/if}
</article>
{/block}
