{block name="title" prepend}{$LNG.whats_new_title}{/block}
{block name="content"}
<div class="whats-new">
	<h2 class="whats-new__title">{$LNG.whats_new_title}</h2>
	<p class="whats-new__intro">{$LNG.whats_new_intro}</p>

	{if $releases|@count == 0}
		<p class="whats-new__empty">{$LNG.whats_new_empty}</p>
	{else}
		{foreach $releases as $release}
		<section class="whats-new__release{if $release.unseen} whats-new__release--unseen{/if}">
			<header class="whats-new__release-header">
				<h3 class="whats-new__version">{$release.version|escape:'html'}</h3>
				{if $release.unseen}
					<span class="whats-new__new-label">{$LNG.whats_new_new_label}</span>
				{/if}
			</header>
			{if $release.items|@count > 0}
			<ul class="whats-new__items">
				{foreach $release.items as $item}
				<li>{$item|escape:'html'}</li>
				{/foreach}
			</ul>
			{else}
			<p class="whats-new__empty">{$LNG.whats_new_empty}</p>
			{/if}
		</section>
		{/foreach}
	{/if}
</div>
{/block}
