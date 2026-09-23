<?php

namespace HiveNova\Core;

/**
 * Safe numeric conversions that never overflow PHP 8 int casts.
 *
 * STATPOINTS and other DOUBLE columns can be tiny fractions or values
 * far beyond PHP_INT_MAX. Bare `(int)` / `intval()` then warn or wrap.
 */
class NumericCast
{
	/**
	 * Truncate toward zero and clamp to PHP_INT_MIN..PHP_INT_MAX.
	 * Non-numeric, NaN, and non-finite values become 0.
	 */
	public static function toInt(mixed $value): int
	{
		if (is_int($value)) {
			return $value;
		}

		if (is_bool($value)) {
			return $value ? 1 : 0;
		}

		if (!is_numeric($value)) {
			return 0;
		}

		$asFloat = (float) $value;
		if (!is_finite($asFloat)) {
			return 0;
		}

		if ($asFloat >= (float) PHP_INT_MAX) {
			return PHP_INT_MAX;
		}

		if ($asFloat <= (float) PHP_INT_MIN) {
			return PHP_INT_MIN;
		}

		if ($asFloat > -1.0 && $asFloat < 1.0) {
			return 0;
		}

		$truncated = $asFloat >= 0.0 ? floor($asFloat) : ceil($asFloat);

		return (int) $truncated;
	}

	/**
	 * Finite float for display / number_format. Non-numeric and non-finite become 0.0.
	 */
	public static function toFiniteFloat(mixed $value): float
	{
		if (is_bool($value)) {
			return $value ? 1.0 : 0.0;
		}

		if (!is_numeric($value)) {
			return 0.0;
		}

		$asFloat = (float) $value;
		if (!is_finite($asFloat)) {
			return 0.0;
		}

		return $asFloat;
	}
}
