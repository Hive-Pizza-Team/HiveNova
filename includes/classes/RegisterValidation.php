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
}
