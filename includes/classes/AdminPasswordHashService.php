<?php

namespace HiveNova\Core;

/**
 * Admin password-hash preview (Tools → Password hash).
 *
 * The page must not bcrypt an empty default on GET.
 */
class AdminPasswordHashService
{
	/**
	 * Hash only after an explicit submit with a non-empty password.
	 */
	public static function previewHash(string $password, bool $submitted): string
	{
		if (!$submitted || $password === '') {
			return '';
		}

		return PlayerUtil::cryptPassword($password);
	}
}
