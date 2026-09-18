<?php

declare(strict_types=1);

use HiveNova\Core\ShipyardPageModeService;
use HiveNova\Core\TechTreeGuideService;
use PHPUnit\Framework\TestCase;

class TechTreeGuideServiceTest extends TestCase
{
	/** Subset of install.sql vars_requirements for the Day-0 cargo path. */
	private function hiveDay0Requirements(): array
	{
		return [
			21  => [14 => 2],
			113 => [31 => 1],
			115 => [113 => 1, 31 => 1],
			202 => [21 => 2, 115 => 2],
			204 => [21 => 1, 115 => 1],
		];
	}

	private function resourceColumns(): array
	{
		return [
			14  => 'robotic_factory',
			21  => 'hangar',
			31  => 'laboratory',
			113 => 'energy_tech',
			115 => 'combustion_tech',
			202 => 'small_ship_cargo',
			204 => 'light_fighter',
		];
	}

	private function names(): array
	{
		return [
			14  => 'Gigafactory',
			21  => 'Shipyard',
			31  => 'Research Lab',
			113 => 'Energy Technology',
			115 => 'Combustion Engine',
			202 => 'Small Cargo',
			204 => 'Light Fighter',
		];
	}

	private function copy(): array
	{
		return [
			'need'  => 'Need %s %d (%d/%d)',
			'ready' => 'Requirements met — go start it.',
			'sep'   => '; ',
		];
	}

	private function emptyState(): array
	{
		$user = [
			'energy_tech'      => 0,
			'combustion_tech'  => 0,
			'small_ship_cargo' => 0,
			'light_fighter'    => 0,
		];
		$planet = [
			'robotic_factory' => 0,
			'hangar'          => 0,
			'laboratory'      => 0,
		];

		return [$user, $planet];
	}

	public function testLiveInstallSqlDocumentsEnergyShipyardCombustionCargoChain(): void
	{
		$sql = file_get_contents(__DIR__ . '/../../install/install.sql');
		$this->assertNotFalse($sql);
		$this->assertMatchesRegularExpression('/\(113,\s*31,\s*1\)/', $sql);
		$this->assertMatchesRegularExpression('/\(21,\s*14,\s*2\)/', $sql);
		$this->assertMatchesRegularExpression('/\(115,\s*113,\s*1\)/', $sql);
		$this->assertMatchesRegularExpression('/\(115,\s*31,\s*1\)/', $sql);
		$this->assertMatchesRegularExpression('/\(202,\s*21,\s*2\)/', $sql);
		$this->assertMatchesRegularExpression('/\(202,\s*115,\s*2\)/', $sql);
	}

	public function testHrefMatchesRequirementDeepLinkContract(): void
	{
		$service = new TechTreeGuideService([]);

		$this->assertSame('game.php?page=buildings#g21', $service->hrefForElement(TechTreeGuideService::SHIPYARD));
		$this->assertSame('game.php?page=research#t115', $service->hrefForElement(TechTreeGuideService::COMBUSTION));
		$this->assertSame(
			'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_FLEET . '#s202',
			$service->hrefForElement(TechTreeGuideService::SMALL_CARGO)
		);
		$this->assertSame(
			'game.php?page=shipyard&mode=' . ShipyardPageModeService::MODE_DEFENSE . '#s401',
			$service->hrefForElement(401)
		);
		$this->assertSame('game.php?page=officier#o603', $service->hrefForElement(603));
		$this->assertNull($service->hrefForElement(901));
	}

	public function testStartHereCoversEmpireBasicsAndFirstShips(): void
	{
		$service = new TechTreeGuideService([]);
		$ids = $service->startHereIds();

		$this->assertContains(TechTreeGuideService::SOLAR_PLANT, $ids);
		$this->assertContains(TechTreeGuideService::ORE_EXTRACTOR, $ids);
		$this->assertContains(TechTreeGuideService::SHIPYARD, $ids);
		$this->assertContains(TechTreeGuideService::ENERGY_TECH, $ids);
		$this->assertContains(TechTreeGuideService::COMBUSTION, $ids);
		$this->assertContains(TechTreeGuideService::SMALL_CARGO, $ids);
		$this->assertContains(TechTreeGuideService::LIGHT_FIGHTER, $ids);
	}

	public function testDayZeroNextUnlocksAreEnergyShipyardCombustion(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();

		$cards = $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 3);
		$ids = array_column($cards, 'id');

		$this->assertSame(
			[
				TechTreeGuideService::ENERGY_TECH,
				TechTreeGuideService::SHIPYARD,
				TechTreeGuideService::COMBUSTION,
			],
			$ids
		);

		$this->assertSame('Need Research Lab 1 (0/1)', $cards[0]['missing']);
		$this->assertSame('game.php?page=buildings#g31', $cards[0]['href']);
		$this->assertSame('0/1', $cards[0]['progress']);
		$this->assertFalse($cards[0]['ready']);

		$this->assertSame('Need Gigafactory 2 (0/2)', $cards[1]['missing']);
		$this->assertSame('game.php?page=buildings#g14', $cards[1]['href']);

		$this->assertStringContainsString('Energy Technology', $cards[2]['missing']);
		$this->assertStringContainsString('Research Lab', $cards[2]['missing']);
	}

	public function testReadyEnergyTechGoesToResearchPanel(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();
		$planet['laboratory'] = 1;

		$cards = $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 3);

		$this->assertSame(TechTreeGuideService::ENERGY_TECH, $cards[0]['id']);
		$this->assertTrue($cards[0]['ready']);
		$this->assertSame('Requirements met — go start it.', $cards[0]['missing']);
		$this->assertSame('game.php?page=research#t113', $cards[0]['href']);
		$this->assertSame('', $cards[0]['progress']);
	}

	public function testSkipsCompletedPathItemsAndSurfacesSmallCargo(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();
		$planet['laboratory'] = 1;
		$planet['robotic_factory'] = 2;
		$planet['hangar'] = 2;
		$user['energy_tech'] = 1;
		$user['combustion_tech'] = 1;

		$cards = $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 3);

		$this->assertCount(1, $cards);
		$this->assertSame(TechTreeGuideService::SMALL_CARGO, $cards[0]['id']);
		$this->assertSame('Need Combustion Engine 2 (1/2)', $cards[0]['missing']);
		$this->assertSame('game.php?page=research#t115', $cards[0]['href']);
		$this->assertSame('1/2', $cards[0]['progress']);
	}

	public function testEmptyWhenStarterPathIsDone(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();
		$planet['laboratory'] = 3;
		$planet['robotic_factory'] = 4;
		$planet['hangar'] = 2;
		$user['energy_tech'] = 2;
		$user['combustion_tech'] = 2;

		$cards = $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 3);
		$this->assertSame([], $cards);
		$this->assertTrue($service->isComplete(TechTreeGuideService::SMALL_CARGO, $user, $planet, $this->resourceColumns()));
	}

	public function testOwnedLevelPrefersPlanetThenUser(): void
	{
		$service = new TechTreeGuideService([]);
		$cols = [31 => 'laboratory', 113 => 'energy_tech'];

		$this->assertSame(4, $service->ownedLevel(31, ['laboratory' => 9], ['laboratory' => 4], $cols));
		$this->assertSame(3, $service->ownedLevel(113, ['energy_tech' => 3], [], $cols));
		$this->assertSame(0, $service->ownedLevel(113, [], [], $cols));
		$this->assertSame(0, $service->ownedLevel(99, [], [], $cols));
	}

	public function testAcceptsStringRequirementKeys(): void
	{
		$service = new TechTreeGuideService([
			'21' => ['14' => '2'],
		]);
		[$user, $planet] = $this->emptyState();

		$this->assertSame(2, $service->remainingGap(21, $user, $planet, $this->resourceColumns()));
		$this->assertFalse($service->requirementsMet(21, $user, $planet, $this->resourceColumns()));
	}

	public function testShipCompleteUsesRequirementsNotOwnership(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();
		$planet['hangar'] = 2;
		$user['combustion_tech'] = 2;

		$this->assertTrue($service->isComplete(TechTreeGuideService::SMALL_CARGO, $user, $planet, $this->resourceColumns()));
		$this->assertSame(0, $service->ownedLevel(TechTreeGuideService::SMALL_CARGO, $user, $planet, $this->resourceColumns()));
	}

	public function testClampsCardLimit(): void
	{
		$service = new TechTreeGuideService($this->hiveDay0Requirements());
		[$user, $planet] = $this->emptyState();

		$this->assertCount(1, $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 0));
		$this->assertCount(4, $service->nextUnlocks($user, $planet, $this->resourceColumns(), $this->names(), $this->copy(), 99));
	}
}
