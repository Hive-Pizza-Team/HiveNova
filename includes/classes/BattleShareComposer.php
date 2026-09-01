<?php

namespace HiveNova\Core;

/**
 * Builds a Hive Keychain-ready battle share draft from combat report data.
 */
class BattleShareComposer
{
	public const APP_TAG = 'hivenova/battle-share';
	public const MAX_BODY_BYTES = 8192;
	public const SNAP_CHAR_LIMIT = 280;
	public const SNAP_CONTAINER_AUTHOR = 'peak.snaps';

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
		string $raportMode = '',
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

		$ctaUrl = $this->buildCtaUrl($baseUrl, $raportId, $userId, $refActive, $raportMode);
		$gameName = $this->sanitizeText((string) ($labels['game_name'] ?? ''));
		if ($gameName === '') {
			$gameName = 'Game';
		}

		$attackerName = $this->sanitizeText($attackerName);
		$defenderName = $this->sanitizeText($defenderName);

		$titleFormat = $labels['title_format'] ?? '%s Battle: %s vs %s';
		$previewTitle = sprintf($titleFormat, $gameName, $attackerName, $defenderName);
		$formattedTime = $this->sanitizeText($formattedTime);

		$body = $this->buildSnapBody(
			$gameName,
			$attackerName,
			$defenderName,
			$resultKey,
			$resultText,
			$attackerLoss,
			$defenderLoss,
			$formattedTime,
			$combatReport['debris'] ?? [],
			$combatReport['steal'] ?? [],
			$combatReport['koords'] ?? [],
			$ctaUrl
		);

		$permlink = $this->buildSnapPermlink();
		$tags = ['snaps', 'hivenova', 'gaming', 'hive-pizza', 'battle'];
		$metadata = [
			'tags'  => $tags,
			'app'   => self::APP_TAG,
			'image' => [],
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
				'title'            => '',
				'preview_title'    => $previewTitle,
				'body'             => $body,
				'permlink'         => $permlink,
				'tags'             => $tags,
				'parent_author'    => self::SNAP_CONTAINER_AUTHOR,
				'parent_permlink'  => '',
				'json_metadata'    => $jsonMetadata,
				'cta_url'          => $ctaUrl,
				'snap_mode'        => true,
			],
		];
	}

	public function buildCtaUrl(
		string $baseUrl,
		string $raportId,
		int $userId,
		bool $refActive,
		string $raportMode = '',
	): string {
		$params = [
			'page'   => 'raport',
			'raport' => $raportId,
		];
		if ($raportMode !== '') {
			$params['mode'] = $raportMode;
		}
		if ($refActive && $userId > 0) {
			$params['ref'] = (string) $userId;
		}

		return rtrim($baseUrl, '/') . '/game.php?' . http_build_query($params);
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

	public function buildSnapPermlink(): string
	{
		return 're-peaksnaps-' . base_convert((string) (int) (microtime(true) * 1000), 10, 36);
	}

	private function buildSnapBody(
		string $gameName,
		string $attackerName,
		string $defenderName,
		string $resultKey,
		string $resultText,
		string $attackerLoss,
		string $defenderLoss,
		string $formattedTime,
		array $debris,
		array $steal,
		array $koords,
		string $ctaUrl,
	): string {
		$lines = [];
		$lines[] = sprintf('⚔️ %s: %s vs %s', $gameName, $attackerName, $defenderName);
		$lines[] = sprintf('%s · Loss %s/%s', $resultText, $attackerLoss, $defenderLoss);

		$debrisLine = $this->formatCompactResourceLine($debris);
		if ($debrisLine !== '') {
			$lines[] = '💥 ' . $debrisLine;
		}

		if ($resultKey === 'a') {
			$stealLine = $this->formatCompactResourceLine($steal);
			if ($stealLine !== '') {
				$lines[] = '📦 ' . $stealLine;
			}
		}

		$location = $this->formatKoords($koords);
		if ($location !== '') {
			$lines[] = '📍 ' . $location;
		}

		if ($formattedTime !== '') {
			$lines[] = '🕐 ' . $formattedTime;
		}

		$lines[] = $ctaUrl;

		$optionalCount = count($lines) - 2;
		while ($optionalCount > 0 && strlen(implode("\n", $lines)) > self::SNAP_CHAR_LIMIT) {
			array_splice($lines, count($lines) - 2, 1);
			$optionalCount--;
		}

		$body = implode("\n", $lines);
		if (strlen($body) > self::SNAP_CHAR_LIMIT) {
			$body = substr($body, 0, self::SNAP_CHAR_LIMIT - 3) . '...';
		}

		return $body;
	}

	/**
	 * @param array<int|string, int|float|string> $resources
	 */
	private function formatCompactResourceLine(array $resources): string
	{
		$parts = [];
		foreach ([901 => 'M', 902 => 'C', 903 => 'D'] as $elementId => $abbrev) {
			$amount = (float) ($resources[$elementId] ?? 0);
			if ($amount <= 0) {
				continue;
			}
			$parts[] = $this->formatCompactNumber($amount) . $abbrev;
		}

		return implode(' · ', $parts);
	}

	/**
	 * @param array<int|string, int|float|string> $koords
	 */
	private function formatKoords(array $koords): string
	{
		if (count($koords) < 3) {
			return '';
		}

		$galaxy = (int) ($koords[0] ?? 0);
		$system = (int) ($koords[1] ?? 0);
		$planet = (int) ($koords[2] ?? 0);
		if ($galaxy <= 0 && $system <= 0 && $planet <= 0) {
			return '';
		}

		return sprintf('[%d:%d:%d]', $galaxy, $system, $planet);
	}

	private function formatCompactNumber(float $value): string
	{
		if ($value >= 1000000) {
			$n = $value / 1000000;
			return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') . 'M';
		}
		if ($value >= 1000) {
			$n = $value / 1000;
			return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.') . 'K';
		}

		return number_format($value, 0, '.', ',');
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
