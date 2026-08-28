<?php

declare(strict_types=1);

use HiveNova\Page\Game\ShowImperiumPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ShowImperiumPageTest extends TestCase
{
	public function test_planet_eco_needs_persist_when_hangar_queue_present(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => serialize([[202, 5]]), 'b_building' => 0]
		));
	}

	public function test_planet_eco_needs_persist_when_building_finished(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => '', 'b_building' => TIMESTAMP - 10]
		));
	}

	public function test_planet_eco_needs_persist_when_research_finished(): void
	{
		$this->assertTrue(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => TIMESTAMP - 5],
			['b_hangar_id' => '', 'b_building' => 0]
		));
	}

	public function test_planet_eco_skips_persist_for_idle_planet(): void
	{
		$this->assertFalse(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => 0],
			['b_hangar_id' => '', 'b_building' => 0]
		));
	}

	public function test_planet_eco_skips_persist_while_building_still_running(): void
	{
		$this->assertFalse(ShowImperiumPage::planetEcoNeedsPersist(
			['b_tech' => TIMESTAMP + 3600],
			['b_hangar_id' => '', 'b_building' => TIMESTAMP + 3600]
		));
	}
}
