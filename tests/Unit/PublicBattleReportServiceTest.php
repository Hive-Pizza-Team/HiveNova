<?php

use HiveNova\Core\PublicBattleReportService;
use PHPUnit\Framework\TestCase;

class PublicBattleReportServiceTest extends TestCase
{
	public function testFormatCompactUnits(): void
	{
		$this->assertSame('488.7M', PublicBattleReportService::formatCompactUnits(488700000));
		$this->assertSame('1.2B', PublicBattleReportService::formatCompactUnits(1200000000));
		$this->assertSame('999', PublicBattleReportService::formatCompactUnits(999));
	}
}
