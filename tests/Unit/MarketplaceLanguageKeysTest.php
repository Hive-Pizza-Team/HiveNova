<?php

declare(strict_types=1);

use HiveNova\Core\Language;
use PHPUnit\Framework\TestCase;

class MarketplaceLanguageKeysTest extends TestCase
{
	/** @return list<string> */
	private static function marketplaceKeys(): array
	{
		return [
			'lm_marketplace',
			'market_p_total',
			'market_p_ratio',
			'market_p_cost_type',
			'market_p_cost_amount',
			'market_p_end',
			'market_p_from_duration',
			'market_p_to_duration',
			'market_p_submit',
			'market_p_buy',
			'market_ship_as_first',
			'market_confirm_are_you_sure',
			'market_info_header',
			'market_info_description',
			'market_fleet',
			'market_p_msg_not_found',
			'market_p_msg_more_ships_is_needed',
			'market_p_msg_resources_error',
			'market_p_msg_wrong_resource_type',
			'market_p_msg_sent',
			'market_msg_trade_topic',
			'market_msg_trade_from',
			'market_msg_trade_bought',
			'market_msg_trade_sold',
			'market_buyable_only_trade_partners',
			'market_buyable_no_enemies',
			'market_buyable_no_tech',
		];
	}

	/** @return list<string> */
	public static function languageProvider(): array
	{
		return [
			['de'],
			['en'],
			['es'],
			['fr'],
			['pl'],
			['pt'],
			['ru'],
			['tr'],
		];
	}

	/**
	 * @dataProvider languageProvider
	 */
	public function testMarketplaceKeysResolveInEveryLanguage(string $lang): void
	{
		$lng = new Language($lang);
		$lng->includeData(['INGAME']);

		foreach (self::marketplaceKeys() as $key) {
			$value = $lng[$key];
			$this->assertNotSame($key, $value, "Missing or unresolved language key: {$key} ({$lang})");
			$this->assertNotSame('', trim((string) $value), "Empty language string for key: {$key} ({$lang})");
		}
	}

	public function testBuyMessagesRetainSprintfPlaceholdersInEnglish(): void
	{
		$lng = new Language('en');
		$lng->includeData(['INGAME']);

		$this->assertSame(9, substr_count((string) $lng['market_msg_trade_bought'], '%s'));
		$this->assertSame(7, substr_count((string) $lng['market_msg_trade_sold'], '%s'));
		$this->assertSame(2, substr_count((string) $lng['market_p_msg_sent'], '%s'));
	}
}
