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
		return HIVE_RPC_NODES;
	}

	static public function isRpcError(mixed $result): bool
	{
		if (!is_array($result)) {
			return true;
		}

		return array_key_exists('code', $result) && array_key_exists('message', $result);
	}

	static public function rpcCall(string $method, string $params): mixed
	{
		foreach (HiveUtil::getRpcNodes() as $rpcNode) {
			try {
				$hive = new Hive([
					'rpcNodes' => [$rpcNode],
					'timeout'  => HIVE_RPC_TIMEOUT,
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
}
