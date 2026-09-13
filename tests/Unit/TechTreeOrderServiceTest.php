<?php

use HiveNova\Core\TechTreeOrderService;

use PHPUnit\Framework\TestCase;

class TechTreeOrderServiceTest extends TestCase
{
	/** University */
	private const UNIVERSITY = 6;

	/** Shipyard */
	private const SHIPYARD = 21;

	/**
	 * Subset of install.sql vars_requirements for the University vs Shipyard chain.
	 *
	 * @return array<int, array<int, int>>
	 */
	private function hiveRequirements(): array
	{
		return [
			6   => [14 => 20, 31 => 22, 15 => 4, 108 => 12, 123 => 3],
			12  => [3 => 5, 113 => 3],
			15  => [14 => 10, 108 => 10],
			21  => [14 => 2],
			33  => [15 => 1, 113 => 12],
			42  => [41 => 1],
			43  => [41 => 1, 114 => 7],
			44  => [21 => 1],
			106 => [31 => 3],
			108 => [31 => 1],
			109 => [31 => 4],
			110 => [113 => 3, 31 => 6],
			111 => [31 => 2],
			113 => [31 => 1],
			114 => [113 => 5, 110 => 5, 31 => 7],
			115 => [113 => 1, 31 => 1],
			117 => [113 => 1, 31 => 2],
			118 => [114 => 3, 31 => 7],
			120 => [31 => 1, 113 => 2],
			121 => [31 => 4, 120 => 5, 113 => 4],
			122 => [31 => 5, 113 => 8, 120 => 10, 121 => 5],
			123 => [31 => 10, 108 => 8, 114 => 8],
			124 => [106 => 3, 117 => 3, 31 => 3],
			131 => [31 => 8, 113 => 5],
			199 => [31 => 12],
		];
	}

	public function testLeavesHaveZeroEffortAndDepth(): void
	{
		$service = new TechTreeOrderService([]);

		$this->assertSame(0, $service->effort(14));
		$this->assertSame(0, $service->depth(14));
	}

	public function testDirectRequirementUsesRequiredLevelAsEffort(): void
	{
		$service = new TechTreeOrderService([21 => [14 => 2]]);

		$this->assertSame(2, $service->effort(21));
		$this->assertSame(1, $service->depth(21));
	}

	public function testEffortWalksTheHardestPrerequisitePath(): void
	{
		$service = new TechTreeOrderService([
			2 => [1 => 3],
			3 => [2 => 4, 1 => 10],
		]);

		// Path 1→2→3 = 3+4 = 7; direct 1→3 = 10.
		$this->assertSame(10, $service->effort(3));
		$this->assertSame(2, $service->depth(3));
	}

	public function testCycleDoesNotRecurseForever(): void
	{
		$service = new TechTreeOrderService([
			1 => [2 => 1],
			2 => [1 => 1],
		]);

		$this->assertSame(2, $service->effort(1));
		$this->assertSame(2, $service->depth(1));
		$this->assertSame(1, $service->effort(2));
		$this->assertSame(1, $service->depth(2));
	}

	public function testAcceptsStringRequirementKeys(): void
	{
		$service = new TechTreeOrderService([
			'21' => ['14' => '2'],
		]);

		$this->assertSame(2, $service->effort(21));
		$this->assertSame([21], $service->sortIds(['21']));
	}

	public function testShipyardSortsBeforeUniversity(): void
	{
		$service = new TechTreeOrderService($this->hiveRequirements());

		$this->assertLessThan($service->effort(self::UNIVERSITY), $service->effort(self::SHIPYARD));

		$sorted = $service->sortIds([self::UNIVERSITY, self::SHIPYARD]);
		$this->assertSame([self::SHIPYARD, self::UNIVERSITY], $sorted);

		$reversed = $service->sortIds([self::SHIPYARD, self::UNIVERSITY]);
		$this->assertSame([self::SHIPYARD, self::UNIVERSITY], $reversed);
	}

	public function testBuildingCategoryPutsEarlyUnlocksFirst(): void
	{
		$service = new TechTreeOrderService($this->hiveRequirements());
		$buildings = [6, 12, 15, 21, 33, 42, 43, 44];
		$sorted = $service->sortIds($buildings);

		$shipyardPos = array_search(self::SHIPYARD, $sorted, true);
		$universityPos = array_search(self::UNIVERSITY, $sorted, true);
		$this->assertNotFalse($shipyardPos);
		$this->assertNotFalse($universityPos);
		$this->assertLessThan($universityPos, $shipyardPos);

		$this->assertSame(self::UNIVERSITY, $sorted[array_key_last($sorted)]);
	}

	public function testEqualEffortFallsBackToDepthThenId(): void
	{
		$service = new TechTreeOrderService([
			10 => [1 => 5],
			20 => [1 => 5],
			30 => [10 => 0],
		]);

		// 10 and 20 both effort 5 / depth 1 → ID order. 30 effort 5 / depth 2 → after them.
		$this->assertSame([10, 20, 30], $service->sortIds([30, 20, 10]));
	}

	public function testOrderByCategoryGroupsAndSorts(): void
	{
		$service = new TechTreeOrderService($this->hiveRequirements());
		$order = $service->orderByCategory([6, 21, 108, 199, 204, 401, 502, 603, 999]);

		$this->assertSame([0, 100, 200, 400, 500, 600], array_keys($order));
		$this->assertSame([21, 6], $order[0]);
		$this->assertSame([108, 199], $order[100]);
		$this->assertSame([204], $order[200]);
		$this->assertSame([401], $order[400]);
		$this->assertSame([502], $order[500]);
		$this->assertSame([603], $order[600]);

		$encoded = json_decode(json_encode($order, JSON_THROW_ON_ERROR), true);
		$this->assertSame([21, 6], $encoded[0]);
	}

	public function testCategoryOfMatchesTechtreeRanges(): void
	{
		$service = new TechTreeOrderService([]);

		$this->assertSame(0, $service->categoryOf(21));
		$this->assertSame(100, $service->categoryOf(108));
		$this->assertSame(200, $service->categoryOf(204));
		$this->assertSame(400, $service->categoryOf(401));
		$this->assertSame(500, $service->categoryOf(502));
		$this->assertSame(600, $service->categoryOf(603));
		$this->assertNull($service->categoryOf(300));
		$this->assertNull($service->categoryOf(0));
	}

	public function testSkipsNonArrayRequirementRows(): void
	{
		$service = new TechTreeOrderService([
			21 => 'invalid',
			22 => [14 => 1],
		]);

		$this->assertSame(0, $service->effort(21));
		$this->assertSame(1, $service->effort(22));
	}
}
