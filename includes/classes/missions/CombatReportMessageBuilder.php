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
			<td colspan="2"><a href="game.php?page=raport&raport=%s"><span class="%s">%s %s (%s)</span></a></td>
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

	/**
	 * Drop target=_blank on combat-report anchors so PWA / in-app Back can return to the game.
	 * Existing inbox rows still store the old attribute in message_text.
	 */
	public static function sameTabHtml(string $html): string
	{
		$rewritten = preg_replace_callback(
			'/<a\b[^>]*>/i',
			static function (array $match): string {
				$tag = $match[0];
				if (!preg_match('/href\s*=\s*(["\'])[^"\']*(?:page=raport|CombatReport\.php)/i', $tag)) {
					return $tag;
				}

				return preg_replace('/\s+target\s*=\s*(?:(["\'])_blank\1|_blank)/i', '', $tag) ?? $tag;
			},
			$html
		);

		return is_string($rewritten) ? $rewritten : $html;
	}
}
