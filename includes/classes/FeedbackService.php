<?php

namespace HiveNova\Core;

/**
 * In-game / lobby feedback and bug reports with auto-captured context.
 */
class FeedbackService
{
	public const CSRF_SESSION_KEY = 'feedback_csrf';
	public const RATE_SESSION_KEY = 'feedback_last_submit';

	public const TYPE_BUG = 'bug';
	public const TYPE_IDEA = 'idea';
	public const TYPE_OTHER = 'other';

	public const MAX_DESCRIPTION_LENGTH = 8000;
	public const MAX_SCREENSHOT_BYTES = 2097152; // 2 MiB
	public const RATE_LIMIT_SECONDS = 120;
	public const FEEDBACK_CATEGORY_ID = 2;

	/** @var list<string> */
	public const SENSITIVE_QUERY_KEYS = [
		'token',
		'csrf',
		'admin_csrf',
		'password',
		'passwd',
		'pass',
		'session',
		'sessionid',
		'sid',
		'access_token',
		'refresh_token',
		'api_key',
		'apikey',
		'auth',
		'authorization',
		'keychain',
		'code',
		'state',
	];

	/** @var list<string> */
	public const ALLOWED_IMAGE_MIME = [
		'image/png',
		'image/jpeg',
		'image/gif',
		'image/webp',
	];

	/**
	 * @return list<string>
	 */
	public static function allowedTypes(): array
	{
		return [self::TYPE_BUG, self::TYPE_IDEA, self::TYPE_OTHER];
	}

	public static function normalizeType(?string $type): string
	{
		$type = strtolower(trim((string) $type));
		if ($type === '') {
			return self::TYPE_OTHER;
		}

		return in_array($type, self::allowedTypes(), true) ? $type : self::TYPE_OTHER;
	}

	public static function ensureSession(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_DISABLED) {
			return;
		}
		if (class_exists(Session::class, false)) {
			Session::init();
		}
		@session_start();
	}

	public static function issueCsrfToken(): string
	{
		self::ensureSession();
		$token = bin2hex(random_bytes(16));
		$_SESSION[self::CSRF_SESSION_KEY] = $token;

		return $token;
	}

	public static function validateCsrfToken(?string $token): bool
	{
		self::ensureSession();
		$expected = $_SESSION[self::CSRF_SESSION_KEY] ?? '';
		if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
			return false;
		}

		return hash_equals($expected, $token);
	}

	public static function lastSubmitAtFromSession(): int
	{
		self::ensureSession();
		return isset($_SESSION[self::RATE_SESSION_KEY]) ? (int) $_SESSION[self::RATE_SESSION_KEY] : 0;
	}

	public static function rememberSubmitAt(int $now): void
	{
		self::ensureSession();
		$_SESSION[self::RATE_SESSION_KEY] = $now;
	}

	public static function isSameOriginRequest(): bool
	{
		$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
		$host = strtolower(explode(':', $hostHeader)[0]);
		$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
		if ($origin !== '') {
			$originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));

			return $originHost !== '' && $originHost === $host;
		}

		$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
		if ($referer === '') {
			return true;
		}
		$refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));

		return $refererHost === '' || $refererHost === $host;
	}

	public static function rateLimitRemaining(int $lastSubmitAt, int $now): int
	{
		if ($lastSubmitAt <= 0) {
			return 0;
		}

		$elapsed = $now - $lastSubmitAt;
		if ($elapsed >= self::RATE_LIMIT_SECONDS) {
			return 0;
		}

		return self::RATE_LIMIT_SECONDS - $elapsed;
	}

	/**
	 * Strip secrets from a page URL before storage or display.
	 */
	public static function sanitizeUrl(string $url): string
	{
		$url = trim($url);
		if ($url === '') {
			return '';
		}

		$parts = parse_url($url);
		if (!is_array($parts)) {
			return self::stripSensitiveQueryString($url);
		}

		$query = [];
		if (!empty($parts['query'])) {
			parse_str((string) $parts['query'], $query);
			$query = self::stripSensitiveQueryParams(is_array($query) ? $query : []);
		}

		$path = (string) ($parts['path'] ?? '');
		$rebuild = $path;
		if ($query !== []) {
			$rebuild .= '?' . http_build_query($query);
		}
		if (!empty($parts['fragment'])) {
			// Fragments can hold tokens in some OAuth flows; drop them.
		}

		if (!empty($parts['scheme']) && !empty($parts['host'])) {
			$port = isset($parts['port']) ? ':' . $parts['port'] : '';
			$rebuild = $parts['scheme'] . '://' . $parts['host'] . $port . $rebuild;
		}

		return $rebuild;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	public static function stripSensitiveQueryParams(array $params): array
	{
		$blocked = array_fill_keys(self::SENSITIVE_QUERY_KEYS, true);
		$clean = [];
		foreach ($params as $key => $value) {
			$lower = strtolower((string) $key);
			if (isset($blocked[$lower])) {
				continue;
			}
			if (is_array($value)) {
				$clean[$key] = self::stripSensitiveQueryParams($value);
				continue;
			}
			$clean[$key] = $value;
		}

		return $clean;
	}

	public static function stripSensitiveQueryString(string $raw): string
	{
		if (!str_contains($raw, '?')) {
			return $raw;
		}
		[$path, $query] = explode('?', $raw, 2);
		parse_str($query, $params);
		$params = self::stripSensitiveQueryParams(is_array($params) ? $params : []);
		if ($params === []) {
			return $path;
		}

		return $path . '?' . http_build_query($params);
	}

	/**
	 * @param array{
	 *     pageUrl?: string|null,
	 *     universe?: int|string|null,
	 *     userId?: int|string|null,
	 *     username?: string|null,
	 *     planetId?: int|string|null,
	 *     planetName?: string|null,
	 *     planetCoords?: string|null,
	 *     userAgent?: string|null,
	 *     viewport?: string|null,
	 *     language?: string|null,
	 *     version?: string|null,
	 *     timestamp?: int|string|null
	 * } $input
	 * @return array{
	 *     page: string,
	 *     universe: string,
	 *     account: string,
	 *     planet: string,
	 *     browser: string,
	 *     viewport: string,
	 *     language: string,
	 *     version: string,
	 *     time: string
	 * }
	 */
	public static function buildContext(array $input): array
	{
		$page = self::sanitizeUrl((string) ($input['pageUrl'] ?? ''));
		$universe = trim((string) ($input['universe'] ?? ''));
		$userId = (int) ($input['userId'] ?? 0);
		$username = trim((string) ($input['username'] ?? ''));
		$account = $userId > 0
			? ($username !== '' ? $username . ' (#' . $userId . ')' : '#' . $userId)
			: 'guest';

		$planetParts = [];
		$planetName = trim((string) ($input['planetName'] ?? ''));
		$planetCoords = trim((string) ($input['planetCoords'] ?? ''));
		$planetId = (int) ($input['planetId'] ?? 0);
		if ($planetName !== '') {
			$planetParts[] = $planetName;
		}
		if ($planetCoords !== '') {
			$planetParts[] = '[' . $planetCoords . ']';
		}
		if ($planetId > 0) {
			$planetParts[] = '#' . $planetId;
		}
		$planet = $planetParts !== [] ? implode(' ', $planetParts) : 'n/a';

		$ua = trim((string) ($input['userAgent'] ?? ''));
		if (strlen($ua) > 512) {
			$ua = substr($ua, 0, 512);
		}

		$viewport = trim((string) ($input['viewport'] ?? ''));
		$language = trim((string) ($input['language'] ?? ''));
		$version = trim((string) ($input['version'] ?? ''));
		$timestamp = (int) ($input['timestamp'] ?? 0);
		if ($timestamp <= 0) {
			$timestamp = defined('TIMESTAMP') ? (int) TIMESTAMP : time();
		}

		return [
			'page' => $page !== '' ? $page : 'n/a',
			'universe' => $universe !== '' ? $universe : 'n/a',
			'account' => $account,
			'planet' => $planet,
			'browser' => $ua !== '' ? $ua : 'n/a',
			'viewport' => $viewport !== '' ? $viewport : 'n/a',
			'language' => $language !== '' ? $language : 'n/a',
			'version' => $version !== '' ? $version : 'n/a',
			'time' => gmdate('Y-m-d H:i:s', $timestamp) . ' UTC',
		];
	}

	/**
	 * @param array<string, string> $context
	 */
	public static function formatContextText(array $context): string
	{
		$lines = [
			'Page: ' . ($context['page'] ?? 'n/a'),
			'Universe: ' . ($context['universe'] ?? 'n/a'),
			'Account: ' . ($context['account'] ?? 'n/a'),
			'Planet: ' . ($context['planet'] ?? 'n/a'),
			'Browser/OS: ' . ($context['browser'] ?? 'n/a'),
			'Viewport: ' . ($context['viewport'] ?? 'n/a'),
			'Language: ' . ($context['language'] ?? 'n/a'),
			'Version: ' . ($context['version'] ?? 'n/a'),
			'Time: ' . ($context['time'] ?? 'n/a'),
		];

		return implode("\n", $lines);
	}

	public static function validateDescription(string $description): ?string
	{
		$description = trim($description);
		if ($description === '') {
			return 'empty_description';
		}
		if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
			return 'description_too_long';
		}

		return null;
	}

	/**
	 * @param array{error?: int, size?: int, type?: string, tmp_name?: string, name?: string}|null $file
	 */
	public static function validateScreenshot(?array $file): ?string
	{
		if ($file === null || $file === []) {
			return null;
		}

		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error === UPLOAD_ERR_NO_FILE) {
			return null;
		}
		if ($error !== UPLOAD_ERR_OK) {
			return 'screenshot_upload_failed';
		}

		$size = (int) ($file['size'] ?? 0);
		if ($size <= 0) {
			return 'screenshot_upload_failed';
		}
		if ($size > self::MAX_SCREENSHOT_BYTES) {
			return 'screenshot_too_large';
		}

		$mime = strtolower((string) ($file['type'] ?? ''));
		$tmp = (string) ($file['tmp_name'] ?? '');
		if ($tmp !== '' && is_file($tmp) && function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$detected = finfo_file($finfo, $tmp);
				finfo_close($finfo);
				if (is_string($detected) && $detected !== '') {
					$mime = strtolower($detected);
				}
			}
		}

		if (!in_array($mime, self::ALLOWED_IMAGE_MIME, true)) {
			return 'screenshot_invalid_type';
		}

		return null;
	}

	public static function subjectForType(string $type, string $description): string
	{
		$prefix = match (self::normalizeType($type)) {
			self::TYPE_BUG => '[Bug]',
			self::TYPE_IDEA => '[Idea]',
			default => '[Feedback]',
		};

		$snippet = preg_replace('/\s+/', ' ', trim($description)) ?? '';
		if (strlen($snippet) > 80) {
			$snippet = substr($snippet, 0, 77) . '...';
		}
		if ($snippet === '') {
			$snippet = 'Player feedback';
		}

		return $prefix . ' ' . $snippet;
	}

	/**
	 * @param array<string, string> $context
	 */
	public static function composeTicketMessage(
		string $description,
		array $context,
		string $type,
		?string $screenshotPath = null
	): string {
		$parts = [
			trim($description),
			'',
			'--- Auto-captured context ---',
			self::formatContextText($context),
			'Type: ' . self::normalizeType($type),
		];
		if ($screenshotPath !== null && $screenshotPath !== '') {
			$parts[] = 'Screenshot: ' . $screenshotPath;
		}

		return implode("\n", $parts);
	}

	public static function referenceId(int $ticketId): string
	{
		return '#' . max(0, $ticketId);
	}

	/**
	 * Persist an uploaded screenshot under cache/feedback/. Returns relative path or null.
	 *
	 * @param array{error?: int, size?: int, type?: string, tmp_name?: string, name?: string}|null $file
	 */
	public static function storeScreenshot(?array $file, int $ticketId): ?string
	{
		if ($file === null || self::validateScreenshot($file) !== null) {
			return null;
		}
		if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			return null;
		}

		$tmp = (string) ($file['tmp_name'] ?? '');
		if ($tmp === '' || !is_uploaded_file($tmp)) {
			// Unit tests / non-HTTP callers may pass a temp path.
			if ($tmp === '' || !is_file($tmp)) {
				return null;
			}
		}

		$mime = strtolower((string) ($file['type'] ?? 'image/png'));
		$ext = match ($mime) {
			'image/jpeg' => 'jpg',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			default => 'png',
		};

		$root = defined('ROOT_PATH') ? ROOT_PATH : (dirname(__DIR__, 2) . '/');
		$dir = $root . 'cache/feedback';
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
			return null;
		}

		$name = 'fb_' . $ticketId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
		$dest = $dir . '/' . $name;
		$moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : @rename($tmp, $dest);
		if (!$moved) {
			return null;
		}

		return 'cache/feedback/' . $name;
	}

	/**
	 * Create a support ticket (logged-in or guest) and return the ticket id.
	 *
	 * @param array<string, string> $context
	 * @param array{error?: int, size?: int, type?: string, tmp_name?: string, name?: string}|null $screenshot
	 * @param callable|null $ticketFactory fn(int $ownerId, int $categoryId, string $subject): int
	 * @param callable|null $answerFactory fn(int $ticketId, int $ownerId, string $ownerName, string $subject, string $message, int $status): int
	 */
	public static function submit(
		string $description,
		string $type,
		array $context,
		int $ownerId,
		string $ownerName,
		?array $screenshot = null,
		?callable $ticketFactory = null,
		?callable $answerFactory = null,
		?callable $screenshotStorer = null
	): array {
		$error = self::validateDescription($description);
		if ($error !== null) {
			return ['ok' => false, 'error' => $error];
		}
		$shotError = self::validateScreenshot($screenshot);
		if ($shotError !== null) {
			return ['ok' => false, 'error' => $shotError];
		}

		$type = self::normalizeType($type);
		$subject = self::subjectForType($type, $description);
		$ownerId = max(0, $ownerId);
		$ownerName = trim($ownerName);
		if ($ownerName === '') {
			$ownerName = $ownerId > 0 ? 'Player' : 'Guest';
		}

		if ($ticketFactory === null) {
			$tickets = new SupportTickets();
			$ticketFactory = static function (int $oid, int $categoryId, string $subj) use ($tickets): int {
				return (int) $tickets->createTicket($oid, $categoryId, $subj);
			};
			$answerFactory = static function (
				int $ticketId,
				int $oid,
				string $oname,
				string $subj,
				string $message,
				int $status
			) use ($tickets): int {
				return (int) $tickets->createAnswer($ticketId, $oid, $oname, $subj, $message, $status);
			};
		}

		$ticketId = (int) $ticketFactory($ownerId, self::FEEDBACK_CATEGORY_ID, $subject);
		if ($ticketId <= 0) {
			return ['ok' => false, 'error' => 'submit_failed'];
		}

		$storer = $screenshotStorer ?? static fn(?array $file, int $id): ?string => self::storeScreenshot($file, $id);
		$screenshotPath = $storer($screenshot, $ticketId);
		$message = self::composeTicketMessage($description, $context, $type, $screenshotPath);

		if ($answerFactory !== null) {
			$answerFactory($ticketId, $ownerId, $ownerName, $subject, $message, 0);
		}

		return [
			'ok' => true,
			'ticketId' => $ticketId,
			'reference' => self::referenceId($ticketId),
			'screenshot' => $screenshotPath,
		];
	}

	/**
	 * Client bootstrap payload for the feedback modal.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed>|null $planet
	 * @return array<string, mixed>
	 */
	public static function clientConfig(
		string $submitUrl,
		array $user,
		?array $planet,
		string $language,
		string $version,
		int $universe
	): array {
		$userId = (int) ($user['id'] ?? 0);
		$username = (string) ($user['username'] ?? '');
		$planetId = (int) ($planet['id'] ?? 0);
		$planetName = (string) ($planet['name'] ?? '');
		$coords = '';
		if ($planet !== null) {
			$g = $planet['galaxy'] ?? null;
			$s = $planet['system'] ?? null;
			$p = $planet['planet'] ?? null;
			if ($g !== null && $s !== null && $p !== null) {
				$coords = $g . ':' . $s . ':' . $p;
			}
		}

		return [
			'submitUrl' => $submitUrl,
			'csrf' => self::issueCsrfToken(),
			'maxScreenshotBytes' => self::MAX_SCREENSHOT_BYTES,
			'context' => [
				'universe' => (string) $universe,
				'userId' => $userId,
				'username' => $username,
				'planetId' => $planetId,
				'planetName' => $planetName,
				'planetCoords' => $coords,
				'language' => $language,
				'version' => $version,
			],
		];
	}
}
