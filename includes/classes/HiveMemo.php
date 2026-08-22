<?php

namespace HiveNova\Core;

use Elliptic\EC;
use Hive\Helpers\PrivateKey;
use Hive\Helpers\PublicKey;
use StephenHill\Base58;
use Throwable;

/**
 * Hive wallet memo encryption (hive-tx / dhive compatible).
 * Never logs keys. Fail-open callers should catch or use encodeOrEmpty().
 */
class HiveMemo
{
	public const MAX_MEMO_BYTES = 2048;

	public static function encodeOrEmpty(string $fromMemoWif, string $toMemoPub, string $memo, ?int $nonce = null): string
	{
		try {
			return self::encode($fromMemoWif, $toMemoPub, $memo, $nonce);
		} catch (Throwable $e) {
			return '';
		}
	}

	public static function encode(string $fromMemoWif, string $toMemoPub, string $memo, ?int $nonce = null): string
	{
		if ($memo === '' || !str_starts_with($memo, '#')) {
			return $memo;
		}

		$plaintext = substr($memo, 1);
		$fromPriv = new PrivateKey($fromMemoWif);
		$toPub = new PublicKey($toMemoPub);
		$fromPub = $fromPriv->createPublic();

		$fromBytes = self::compressedKeyBytes($fromPub->hexKey);
		$toBytes = self::compressedKeyBytes($toPub->hexKey);
		$nonceValue = $nonce ?? self::uniqueNonce();
		$packedPlain = self::writeVString($plaintext);

		[$ciphertext, $check] = self::crypt($fromPriv->hexKey, $toPub->hexKey, $nonceValue, $packedPlain);

		$payload = $fromBytes . $toBytes . self::u64le($nonceValue) . self::u32le($check)
			. self::varint32(strlen($ciphertext)) . $ciphertext;

		$encoded = '#' . (new Base58())->encode($payload);
		if (strlen($encoded) > self::MAX_MEMO_BYTES) {
			throw new \RuntimeException('encrypted memo exceeds chain limit');
		}

		return $encoded;
	}

	public static function decode(string $memoWif, string $encrypted): string
	{
		if ($encrypted === '' || !str_starts_with($encrypted, '#')) {
			return $encrypted;
		}

		$raw = (new Base58())->decode(substr($encrypted, 1));
		if (strlen($raw) < 78) {
			throw new \RuntimeException('encrypted memo too short');
		}

		$fromHex = bin2hex(substr($raw, 0, 33));
		$toHex = bin2hex(substr($raw, 33, 33));
		$nonce = self::u64leDecode(substr($raw, 66, 8));
		$check = self::u32leDecode(substr($raw, 74, 4));
		[$cipherLen, $varintSize] = self::readVarint32(substr($raw, 78));
		$ciphertext = substr($raw, 78 + $varintSize, $cipherLen);
		if (strlen($ciphertext) !== $cipherLen) {
			throw new \RuntimeException('encrypted memo truncated');
		}

		$priv = new PrivateKey($memoWif);
		$ourPub = $priv->createPublic()->hexKey;
		$otherHex = hash_equals($ourPub, $fromHex) ? $toHex : $fromHex;

		$plainPacked = self::decrypt($priv->hexKey, $otherHex, $nonce, $ciphertext, $check);

		return '#' . self::readVString($plainPacked);
	}

	/**
	 * @return array{0: string, 1: int}
	 */
	private static function crypt(string $privHex, string $pubHex, int $nonce, string $message): array
	{
		$shared = self::sharedSecret($privHex, $pubHex);
		$keyMaterial = hash('sha512', self::u64le($nonce) . $shared, true);
		$aesKey = substr($keyMaterial, 0, 32);
		$iv = substr($keyMaterial, 32, 16);
		$check = self::u32leDecode(substr(hash('sha256', $keyMaterial, true), 0, 4));
		$ciphertext = openssl_encrypt($message, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
		if (!is_string($ciphertext) || $ciphertext === '') {
			throw new \RuntimeException('aes encrypt failed');
		}

		return [$ciphertext, $check];
	}

	private static function decrypt(string $privHex, string $pubHex, int $nonce, string $ciphertext, int $check): string
	{
		$shared = self::sharedSecret($privHex, $pubHex);
		$keyMaterial = hash('sha512', self::u64le($nonce) . $shared, true);
		$computed = self::u32leDecode(substr(hash('sha256', $keyMaterial, true), 0, 4));
		if ($computed !== $check) {
			throw new \RuntimeException('invalid memo key');
		}
		$aesKey = substr($keyMaterial, 0, 32);
		$iv = substr($keyMaterial, 32, 16);
		$plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);
		if (!is_string($plain)) {
			throw new \RuntimeException('aes decrypt failed');
		}

		return $plain;
	}

	private static function sharedSecret(string $privHex, string $pubHex): string
	{
		$ec = new EC('secp256k1');
		$priv = $ec->keyFromPrivate($privHex, 'hex');
		$pub = $ec->keyFromPublic($pubHex, 'hex');
		$xHex = $priv->derive($pub->getPublic())->toString(16);
		if (strlen($xHex) % 2 === 1) {
			$xHex = '0' . $xHex;
		}
		$xHex = str_pad($xHex, 64, '0', STR_PAD_LEFT);

		return hash('sha512', hex2bin($xHex), true);
	}

	private static function uniqueNonce(): int
	{
		$ms = (int) floor(microtime(true) * 1000);
		$entropy = random_int(0, 0xFFFF);

		return ($ms << 16) | $entropy;
	}

	private static function compressedKeyBytes(string $hex): string
	{
		$bin = hex2bin($hex);
		if ($bin === false || strlen($bin) !== 33) {
			throw new \RuntimeException('invalid compressed public key');
		}

		return $bin;
	}

	private static function writeVString(string $value): string
	{
		$bytes = $value;

		return self::varint32(strlen($bytes)) . $bytes;
	}

	private static function readVString(string $packed): string
	{
		[$len, $size] = self::readVarint32($packed);
		$value = substr($packed, $size, $len);
		if (strlen($value) !== $len) {
			throw new \RuntimeException('vstring truncated');
		}

		return $value;
	}

	private static function varint32(int $value): string
	{
		$out = '';
		while ($value >= 0x80) {
			$out .= chr(($value & 0x7F) | 0x80);
			$value >>= 7;
		}

		return $out . chr($value);
	}

	/**
	 * @return array{0: int, 1: int}
	 */
	private static function readVarint32(string $data): array
	{
		$value = 0;
		$shift = 0;
		$len = strlen($data);
		for ($i = 0; $i < $len; $i++) {
			$byte = ord($data[$i]);
			$value |= ($byte & 0x7F) << $shift;
			if (($byte & 0x80) === 0) {
				return [$value, $i + 1];
			}
			$shift += 7;
			if ($shift > 28) {
				break;
			}
		}

		throw new \RuntimeException('invalid varint');
	}

	private static function u64le(int $value): string
	{
		$out = '';
		for ($i = 0; $i < 8; $i++) {
			$out .= chr($value & 0xFF);
			$value >>= 8;
		}

		return $out;
	}

	private static function u64leDecode(string $bytes): int
	{
		$value = 0;
		for ($i = 7; $i >= 0; $i--) {
			$value = ($value << 8) | ord($bytes[$i]);
		}

		return $value;
	}

	private static function u32le(int $value): string
	{
		return pack('V', $value);
	}

	private static function u32leDecode(string $bytes): int
	{
		$parsed = unpack('V', $bytes);

		return is_array($parsed) ? (int) $parsed[1] : 0;
	}
}
