<?php

namespace HiveNova\Core;

use Hive\Hive;

$hivePhp = __DIR__.'/../../vendor/mahdiyari/hive-php/lib/Hive.php';
if (file_exists($hivePhp)) {
	require_once $hivePhp;
}

class HiveUtil
{
	static public function getRpcNodes(): array
	{
		return \HIVE_RPC_NODES;
	}

	static public function isRpcError(mixed $result): bool
	{
		if (!is_array($result)) {
			return true;
		}

		return array_key_exists('code', $result) && array_key_exists('message', $result);
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
				$hive = new Hive([
					'rpcNodes' => [$rpcNode],
					'timeout'  => \HIVE_RPC_TIMEOUT,
				]);
				$result = $hive->call($method, $params);
			} catch (\Throwable $e) {
				continue;
			}

			if (!HiveUtil::isRpcError($result)) {
				return $result;
			}
		}

		return null;
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

		if (!is_array($result) || count($result) == 0 || !isset($result[0]) || !array_key_exists('posting', $result[0])) {
			return false;
		}

		$publicKeyString = $result[0]['posting']['key_auths'][0][0];
		$publicKey = (new Hive())->publicKeyFrom($publicKeyString);

		if (is_null($publicKey)) {
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

	static public function accountExists($hiveaccount): bool
	{
		$hiveaccount = strtolower((string) $hiveaccount);

		if (!HiveUtil::isAccountValid($hiveaccount)) {
			return false;
		}

		$result = HiveUtil::rpcCall('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]');

		return is_array($result) && count($result) > 0;
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
