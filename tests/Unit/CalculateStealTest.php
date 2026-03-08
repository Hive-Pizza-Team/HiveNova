<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for calculateSteal() using $simulate = true to skip DB writes.
 *
 * Algorithm (5-step owiki.de/Beute):
 *   Step 1: steal min(capacity/3, metal/2)
 *   Step 2: steal min(remaining/2, crystal/2)
 *   Step 3: steal min(remaining, deuterium/2)
 *   Step 4: re-fill metal up to half if capacity remains
 *   Step 5: re-fill crystal up to half if capacity remains
 *
 * Priority order: metal > crystal > deuterium (deut gets leftover only).
 */
class CalculateStealTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Fixtures
	// -----------------------------------------------------------------------

	/**
	 * Build a minimal attacker fleet entry.
	 *
	 * @param int   $capacity       Raw capacity per ship × count (from pricelist)
	 * @param float $storageFactor  Player ShipStorage bonus (0 = no bonus)
	 */
	private function makeAttacker(int $shipCount, int $shipId = 202, float $storageFactor = 0.0): array
	{
		// pricelist[202]['capacity'] = 50 (set in game_data.php fixture)
		return [
			'unit'        => [$shipId => $shipCount],
			'player'      => ['factor' => ['ShipStorage' => $storageFactor]],
			'fleetDetail' => [
				'fleet_resource_metal'      => 0,
				'fleet_resource_crystal'    => 0,
				'fleet_resource_deuterium'  => 0,
			],
		];
	}

	/** Planet with equal resources unless overridden. */
	private function makePlanet(array $overrides = []): array
	{
		return array_merge([
			'metal'      => 10000,
			'crystal'    => 10000,
			'deuterium'  => 10000,
		], $overrides);
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	public function testZeroCapacityReturnsNoLoot(): void
	{
		// 0 ships → 0 capacity → all steal amounts must be 0
		$fleets = [1 => $this->makeAttacker(0)];
		$planet = $this->makePlanet();

		$steal = calculateSteal($fleets, $planet, true);

		$this->assertEquals(0, $steal[901]);
		$this->assertEquals(0, $steal[902]);
		$this->assertEquals(0, $steal[903]);
	}

	public function testStealHalvesDefenderResourcesWithHugeCapacity(): void
	{
		// Attacker has massive capacity — should steal exactly half of each resource
		// pricelist[202]['capacity'] = 50, 1000 ships = 50000 capacity
		$fleets = [1 => $this->makeAttacker(1000)];
		$planet = $this->makePlanet(['metal' => 10000, 'crystal' => 10000, 'deuterium' => 10000]);

		$steal = calculateSteal($fleets, $planet, true);

		$this->assertEquals(5000, $steal[901], 'Should steal half the metal');
		$this->assertEquals(5000, $steal[902], 'Should steal half the crystal');
		$this->assertEquals(5000, $steal[903], 'Should steal half the deuterium');
	}

	public function testStealIsLimitedByCapacity(): void
	{
		// Attacker has exactly 300 capacity (6 ships × 50)
		// Planet has 10000 of each. Total loot must not exceed 300.
		$fleets = [1 => $this->makeAttacker(6)]; // 6 × 50 = 300 capacity
		$planet = $this->makePlanet();

		$steal = calculateSteal($fleets, $planet, true);

		$total = $steal[901] + $steal[902] + $steal[903];
		$this->assertLessThanOrEqual(300, $total, 'Total loot must not exceed attacker capacity');
	}

	public function testMultipleAttackersShareLootByCapacity(): void
	{
		// Two attackers with total capacity 500 should steal the same as one with 500
		$fleets2 = [
			1 => $this->makeAttacker(5),   // 250 capacity
			2 => $this->makeAttacker(5),   // 250 capacity
		];
		$fleets1 = [
			1 => $this->makeAttacker(10),  // 500 capacity
		];
		$planet = $this->makePlanet();

		$steal2 = calculateSteal($fleets2, $planet, true);
		$steal1 = calculateSteal($fleets1, $planet, true);

		$this->assertEquals(array_sum($steal1), array_sum($steal2), 'Total loot must be same regardless of fleet count');
	}

	public function testDeuteriumIsLastPriorityInRefillSteps(): void
	{
		// Steps 4 and 5 of the algorithm re-fill metal and crystal using leftover capacity,
		// but there is no re-fill step for deuterium.
		//
		// Setup: 42 ships × 50 capacity = 2100 capacity.
		// Planet: 100 000 metal, 100 000 crystal, 1 000 deuterium (only 500 stealable).
		//
		// Step 1: metal = min(700, 50000) = 700, remaining = 1400
		// Step 2: crystal = min(700, 50000) = 700, remaining = 700
		// Step 3: deut = min(700, 500) = 500 (capped by planet), remaining = 200
		// Step 4: metal += min(100, 49300) = 100, remaining = 100
		// Step 5: crystal += min(100, 49300) = 100, remaining = 0
		//
		// Expected: metal = 800, crystal = 800, deut = 500
		$fleets = [1 => $this->makeAttacker(42)]; // 42 × 50 = 2100 capacity
		$planet = $this->makePlanet([
			'metal'     => 100000,
			'crystal'   => 100000,
			'deuterium' => 1000,    // only 500 stealable (half)
		]);

		$steal = calculateSteal($fleets, $planet, true);

		$this->assertEquals(800, $steal[901], 'Metal gets re-filled in step 4');
		$this->assertEquals(800, $steal[902], 'Crystal gets re-filled in step 5');
		$this->assertEquals(500, $steal[903], 'Deuterium is capped and not re-filled');
		$this->assertLessThan($steal[901], $steal[903], 'Deuterium ends up less than metal due to no re-fill');
	}

	public function testStorageBonusIncreasesLoot(): void
	{
		$fleets_no_bonus   = [1 => $this->makeAttacker(2, 202, 0.0)];  // 100 capacity
		$fleets_with_bonus = [1 => $this->makeAttacker(2, 202, 1.0)];  // 200 capacity (100% bonus)

		$planet = $this->makePlanet(['metal' => 10000, 'crystal' => 10000, 'deuterium' => 10000]);

		$steal_no    = calculateSteal($fleets_no_bonus, $planet, true);
		$steal_bonus = calculateSteal($fleets_with_bonus, $planet, true);

		$this->assertGreaterThan(
			array_sum($steal_no),
			array_sum($steal_bonus),
			'Storage bonus must increase total loot'
		);
	}
}
