<?php

use HiveNova\Core\HiveEcdsaSignature;

use PHPUnit\Framework\TestCase;

class HiveEcdsaSignatureTest extends TestCase
{
	public function testSignDigestAlwaysReturns130HexChars(): void
	{
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';

		$hive = new Hive\Hive([
			'rpcNodes' => ['https://api.hive.blog'],
			'timeout'  => 5,
		]);
		$key = $hive->privateKeyFromLogin('hivenova-sig-test', 'unit-test-pass', 'posting');

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
