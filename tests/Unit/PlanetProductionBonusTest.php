<?php

use HiveNova\Core\PlanetProductionBonus;
use PHPUnit\Framework\TestCase;

class PlanetProductionBonusTest extends TestCase
{
	public function testSlotsOneThroughFifteen(): void
	{
		$metal = [
			1 => 1.00, 2 => 1.00, 3 => 1.00, 4 => 1.00, 5 => 1.00,
			6 => 1.17, 7 => 1.23, 8 => 1.35, 9 => 1.23, 10 => 1.17,
			11 => 1.00, 12 => 1.00, 13 => 1.00, 14 => 1.00, 15 => 1.00,
		];
		$crystal = [
			1 => 1.40, 2 => 1.30, 3 => 1.20, 4 => 1.00, 5 => 1.00,
			6 => 1.00, 7 => 1.00, 8 => 1.00, 9 => 1.00, 10 => 1.00,
			11 => 1.00, 12 => 1.00, 13 => 1.00, 14 => 1.00, 15 => 1.00,
		];

		for ($slot = 1; $slot <= 15; $slot++) {
			$factors = PlanetProductionBonus::factors(1, $slot);
			$this->assertEqualsWithDelta($metal[$slot], $factors[901], 0.0001, "metal slot {$slot}");
			$this->assertEqualsWithDelta($crystal[$slot], $factors[902], 0.0001, "silicon slot {$slot}");
		}
	}

	public function testMoonHasNoBonusEvenOnSlotEight(): void
	{
		$factors = PlanetProductionBonus::factors(3, 8);
		$this->assertSame(1.0, $factors[901]);
		$this->assertSame(1.0, $factors[902]);
	}

	public function testUnknownSlotsAreNeutral(): void
	{
		foreach ([0, 16, -1] as $slot) {
			$factors = PlanetProductionBonus::factors(1, $slot);
			$this->assertSame(1.0, $factors[901], "metal slot {$slot}");
			$this->assertSame(1.0, $factors[902], "silicon slot {$slot}");
		}
	}

	public function testForResourceFallsBackToOne(): void
	{
		$this->assertSame(1.0, PlanetProductionBonus::forResource(1, 8, 903));
		$this->assertSame(1.35, PlanetProductionBonus::forResource(1, 8, 901));
	}
}
