<?php

namespace HiveNova\Core;

/**
 * Encrypt-at-rest for config secrets (Hive WIFs) and env overrides.
 *
 * Ciphertext format: enc:v1:base64(nonce || ciphertext || tag) using AES-256-GCM.
 * Key material: APP_KEY env, else installer $salt from includes/config.php.
 * Without key material, values are stored/returned as plaintext (compat).
 */
class ConfigSecret
{
	public const PREFIX = 'enc:v1:';

	public const ENV_INACTIVE_ACTIVE_KEY = 'HIVE_INACTIVE_MEMO_ACTIVE_KEY';
	public const ENV_SOCIAL_MEMO_KEY = 'HIVE_SOCIAL_MEMO_MEMO_KEY';
	public const ENV_SEASON_WALLET_KEY = 'SEASON_WALLET_ACTIVE_KEY';
	public const ENV_SEASON_BLOG_KEY = 'SEASON_BLOG_POSTING_KEY';

	public static function isEncrypted(string $value): bool
	{
		return str_starts_with($value, self::PREFIX);
	}

	public static function hasKeyMaterial(): bool
	{
		return self::keyBytes() !== null;
	}

	/**
	 * Encrypt plaintext for DB storage when key material is available.
	 * Empty string stays empty. Already-encrypted values are returned unchanged.
	 */
	public static function seal(string $plaintext): string
	{
		$plaintext = trim($plaintext);
		if ($plaintext === '' || self::isEncrypted($plaintext)) {
			return $plaintext;
		}

		$key = self::keyBytes();
		if ($key === null) {
			return $plaintext;
		}

		$nonce = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
		if ($cipher === false || $tag === '') {
			throw new \RuntimeException('Failed to encrypt config secret.');
		}

		return self::PREFIX . base64_encode($nonce . $cipher . $tag);
	}

	/**
	 * Decrypt a sealed value, or return plaintext unchanged.
	 */
	public static function reveal(string $stored): string
	{
		$stored = trim($stored);
		if ($stored === '' || !self::isEncrypted($stored)) {
			return $stored;
		}

		$key = self::keyBytes();
		if ($key === null) {
			throw new \RuntimeException('Encrypted config secret present but APP_KEY/salt is unavailable.');
		}

		$raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
		if ($raw === false || strlen($raw) < 12 + 16 + 1) {
			throw new \RuntimeException('Encrypted config secret is corrupt.');
		}

		$nonce = substr($raw, 0, 12);
		$tag = substr($raw, -16);
		$cipher = substr($raw, 12, -16);
		$plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
		if ($plain === false) {
			throw new \RuntimeException('Failed to decrypt config secret.');
		}

		return $plain;
	}

	/**
	 * Prefer env override, else reveal DB value.
	 */
	public static function resolve(?string $envName, mixed $dbValue): string
	{
		if ($envName !== null && $envName !== '') {
			$env = getenv($envName);
			if (is_string($env) && trim($env) !== '') {
				return trim($env);
			}
		}

		return self::reveal((string) $dbValue);
	}

	/**
	 * True when env override or non-empty DB value is set (without decrypting).
	 */
	public static function isPresent(?string $envName, mixed $dbValue): bool
	{
		if ($envName !== null && $envName !== '') {
			$env = getenv($envName);
			if (is_string($env) && trim($env) !== '') {
				return true;
			}
		}

		return trim((string) $dbValue) !== '';
	}

	/**
	 * @return non-empty-string|null 32 raw bytes
	 */
	private static function keyBytes(): ?string
	{
		$env = getenv('APP_KEY');
		if (is_string($env) && $env !== '') {
			$raw = str_starts_with($env, 'base64:')
				? base64_decode(substr($env, 7), true)
				: $env;
			if (is_string($raw) && $raw !== '') {
				return hash('sha256', 'hivenova-config-secret|' . $raw, true);
			}
		}

		if (!isset($GLOBALS['salt'])) {
			$path = (defined('ROOT_PATH') ? ROOT_PATH : '') . 'includes/config.php';
			if ($path !== 'includes/config.php' && is_readable($path)) {
				require $path;
			} elseif (is_readable('includes/config.php')) {
				require 'includes/config.php';
			}
		}

		$salt = $GLOBALS['salt'] ?? null;
		if (is_string($salt) && $salt !== '') {
			return hash('sha256', 'hivenova-config-secret|' . $salt, true);
		}

		return null;
	}
}
