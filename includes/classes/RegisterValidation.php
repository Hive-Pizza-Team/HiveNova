<?php

namespace HiveNova\Core;

/**
 * Field checks for the public registration form.
 */
class RegisterValidation
{
	/**
	 * Language key for an email error, or null when the address is present and valid.
	 *
	 * Empty and invalid are mutually exclusive so the form never shows both.
	 */
	public static function mailErrorKey(string $mailAddress): ?string
	{
		if ($mailAddress === '') {
			return 'registerErrorMailEmpty';
		}

		if (!PlayerUtil::isMailValid($mailAddress)) {
			return 'registerErrorMailInvalid';
		}

		return null;
	}

	/**
	 * In-game uniqueness errors for registration.
	 *
	 * On-chain Hive names do not reserve in-game usernames: email signup is
	 * allowed when the name exists on the Hive blockchain but is unused in
	 * this universe. Keychain signup still validates signature / Hive account
	 * separately in the page controller.
	 *
	 * @param bool $onChainHiveNameExists HiveUtil::accountExists($userName); ignored by product rule
	 * @return list<string> language keys
	 */
	public static function uniquenessErrorKeys(
		int $inGameUsernameCount,
		int $inGameMailCount,
		int $inGameHiveAccountCount,
		string $hiveAccount,
		bool $onChainHiveNameExists = false
	): array {
		$keys = [];

		if ($inGameUsernameCount !== 0) {
			$keys[] = 'registerErrorUsernameExist';
		}

		// Email path used to fail here when HiveUtil::accountExists($userName).
		// That reserved common Hive names that were unused in-game.
		if ($hiveAccount === '' && self::blocksEmailRegisterForOnChainHiveName($onChainHiveNameExists)) {
			$keys[] = 'registerErrorUsernameExist';
		}

		if ($inGameMailCount !== 0) {
			$keys[] = 'registerErrorMailExist';
		}

		if ($hiveAccount !== '' && $inGameHiveAccountCount !== 0) {
			$keys[] = 'registerErrorHiveAccountExist';
		}

		return $keys;
	}

	/**
	 * Email (non-Keychain) signup is never blocked solely because the chosen
	 * name exists as a Hive blockchain account.
	 */
	public static function blocksEmailRegisterForOnChainHiveName(bool $onChainHiveNameExists): bool
	{
		unset($onChainHiveNameExists);

		return false;
	}
}
