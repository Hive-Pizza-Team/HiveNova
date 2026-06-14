<?php

use HiveNova\Page\Game\ShowOverviewPage;
use PHPUnit\Framework\TestCase;

/**
 * Validates buildPlanetVizData() output shape matches the JS planet-viz-payload contract.
 */
class PlanetVizPayloadTest extends TestCase
{
	protected function setUp(): void
	{
		if (!defined('FIELDS_BY_TERRAFORMER')) {
			define('FIELDS_BY_TERRAFORMER', 5);
		}
		if (!defined('FIELDS_BY_MOONBASIS_LEVEL')) {
			define('FIELDS_BY_MOONBASIS_LEVEL', 3);
		}
	}

	private function invokeBuildPlanetVizData(array $planet, ?array $moonRow, string $themePath): array
	{
		global $PLANET, $resource, $reslist, $THEME;

		$PLANET = $planet;
		$resource = $GLOBALS['resource'];
		$reslist = $GLOBALS['reslist'];
		$THEME = new class($themePath) {
			private string $path;
			public function __construct(string $path) { $this->path = $path; }
			public function getTheme(): string { return $this->path; }
		};

		$page = (new ReflectionClass(ShowOverviewPage::class))->newInstanceWithoutConstructor();
		$method = new ReflectionMethod(ShowOverviewPage::class, 'buildPlanetVizData');
		$method->setAccessible(true);

		return $method->invoke($page, $moonRow);
	}

	private function makePlanet(array $overrides = []): array
	{
		return array_merge([
			'planet_type'   => 1,
			'image'         => 'normaltempplanet03',
			'temp_min'      => 30,
			'temp_max'      => 70,
			'diameter'      => 12767,
			'field_current' => 42,
			'field_max'     => 163,
			'galaxy'        => 1,
			'system'        => 88,
			'planet'        => 7,
			'der_metal'     => 0,
			'der_crystal'   => 0,
			'b_building'    => TIMESTAMP + 3600,
			'b_hangar_id'   => '',
			'metal_mine'    => 10,
			'crystal_mine'  => 8,
			'solar_plant'   => 5,
			'light_fighter' => 12,
			'rocket_launcher' => 50,
		], $overrides);
	}

	public function testBuildPlanetVizDataMatchesContractForOverviewPlanet(): void
	{
		$payload = $this->invokeBuildPlanetVizData(
			$this->makePlanet(),
			['id' => 9001, 'name' => 'Luna', 'diameter' => 4200],
			'./styles/theme/hive/'
		);

		$this->assertSame('normaltempplanet03', $payload['texture']);
		$this->assertSame(1, $payload['type']);
		$this->assertSame(30, $payload['tempMin']);
		$this->assertSame(70, $payload['tempMax']);
		$this->assertSame(12767, $payload['diameter']);
		$this->assertSame(['current' => 42, 'max' => 163], $payload['fields']);
		$this->assertSame(1, $payload['galaxy']);
		$this->assertSame(88, $payload['system']);
		$this->assertSame(7, $payload['planet']);
		$this->assertSame(['building' => 1, 'hangar' => 0], $payload['queue']);
		$this->assertSame('./styles/theme/hive/', $payload['dpath']);
		$this->assertSame(10, $payload['buildings'][1]);
		$this->assertSame(12, $payload['fleet'][202]);
		$this->assertSame(50, $payload['defense'][401]);
		$this->assertSame(
			['id' => 9001, 'name' => 'Luna', 'diameter' => 4200],
			$payload['moon']
		);
		$this->assertSame(['metal' => 0, 'crystal' => 0], $payload['debris']);

		$json = json_encode(
			$payload,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		$this->assertIsString($json);
		$this->assertSame($payload, json_decode($json, true));
	}

	public function testBuildPlanetVizDataUsesThemePathFromSelectedSkin(): void
	{
		$payload = $this->invokeBuildPlanetVizData(
			$this->makePlanet(['b_building' => 0]),
			null,
			'./styles/theme/nova/'
		);

		$this->assertSame('./styles/theme/nova/', $payload['dpath']);
		$this->assertNull($payload['moon']);
		$this->assertSame(['building' => 0, 'hangar' => 0], $payload['queue']);
	}

	public function testBuildPlanetVizDataMoonPlanetUsesMondTexture(): void
	{
		$payload = $this->invokeBuildPlanetVizData(
			$this->makePlanet([
				'planet_type' => 3,
				'image'       => 'mond',
			]),
			null,
			'./styles/theme/hive/'
		);

		$this->assertSame('mond', $payload['texture']);
		$this->assertSame(3, $payload['type']);
		$this->assertNull($payload['moon']);
	}
}
