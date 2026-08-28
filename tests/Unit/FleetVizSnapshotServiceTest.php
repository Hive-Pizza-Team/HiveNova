<?php

declare(strict_types=1);

use HiveNova\Core\Config;
use HiveNova\Core\FleetVizSnapshotService;
use HiveNova\Core\Universe;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

class FleetVizSnapshotServiceTest extends TestCase
{
	use SwapDatabaseInstance;

	/** @var FakeDatabase&object{vizFleets: list<array<string, mixed>>} */
	private FakeDatabase $fake;

	/** @var array<int|string, Config> */
	private array $savedConfigCache = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->fake = new class extends FakeDatabase {
			/** @var list<array<string, mixed>> */
			public array $vizFleets = [];

			public function select($qry, array $params = [])
			{
				if (str_contains($qry, '%%FLEETS%%') && str_contains($qry, 'sizeClass')) {
					return $this->vizFleets;
				}

				return parent::select($qry, $params);
			}
		};
		$this->swapDatabaseInstance($this->fake);
		$this->savedConfigCache = $this->getConfigCache();
		$this->clearConfigCache();
		$this->resetUniverseList([1, 3]);
	}

	protected function tearDown(): void
	{
		$this->setConfigCache($this->savedConfigCache);
		$this->resetUniverseList([]);
		$this->restoreDatabaseInstance();
		parent::tearDown();
	}

	public function test_for_universe_returns_privacy_safe_fleet_shape(): void
	{
		Config::setInstance($this->uniConfig(1, 'Classic', true), 1);
		$this->fake->vizFleets = [[
			'startGroup' => '1',
			'startCircle' => '10',
			'startPoint' => '2',
			'endGroup' => '2',
			'endCircle' => '20',
			'endPoint' => '4',
			'duration' => '12.5',
			'mission' => '1',
			'sizeClass' => '3',
		]];

		$snap = (new FleetVizSnapshotService())->forUniverse(1);
		$this->assertSame(1, $snap['id']);
		$this->assertSame('Classic', $snap['name']);
		$this->assertSame(9, $snap['maxGalaxy']);
		$this->assertCount(1, $snap['fleets']);
		$fleet = $snap['fleets'][0];
		$this->assertSame([
			'startGroup', 'startCircle', 'startPoint',
			'endGroup', 'endCircle', 'endPoint',
			'duration', 'mission', 'sizeClass',
		], array_keys($fleet));
		$this->assertSame(1, $fleet['startGroup']);
		$this->assertSame(3, $fleet['sizeClass']);
		$this->assertArrayNotHasKey('fleet_id', $fleet);
		$this->assertArrayNotHasKey('username', $fleet);
	}

	public function test_for_open_universes_skips_closed_and_embeds_three_src(): void
	{
		Config::setInstance($this->uniConfig(1, 'Classic', true), 1);
		Config::setInstance($this->uniConfig(3, 'Season', false), 3);
		$this->fake->vizFleets = [];

		$payload = (new FleetVizSnapshotService())->forOpenUniverses([1, 3]);
		$this->assertStringContainsString('scripts/threejs/three.min.js', $payload['threeSrc']);
		$this->assertCount(1, $payload['universes']);
		$this->assertSame(1, $payload['universes'][0]['id']);
	}

	/**
	 * @param list<int> $ids
	 */
	private function resetUniverseList(array $ids): void
	{
		$ref = new ReflectionProperty(Universe::class, 'availableUniverses');
		$ref->setAccessible(true);
		$ref->setValue(null, $ids);
	}

	/** @return array<int|string, Config> */
	private function getConfigCache(): array
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$value = $ref->getValue(null);

		return is_array($value) ? $value : [];
	}

	private function clearConfigCache(): void
	{
		$this->setConfigCache([]);
	}

	/** @param array<int|string, Config> $cache */
	private function setConfigCache(array $cache): void
	{
		$ref = new ReflectionProperty(Config::class, 'instances');
		$ref->setAccessible(true);
		$ref->setValue(null, $cache);
	}

	private function uniConfig(int $id, string $name, bool $open): Config
	{
		return new Config([
			'uni' => $id,
			'uni_name' => $name,
			'game_disable' => $open ? 1 : 0,
			'max_galaxy' => 9,
			'max_system' => 400,
			'max_planets' => 15,
			'VERSION' => '1.0.0-test',
		]);
	}
}
