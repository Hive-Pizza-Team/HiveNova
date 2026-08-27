<?php

namespace HiveNova\Mission;

class CombatReportMessageBuilder
{
	public static function template(): string
	{
		$html = <<<HTML
<div class="raportMessage">
	<table>
		<tr>
			<td colspan="2"><a href="game.php?page=raport&raport=%s" target="_blank"><span class="%s">%s %s (%s)</span></a></td>
		</tr>
		<tr>
			<td>%s</td><td><span class="%s">%s: %s</span>&nbsp;<span class="%s">%s: %s</span></td>
		</tr>
		<tr>
			<td>%s</td><td><span>%s:&nbsp;<span class="reportSteal element901">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="reportSteal element902">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="reportSteal element903">%s</span></span></td>
		</tr>
		<tr>
			<td>%s</td><td><span>%s:&nbsp;<span class="reportDebris element901">%s</span>&nbsp;</span><span>%s:&nbsp;<span class="reportDebris element902">%s</span></span></td>
		</tr>
	</table>
</div>
HTML;

		return str_replace(array("\n", "\t", "\r"), '', $html);
	}
}
