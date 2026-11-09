<?php

namespace HiveNova\Core;

/**
 * Public Battle Hall list/report helpers for login SEO / signup CTA pages (#645).
 */
class PublicBattleHallService
{
	/**
	 * Absolute or root-relative public URL for a Hall-of-Fame combat report.
	 */
	public static function publicReportUrl(string $rid, string $lang = '', string $defaultLang = 'en'): string
	{
		$query = [
			'page'   => 'battleHall',
			'raport' => $rid,
		];
		if ($lang !== '' && $lang !== $defaultLang) {
			$query['lang'] = $lang;
		}

		return 'index.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Absolute or root-relative Register CTA (optional ref preserved by caller).
	 *
	 * @param array<string, scalar> $extraQuery
	 */
	public static function registerCtaUrl(string $lang = '', string $defaultLang = 'en', array $extraQuery = []): string
	{
		$query = array_merge(['page' => 'register'], $extraQuery);
		if ($lang !== '' && $lang !== $defaultLang) {
			$query['lang'] = $lang;
		}

		return 'index.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * @param list<array<string, mixed>> $hallRows
	 * @return list<array{result: mixed, time: string, units: mixed, rid: string, attacker: mixed, defender: mixed, reportUrl: string}>
	 */
	public static function decorateHallList(array $hallRows, string $lang = '', string $defaultLang = 'en'): array
	{
		$list = [];
		foreach ($hallRows as $row) {
			$rid = (string) ($row['rid'] ?? '');
			$list[] = [
				'result'    => $row['result'] ?? '',
				'time'      => (string) ($row['time'] ?? ''),
				'units'     => $row['units'] ?? 0,
				'rid'       => $rid,
				'attacker'  => $row['attacker'] ?? '',
				'defender'  => $row['defender'] ?? '',
				'reportUrl' => $rid !== '' ? self::publicReportUrl($rid, $lang, $defaultLang) : '',
			];
		}

		return $list;
	}
}
