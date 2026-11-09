<?php

declare(strict_types=1);

use HiveNova\Core\EnergyBalanceService;
use HiveNova\Core\ImperiumView;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class EnergyBalanceServiceTest extends TestCase
{
	public function test_available_matches_shared_balance(): void
	{
		// Ore Extractor 1 with no solar plant: consume 1100, produce 0.
		$this->assertSame(-1100.0, EnergyBalanceService::available(0, -1100));
		$this->assertSame(0.0, EnergyBalanceService::available(1100, -1100));
		$this->assertSame(9039.0, EnergyBalanceService::available(34297, -25258));
		$this->assertSame(-50.0, EnergyBalanceService::available(100, 150));
	}

	public function test_imperium_view_delegates_to_energy_balance_service(): void
	{
		$this->assertSame(
			EnergyBalanceService::available(0, -1100),
			ImperiumView::energyAvailable(0, -1100)
		);
	}

	public function test_consumed_is_absolute(): void
	{
		$this->assertSame(1100.0, EnergyBalanceService::consumed(-1100));
		$this->assertSame(0.0, EnergyBalanceService::consumed(0));
		$this->assertSame(50.0, EnergyBalanceService::consumed(50));
	}

	public function test_header_resource_fields_expose_consumption_vs_production(): void
	{
		$fields = EnergyBalanceService::headerResourceFields(0, -1100);

		$this->assertSame('energy', $fields['name']);
		$this->assertSame(1100.0, $fields['consumed']);
		$this->assertSame(0.0, $fields['max']);
		$this->assertSame(-1100.0, $fields['available']);
		$this->assertTrue($fields['inDeficit']);
		$this->assertSame(-1100.0, $fields['used']);
	}

	public function test_header_balanced_planet_is_not_in_deficit(): void
	{
		$fields = EnergyBalanceService::headerResourceFields(1100, -1100);

		$this->assertSame(1100.0, $fields['consumed']);
		$this->assertSame(1100.0, $fields['max']);
		$this->assertSame(0.0, $fields['available']);
		$this->assertFalse($fields['inDeficit']);
	}

	public function test_resources_summary_matches_header_and_omits_stock_periods(): void
	{
		$summary = EnergyBalanceService::resourcesSummary(0, -1100, 0);

		$this->assertSame(-1100.0, $summary['available']);
		$this->assertSame(0.0, $summary['produced']);
		$this->assertSame(1100.0, $summary['consumed']);
		$this->assertNull($summary['daily']);
		$this->assertNull($summary['weekly']);
	}

	public function test_resources_summary_includes_basic_energy_income_in_produced(): void
	{
		$summary = EnergyBalanceService::resourcesSummary(1000, -400, 100);

		$this->assertSame(1100.0, $summary['produced']);
		$this->assertSame(700.0, $summary['available']);
		$this->assertSame(100.0, $summary['basic']);
		$this->assertSame(400.0, $summary['consumed']);
	}
}
