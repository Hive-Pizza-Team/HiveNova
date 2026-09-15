<?php

namespace HiveNova\Core;

/**
 * Defaults for login/register universe selects.
 *
 * Email/password flows prefer the newest open seasonal universe (Uni3 today).
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
	 * Default for email/password flows.
	 *
	 * Login prefers the newest open seasonal universe (Uni3).
	 * Registration prefers a non-seasonal open universe — seasonal unis require Hive.
	 */
	public static function forEmail(bool $forRegistration = false): int
	{
		if ($forRegistration) {
			foreach (array_reverse(Universe::availableUniverses()) as $uniId) {
				$config = Config::get($uniId);
				if ((int) $config->game_disable === 0) {
					continue;
				}
				if ((int) $config->reg_closed === 1) {
					continue;
				}
				if (isset($config->season_mode) && (int) $config->season_mode === 1) {
					continue;
				}

				return (int) $uniId;
			}

			return self::newestOpen(true);
		}

		foreach (array_reverse(Universe::availableUniverses()) as $uniId) {
			$config = Config::get($uniId);
			if ((int) $config->game_disable === 0) {
				continue;
			}
			if (isset($config->season_mode) && (int) $config->season_mode === 1) {
				return (int) $uniId;
			}
		}

		return self::newestOpen(false);
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
