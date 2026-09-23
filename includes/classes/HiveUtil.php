<?php

namespace HiveNova\Core;

use Hive\Hive;

$hivePhp = __DIR__.'/../../vendor/mahdiyari/hive-php/lib/Hive.php';
if (file_exists($hivePhp)) {
	require_once $hivePhp;
}

class HiveUtil
{
	/** @var list<string>|null Test seam so RPC retries can target a closed port. */
	static public $rpcNodesOverride = null;

	static public function getRpcNodes(): array
	{
		if (self::$rpcNodesOverride !== null) {
			return self::$rpcNodesOverride;
		}

		return \HIVE_RPC_NODES;
	}

	static public function isRpcError(mixed $result): bool
	{
		if (!is_array($result)) {
			return true;
		}

		return array_key_exists('code', $result) && array_key_exists('message', $result);
	}

	static public function rpcErrorMessage(mixed $result): string
	{
		$parts = [];
		self::collectErrorMessages($result, $parts, 0);

		return trim(implode(' ', $parts));
	}

	static public function isResourceCreditError(string $message): bool
	{
		$text = strtolower($message);
		if ($text === '') {
			return false;
		}
		if (str_contains($text, 'resource credit')) {
			return true;
		}
		if (str_contains($text, 'rc_needed') || str_contains($text, 'has_mana')) {
			return true;
		}

		return (bool) preg_match('/\bhas\b.+\brc\b.+\bneeds\b.+\brc\b/s', $text);
	}

	/**
	 * @param list<string> $parts
	 */
	private static function collectErrorMessages(mixed $value, array &$parts, int $depth): void
	{
		if ($depth > 6 || count($parts) >= 8) {
			return;
		}
		if (is_string($value)) {
			$trimmed = trim($value);
			if ($trimmed !== '') {
				$parts[] = $trimmed;
			}
			return;
		}
		if (!is_array($value)) {
			return;
		}
		foreach (['message', 'data'] as $key) {
			if (array_key_exists($key, $value)) {
				self::collectErrorMessages($value[$key], $parts, $depth + 1);
			}
		}
	}

	static public function rpcNodesToTry(?int $maxNodes = null): array
	{
		$nodes = self::getRpcNodes();
		if ($maxNodes === null) {
			return $nodes;
		}

		return array_slice($nodes, 0, max(1, $maxNodes));
	}

	static public function rpcCall(string $method, string $params, ?int $maxNodes = null): mixed
	{
		foreach (HiveUtil::rpcNodesToTry($maxNodes) as $rpcNode) {
			try {
				$result = self::callHive([
					'rpcNodes' => [$rpcNode],
					'timeout'  => \HIVE_RPC_TIMEOUT,
				], static function (Hive $hive) use ($method, $params) {
					return $hive->call($method, $params);
				});
			} catch (\Throwable $e) {
				continue;
			}

			if (!HiveUtil::isRpcError($result)) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Run $callback with a Hive client, then restore the previous error handler.
	 *
	 * Hive::__construct() pushes a handler that throws on every diagnostic, including
	 * E_DEPRECATED. Leaving it in place turns vendor deprecations into uncaught errors.
	 * The locked hive-php build installs that handler before the constructor can throw.
	 *
	 * @template T
	 * @param callable(Hive): T $callback
	 * @return T
	 */
	static public function callHive(array $options, callable $callback): mixed
	{
		try {
			$hive = new Hive($options);
			return $callback($hive);
		} finally {
			restore_error_handler();
		}
	}

	static public function isAccountValid($hiveaccount): bool
	{
		if (is_null($hiveaccount) || strlen($hiveaccount) == 0 || strlen((string) $hiveaccount) > 16) {
			return false;
		}

		return (bool) preg_match('/^[a-z][-a-z0-9]+[a-z0-9](\.[a-z][-a-z0-9]+[a-z0-9])*$/', (string) $hiveaccount);
	}

	static public function isSignValid($hiveaccount, $signedblob): bool
	{
		if (!HiveUtil::isAccountValid($hiveaccount)) {
			return false;
		}

		if (is_null($signedblob) || strlen($signedblob) < 32 || strlen($signedblob) > 132) {
			return false;
		}

		if (!PlayerUtil::isNameValid($signedblob)) {
			return false;
		}

		$result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]');

		return self::postingSignatureMatches((string) $hiveaccount, (string) $signedblob, $result);
	}

	/**
	 * Confirm a Keychain blob against the posting key in a get_accounts row.
	 * Decode failures and vendor throwables are invalid signatures, not page fatals.
	 */
	static public function postingSignatureMatches(string $hiveaccount, string $signedblob, mixed $accountResult): bool
	{
		if (!is_array($accountResult) || count($accountResult) == 0 || !isset($accountResult[0]) || !is_array($accountResult[0]) || !array_key_exists('posting', $accountResult[0])) {
			return false;
		}

		$publicKeyString = $accountResult[0]['posting']['key_auths'][0][0] ?? null;
		if (!is_string($publicKeyString) || $publicKeyString === '') {
			return false;
		}

		$publicKey = self::publicKeyFromString($publicKeyString);
		if ($publicKey === null) {
			return false;
		}

		$message = hash('sha256', $hiveaccount.' is my account.');
		try {
			$verified = $publicKey->verify($message, $signedblob);
		} catch (\Throwable $e) {
			return false;
		}

		return (bool) $verified;
	}

	/**
	 * Decode a Hive public key without installing Hive's throw-on-deprecation handler.
	 * stephenhill/base58 1.x marks Base58::__construct($service = null) implicitly nullable;
	 * PHP 8.4+ deprecates that while the class is compiled. Hive::__construct promotes the
	 * deprecation to an exception, which is the Keychain register "Unknown error".
	 */
	static public function publicKeyFromString(string $publicKeyString): ?\Hive\Helpers\PublicKey
	{
		try {
			// Compile Base58 before PublicKey constructs it, under the caller's handler.
			class_exists(\StephenHill\Base58::class);

			return new \Hive\Helpers\PublicKey($publicKeyString);
		} catch (\Throwable $e) {
			return null;
		}
	}

	static public function accountExists($hiveaccount): bool
	{
		$hiveaccount = strtolower((string) $hiveaccount);

		if (!HiveUtil::isAccountValid($hiveaccount)) {
			return false;
		}

		$result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]');

		return is_array($result) && count($result) > 0;
	}

	/**
	 * Batch Hive account existence. Invalid Hive usernames are false without an RPC.
	 *
	 * @param list<string> $accounts
	 * @return array<string, bool> lowercase name => exists on chain
	 */
	static public function accountsExist(array $accounts): array
	{
		$result = [];
		$valid = [];

		foreach ($accounts as $account) {
			$key = strtolower(trim((string) $account));
			if ($key === '' || array_key_exists($key, $result)) {
				continue;
			}
			if (!self::isAccountValid($key)) {
				$result[$key] = false;
				continue;
			}
			$result[$key] = false;
			$valid[] = $key;
		}

		if ($valid === []) {
			return $result;
		}

		$rpcResult = self::rpcCall('condenser_api.get_accounts', json_encode([$valid]));
		foreach (self::existingNamesFromAccountList($rpcResult) as $name) {
			if (array_key_exists($name, $result)) {
				$result[$name] = true;
			}
		}

		return $result;
	}

	/**
	 * @return list<string> lowercase account names present in a get_accounts result
	 */
	static public function existingNamesFromAccountList(mixed $result): array
	{
		if (!is_array($result) || self::isRpcError($result)) {
			return [];
		}

		$names = [];
		foreach ($result as $account) {
			if (!is_array($account)) {
				continue;
			}
			$name = strtolower(trim((string) ($account['name'] ?? '')));
			if ($name !== '') {
				$names[$name] = $name;
			}
		}

		return array_values($names);
	}

	static public function extractProfileAbout(mixed $account): string
	{
		if (!is_array($account)) {
			return '';
		}

		foreach (['posting_json_metadata', 'json_metadata'] as $field) {
			if (empty($account[$field]) || !is_string($account[$field])) {
				continue;
			}

			$decoded = json_decode($account[$field], true);
			if (!is_array($decoded)) {
				continue;
			}

			$about = $decoded['profile']['about'] ?? null;
			if (is_string($about) && trim($about) !== '') {
				return trim($about);
			}
		}

		return '';
	}

	static public function getAccountAbout(string $hiveaccount): string
	{
		if (!HiveUtil::isAccountValid($hiveaccount)) {
			return '';
		}

		$result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]', 3);
		if (!is_array($result) || !isset($result[0])) {
			return '';
		}

		return HiveUtil::extractProfileAbout($result[0]);
	}

	static public function extractMemoKey(mixed $account): string
	{
		if (!is_array($account) || empty($account['memo_key']) || !is_string($account['memo_key'])) {
			return '';
		}

		$key = trim($account['memo_key']);
		if (!preg_match('/^STM[1-9A-HJ-NP-Za-km-z]{40,80}$/', $key)) {
			return '';
		}

		return $key;
	}

	static public function getMemoPublicKey(string $hiveaccount): string
	{
		if (!HiveUtil::isAccountValid($hiveaccount)) {
			return '';
		}

		$result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]', 3);
		if (!is_array($result) || !isset($result[0])) {
			return '';
		}

		return HiveUtil::extractMemoKey($result[0]);
	}
}
