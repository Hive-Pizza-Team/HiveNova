<?php

use HiveNova\Core\EconomyPlayService;
use HiveNova\Core\GalaxyPlayService;
use HiveNova\Core\MessagePlayService;
use HiveNova\Core\ApiRouteTable;
use HiveNova\Core\ApiTickClass;
use PHPUnit\Framework\TestCase;

class EconomyPlayServiceTest extends TestCase
{
	public function testIntMapCastsKeys(): void
	{
		$this->assertSame([901 => 40, 902 => 10], EconomyPlayService::intMap(['901' => '40', 902 => 10.9]));
	}

	public function testBuildingQueueSkipsCompleted(): void
	{
		$planet = [
			'b_building' => TIMESTAMP + 50,
			'b_building_id' => serialize([
				[1, 12, 30, TIMESTAMP - 5, 'build'],
				[2, 8, 40, TIMESTAMP + 40, 'build'],
			]),
		];
		$result = EconomyPlayService::buildingQueue($planet);
		$this->assertCount(1, $result['queue']);
		$this->assertSame(2, $result['queue'][0]['elementId']);
		$this->assertSame(8, $result['quick'][2]);
	}

	public function testGalaxyEmptySlot(): void
	{
		$row = GalaxyPlayService::summarizeSlot(3, false);
		$this->assertTrue($row['empty']);
		$this->assertSame(3, $row['position']);
		$this->assertSame('unknown', GalaxyPlayService::summarizeSlot(4, [
			'uncolonized' => true,
			'planet' => ['image' => 'unknown'],
		])['image']);
	}

	public function testMessageMapRow(): void
	{
		$mapped = MessagePlayService::mapRow([
			'message_id' => '9',
			'message_time' => '100',
			'message_from' => 'Ops',
			'message_subject' => 'Spy',
			'message_sender' => '0',
			'message_type' => '0',
			'message_unread' => '1',
			'message_text' => '<b>hi</b>',
		]);
		$this->assertSame(9, $mapped['id']);
		$this->assertTrue($mapped['unread']);
		$this->assertSame('<b>hi</b>', $mapped['text']);
		$report = MessagePlayService::mapRow([
			'message_id' => '2',
			'message_text' => '<a href="game.php?page=raport&raport=abc" target="_blank">CR</a>',
		]);
		$this->assertSame('<a href="game.php?page=raport&raport=abc">CR</a>', $report['text']);
		$this->assertSame('All', MessagePlayService::categoryLabels(['mg_type' => [100 => 'All']])[100]);
	}

	public function testPlayableRoutesAreKnown(): void
	{
		foreach (['buildings', 'research', 'shipyard', 'fleet', 'galaxy', 'messages', 'catalog'] as $r) {
			$this->assertTrue(ApiRouteTable::isKnown($r), $r);
		}
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('buildings', 'insert', 'POST'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('fleet', 'send', 'POST'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('galaxy', 'spy', 'POST'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('galaxy', 'recycle', 'POST'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('galaxy', 'colonize', 'POST'));
		$this->assertSame(ApiTickClass::Poll, ApiRouteTable::tickClass('fleet', 'preview', 'GET'));
		$this->assertTrue(ApiRouteTable::isKnownAction('fleet', 'preview'));
		$this->assertTrue(ApiRouteTable::isPollAction('fleet', 'preview'));
		$this->assertFalse(ApiRouteTable::isPollAction('fleet', 'send'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('catalog', 'prod', 'POST'));
		$this->assertSame(ApiTickClass::Mutate, ApiRouteTable::tickClass('catalog', 'trade', 'POST'));
		$this->assertTrue(ApiRouteTable::isKnownAction('buildings', 'cancel'));
		$this->assertTrue(ApiRouteTable::isKnownAction('catalog', 'note'));
		$this->assertTrue(ApiRouteTable::isKnownAction('catalog', 'trade'));
	}

	public function testTechNameAndQueues(): void
	{
		$this->assertSame('Mine', EconomyPlayService::techName(['tech' => [1 => 'Mine']], 1));
		$this->assertSame('9', EconomyPlayService::techName(null, 9));
		$emptyBuild = EconomyPlayService::buildingQueue(['b_building' => 0, 'b_building_id' => '']);
		$this->assertSame([], $emptyBuild['queue']);
		$research = EconomyPlayService::researchQueue(['b_tech_queue' => serialize([
			[106, 2, 30, TIMESTAMP - 1, 4],
			[108, 3, 40, TIMESTAMP + 20, 4],
		])]);
		$this->assertCount(1, $research['queue']);
		$this->assertSame(3, $research['quick'][108]);
		$this->assertSame([], EconomyPlayService::researchQueue([])['queue']);
	}

	public function testCancelBuildingEmptyQueue(): void
	{
		$user = [];
		$planet = ['b_building_id' => '', 'b_building' => 9];
		$this->assertFalse(EconomyPlayService::cancelBuilding($user, $planet));
		$planet = ['b_building_id' => serialize([]), 'b_building' => 9];
		$this->assertFalse(EconomyPlayService::cancelBuilding($user, $planet));
	}

	public function testGalaxyOccupiedSlot(): void
	{
		$slot = GalaxyPlayService::summarizeSlot(7, [
			'ownPlanet' => true,
			'planet' => ['id' => 4, 'name' => 'Home', 'image' => 'eisplanet02'],
			'user' => ['username' => 'pilot', 'id' => 9],
			'missions' => [FLEET_MISSION_SPY => true, FLEET_MISSION_ATTACK => true],
		]);
		$this->assertFalse($slot['empty']);
		$this->assertTrue($slot['own']);
		$this->assertTrue($slot['canSpy']);
		$this->assertSame('eisplanet02', $slot['image']);
		$this->assertFalse($slot['canRecycle']);
		$this->assertNull($slot['moon']);
		$rich = GalaxyPlayService::summarizeSlot(8, [
			'ownPlanet' => false,
			'planet' => ['id' => 9, 'name' => 'Target', 'image' => 'wasserplanet01'],
			'user' => ['username' => 'foe', 'id' => 2],
			'alliance' => ['tag' => 'HIVE'],
			'debris' => ['metal' => 400, 'crystal' => 50],
			'moon' => ['id' => 77, 'name' => 'Luna'],
			'missions' => [
				FLEET_MISSION_SPY => true,
				FLEET_MISSION_ATTACK => true,
				FLEET_MISSION_RECYCLE => true,
			],
			'lastActivity' => '*',
		]);
		$this->assertTrue($rich['canRecycle']);
		$this->assertSame('HIVE', $rich['alliance']);
		$this->assertSame(77, $rich['moon']['id']);
		$this->assertSame(400, $rich['debris']['metal']);
	}

	public function testMessageCategoryLabelsWithoutLng(): void
	{
		$this->assertSame([], MessagePlayService::categoryLabels(null));
		$this->assertSame([], MessagePlayService::categoryLabels(['mg_type' => 'x']));
	}
}
