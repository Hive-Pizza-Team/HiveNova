<?php

use HiveNova\Core\HiveEcdsaSignature;
use Hive\Helpers\PrivateKey;

use PHPUnit\Framework\TestCase;

class HiveEcdsaSignatureTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';
	}

	public function testSignDigestAlwaysReturns130HexChars(): void
	{
		// Construct PrivateKey from seed hex — avoid Hive::__construct (leaks a throwing error handler).
		$key = new PrivateKey(hash('sha256', 'hivenova-sig-test|unit-test-pass|posting'), true);

		for ($i = 0; $i < 80; $i++) {
			$digest = hash('sha256', 'hivenova-ecdsa-pad-' . $i);
			$sig = HiveEcdsaSignature::signDigest($key->hexKey, $digest);
			$this->assertSame(
				HiveEcdsaSignature::LENGTH,
				strlen($sig),
				'iteration ' . $i . ' produced length ' . strlen($sig)
			);
			$this->assertTrue(HiveEcdsaSignature::isCanonical(substr($sig, 2)));
		}
	}

	public function testIsCanonicalRejectsWrongLength(): void
	{
		$this->assertFalse(HiveEcdsaSignature::isCanonical(str_repeat('0', 126)));
	}
}
