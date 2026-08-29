<?php

namespace HiveNova\Core;

use Hive\Hive;
use Throwable;

/**
 * Fail-closed Hive root comment (blog post) helper. Never logs the posting key.
 */
class HiveCommentPoster
{
	/** Empty for top-level/root posts; set only for replies or community posts. */
	public const DEFAULT_PARENT_PERMLINK = '';

	/** Used when the caller passes no valid tags. */
	public const FALLBACK_TAG = 'moon';

	/** @var callable|null fn(string $parentAuthor, string $parentPermlink, string $author, string $permlink, string $title, string $body, string $jsonMetadata, string $wif): mixed */
	private static $broadcaster = null;

	/** @var callable|null fn(string $message): void */
	private static $errorLogger = null;

	public static function setBroadcaster(?callable $broadcaster): void
	{
		self::$broadcaster = $broadcaster;
	}

	public static function setErrorLogger(?callable $logger): void
	{
		self::$errorLogger = $logger;
	}

	/**
	 * @param list<string> $tags
	 * @return array{ok: bool, trx_id: string}
	 */
	public function post(
		string $author,
		string $permlink,
		string $title,
		string $body,
		array $tags,
		string $wif,
		string $parentAuthor = '',
		string $parentPermlink = self::DEFAULT_PARENT_PERMLINK,
	): array {
		try {
			$author = strtolower(trim($author));
			$permlink = trim($permlink);
			$title = trim($title);
			$body = trim($body);
			$parentPermlink = trim($parentPermlink);
			$parentAuthor = strtolower(trim($parentAuthor));

			if ($wif === '' || !HiveUtil::isAccountValid($author)) {
				return self::fail();
			}
			if ($permlink === '' || $title === '' || $body === '') {
				return self::fail();
			}
			// Replies/community posts need both parent fields; root posts use empty parent_author
			// and empty parent_permlink by default.
			if ($parentAuthor !== '') {
				if (!HiveUtil::isAccountValid($parentAuthor) || $parentPermlink === '') {
					return self::fail();
				}
			}
			if ($parentPermlink !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/', $parentPermlink)) {
				return self::fail();
			}
			if (!preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/', $permlink)) {
				return self::fail();
			}

			$cleanTags = [];
			foreach ($tags as $tag) {
				$tag = strtolower(trim((string) $tag));
				if ($tag !== '' && preg_match('/^[a-z0-9-]{1,24}$/', $tag)) {
					$cleanTags[] = $tag;
				}
			}
			$cleanTags = array_values(array_unique($cleanTags));
			if ($cleanTags === []) {
				$cleanTags = [self::FALLBACK_TAG];
			}

			$jsonMetadata = (string) json_encode([
				'tags' => $cleanTags,
				'app'  => 'hivenova/season',
			], JSON_UNESCAPED_SLASHES);
			if ($jsonMetadata === '') {
				return self::fail();
			}

			$result = $this->broadcast(
				$parentAuthor,
				$parentPermlink,
				$author,
				$permlink,
				$title,
				$body,
				$jsonMetadata,
				$wif
			);
			$trxId = self::extractTrxId($result);
			if ($trxId === '') {
				self::logFailure($author, $permlink, HiveUtil::rpcErrorMessage($result));
				return self::fail();
			}

			return ['ok' => true, 'trx_id' => $trxId];
		} catch (Throwable $e) {
			self::logFailure($author ?? '', $permlink ?? '', $e->getMessage());
			return self::fail();
		}
	}

	private function broadcast(
		string $parentAuthor,
		string $parentPermlink,
		string $author,
		string $permlink,
		string $title,
		string $body,
		string $jsonMetadata,
		string $wif,
	): mixed {
		if (self::$broadcaster !== null) {
			return (self::$broadcaster)(
				$parentAuthor,
				$parentPermlink,
				$author,
				$permlink,
				$title,
				$body,
				$jsonMetadata,
				$wif
			);
		}

		$hivePhp = __DIR__ . '/../../vendor/mahdiyari/hive-php/lib/Hive.php';
		if (is_readable($hivePhp)) {
			require_once $hivePhp;
		}

		return HiveUtil::withHiveClient(static function () use (
			$parentAuthor,
			$parentPermlink,
			$author,
			$permlink,
			$title,
			$body,
			$jsonMetadata,
			$wif
		) {
			$hive = new Hive([
				'rpcNodes' => HiveUtil::rpcNodesToTry(1),
				'timeout'  => \HIVE_RPC_TIMEOUT,
			]);
			HiveUtil::installHiveClientErrorHandler();
			$key = $hive->privateKeyFrom($wif);

			return $hive->broadcast($key, 'comment', [
				$parentAuthor,
				$parentPermlink,
				$author,
				$permlink,
				$title,
				$body,
				$jsonMetadata,
			]);
		});
	}

	private static function extractTrxId(mixed $result): string
	{
		if (!is_array($result) || HiveUtil::isRpcError($result)) {
			return '';
		}

		foreach (['trx_id', 'id'] as $key) {
			if (!empty($result[$key]) && is_string($result[$key])) {
				return $result[$key];
			}
		}

		return '';
	}

	/**
	 * @return array{ok: bool, trx_id: string}
	 */
	private static function fail(): array
	{
		return ['ok' => false, 'trx_id' => ''];
	}

	private static function logFailure(string $author, string $permlink, string $detail): void
	{
		$line = 'HiveCommentPoster: failed for @' . $author . '/' . $permlink . ': ' . $detail;
		if (self::$errorLogger !== null) {
			(self::$errorLogger)($line);
			return;
		}

		error_log($line);
	}
}
