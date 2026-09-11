<?php

declare(strict_types=1);

use HiveNova\Core\GamePageState;
use PHPUnit\Framework\TestCase;

class GamePageStateTest extends TestCase
{
	public function testLivePlanetResourcesWinOverConstructorSnapshot(): void
	{
		$snapshot = [
			'metal' => 994000,
			'crystal' => 994000,
			'deuterium' => 394000,
		];
		$live = [
			'metal' => 992000,
			'crystal' => 992000,
			'deuterium' => 394000,
		];

		$resolved = GamePageState::resolve($snapshot, $live);

		$this->assertSame(992000, $resolved['metal']);
		$this->assertSame(992000, $resolved['crystal']);
		$this->assertSame(394000, $resolved['deuterium']);
	}

	public function testLiveUserDarkmatterWinsOverConstructorSnapshot(): void
	{
		$snapshot = ['id' => 1, 'darkmatter' => 500];
		$live = ['id' => 1, 'darkmatter' => 400];

		$this->assertSame(400, GamePageState::resolve($snapshot, $live)['darkmatter']);
	}

	public function testSnapshotIsUsedWhenLiveGlobalIsMissing(): void
	{
		$snapshot = ['metal' => 1000];

		$this->assertSame($snapshot, GamePageState::resolve($snapshot, null));
	}

	public function testSnapshotIsUsedWhenLiveGlobalIsNotAnArray(): void
	{
		$snapshot = ['metal' => 1000];

		$this->assertSame($snapshot, GamePageState::resolve($snapshot, 'unset'));
	}

	public function testMissingStateThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Game page player state is missing');

		GamePageState::resolve(null, null);
	}
}
