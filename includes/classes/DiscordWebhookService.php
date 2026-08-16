<?php

namespace HiveNova\Core;

/**
 * Per-alliance Discord incoming-webhook alerts for hostile fleets.
 * Fail-open: never throws to callers. Webhook URLs are secrets — do not log them.
 */
class DiscordWebhookService
{
	private const USER_AGENT = 'DiscordBot (https://github.com/Hive-Pizza-Team/HiveNova, 1.0)';
	private const USERNAME = 'HiveNova';
	private const CONNECT_TIMEOUT = 1;
	private const TIMEOUT = 1;

	private const MISSION_NAMES = [
		1  => 'Attack',
		2  => 'ACS',
		6  => 'Espionage',
		9  => 'Destruction',
		10 => 'Missile attack',
	];

	private const PLANET_TYPES = [
		1 => 'planet',
		3 => 'moon',
	];

	/** @var callable|null fn(string $url, string $json): int */
	private static $poster = null;

	public static function setPoster(?callable $poster): void
	{
		self::$poster = $poster;
	}

	/**
	 * Validate and rebuild a Discord execute URL. Returns null if rejected.
	 */
	public static function normalizeUrl(string $raw): ?string
	{
		$raw = trim($raw);
		if ($raw === '' || strlen($raw) > 512) {
			return null;
		}

		$parts = parse_url($raw);
		if (!is_array($parts)) {
			return null;
		}

		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		$host   = strtolower((string) ($parts['host'] ?? ''));
		$path   = (string) ($parts['path'] ?? '');

		if ($scheme !== 'https') {
			return null;
		}
		if (isset($parts['port']) || isset($parts['user']) || isset($parts['pass'])) {
			return null;
		}

		$allowedHosts = [
			'discord.com',
			'ptb.discord.com',
			'canary.discord.com',
			'discordapp.com',
			'ptb.discordapp.com',
			'canary.discordapp.com',
		];
		if (!in_array($host, $allowedHosts, true)) {
			return null;
		}

		if (!preg_match('#^/api(?:/v(?:9|10))?/webhooks/([0-9]{17,20})/([A-Za-z0-9_-]{20,})$#', $path, $m)) {
			return null;
		}

		return 'https://discord.com/api/webhooks/' . $m[1] . '/' . $m[2];
	}

	/**
	 * Owner admin form: empty keeps the current URL unless $clear is set.
	 *
	 * @return string|false Normalized URL to store, or false when the paste is invalid.
	 */
	public static function resolveAdminInput(string $submitted, bool $clear, string $current): string|false
	{
		if ($clear) {
			return '';
		}

		$submitted = trim($submitted);
		if ($submitted === '') {
			return $current;
		}

		$normalized = self::normalizeUrl($submitted);
		return $normalized ?? false;
	}

	public static function formatIncoming(string $username, int $mission, int $galaxy, int $system, int $planet, int $planetType): string
	{
		return sprintf(
			'Incoming %s to %s at %s',
			self::missionName($mission),
			$username,
			self::coords($galaxy, $system, $planet, $planetType)
		);
	}

	public static function formatCombat(string $username, int $mission, int $galaxy, int $system, int $planet, int $planetType): string
	{
		return sprintf(
			'Combat resolved (%s) involving %s at %s',
			self::missionName($mission),
			$username,
			self::coords($galaxy, $system, $planet, $planetType)
		);
	}

	public static function notifyIncomingHostile(
		int $targetUserId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType
	): void {
		self::notify($targetUserId, $mission, $galaxy, $system, $planet, $planetType, true);
	}

	public static function notifyCombatResolved(
		int $targetUserId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType
	): void {
		self::notify($targetUserId, $mission, $galaxy, $system, $planet, $planetType, false);
	}

	private static function notify(
		int $targetUserId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType,
		bool $incoming
	): void {
		try {
			if ($targetUserId <= 0) {
				return;
			}

			$row = self::loadDefender($targetUserId);
			if ($row === null) {
				return;
			}

			$webhook = self::normalizeUrl((string) ($row['ally_discord_webhook'] ?? ''));
			if ($webhook === null) {
				return;
			}

			$username = (string) ($row['username'] ?? 'Unknown');
			$content  = $incoming
				? self::formatIncoming($username, $mission, $galaxy, $system, $planet, $planetType)
				: self::formatCombat($username, $mission, $galaxy, $system, $planet, $planetType);

			self::post($webhook, $content);
		} catch (\Throwable $e) {
			return;
		}
	}

	/**
	 * @return array{username: string, ally_id: int, ally_discord_webhook: string}|null
	 */
	private static function loadDefender(int $userId): ?array
	{
		$db = Database::get();
		$row = $db->selectSingle(
			'SELECT u.username, u.ally_id, a.ally_discord_webhook
			FROM %%USERS%% u
			LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
			WHERE u.id = :userId',
			[':userId' => $userId]
		);

		if (!is_array($row) || empty($row['ally_id'])) {
			return null;
		}

		return $row;
	}

	private static function post(string $url, string $content): void
	{
		$payload = json_encode([
			'username'         => self::USERNAME,
			'allowed_mentions' => ['parse' => []],
			'content'          => $content,
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

		$poster = self::$poster ?? self::defaultPoster(...);
		$poster($url, $payload);
	}

	private static function defaultPoster(string $url, string $json): int
	{
		if (!function_exists('curl_init')) {
			return 0;
		}

		$ch = curl_init($url);
		if ($ch === false) {
			return 0;
		}

		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $json,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'User-Agent: ' . self::USER_AGENT,
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_TIMEOUT        => self::TIMEOUT,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		]);

		curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		return $code;
	}

	private static function missionName(int $mission): string
	{
		return self::MISSION_NAMES[$mission] ?? ('Mission ' . $mission);
	}

	private static function coords(int $galaxy, int $system, int $planet, int $planetType): string
	{
		$type = self::PLANET_TYPES[$planetType] ?? 'planet';
		return sprintf('[%d:%d:%d] (%s)', $galaxy, $system, $planet, $type);
	}
}
