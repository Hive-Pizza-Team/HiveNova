{block name="title" prepend}{$LNG.lm_faq}{/block}
{block name="content"}
<table>
	<tr>
		<th>{$LNG.faq_overview}</th>
	</tr>
	<tr>
		<td class="left">
		{foreach $faqIndex as $categoryRow}
			<h2>{$categoryRow.category}</h2>
			<ul>
			{foreach $categoryRow.questions as $questionRow}
				<li><a class="faq-index-link" href="game.php?page=questions&amp;mode=single&amp;categoryID={$questionRow.categoryId}&amp;questionID={$questionRow.questionId}">{$questionRow.title}</a></li>
			{/foreach}
			</ul>
		{/foreach}
		</td>
	</tr>
</table>
{/block}
