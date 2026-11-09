<?php

use HiveNova\Core\PublicBattleHallService;
use PHPUnit\Framework\TestCase;

class PublicBattleHallServiceTest extends TestCase
{
	public function testPublicReportUrlIncludesRidAndOptionalLang(): void
	{
		$this->assertSame(
			'index.php?page=battleHall&raport=abc123',
			PublicBattleHallService::publicReportUrl('abc123')
		);
		$this->assertSame(
			'index.php?page=battleHall&raport=abc123&lang=de',
			PublicBattleHallService::publicReportUrl('abc123', 'de')
		);
		$this->assertSame(
			'index.php?page=battleHall&raport=abc123',
			PublicBattleHallService::publicReportUrl('abc123', 'en')
		);
	}

	public function testRegisterCtaUrl(): void
	{
		$this->assertSame(
			'index.php?page=register',
			PublicBattleHallService::registerCtaUrl()
		);
		$this->assertSame(
			'index.php?page=register&ref=9&lang=de',
			PublicBattleHallService::registerCtaUrl('de', 'en', ['ref' => 9])
		);
	}

	public function testDecorateHallListAddsReportUrls(): void
	{
		$list = PublicBattleHallService::decorateHallList([
			[
				'result'   => 'a',
				'time'     => '1. Jan',
				'units'    => 100,
				'rid'      => 'rid1',
				'attacker' => 'A',
				'defender' => 'B',
			],
			[
				'result'   => 'r',
				'time'     => '2. Jan',
				'units'    => 50,
				'rid'      => '',
				'attacker' => 'C',
				'defender' => 'D',
			],
		], 'de');

		$this->assertSame('index.php?page=battleHall&raport=rid1&lang=de', $list[0]['reportUrl']);
		$this->assertSame('', $list[1]['reportUrl']);
	}
}
