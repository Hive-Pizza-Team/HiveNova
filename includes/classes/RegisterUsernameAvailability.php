<?php

namespace HiveNova\Core;

/**
 * Live register-username availability: game DB + Hive anti-masquerade + suggestions.
 */
class RegisterUsernameAvailability
{
	public const MIN_LENGTH = 3;
	public const MAX_LENGTH = 25;
	public const SUGGESTION_LIMIT = 5;
	public const HIVE_LOOKUP_CAP = 12;

	public const REASON_INVALID = 'invalid';
	public const REASON_TAKEN_GAME = 'taken_game';
	public const REASON_TAKEN_HIVE = 'taken_hive';
	public const REASON_MISSING_HIVE = 'missing_hive';

	/** @var list<int> */
	private const DIGIT_SUFFIXES = [2, 42, 7, 99, 3];

	/** @var list<string> */
	private const PREFIXES = ['x'];

	/** @var list<string> */
	private const SUFFIXES = ['Moon', 'Nova', 'HQ'];

	/** @var callable(int, list<string>): list<string> */
	private $gameTakenLookup;

	/** @var callable(list<string>): array<string, bool> */
	private $hiveExistsLookup;

	/**
	 * @param callable(int $universeId, list<string> $names): list<string>|null $gameTakenLookup
	 * @param callable(list<string> $names): array<string, bool>|null $hiveExistsLookup
	 */
	public function __construct(?callable $gameTakenLookup = null, ?callable $hiveExistsLookup = null)
	{
		$this->gameTakenLookup = $gameTakenLookup ?? static function (int $universeId, array $names): array {
			return self::lookupGameTaken(Database::get(), $universeId, $names);
		};
		$this->hiveExistsLookup = $hiveExistsLookup ?? static function (array $names): array {
			return HiveUsernameExistsCache::lookup($names, static function (array $missing): array {
				return HiveUtil::accountsExist($missing);
			});
		};
	}

	public static function fromDefaults(DatabaseInterface $db, ?callable $hiveExistsLookup = null): self
	{
		return new self(
			static function (int $universeId, array $names) use ($db): array {
				return self::lookupGameTaken($db, $universeId, $names);
			},
			$hiveExistsLookup
		);
	}

	/**
	 * @return array{
	 *     available: bool,
	 *     reason: ?string,
	 *     suggestions: list<string>,
	 *     hiveOwn: bool
	 * }
	 */
	public function check(string $username, int $universeId, bool $hiveSignup = false): array
	{
		$username = trim($username);
		if ($hiveSignup) {
			$username = strtolower($username);
		}

		$empty = [
			'available' => false,
			'reason' => self::REASON_INVALID,
			'suggestions' => [],
			'hiveOwn' => false,
		];

		if ($username === '') {
			return $empty;
		}

		$candidates = self::similarNameCandidates($username, $hiveSignup);
		$probe = array_values(array_unique(array_merge([$username], $candidates)));

		$gameTaken = array_fill_keys(
			($this->gameTakenLookup)($universeId, $probe),
			true
		);

		$hiveExists = $this->lookupHive($username, $candidates);

		$formatReason = self::formatReason($username, $hiveSignup);
		if ($formatReason !== null) {
			return [
				'available' => false,
				'reason' => self::REASON_INVALID,
				'suggestions' => $hiveSignup ? [] : $this->pickSuggestions(
					$username,
					$candidates,
					$gameTaken,
					$hiveExists
				),
				'hiveOwn' => false,
			];
		}

		if (isset($gameTaken[$username])) {
			return [
				'available' => false,
				'reason' => self::REASON_TAKEN_GAME,
				'suggestions' => $hiveSignup ? [] : $this->pickSuggestions(
					$username,
					$candidates,
					$gameTaken,
					$hiveExists
				),
				'hiveOwn' => false,
			];
		}

		$existsOnHive = !empty($hiveExists[strtolower($username)]);

		if ($existsOnHive && $hiveSignup) {
			return [
				'available' => true,
				'reason' => null,
				'suggestions' => [],
				'hiveOwn' => true,
			];
		}

		if ($existsOnHive) {
			return [
				'available' => false,
				'reason' => self::REASON_TAKEN_HIVE,
				'suggestions' => $this->pickSuggestions($username, $candidates, $gameTaken, $hiveExists),
				'hiveOwn' => false,
			];
		}

		if ($hiveSignup) {
			return [
				'available' => false,
				'reason' => self::REASON_MISSING_HIVE,
				'suggestions' => [],
				'hiveOwn' => false,
			];
		}

		return [
			'available' => true,
			'reason' => null,
			'suggestions' => [],
			'hiveOwn' => false,
		];
	}

	public static function formatReason(string $username, bool $hiveSignup = false): ?string
	{
		if ($username === '') {
			return self::REASON_INVALID;
		}

		if ($hiveSignup) {
			return HiveUtil::isAccountValid(strtolower($username)) ? null : self::REASON_INVALID;
		}

		$length = self::nameLength($username);
		if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
			return self::REASON_INVALID;
		}

		if (!PlayerUtil::isNameValid($username)) {
			return self::REASON_INVALID;
		}

		return null;
	}

	public static function nameLength(string $name): int
	{
		if (function_exists('mb_strlen')) {
			return (int) mb_strlen($name, 'UTF-8');
		}

		return strlen($name);
	}

	/**
	 * Similar names that pass game format rules. Availability is applied later.
	 *
	 * @return list<string>
	 */
	public static function similarNameCandidates(string $username, bool $hiveSignup = false): array
	{
		$trimmed = trim($username);
		$cleaned = self::stripInvalidChars($trimmed);
		if ($cleaned === '') {
			return [];
		}

		$compact = preg_replace('/\s+/', '', $cleaned) ?? $cleaned;
		$raw = [];

		foreach (self::DIGIT_SUFFIXES as $digit) {
			$raw[] = $cleaned.$digit;
			if ($compact !== $cleaned) {
				$raw[] = $compact.$digit;
			}
		}

		foreach (self::PREFIXES as $prefix) {
			$raw[] = $prefix.$compact;
		}

		foreach (self::SUFFIXES as $suffix) {
			$raw[] = $compact.$suffix;
		}

		$underscored = str_replace(' ', '_', $cleaned);
		if ($underscored !== $cleaned) {
			$raw[] = $underscored;
		}
		$raw[] = $compact.'_1';

		$leet = self::leetSpeak($compact);
		if ($leet !== $compact) {
			$raw[] = $leet;
		}

		$out = [];
		$seen = [];
		foreach ($raw as $candidate) {
			if ($hiveSignup) {
				$candidate = strtolower($candidate);
			}
			if ($candidate === $trimmed || $candidate === '') {
				continue;
			}
			if (self::formatReason($candidate, $hiveSignup) !== null) {
				continue;
			}
			$key = strtolower($candidate);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $candidate;
		}

		return $out;
	}

	/**
	 * @param list<string> $names
	 * @return list<string>
	 */
	public static function lookupGameTaken(DatabaseInterface $db, int $universeId, array $names): array
	{
		$unique = [];
		foreach ($names as $name) {
			if (!is_string($name)) {
				continue;
			}
			$name = trim($name);
			if ($name === '') {
				continue;
			}
			$unique[$name] = $name;
		}
		$names = array_values($unique);
		if ($names === [] || $universeId <= 0) {
			return [];
		}

		$placeholders = [];
		$params = [':universe' => $universeId];
		foreach ($names as $i => $name) {
			$key = ':n'.$i;
			$placeholders[] = $key;
			$params[$key] = $name;
		}
		$in = implode(',', $placeholders);

		$sql = 'SELECT username FROM %%USERS%%
			WHERE universe = :universe AND username IN ('.$in.')
			UNION
			SELECT username FROM %%USERS_VALID%%
			WHERE universe = :universe AND username IN ('.$in.');';

		$rows = $db->select($sql, $params);
		if (!is_array($rows)) {
			return [];
		}

		$taken = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			foreach (['username', 'userName'] as $field) {
				if (isset($row[$field]) && is_string($row[$field]) && $row[$field] !== '') {
					$taken[$row[$field]] = $row[$field];
				}
			}
		}

		return array_values($taken);
	}

	/**
	 * @param list<string> $candidates
	 * @param array<string, true> $gameTaken
	 * @param array<string, bool> $hiveExists
	 * @return list<string>
	 */
	private function pickSuggestions(
		string $username,
		array $candidates,
		array $gameTaken,
		array $hiveExists
	): array {
		$out = [];
		$seen = [strtolower($username) => true];

		foreach ($candidates as $candidate) {
			if (count($out) >= self::SUGGESTION_LIMIT) {
				break;
			}
			$key = strtolower($candidate);
			if (isset($seen[$key]) || isset($gameTaken[$candidate])) {
				continue;
			}
			if (!self::hiveAvailabilityKnown($candidate, $hiveExists)) {
				continue;
			}
			if (!empty($hiveExists[$key])) {
				continue;
			}
			$seen[$key] = true;
			$out[] = $candidate;
		}

		return $out;
	}

	/**
	 * @param list<string> $candidates
	 * @return array<string, bool>
	 */
	private function lookupHive(string $username, array $candidates): array
	{
		$keys = [];
		foreach (array_merge([$username], $candidates) as $name) {
			$key = strtolower($name);
			if ($key === '' || isset($keys[$key])) {
				continue;
			}
			if (!HiveUtil::isAccountValid($key)) {
				continue;
			}
			$keys[$key] = $key;
		}

		$ordered = array_values($keys);
		if ($ordered === []) {
			return [];
		}

		$capped = array_slice($ordered, 0, self::HIVE_LOOKUP_CAP);
		$original = strtolower($username);
		if (HiveUtil::isAccountValid($original) && !in_array($original, $capped, true)) {
			array_pop($capped);
			array_unshift($capped, $original);
		}

		$result = ($this->hiveExistsLookup)($capped);
		return is_array($result) ? $result : [];
	}

	/**
	 * @param array<string, bool> $hiveExists
	 */
	private static function hiveAvailabilityKnown(string $candidate, array $hiveExists): bool
	{
		$key = strtolower($candidate);
		if (array_key_exists($key, $hiveExists)) {
			return true;
		}

		// Names that cannot exist on Hive need no RPC confirmation.
		return !HiveUtil::isAccountValid($key);
	}

	private static function stripInvalidChars(string $name): string
	{
		$stripped = preg_replace('/[^\p{L}\p{N}_\-. ]/u', '', $name);

		return is_string($stripped) ? trim($stripped) : '';
	}

	private static function leetSpeak(string $name): string
	{
		return strtr($name, [
			'a' => '4', 'A' => '4',
			'e' => '3', 'E' => '3',
			'i' => '1', 'I' => '1',
			'o' => '0', 'O' => '0',
			's' => '5', 'S' => '5',
		]);
	}
}
