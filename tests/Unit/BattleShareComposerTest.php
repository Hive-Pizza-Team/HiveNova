<?php

use HiveNova\Core\BattleShareComposer;
use PHPUnit\Framework\TestCase;

class BattleShareComposerTest extends TestCase
{
	private BattleShareComposer $composer;

	private array $labels;

	protected function setUp(): void
	{
		parent::setUp();
		$this->composer = new BattleShareComposer();
		$this->labels = [
			'result_attacker' => 'Attacker won',
			'result_defender' => 'Defender won',
			'result_draw'     => 'Draw',
			'result_label'    => 'Result',
			'time_label'      => 'Time',
			'attacker_lost'   => 'Attacker losses',
			'defender_lost'   => 'Defender losses',
			'debris'          => 'Debris',
			'steal'           => 'Captured',
			'vs'              => 'vs',
			'game_name'       => 'LocalMoon',
			'title_format'    => '%s Battle: %s vs %s',
			'cta'             => 'Play on LocalMoon',
			'footer'          => 'Shared via LocalMoon',
			'resource_901'    => 'Metal',
			'resource_902'    => 'Crystal',
			'resource_903'    => 'Deuterium',
		];
	}

	private function sampleReport(string $result = 'a', int $time = 1700000000): array
	{
		return [
			'result' => $result,
			'time'   => $time,
			'units'  => [1200, 800],
			'debris' => [901 => 50000, 902 => 25000],
			'steal'  => [901 => 100000, 902 => 50000, 903 => 10000],
			'players' => [
				1 => ['name' => 'Alice'],
				2 => ['name' => 'Bob'],
			],
		];
	}

	public function testHappyPathIncludesReferralWhenActive(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport(),
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertTrue($result['canShare']);
		$this->assertNotNull($result['draft']);
		$this->assertSame('LocalMoon Battle: Alice vs Bob', $result['draft']['preview_title']);
		$this->assertSame('', $result['draft']['title']);
		$this->assertStringContainsString('Alice vs Bob', $result['draft']['body']);
		$this->assertStringContainsString('Attacker won', $result['draft']['body']);
		$this->assertStringContainsString('https://moon.hive.pizza/index.php?ref=42', $result['draft']['body']);
		$this->assertStringStartsWith('re-peaksnaps-', $result['draft']['permlink']);
		$this->assertSame('peak.snaps', $result['draft']['parent_author']);
		$this->assertSame('', $result['draft']['parent_permlink']);
		$this->assertTrue($result['draft']['snap_mode']);
		$meta = json_decode($result['draft']['json_metadata'], true);
		$this->assertSame('hivenova/battle-share', $meta['app']);
		$this->assertContains('snaps', $meta['tags']);
	}

	public function testReferralsOffOmitsRefQuery(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport(),
			'abc123',
			42,
			'aliceaaa',
			false,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertTrue($result['canShare']);
		$this->assertStringContainsString('https://moon.hive.pizza/index.php', $result['draft']['body']);
		$this->assertStringNotContainsString('?ref=', $result['draft']['body']);
	}

	public function testMissingHiveAccountBlocksShare(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport(),
			'abc123',
			42,
			'',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertFalse($result['canShare']);
		$this->assertSame('no_hive_account', $result['reason']);
		$this->assertNull($result['draft']);
	}

	public function testDefenderWinResultText(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport('r'),
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertStringContainsString('Defender won', $result['draft']['body']);
		$this->assertStringNotContainsString('Captured', $result['draft']['body']);
	}

	public function testDrawResultText(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport('d'),
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertStringContainsString('Draw', $result['draft']['body']);
	}

	public function testPermlinkDiffersByTimestamp(): void
	{
		$a = $this->composer->buildPermlink('rid1', 100);
		$b = $this->composer->buildPermlink('rid1', 200);
		$this->assertNotSame($a, $b);
	}

	public function testSuggestedCommunitiesNotEmpty(): void
	{
		$communities = BattleShareComposer::suggestedCommunities();
		$this->assertNotEmpty($communities);
		foreach ($communities as $community) {
			$this->assertNotSame('', $community['permlink']);
		}
	}

	public function testInvalidReportWhenRaportIdEmpty(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport(),
			'',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertFalse($result['canShare']);
		$this->assertSame('invalid_report', $result['reason']);
		$this->assertNull($result['draft']);
	}

	public function testInvalidReportWhenTimeZero(): void
	{
		$report = $this->sampleReport();
		$report['time'] = 0;

		$result = $this->composer->compose(
			$report,
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertFalse($result['canShare']);
		$this->assertSame('invalid_report', $result['reason']);
	}

	public function testBodyTruncatedWhenTooLong(): void
	{
		$longName = str_repeat('X', 300);
		$result = $this->composer->compose(
			$this->sampleReport(),
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			$longName,
			$longName,
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertTrue($result['canShare']);
		$this->assertLessThanOrEqual(BattleShareComposer::SNAP_CHAR_LIMIT, strlen($result['draft']['body']));
		$this->assertStringEndsWith('...', $result['draft']['body']);
	}

	public function testBuildPermlinkFallsBackWhenSlugEmpty(): void
	{
		$this->assertSame(
			'hivenova-battle-battle-1700000000',
			$this->composer->buildPermlink('!!!', 1700000000)
		);
	}

	public function testIncludesLossesInSnapBody(): void
	{
		$result = $this->composer->compose(
			$this->sampleReport(),
			'abc123',
			42,
			'aliceaaa',
			true,
			'https://moon.hive.pizza/',
			'Alice',
			'Bob',
			'2024-01-01 12:00',
			$this->labels
		);

		$this->assertStringContainsString('Losses:', $result['draft']['body']);
		$this->assertStringContainsString('1,200', $result['draft']['body']);
		$this->assertStringContainsString('800', $result['draft']['body']);
	}

	public function testBuildSnapPermlinkFormat(): void
	{
		$permlink = $this->composer->buildSnapPermlink();
		$this->assertStringStartsWith('re-peaksnaps-', $permlink);
	}
}
