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
}
