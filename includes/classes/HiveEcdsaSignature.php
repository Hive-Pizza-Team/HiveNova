<?php

namespace HiveNova\Core;

use Elliptic\EC;
use Hive\Helpers\Serializer;

/**
 * Hive ECDSA signatures with correct 32-byte r/s padding.
 *
 * mahdiyari/hive-php PrivateKey::sign() omits leading zero bytes in r/s hex, which
 * yields 128-char signatures the chain rejects as "Missing Posting Authority".
 */
final class HiveEcdsaSignature
{
	/** Hive signature hex length: 1-byte recovery + 32-byte r + 32-byte s. */
	public const LENGTH = 130;

	/**
	 * Sign a hex-encoded SHA-256 digest. Always returns a 130-character hex signature.
	 */
	public static function signDigest(string $privateKeyHex, string $digestHex): string
	{
		$ec = new EC('secp256k1');
		$key = $ec->keyFromPrivate($privateKeyHex, 'hex');
		$serializer = new Serializer();
		$attempts = 0;
		$signatureString = '';
		$recovery = 0;

		do {
			$attempts++;
			$attemptsHex = $serializer->dec2hex($attempts);
			$pers = hash('sha256', hex2bin($digestHex . $attemptsHex));
			$signature = $key->sign($digestHex, [
				'canonical' => true,
				'pers'      => $pers,
			]);
			$r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
			$s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
			$signatureString = $r . $s;
			$recovery = (int) $signature->recoveryParam;
		} while (!self::isCanonical($signatureString));

		return $serializer->dec2hex($recovery + 31) . $signatureString;
	}

	/**
	 * Canonical check over r||s hex (128 chars), matching hive-js / dhive byte offsets.
	 */
	public static function isCanonical(string $rAndSHex): bool
	{
		if (strlen($rAndSHex) !== 128) {
			return false;
		}

		$sig0 = hexdec(substr($rAndSHex, 0, 2));
		$sig1 = hexdec(substr($rAndSHex, 2, 2));
		$sig32 = hexdec(substr($rAndSHex, 64, 2));
		$sig33 = hexdec(substr($rAndSHex, 66, 2));

		return !($sig0 & 0x80)
			&& !($sig0 === 0 && !($sig1 & 0x80))
			&& !($sig32 & 0x80)
			&& !($sig32 === 0 && !($sig33 & 0x80));
	}
}
