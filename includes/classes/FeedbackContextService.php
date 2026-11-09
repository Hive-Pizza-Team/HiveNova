<?php

namespace HiveNova\Core;

/**
 * Builds sanitized feedback context and compose URLs for support tickets / GitHub issues.
 */
class FeedbackContextService
{
	public const GITHUB_NEW_ISSUE_URL = 'https://github.com/Hive-Pizza-Team/HiveNova/issues/new';

	public const SUPPORT_COMPOSE_PATH = 'game.php?page=ticket&mode=create';

	public const DEFAULT_SUBJECT = 'Feedback / bug report';

	public const CONTEXT_HEADING = 'Auto-captured context (do not remove):';

	/** @var list<string> */
	private const SENSITIVE_QUERY_KEYS = [
		'password',
		'passwd',
		'pass',
		'token',
		'session',
		'sid',
		'phpsessid',
		'csrf',
		'hash',
		'key',
		'secret',
		'api_key',
		'access_token',
		'refresh_token',
		'auth',
		'authorization',
		'validationkey',
		'k',
	];

	/**
	 * Strip credentials/tokens from a page URL (absolute or relative).
	 */
	public static function sanitizePageUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '') {
			return '';
		}

		$parts = parse_url($url);
		if ($parts === false) {
			return '';
		}

		$query = [];
		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
			foreach (array_keys($query) as $key) {
				if (in_array(strtolower((string) $key), self::SENSITIVE_QUERY_KEYS, true)) {
					unset($query[$key]);
				}
			}
		}

		$rebuilt = '';
		if (!empty($parts['scheme']) && !empty($parts['host'])) {
			$rebuilt .= $parts['scheme'].'://'.$parts['host'];
			if (!empty($parts['port'])) {
				$rebuilt .= ':'.$parts['port'];
			}
		}

		$rebuilt .= $parts['path'] ?? '';
		if ($query !== []) {
			$rebuilt .= '?'.http_build_query($query);
		}

		return $rebuilt;
	}

	/**
	 * @param array{
	 *     pageUrl?: string|null,
	 *     universe?: int|string|null,
	 *     userId?: int|string|null,
	 *     username?: string|null,
	 *     planet?: string|null,
	 *     userAgent?: string|null,
	 *     language?: string|null,
	 *     version?: string|null,
	 *     commit?: string|null,
	 *     timestamp?: int|string|null
	 * } $context
	 */
	public static function formatContextBlock(array $context): string
	{
		$lines = [];
		$pageUrl = self::sanitizePageUrl((string) ($context['pageUrl'] ?? ''));
		if ($pageUrl !== '') {
			$lines[] = 'Page: '.$pageUrl;
		}

		$universe = self::scalarToTrimmedString($context['universe'] ?? null);
		if ($universe !== '') {
			$lines[] = 'Universe: '.$universe;
		}

		$userId = self::scalarToTrimmedString($context['userId'] ?? null);
		if ($userId !== '') {
			$lines[] = 'User ID: '.$userId;
		}

		$username = self::scalarToTrimmedString($context['username'] ?? null);
		if ($username !== '') {
			$lines[] = 'Username: '.$username;
		}

		$planet = self::scalarToTrimmedString($context['planet'] ?? null);
		if ($planet !== '') {
			$lines[] = 'Planet: '.$planet;
		}

		$userAgent = self::scalarToTrimmedString($context['userAgent'] ?? null);
		if ($userAgent !== '') {
			$lines[] = 'Browser: '.$userAgent;
		}

		$language = self::scalarToTrimmedString($context['language'] ?? null);
		if ($language !== '') {
			$lines[] = 'Language: '.$language;
		}

		$version = self::scalarToTrimmedString($context['version'] ?? null);
		if ($version !== '') {
			$lines[] = 'Version: '.$version;
		}

		$commit = self::scalarToTrimmedString($context['commit'] ?? null);
		if ($commit !== '') {
			$lines[] = 'Commit: '.$commit;
		}

		$timestamp = $context['timestamp'] ?? null;
		if (is_int($timestamp) || (is_string($timestamp) && ctype_digit($timestamp))) {
			$lines[] = 'Time (UTC): '.gmdate('c', (int) $timestamp);
		} elseif (is_string($timestamp) && trim($timestamp) !== '') {
			$lines[] = 'Time (UTC): '.trim($timestamp);
		}

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function composeMessage(array $context, string $playerNotes = ''): string
	{
		$block = self::formatContextBlock($context);
		$notes = trim($playerNotes);
		$parts = [];
		if ($notes !== '') {
			$parts[] = $notes;
		}
		if ($block !== '') {
			$parts[] = self::CONTEXT_HEADING;
			$parts[] = $block;
		}

		return implode("\n\n", $parts);
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function supportComposeHref(array $context, string $subject = self::DEFAULT_SUBJECT): string
	{
		$subject = trim($subject) !== '' ? trim($subject) : self::DEFAULT_SUBJECT;
		$query = http_build_query([
			'page'    => 'ticket',
			'mode'    => 'create',
			'subject' => $subject,
			'message' => self::composeMessage($context),
		]);

		return 'game.php?'.$query;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function githubIssueHref(array $context, string $title = self::DEFAULT_SUBJECT): string
	{
		$title = trim($title) !== '' ? trim($title) : self::DEFAULT_SUBJECT;
		$query = http_build_query([
			'title' => $title,
			'body'  => self::composeMessage($context),
		]);

		return self::GITHUB_NEW_ISSUE_URL.'?'.$query;
	}

	/**
	 * Prefer in-game support compose when available; otherwise GitHub new-issue.
	 *
	 * @param array<string, mixed> $context
	 * @return array{href: string, external: bool, channel: 'support'|'github'}
	 */
	public static function resolveEntryPoint(array $context, bool $supportAvailable): array
	{
		if ($supportAvailable) {
			return [
				'href'     => self::supportComposeHref($context),
				'external' => false,
				'channel'  => 'support',
			];
		}

		return [
			'href'     => self::githubIssueHref($context),
			'external' => true,
			'channel'  => 'github',
		];
	}

	/**
	 * Planet label for context: "Name [g:s:p]".
	 *
	 * @param array{name?: string|null, galaxy?: int|string|null, system?: int|string|null, planet?: int|string|null} $planet
	 */
	public static function formatPlanetLabel(array $planet): string
	{
		$name = self::scalarToTrimmedString($planet['name'] ?? null);
		$galaxy = self::scalarToTrimmedString($planet['galaxy'] ?? null);
		$system = self::scalarToTrimmedString($planet['system'] ?? null);
		$slot = self::scalarToTrimmedString($planet['planet'] ?? null);
		if ($name === '' && ($galaxy === '' || $system === '' || $slot === '')) {
			return '';
		}

		$coords = ($galaxy !== '' && $system !== '' && $slot !== '')
			? '['.$galaxy.':'.$system.':'.$slot.']'
			: '';

		if ($name === '') {
			return $coords;
		}

		return $coords !== '' ? $name.' '.$coords : $name;
	}

	private static function scalarToTrimmedString(mixed $value): string
	{
		if ($value === null || is_bool($value) || is_array($value)) {
			return '';
		}

		return trim((string) $value);
	}
}
