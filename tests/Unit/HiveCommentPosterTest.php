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
		$this->assertSame('', $this->calls[0][1]);
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
}