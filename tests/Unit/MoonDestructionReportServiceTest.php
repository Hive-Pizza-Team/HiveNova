<?php

declare(strict_types=1);

use HiveNova\Core\EventFirehoseWriter;
use HiveNova\Core\MoonDestructionReportService;
use PHPUnit\Framework\TestCase;

class MoonDestructionReportServiceTest extends TestCase
{
	public function testCountSurvivingDestroyersSumsDeathstarAndBlackMoon(): void
	{
		$count = MoonDestructionReportService::countSurvivingDestroyers([
			1 => ['unit' => [SHIP_DEATHSTAR => 3, 202 => 10]],
			2 => ['unit' => [SHIP_BLACK_MOON => 2]],
			3 => ['unit' => [204 => 5]],
		]);
		$this->assertSame(5, $count);
	}

	public function testCountSurvivingDestroyersEmptyFleets(): void
	{
		$this->assertSame(0, MoonDestructionReportService::countSurvivingDestroyers([]));
		$this->assertSame(0, MoonDestructionReportService::countSurvivingDestroyers([1 => ['unit' => []]]));
	}

	/** @dataProvider classifyProvider */
	public function testClassify(int $moonSuccess, bool $fleetLost, string $expected): void
	{
		$this->assertSame($expected, MoonDestructionReportService::classify($moonSuccess, $fleetLost));
	}

	public static function classifyProvider(): array
	{
		return [
			'repel combat' => [-1, false, MoonDestructionReportService::BRANCH_REPELLED],
			'repel ignore fleet flag' => [-1, true, MoonDestructionReportService::BRANCH_REPELLED],
			'pyrrhic' => [1, true, MoonDestructionReportService::BRANCH_PYRRHIC],
			'clean' => [1, false, MoonDestructionReportService::BRANCH_CLEAN],
			'held bled' => [0, true, MoonDestructionReportService::BRANCH_HELD_BLED],
			'held intact' => [0, false, MoonDestructionReportService::BRANCH_HELD_INTACT],
		];
	}

	public function testLabelsForRepelAndStage2(): void
	{
		$this->assertSame('—', MoonDestructionReportService::moonDestroyedLabel(-1));
		$this->assertSame('—', MoonDestructionReportService::fortressLossLabel(-1, true));
		$this->assertSame('YES', MoonDestructionReportService::moonDestroyedLabel(1));
		$this->assertSame('NO', MoonDestructionReportService::moonDestroyedLabel(0));
		$this->assertSame('YES', MoonDestructionReportService::fortressLossLabel(1, true));
		$this->assertSame('NO', MoonDestructionReportService::fortressLossLabel(0, false));
	}

	public function testFirehoseOutcomeMapping(): void
	{
		$this->assertSame(
			EventFirehoseWriter::OUTCOME_DESTROYED,
			MoonDestructionReportService::firehoseOutcome(MoonDestructionReportService::BRANCH_CLEAN)
		);
		$this->assertSame(
			EventFirehoseWriter::OUTCOME_DESTROYED,
			MoonDestructionReportService::firehoseOutcome(MoonDestructionReportService::BRANCH_PYRRHIC)
		);
		$this->assertSame(
			EventFirehoseWriter::OUTCOME_HELD,
			MoonDestructionReportService::firehoseOutcome(MoonDestructionReportService::BRANCH_HELD_INTACT)
		);
		$this->assertSame(
			EventFirehoseWriter::OUTCOME_REPELLED,
			MoonDestructionReportService::firehoseOutcome(MoonDestructionReportService::BRANCH_REPELLED)
		);
	}

	public function testBranchLanguageKeys(): void
	{
		$this->assertSame(
			'sys_destruc_tree_clean',
			MoonDestructionReportService::branchLanguageKey(MoonDestructionReportService::BRANCH_CLEAN)
		);
		$this->assertSame(
			'sys_destruc_tree_repelled',
			MoonDestructionReportService::branchLanguageKey('unknown')
		);
	}

	public function testReportUrlAndSnapshot(): void
	{
		$url = MoonDestructionReportService::reportUrl('abc123', 'https://example.test/');
		$this->assertSame('https://example.test/game.php?page=raport&raport=abc123', $url);
		$this->assertSame('', MoonDestructionReportService::reportUrl(''));

		$snap = MoonDestructionReportService::snapshot(
			1,
			false,
			4,
			'Luna',
			8500,
			1,
			19,
			8
		);
		$this->assertSame(MoonDestructionReportService::BRANCH_CLEAN, $snap['branch']);
		$this->assertSame(4, $snap['survivingDestroyers']);
		$this->assertSame('YES', $snap['moonDestroyedLabel']);
		$this->assertSame('NO', $snap['fortressLossLabel']);
		$this->assertSame('Luna', $snap['moonName']);
		$this->assertSame(8500, $snap['moonDiameter']);
		$this->assertSame('[1:19:8] (moon)', $snap['coords']);
	}
}
