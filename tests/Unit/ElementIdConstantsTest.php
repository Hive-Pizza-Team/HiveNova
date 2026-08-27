<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards named element / mission ID constants against silent renumbering.
 */
class ElementIdConstantsTest extends TestCase
{
	public function testResourceConstantsMatchSchemaIds(): void
	{
		$this->assertSame(901, RESOURCE_METAL);
		$this->assertSame(902, RESOURCE_CRYSTAL);
		$this->assertSame(903, RESOURCE_DEUTERIUM);
		$this->assertSame(911, RESOURCE_ENERGY);
		$this->assertSame(921, RESOURCE_DARKMATTER);
	}

	public function testSpecialShipConstantsAndAliases(): void
	{
		$this->assertSame(216, SHIP_BLACK_MOON);
		$this->assertSame(217, SHIP_BATTLE_TRANSPORTER);
		$this->assertSame(218, SHIP_AVATAR);
		$this->assertSame(219, SHIP_PATHFINDER);
		$this->assertSame(220, SHIP_DARK_MATTER);
		$this->assertSame(SHIP_PATHFINDER, SHIP_BATTLE_RECYCLER);
		$this->assertSame(SHIP_DARK_MATTER, SHIP_PIZZABITS_COLLECTOR);
	}

	public function testFleetMissionConstantsKeepStableIds(): void
	{
		$this->assertSame(1, FLEET_MISSION_ATTACK);
		$this->assertSame(8, FLEET_MISSION_RECYCLE);
		$this->assertSame(11, FLEET_MISSION_DARKMATTER);
		$this->assertSame(15, FLEET_MISSION_EXPEDITION);
		$this->assertSame(18, FLEET_MISSION_SALVAGE);
	}
}
