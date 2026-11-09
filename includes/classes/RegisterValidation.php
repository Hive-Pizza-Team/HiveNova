<?php

namespace HiveNova\Core;

/**
 * Field checks for the public registration form.
 */
class RegisterValidation
{
	/**
	 * Language key for a username format error, or null when the name is present and valid.
	 *
	 * Empty, length, and charset are mutually exclusive so the form never shows both.
	 */
	public static function usernameErrorKey(string $userName): ?string
	{
		if ($userName === '') {
			return 'registerErrorUsernameEmpty';
		}

		$length = RegisterUsernameAvailability::nameLength($userName);
		if ($length < RegisterUsernameAvailability::MIN_LENGTH
			|| $length > RegisterUsernameAvailability::MAX_LENGTH) {
			return 'registerErrorUsernameLength';
		}

		if (!PlayerUtil::isNameValid($userName)) {
			return 'registerErrorUsernameChar';
		}

		return null;
	}

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
	 * Local (non-DB) registration checks for redisplaying the lobby form.
	 *
	 * @param array{
	 *   username?: string,
	 *   password?: string,
	 *   passwordReplay?: string,
	 *   email?: string,
	 *   emailReplay?: string,
	 *   rules?: int|string,
	 *   hiveAccount?: string,
	 *   hiveSignatureValid?: bool|null,
	 *   seasonRequiresHive?: bool
	 * } $input
	 * @return array{fields: array<string, string>, keys: list<string>}
	 *         fields map template field => language key; keys is the ordered list of LNG keys
	 *         (password length uses registerErrorPasswordLength and needs sprintf at the page).
	 */
	public static function localErrorKeys(array $input): array
	{
		$fields = [];
		$keys = [];

		$hiveAccount = (string) ($input['hiveAccount'] ?? '');
		$hiveSignup = $hiveAccount !== '';

		if ($hiveSignup && array_key_exists('hiveSignatureValid', $input) && $input['hiveSignatureValid'] === false) {
			$keys[] = 'registerErrorHiveSignature';
			$fields['password'] = 'registerErrorHiveSignature';
		}

		if ($hiveSignup) {
			if (!HiveUtil::isAccountValid($hiveAccount)) {
				$keys[] = 'registerErrorHiveAccountInvalid';
				$fields['username'] = 'registerErrorHiveAccountInvalid';
			}
		} else {
			$usernameKey = self::usernameErrorKey((string) ($input['username'] ?? ''));
			if ($usernameKey !== null) {
				$keys[] = $usernameKey;
				$fields['username'] = $usernameKey;
			}
		}

		$password = (string) ($input['password'] ?? '');
		$passwordReplay = (string) ($input['passwordReplay'] ?? '');
		if (!$hiveSignup && !PasswordPolicy::isLongEnough($password)) {
			$keys[] = 'registerErrorPasswordLength';
			$fields['password'] = 'registerErrorPasswordLength';
		}

		if ($password !== $passwordReplay) {
			$keys[] = 'registerErrorPasswordSame';
			$fields['passwordReplay'] = 'registerErrorPasswordSame';
		}

		if (!$hiveSignup) {
			$mailKey = self::mailErrorKey((string) ($input['email'] ?? ''));
			if ($mailKey !== null) {
				$keys[] = $mailKey;
				$fields['email'] = $mailKey;
			}

			$email = (string) ($input['email'] ?? '');
			$emailReplay = (string) ($input['emailReplay'] ?? '');
			if ($email !== $emailReplay) {
				$keys[] = 'registerErrorMailSame';
				$fields['emailReplay'] = 'registerErrorMailSame';
			}
		}

		if ((int) ($input['rules'] ?? 0) !== 1) {
			$keys[] = 'registerErrorRules';
			$fields['rules'] = 'registerErrorRules';
		}

		if (!empty($input['seasonRequiresHive']) && $hiveAccount === '') {
			$keys[] = 'registerErrorHiveRequiredSeason';
			$fields['hiveAccount'] = 'registerErrorHiveRequiredSeason';
		}

		return [
			'fields' => $fields,
			'keys'   => $keys,
		];
	}
}
