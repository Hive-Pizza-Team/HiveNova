<?php

namespace HiveNova\Core;

/**
 * Random readable nickname for the Email register form prefill.
 *
 * Shape: AdjectiveNoun + two digits (ASCII letters/digits only). Candidates are
 * validated with the same rules as registration and checked against taken names
 * in the selected universe (users + pending users_valid).
 */
class NicknameSuggestionService
{
	public const MAX_ATTEMPTS = 8;

	/** @var list<string> */
	private const ADJECTIVES = [
		'Cosmic', 'Lunar', 'Solar', 'Neon', 'Quiet', 'Swift', 'Amber', 'Crimson',
		'Frozen', 'Orbit', 'Nova', 'Silent', 'Bright', 'Hollow', 'Iron', 'Stellar',
		'Vivid', 'Azure', 'Radiant', 'Shadow', 'Wild', 'Polar', 'Ember', 'Astro',
	];

	/** @var list<string> */
	private const NOUNS = [
		'Comet', 'Nebula', 'Phaser', 'Rover', 'Orbit', 'Nova', 'Pulsar', 'Quasar',
		'Rocket', 'Shuttle', 'Drifter', 'Pilot', 'Ranger', 'Beacon', 'Signal', 'Forge',
		'Harbor', 'Cipher', 'Vector', 'Prism', 'Meteor', 'Galaxy', 'Horizon', 'Volt',
	];

	/** @var callable(int, int): int */
	private $randomInt;

	/** @var callable(string, int): bool */
	private $isTaken;

	/**
	 * @param callable(int $min, int $max): int|null $randomInt
	 * @param callable(string $username, int $universeId): bool|null $isTaken
	 */
	public function __construct(?callable $randomInt = null, ?callable $isTaken = null)
	{
		$this->randomInt = $randomInt ?? static function (int $min, int $max): int {
			return random_int($min, $max);
		};
		$this->isTaken = $isTaken ?? static function (string $username, int $universeId): bool {
			return self::isUsernameTaken(Database::get(), $username, $universeId);
		};
	}

	public static function fromDefaults(DatabaseInterface $db, ?callable $randomInt = null): self
	{
		return new self(
			$randomInt,
			static function (string $username, int $universeId) use ($db): bool {
				return self::isUsernameTaken($db, $username, $universeId);
			}
		);
	}

	/**
	 * Suggest an available nickname for the given universe.
	 * Retries on collision; returns the last candidate if all attempts collide.
	 */
	public function suggest(int $universeId): string
	{
		$candidate = '';
		for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
			$candidate = $this->generateCandidate();
			if (!self::isValidCandidate($candidate)) {
				continue;
			}
			if ($universeId > 0 && ($this->isTaken)($candidate, $universeId)) {
				continue;
			}

			return $candidate;
		}

		return $candidate !== '' && self::isValidCandidate($candidate)
			? $candidate
			: $this->fallbackCandidate();
	}

	public function generateCandidate(): string
	{
		$adjectives = self::ADJECTIVES;
		$nouns = self::NOUNS;
		$adj = $adjectives[($this->randomInt)(0, count($adjectives) - 1)];
		$noun = $nouns[($this->randomInt)(0, count($nouns) - 1)];
		$digits = sprintf('%02d', ($this->randomInt)(0, 99));

		return $adj.$noun.$digits;
	}

	public static function isValidCandidate(string $username): bool
	{
		if ($username === '' || !preg_match('/^[A-Za-z0-9]+$/', $username)) {
			return false;
		}

		return RegisterValidation::usernameErrorKey($username) === null;
	}

	public static function isUsernameTaken(DatabaseInterface $db, string $username, int $universeId): bool
	{
		if ($username === '' || $universeId <= 0) {
			return false;
		}

		$sql = 'SELECT (
				SELECT COUNT(*)
				FROM %%USERS%%
				WHERE universe = :universe
				AND username = :userName
			) + (
				SELECT COUNT(*)
				FROM %%USERS_VALID%%
				WHERE universe = :universe
				AND username = :userName
			) as count;';

		$count = $db->selectSingle($sql, [
			':universe' => $universeId,
			':userName' => $username,
		], 'count');

		return (int) $count > 0;
	}

	/**
	 * @return list<string>
	 */
	public static function adjectives(): array
	{
		return self::ADJECTIVES;
	}

	/**
	 * @return list<string>
	 */
	public static function nouns(): array
	{
		return self::NOUNS;
	}

	private function fallbackCandidate(): string
	{
		return 'NovaPilot'.sprintf('%02d', ($this->randomInt)(0, 99));
	}
}
