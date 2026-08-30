<?php

use HiveNova\Core\HiveCommentPoster;

use PHPUnit\Framework\TestCase;

class HiveCommentPosterTest extends TestCase
{
	/** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}> */
	private array $calls = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->calls = [];
		HiveCommentPoster::setBroadcaster(null);
		HiveCommentPoster::setErrorLogger(null);
	}

	protected function tearDown(): void
	{
		HiveCommentPoster::setBroadcaster(null);
		HiveCommentPoster::setErrorLogger(null);
		parent::tearDown();
	}

	public function testHappyPathReturnsTrxId(): void
	{
		HiveCommentPoster::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'blogtrx1'];
		});

		$result = (new HiveCommentPoster())->post(
			'Season.Blog',
			'hivenova-u2-season-12',
			'HiveNova Season 12 Recap',
			"# Season 12\n\nBody",
			['moon', 'hive-pizza', 'gaming', 'season'],
			'5Ktestwif'
		);

		$this->assertTrue($result['ok']);
		$this->assertSame('blogtrx1', $result['trx_id']);
		$this->assertCount(1, $this->calls);
		$this->assertSame('', $this->calls[0][0]);
		$this->assertSame('moon', $this->calls[0][1]);
		$this->assertSame('season.blog', $this->calls[0][2]);
		$this->assertSame('hivenova-u2-season-12', $this->calls[0][3]);
		$this->assertSame('HiveNova Season 12 Recap', $this->calls[0][4]);
		$this->assertStringContainsString('Season 12', $this->calls[0][5]);
		$meta = json_decode($this->calls[0][6], true);
		$this->assertSame(['moon', 'hive-pizza', 'gaming', 'season'], $meta['tags']);
		$this->assertSame('5Ktestwif', $this->calls[0][7]);
	}

	public function testInvalidAccountOrEmptyBodyDoesNotBroadcast(): void
	{
		HiveCommentPoster::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$poster = new HiveCommentPoster();
		$this->assertFalse($poster->post('1bad', 'ok-permlink', 'Title', 'Body', ['hivenova'], '5K')['ok']);
		$this->assertFalse($poster->post('goodacct', 'ok-permlink', 'Title', '', ['hivenova'], '5K')['ok']);
		$this->assertFalse($poster->post('goodacct', 'ok-permlink', 'Title', 'Body', ['hivenova'], '')['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testReplyRequiresValidParentAuthorAndPermlink(): void
	{
		HiveCommentPoster::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'x'];
		});
		$poster = new HiveCommentPoster();
		$this->assertFalse($poster->post(
			'goodacct', 'ok-permlink', 'Title', 'Body', ['moon'], '5K', '1bad', 'parent-post'
		)['ok']);
		$this->assertFalse($poster->post(
			'goodacct', 'ok-permlink', 'Title', 'Body', ['moon'], '5K', 'parentacct', ''
		)['ok']);
		$this->assertFalse($poster->post(
			'goodacct', 'ok-permlink', 'Title', 'Body', ['moon'], '5K', 'parentacct', 'Bad Parent!'
		)['ok']);
		$this->assertSame([], $this->calls);
	}

	public function testEmptyTagsFallBackToMoon(): void
	{
		HiveCommentPoster::setBroadcaster(function (...$args) {
			$this->calls[] = $args;
			return ['trx_id' => 'blogtrx2'];
		});

		$result = (new HiveCommentPoster())->post(
			'goodacct',
			'ok-permlink',
			'Title',
			'Body',
			['', '!!!'],
			'5Ktestwif'
		);

		$this->assertTrue($result['ok']);
		$this->assertCount(1, $this->calls);
		$this->assertSame('', $this->calls[0][0]);
		$this->assertSame(HiveCommentPoster::FALLBACK_TAG, $this->calls[0][1]);
		$meta = json_decode($this->calls[0][6], true);
		$this->assertSame([HiveCommentPoster::FALLBACK_TAG], $meta['tags']);
	}

	public function testBroadcasterExceptionReturnsFailure(): void
	{
		HiveCommentPoster::setBroadcaster(static function () {
			throw new RuntimeException('boom');
		});
		$logs = [];
		HiveCommentPoster::setErrorLogger(static function (string $line) use (&$logs) {
			$logs[] = $line;
		});

		$result = (new HiveCommentPoster())->post(
			'goodacct',
			'hivenova-u1-season-1',
			'Title',
			'Body',
			['hivenova'],
			'5Ktest'
		);
		$this->assertFalse($result['ok']);
		$this->assertNotEmpty($logs);
		$this->assertStringNotContainsString('5Ktest', $logs[0]);
	}

	public function testMissingTrxIdIsFailure(): void
	{
		HiveCommentPoster::setErrorLogger(static function (): void {
		});
		HiveCommentPoster::setBroadcaster(static fn () => ['error' => 'nope']);
		$result = (new HiveCommentPoster())->post(
			'goodacct',
			'hivenova-u1-season-1',
			'Title',
			'Body',
			['hivenova'],
			'5Ktest'
		);
		$this->assertFalse($result['ok']);
	}

	public function testDefaultBroadcastPathUsesHiveBroadcast(): void
	{
		require_once dirname(__DIR__, 2) . '/vendor/mahdiyari/hive-php/lib/Hive.php';
		$key = new \Hive\Helpers\PrivateKey(hash('sha256', 'comment-poster-broadcast|posting'), true);

		HiveCommentPoster::setBroadcaster(null);
		HiveNova\Core\HiveBroadcast::setHiveFactory(static function () use ($key) {
			return new class ($key) {
				public string $chainId = 'beeab0de00000000000000000000000000000000000000000000000000000000';

				public function __construct(private \Hive\Helpers\PrivateKey $key)
				{
				}

				public function privateKeyFrom(string $wif): \Hive\Helpers\PrivateKey
				{
					return $this->key;
				}

				public function createTransaction(array $operations): \Hive\Helpers\Transaction
				{
					$trx = new \Hive\Helpers\Transaction();
					$trx->ref_block_num = 1;
					$trx->ref_block_prefix = 1;
					$trx->expiration = '2030-01-01T00:00:00';
					$trx->extensions = [];
					$trx->signatures = [];
					$trx->operations = $operations;

					return $trx;
				}

				public function broadcastTransaction(\Hive\Helpers\Transaction $trx): array
				{
					return ['trx_id' => 'comment-live-path'];
				}
			};
		});

		try {
			$result = (new HiveCommentPoster())->post(
				'goodacct',
				'hivenova-u3-season-1',
				'Title',
				'Body',
				['moon'],
				$key->stringKey
			);
			$this->assertTrue($result['ok']);
			$this->assertSame('comment-live-path', $result['trx_id']);
		} finally {
			HiveNova\Core\HiveBroadcast::setHiveFactory(null);
			HiveNova\Core\HiveBroadcast::setTransactionBroadcaster(null);
		}
	}
}