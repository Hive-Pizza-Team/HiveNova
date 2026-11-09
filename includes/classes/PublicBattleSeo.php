<?php

namespace HiveNova\Core;

/**
 * SEO + signup CTA helpers for public Battle Hall and battle report pages.
 *
 * Keeps moonsiege host hard-coding out of this class so parallel PublicSeo
 * CANONICAL_BASE work (#644 / #668) can land without merge conflicts.
 */
class PublicBattleSeo
{
	public const CTA_REF = 1;
	public const SRC_HALL = 'battlehall';
	public const SRC_REPORT = 'battlereport';

	/** Dedicated 1200×630 share card (not the 1024×768 login art). */
	public const OG_IMAGE_PATH = 'styles/resource/images/login/battle-hall-og.png';
	public const OG_IMAGE_WIDTH = 1200;
	public const OG_IMAGE_HEIGHT = 630;

	/**
	 * Relative signup CTA. Lobby captures `ref`; `src` is preserved for analytics.
	 */
	public static function signupCtaUrl(string $src = self::SRC_HALL): string
	{
		$allowed = [self::SRC_HALL, self::SRC_REPORT];
		$src = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $src) ?? '');
		if (!in_array($src, $allowed, true)) {
			$src = self::SRC_HALL;
		}

		return 'index.php?'.http_build_query([
			'ref' => self::CTA_REF,
			'src' => $src,
		], '', '&', PHP_QUERY_RFC3986);
	}

	public static function reportPageQuery(string $rid): array
	{
		return [
			'page' => 'battle',
			'rid'  => $rid,
		];
	}

	public static function reportUrl(string $rid, string $lang = '', string $defaultLang = 'en'): string
	{
		$query = self::reportPageQuery($rid);
		if ($lang !== '' && $lang !== $defaultLang) {
			$query['lang'] = $lang;
		}

		return 'index.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	public static function reportCanonicalUrl(
		string $basePath,
		string $rid,
		string $lang,
		string $defaultLang = 'en',
	): string {
		return PublicSeo::canonicalUrl($basePath, 'battle', $lang, $defaultLang, ['rid' => $rid]);
	}

	/**
	 * @param array<string, string> $languages
	 * @return array<string, string>
	 */
	public static function reportHreflangUrls(
		string $basePath,
		string $rid,
		array $languages,
		string $defaultLang = 'en',
	): array {
		return PublicSeo::hreflangUrls($basePath, 'battle', $languages, $defaultLang, ['rid' => $rid]);
	}

	/**
	 * @param object|array<string, string> $LNG
	 */
	public static function hallDocumentTitle(string $gameName, string $universeName, $LNG): string
	{
		$template = self::lng($LNG, 'metaTitleBattleHallUni', '%s Battle Hall — %s');
		$universeName = $universeName !== '' ? $universeName : 'Universe';

		return sprintf($template, $gameName, $universeName);
	}

	/**
	 * @param object|array<string, string> $LNG
	 */
	public static function hallMetaDescription(string $gameName, string $universeName, $LNG): string
	{
		$template = self::lng(
			$LNG,
			'metaDescriptionBattleHallUni',
			'Biggest fleet battles in %2$s on %1$s. Open a report, then play free — room to explore and space to claim.'
		);
		$universeName = $universeName !== '' ? $universeName : 'Universe';

		return sprintf($template, $gameName, $universeName);
	}

	/**
	 * @param object|array<string, string> $LNG
	 */
	public static function reportDocumentTitle(
		string $attacker,
		string $defender,
		float|int|string $units,
		string $gameName,
		$LNG,
	): string {
		$template = self::lng(
			$LNG,
			'metaTitleBattleReport',
			'%s vs %s: %s units destroyed | %s Battle Hall'
		);

		return sprintf(
			$template,
			self::sanitizeName($attacker),
			self::sanitizeName($defender),
			self::formatCompactUnits($units),
			$gameName
		);
	}

	/**
	 * @param object|array<string, string> $LNG
	 * @param array<int|string, mixed> $debris
	 */
	public static function reportMetaDescription(
		string $attacker,
		string $defender,
		string $resultKey,
		float|int|string $units,
		string $formattedDate,
		array $debris,
		string $gameName,
		$LNG,
	): string {
		$resultText = match ($resultKey) {
			'a'     => self::lng($LNG, 'tkb_gewinner', 'Winner').': '.self::sanitizeName($attacker),
			'r'     => self::lng($LNG, 'tkb_gewinner', 'Winner').': '.self::sanitizeName($defender),
			default => self::lng($LNG, 'sys_both_won', 'Draw'),
		};

		$debrisLine = self::formatDebrisSummary($debris, $LNG);
		$template = self::lng(
			$LNG,
			'metaDescriptionBattleReport',
			'%s vs %s — %s, %s units destroyed%s. %s Battle Hall. Play free on the frontier.'
		);
		$debrisSuffix = $debrisLine !== '' ? ', debris '.$debrisLine : '';
		$dateSuffix = $formattedDate !== '' ? ' ('.$formattedDate.')' : '';

		return sprintf(
			$template,
			self::sanitizeName($attacker),
			self::sanitizeName($defender),
			$resultText.$dateSuffix,
			self::formatCompactUnits($units),
			$debrisSuffix,
			$gameName
		);
	}

	public static function formatCompactUnits(float|int|string $value): string
	{
		$n = (float) $value;
		if ($n >= 1000000000) {
			$scaled = $n / 1000000000;

			return self::trimCompact(number_format($scaled, 1, '.', '')).'B';
		}
		if ($n >= 1000000) {
			$scaled = $n / 1000000;

			return self::trimCompact(number_format($scaled, 1, '.', '')).'M';
		}
		if ($n >= 1000) {
			$scaled = $n / 1000;

			return self::trimCompact(number_format($scaled, 1, '.', '')).'K';
		}

		return number_format($n, 0, '.', ',');
	}

	/**
	 * @param array<int|string, mixed> $debris
	 * @param object|array<string, string> $LNG
	 */
	public static function formatDebrisSummary(array $debris, $LNG): string
	{
		$tech = [];
		if (is_array($LNG) && isset($LNG['tech']) && is_array($LNG['tech'])) {
			$tech = $LNG['tech'];
		} elseif (is_object($LNG) && isset($LNG['tech']) && is_array($LNG['tech'])) {
			$tech = $LNG['tech'];
		}

		$labels = [
			RESOURCE_METAL     => (string) ($tech[RESOURCE_METAL] ?? 'metal'),
			RESOURCE_CRYSTAL   => (string) ($tech[RESOURCE_CRYSTAL] ?? 'crystal'),
			RESOURCE_DEUTERIUM => (string) ($tech[RESOURCE_DEUTERIUM] ?? 'deuterium'),
		];

		$parts = [];
		foreach ($labels as $id => $label) {
			$amount = (float) ($debris[$id] ?? 0);
			if ($amount <= 0) {
				continue;
			}
			$parts[] = self::formatCompactUnits($amount).' '.$label;
		}

		return implode(', ', $parts);
	}

	public static function ogImageUrl(string $basePath): string
	{
		return rtrim($basePath, '/').'/'.ltrim(self::OG_IMAGE_PATH, '/');
	}

	public static function sanitizeName(string $name): string
	{
		$name = strip_tags($name);
		$name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$name = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $name) ?? '';
		$name = trim($name);

		return $name !== '' ? $name : 'Unknown';
	}

	/**
	 * @param object|array<string, mixed> $LNG
	 */
	private static function lng($LNG, string $key, ?string $fallback): ?string
	{
		if (is_array($LNG) && array_key_exists($key, $LNG)) {
			return (string) $LNG[$key];
		}
		if (is_object($LNG) && isset($LNG[$key])) {
			return (string) $LNG[$key];
		}

		return $fallback;
	}

	private static function trimCompact(string $formatted): string
	{
		return rtrim(rtrim($formatted, '0'), '.');
	}
}
