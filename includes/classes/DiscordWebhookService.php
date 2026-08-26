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
	private const AVATAR_URL = 'https://moon.hive.pizza/styles/resource/images/login/HiveNova.png';
	private const COLOR_INCOMING = 0xE74C3C;
	private const COLOR_COMBAT = 0x3498DB;
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

	public static function formatIncoming(
		string $username,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType,
		?string $npcName = null
	): string {
		$missionLabel = self::missionName($mission);
		if ($npcName !== null) {
			return sprintf(
				'Incoming %s (%s) to %s at %s',
				$missionLabel,
				$npcName,
				$username,
				self::coords($galaxy, $system, $planet, $planetType)
			);
		}

		return sprintf(
			'Incoming %s to %s at %s',
			$missionLabel,
			$username,
			self::coords($galaxy, $system, $planet, $planetType)
		);
	}

	public static function formatCombat(
		string $username,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType,
		?string $npcName = null
	): string {
		$missionLabel = self::missionName($mission);
		if ($npcName !== null) {
			return sprintf(
				'Combat resolved (%s) — %s vs %s at %s',
				$missionLabel,
				$npcName,
				$username,
				self::coords($galaxy, $system, $planet, $planetType)
			);
		}

		return sprintf(
			'Combat resolved (%s) involving %s at %s',
			$missionLabel,
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
		int $planetType,
		int $attackerOwnerId = -1,
		string $fleetArray = ''
	): void {
		self::notify($targetUserId, $mission, $galaxy, $system, $planet, $planetType, true, $attackerOwnerId, $fleetArray);
	}

	public static function notifyCombatResolved(
		int $targetUserId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType,
		int $attackerOwnerId = -1,
		string $fleetArray = ''
	): void {
		self::notify($targetUserId, $mission, $galaxy, $system, $planet, $planetType, false, $attackerOwnerId, $fleetArray);
	}

	public static function notifyFeatClaimed(int $universe, string $featKey, int $userId): void
	{
		try {
			$raw = (string) (Config::get($universe)->discord_feat_webhook ?? '');
			$webhook = self::normalizeUrl($raw);
			if ($webhook === null) {
				return;
			}

			$username = Database::get()->selectSingle(
				'SELECT username FROM %%USERS%% WHERE id = :id;',
				[':id' => $userId],
				'username'
			);
			$player = is_string($username) && $username !== '' ? $username : '#' . $userId;

			$lang = new Language('en');
			$lang->includeData(['INGAME']);
			$def = FeatCatalog::definition($featKey);
			$featName = $lang[$def['name_key']] ?? $featKey;
			$featDesc = $lang[$def['desc_key']] ?? '';
			$description = $featDesc !== ''
				? $featDesc
				: sprintf($lang['feat_banner_text'] ?? '%s claimed %s', $player, $featName);

			self::post($webhook, [
				'username'         => self::USERNAME,
				'avatar_url'       => self::AVATAR_URL,
				'allowed_mentions' => ['parse' => []],
				'embeds'           => [[
					'title'       => $lang['feat_inbox_subject'] ?? 'Feat of Strength',
					'description' => $description,
					'color'       => 0xF1C40F,
					'thumbnail'   => ['url' => self::AVATAR_URL],
					'fields'      => [
						['name' => 'Player', 'value' => $player, 'inline' => true],
						['name' => 'Feat', 'value' => $featName, 'inline' => true],
					],
				]],
			]);
		} catch (\Throwable) {
			return;
		}
	}

	private static function npcName(int $attackerOwnerId, string $fleetArray): ?string
	{
		if ($attackerOwnerId !== 0) {
			return null;
		}

		return PveNpcFleetFactory::displayName(PveNpcFleetFactory::familyFromFleetArray($fleetArray));
	}

	private static function notify(
		int $targetUserId,
		int $mission,
		int $galaxy,
		int $system,
		int $planet,
		int $planetType,
		bool $incoming,
		int $attackerOwnerId = -1,
		string $fleetArray = ''
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
			$coords   = self::coords($galaxy, $system, $planet, $planetType);
			$npcName  = self::npcName($attackerOwnerId, $fleetArray);
			$content  = $incoming
				? self::formatIncoming($username, $mission, $galaxy, $system, $planet, $planetType, $npcName)
				: self::formatCombat($username, $mission, $galaxy, $system, $planet, $planetType, $npcName);

			self::post($webhook, self::buildPayload($content, $incoming, $username, $mission, $coords, $npcName));
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

	/**
	 * @return array{
	 *   username: string,
	 *   avatar_url: string,
	 *   allowed_mentions: array{parse: list<string>},
	 *   embeds: list<array<string, mixed>>
	 * }
	 */
	public static function buildPayload(
		string $description,
		bool $incoming,
		string $username,
		int $mission,
		string $coords,
		?string $npcName = null
	): array {
		$missionName = self::missionName($mission);
		$title = $incoming
			? ('Incoming ' . $missionName)
			: ('Combat resolved — ' . $missionName);
		if ($npcName !== null) {
			$title .= ' — ' . $npcName;
		}

		$fields = [
			['name' => 'Player', 'value' => $username, 'inline' => true],
			['name' => 'Location', 'value' => $coords, 'inline' => true],
		];
		if ($npcName !== null) {
			$fields[] = ['name' => 'Attacker', 'value' => $npcName, 'inline' => true];
		}

		return [
			'username'         => self::USERNAME,
			'avatar_url'       => self::AVATAR_URL,
			'allowed_mentions' => ['parse' => []],
			'embeds'           => [[
				'title'       => $title,
				'description' => $description,
				'color'       => $incoming ? self::COLOR_INCOMING : self::COLOR_COMBAT,
				'thumbnail'   => ['url' => self::AVATAR_URL],
				'fields'      => $fields,
			]],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private static function post(string $url, array $payload): void
	{
		$json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

		$poster = self::$poster ?? self::defaultPoster(...);
		$poster($url, $json);
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
