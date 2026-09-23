<?php

namespace HiveNova\Core;

/**
 * Defaults for login/register universe selects.
 *
 * Email/password flows prefer Universe 1 when it is open and not seasonal,
 * then the newest other non-seasonal open universe. A newer universe that
 * was not flagged seasonal must not steal the password default.
 * Seasonal universes need Hive Keychain and a PIZZA entry, so they are not
 * the cold-traffic default.
 * Hive Keychain flows prefer the most populated open universe.
 */
class LoginUniverseDefaults
{
	/**
	 * Newest open universe (registration-aware).
	 */
	public static function newestOpen(bool $forRegistration = false): int
	{
		foreach (array_reverse(Universe::availableUniverses()) as $uniId) {
			$config = Config::get($uniId);
			if ((int) $config->game_disable === 0) {
				continue;
			}
			if ($forRegistration && (int) $config->reg_closed === 1) {
				continue;
			}

			return (int) $uniId;
		}

		$universes = array_reverse(Universe::availableUniverses());

		return $universes ? (int) $universes[0] : ROOT_UNI;
	}

	/**
	 * Whether this universe is a short seasonal wipe (Hive + PIZZA entry).
	 */
	public static function isSeasonal(object $config): bool
	{
		return isset($config->season_mode) && (int) $config->season_mode === 1;
	}

	/**
	 * Dropdown label. Seasonal options name the Keychain + PIZZA requirement.
	 */
	public static function selectOptionLabel(string $uniName, bool $seasonal, string $closedSuffix, string $seasonSuffix): string
	{
		$label = $uniName;
		if ($seasonal && $seasonSuffix !== '') {
			$label .= ' — '.$seasonSuffix;
		}
		if ($closedSuffix !== '') {
			$label .= $closedSuffix;
		}

		return $label;
	}

	/**
	 * Default for email/password login and registration.
	 *
	 * Prefer Universe 1 whenever it is an open non-seasonal universe so cold
	 * traffic lands on the free frontier even when a newer universe is not
	 * marked seasonal. Registration also skips universes with registration
	 * closed. Falls back to the newest open universe when every candidate
	 * is seasonal or closed.
	 */
	public static function forEmail(bool $forRegistration = false): int
	{
		foreach (self::emailCandidateOrder() as $uniId) {
			$config = Config::get($uniId);
			if ((int) $config->game_disable === 0) {
				continue;
			}
			if ($forRegistration && (int) $config->reg_closed === 1) {
				continue;
			}
			if (self::isSeasonal($config)) {
				continue;
			}

			return (int) $uniId;
		}

		return self::newestOpen($forRegistration);
	}

	/**
	 * Universe 1 first, then every other universe newest-first.
	 *
	 * Walking newest-first alone lets the highest id win whenever season_mode
	 * is off, which is how password forms defaulted to Universe 3 on hosts
	 * that had not flagged it seasonal.
	 *
	 * @return list<int>
	 */
	private static function emailCandidateOrder(): array
	{
		$ordered = [];
		if (Universe::exists(ROOT_UNI)) {
			$ordered[] = (int) ROOT_UNI;
		}
		foreach (array_reverse(Universe::availableUniverses()) as $uniId) {
			$uniId = (int) $uniId;
			if ($uniId === (int) ROOT_UNI) {
				continue;
			}
			$ordered[] = $uniId;
		}

		return $ordered;
	}

	/**
	 * Default for Hive Keychain — prefer the busiest open universe.
	 */
	public static function forHive(bool $forRegistration = false): int
	{
		$bestId = null;
		$bestPlayers = -1;

		foreach (Universe::availableUniverses() as $uniId) {
			$config = Config::get($uniId);
			if ((int) $config->game_disable === 0) {
				continue;
			}
			if ($forRegistration && (int) $config->reg_closed === 1) {
				continue;
			}
			$players = (int) ($config->users_amount ?? 0);
			if ($players > $bestPlayers) {
				$bestPlayers = $players;
				$bestId = (int) $uniId;
			}
		}

		return $bestId ?? self::newestOpen($forRegistration);
	}

	/**
	 * Whether $universeId may be used for registration lookups/sign-up.
	 *
	 * Same gate as ShowRegisterPage::send(): universe must exist, the game
	 * must be enabled, and registration must not be closed.
	 */
	public static function isOpenForRegistration(int $universeId): bool
	{
		if ($universeId <= 0 || !Universe::exists($universeId)) {
			return false;
		}

		try {
			$config = Config::get($universeId);
		} catch (\Throwable $e) {
			return false;
		}

		return (int) $config->game_disable !== 0 && (int) $config->reg_closed !== 1;
	}
}
