<?php

namespace HiveNova\Core;

/**
 * Shared account-password length policy (register + settings).
 */
class PasswordPolicy
{
	public static function minLength(): int
	{
		return defined('PASSWORD_MIN_LENGTH') ? (int) PASSWORD_MIN_LENGTH : 8;
	}

	public static function isLongEnough(string $password): bool
	{
		return strlen($password) >= self::minLength();
	}
}
