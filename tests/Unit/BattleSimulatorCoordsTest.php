<?php

declare(strict_types=1);

use HiveNova\Core\BattleSimulatorCoords;
use PHPUnit\Framework\TestCase;

class BattleSimulatorCoordsTest extends TestCase
{
	private const LIMITS = [9, 400, 15];

	private function origin(): array
	{
		return [
			'galaxy' => 2,
			'system' => 10,
			'planet' => 4,
			'planet_type' => BattleSimulatorCoords::TYPE_PLANET,
		];
	}

	public function testNormalizeUsesProvidedCoordinates(): void
	{
		$result = BattleSimulatorCoords::normalize(
			['galaxy' => 3, 'system' => 50, 'planet' => 8, 'type' => BattleSimulatorCoords::TYPE_MOON],
			$this->origin(),
			...self::LIMITS
		);

		$this->assertSame([
			'galaxy' => 3,
			'system' => 50,
			'planet' => 8,
			'type' => BattleSimulatorCoords::TYPE_MOON,
		], $result);
	}

	public function testNormalizeFallsBackWhenCoordinatesAreMissing(): void
	{
		$result = BattleSimulatorCoords::normalize([], $this->origin(), ...self::LIMITS);

		$this->assertSame([
			'galaxy' => 2,
			'system' => 10,
			'planet' => 4,
			'type' => BattleSimulatorCoords::TYPE_PLANET,
		], $result);
	}

	public function testNormalizeFallsBackWhenCoordinatesAreOutOfRange(): void
	{
		$result = BattleSimulatorCoords::normalize(
			['galaxy' => 99, 'system' => 0, 'planet' => -1, 'type' => 2],
			$this->origin(),
			...self::LIMITS
		);

		$this->assertSame([
			'galaxy' => 2,
			'system' => 10,
			'planet' => 4,
			'type' => BattleSimulatorCoords::TYPE_PLANET,
		], $result);
	}

	public function testNormalizeAcceptsPlanettypeAliasFromSpyReportLink(): void
	{
		$result = BattleSimulatorCoords::normalize(
			['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 0, 'planettype' => BattleSimulatorCoords::TYPE_MOON],
			$this->origin(),
			...self::LIMITS
		);

		$this->assertSame(BattleSimulatorCoords::TYPE_MOON, $result['type']);
	}

	public function testAttackerFleetDetailUsesStartAndTarget(): void
	{
		$start = ['galaxy' => 1, 'system' => 2, 'planet' => 3, 'type' => 1];
		$end = ['galaxy' => 4, 'system' => 5, 'planet' => 6, 'type' => 3];

		$detail = BattleSimulatorCoords::attackerFleetDetail($start, $end);

		$this->assertSame(1, $detail['fleet_start_galaxy']);
		$this->assertSame(2, $detail['fleet_start_system']);
		$this->assertSame(3, $detail['fleet_start_planet']);
		$this->assertSame(1, $detail['fleet_start_type']);
		$this->assertSame(4, $detail['fleet_end_galaxy']);
		$this->assertSame(5, $detail['fleet_end_system']);
		$this->assertSame(6, $detail['fleet_end_planet']);
		$this->assertSame(3, $detail['fleet_end_type']);
	}

	public function testDefenderFleetDetailUsesTargetAsStartForReportDisplay(): void
	{
		$target = ['galaxy' => 7, 'system' => 80, 'planet' => 12, 'type' => 3];

		$detail = BattleSimulatorCoords::defenderFleetDetail($target);

		$this->assertSame(7, $detail['fleet_start_galaxy']);
		$this->assertSame(80, $detail['fleet_start_system']);
		$this->assertSame(12, $detail['fleet_start_planet']);
		$this->assertSame(3, $detail['fleet_start_type']);
		$this->assertSame(7, $detail['fleet_end_galaxy']);
		$this->assertSame(12, $detail['fleet_end_planet']);
	}
}
