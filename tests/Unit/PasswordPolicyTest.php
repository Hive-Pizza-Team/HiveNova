<?php

declare(strict_types=1);

use HiveNova\Core\PasswordPolicy;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
	public function test_min_length_is_at_least_eight(): void
	{
		$this->assertGreaterThanOrEqual(8, PasswordPolicy::minLength());
	}

	public function test_rejects_passwords_shorter_than_minimum(): void
	{
		$min = PasswordPolicy::minLength();
		$this->assertFalse(PasswordPolicy::isLongEnough(''));
		$this->assertFalse(PasswordPolicy::isLongEnough(str_repeat('a', $min - 1)));
	}

	public function test_accepts_passwords_at_or_above_minimum(): void
	{
		$min = PasswordPolicy::minLength();
		$this->assertTrue(PasswordPolicy::isLongEnough(str_repeat('a', $min)));
		$this->assertTrue(PasswordPolicy::isLongEnough(str_repeat('a', $min + 4)));
	}
}
