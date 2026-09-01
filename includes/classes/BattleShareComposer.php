<?php

namespace HiveNova\Core;

/**
 * Builds a Hive Keychain-ready battle share draft from combat report data.
 */
class BattleShareComposer
{
	public const APP_TAG = 'hivenova/battle-share';
	public const MAX_BODY_BYTES = 8192;

	/**
	 * @return list<array{author: string, permlink: string, label: string}>
	 */
	public static function suggestedCommunities(): array
	{
		return [
			[
				'author'   => '',
				'permlink' => 'hive-gaming',
				'label'    => 'Hive Gaming',
			],
			[
				'author'   => '',
				'permlink' => 'hivepizza',
				'label'    => 'Hive Pizza',
			],
		];
	}

	/**
	 * @param array<string, mixed> $combatReport
	 * @param array<string, string> $labels
	 * @return array{
	 *   canShare: bool,
	 *   reason: string,
	 *   draft: array<string, mixed>|null,
	 *   suggestedCommunities: list<array{author: string, permlink: string, label: string}>
	 * }
	 */
	public function compose(
		array $combatReport,
		string $raportId,
		int $userId,
		string $hiveAccount,
		bool $refActive,
		string $baseUrl,
		string $attackerName,
		string $defenderName,
		string $formattedTime,
		array $labels,
	): array {
		$suggested = self::suggestedCommunities();

		if (!HiveUtil::isAccountValid($hiveAccount)) {
			return [
				'canShare'             => false,
				'reason'               => 'no_hive_account',
				'draft'                => null,
				'suggestedCommunities' => $suggested,
			];
		}

		if ($raportId === '' || $attackerName === '' || $defenderName === '') {
			return [
				'canShare'             => false,
				'reason'               => 'invalid_report',
				'draft'                => null,
				'suggestedCommunities' => $suggested,
			];
		}

		$rawTime = (int) ($combatReport['time'] ?? 0);
		if ($rawTime <= 0) {
			return [
				'canShare'             => false,
				'reason'               => 'invalid_report',
				'draft'                => null,
				'suggestedCommunities' => $suggested,
			];
		}

		$resultKey = (string) ($combatReport['result'] ?? '');
		$resultText = match ($resultKey) {
			'a'     => $labels['result_attacker'] ?? 'Attacker won',
			'r'     => $labels['result_defender'] ?? 'Defender won',
			default => $labels['result_draw'] ?? 'Draw',
		};

		$attackerLoss = $this->formatNumber($combatReport['units'][0] ?? 0);
		$defenderLoss = $this->formatNumber($combatReport['units'][1] ?? 0);

		$ctaUrl = $this->buildCtaUrl($baseUrl, $userId, $refActive);
		$gameName = $this->sanitizeText((string) ($labels['game_name'] ?? ''));
		if ($gameName === '') {
			$gameName = 'Game';
		}
		$ctaLabel = $labels['cta'] ?? sprintf('Play on %s', $gameName);
		$footer = $labels['footer'] ?? sprintf('Shared via %s', $gameName);

		$attackerName = $this->sanitizeText($attackerName);
		$defenderName = $this->sanitizeText($defenderName);

		$titleFormat = $labels['title_format'] ?? '%s Battle: %s vs %s';
		$title = sprintf($titleFormat, $gameName, $attackerName, $defenderName);

		$lines = [];
		$lines[] = '# ' . $title;
		$lines[] = '';
		$lines[] = sprintf('**%s** %s **%s**', $attackerName, $labels['vs'] ?? 'vs', $defenderName);
		$lines[] = '';
		$lines[] = sprintf('**%s** %s', $labels['result_label'] ?? 'Result', $resultText);
		$lines[] = sprintf('**%s** %s', $labels['time_label'] ?? 'Time', $this->sanitizeText($formattedTime));
		$lines[] = sprintf(
			'**%s** %s',
			$labels['attacker_lost'] ?? 'Attacker losses',
			$attackerLoss
		);
		$lines[] = sprintf(
			'**%s** %s',
			$labels['defender_lost'] ?? 'Defender losses',
			$defenderLoss
		);

		$debrisLine = $this->formatResourceLine($combatReport['debris'] ?? [], $labels);
		if ($debrisLine !== '') {
			$lines[] = sprintf('**%s** %s', $labels['debris'] ?? 'Debris', $debrisLine);
		}

		if ($resultKey === 'a') {
			$stealLine = $this->formatResourceLine($combatReport['steal'] ?? [], $labels);
			if ($stealLine !== '') {
				$lines[] = sprintf('**%s** %s', $labels['steal'] ?? 'Captured', $stealLine);
			}
		}

		$lines[] = '';
		$lines[] = '---';
		$lines[] = '';
		$lines[] = sprintf('[%s](%s)', $ctaLabel, $ctaUrl);
		$lines[] = '';
		$lines[] = '*' . $this->sanitizeText($footer) . '*';

		$body = implode("\n", $lines);
		if (strlen($body) > self::MAX_BODY_BYTES) {
			$body = substr($body, 0, self::MAX_BODY_BYTES - 3) . '...';
		}

		$permlink = $this->buildPermlink($raportId, $rawTime);
		$tags = ['moon', 'hivenova', 'gaming', 'hive-pizza', 'battle'];
		$metadata = [
			'tags'   => $tags,
			'app'    => self::APP_TAG,
			'format' => 'markdown',
		];
		$jsonMetadata = (string) json_encode($metadata, JSON_UNESCAPED_SLASHES);
		if ($jsonMetadata === '') {
			return [
				'canShare'             => false,
				'reason'               => 'invalid_report',
				'draft'                => null,
				'suggestedCommunities' => $suggested,
			];
		}

		return [
			'canShare'             => true,
			'reason'               => '',
			'suggestedCommunities' => $suggested,
			'draft'                => [
				'hive_account'     => strtolower($hiveAccount),
				'title'            => $title,
				'body'             => $body,
				'permlink'         => $permlink,
				'tags'             => $tags,
				'parent_author'    => '',
				'parent_permlink'  => $tags[0],
				'json_metadata'    => $jsonMetadata,
				'cta_url'          => $ctaUrl,
			],
		];
	}

	public function buildCtaUrl(string $baseUrl, int $userId, bool $refActive): string
	{
		$url = rtrim($baseUrl, '/') . '/index.php';
		if ($refActive && $userId > 0) {
			$url .= '?ref=' . $userId;
		}

		return $url;
	}

	public function buildPermlink(string $raportId, int $timestamp): string
	{
		$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $raportId) ?? '');
		$slug = trim($slug, '-');
		if ($slug === '') {
			$slug = 'battle';
		}
		$slug = substr($slug, 0, 200);

		return sprintf('hivenova-battle-%s-%d', $slug, $timestamp);
	}

	/**
	 * @param array<int|string, int|float|string> $resources
	 * @param array<string, string> $labels
	 */
	private function formatResourceLine(array $resources, array $labels): string
	{
		$parts = [];
		foreach ([901, 902, 903] as $elementId) {
			$amount = (float) ($resources[$elementId] ?? 0);
			if ($amount <= 0) {
				continue;
			}
			$name = $labels['resource_' . $elementId] ?? ('R' . $elementId);
			$parts[] = $this->formatNumber($amount) . ' ' . $this->sanitizeText($name);
		}

		return implode(', ', $parts);
	}

	private function formatNumber(int|float|string $value): string
	{
		return number_format((float) $value, 0, '.', ',');
	}

	private function sanitizeText(string $value): string
	{
		$value = strip_tags($value);
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

		return trim($value);
	}
}
