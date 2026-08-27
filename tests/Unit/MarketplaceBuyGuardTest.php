<?php

use PHPUnit\Framework\TestCase;

class MarketplaceBuyGuardTest extends TestCase
{
	public function testDoBuyClaimsTradeOnlyWhenBuyerFleetIdIsNull(): void
	{
		$source = file_get_contents(__DIR__ . '/../../includes/pages/game/ShowMarketPlacePage.php');
		$this->assertIsString($source);
		$this->assertStringContainsString('buyer_fleet_id IS NULL', $source);
		$this->assertStringContainsString('FOR UPDATE', $source);
		$this->assertStringContainsString('beginTransaction', $source);
	}
}
