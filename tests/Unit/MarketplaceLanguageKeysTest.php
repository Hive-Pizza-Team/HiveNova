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
			'market_intro',
			'market_sell_cta',
			'market_sell_cta_hint',
			'market_how_title',
			'market_how_step_1',
			'market_how_step_2',
			'market_how_step_3',
			'market_how_step_4',
			'market_my_offers',
			'market_tab_resources',
			'market_tab_fleet',
			'market_pay_with',
			'market_pay_with_help',
			'market_filter_get',
			'market_filter_pay',
			'market_filter_any',
			'market_sort',
			'market_sort_deal',
			'market_sort_delivery',
			'market_sort_expires',
			'market_only_buyable',
			'market_ref_ratio',
			'market_ref_ratio_help',
			'market_col_seller',
			'market_col_get',
			'market_col_pay',
			'market_col_deal',
			'market_col_delivery',
			'market_col_expires',
			'market_col_cost',
			'market_col_sold',
			'market_col_price',
			'market_deal_good',
			'market_deal_fair',
			'market_deal_bad',
			'market_deal_help',
			'market_your_offer',
			'market_cancel',
			'market_cancel_confirm',
			'market_confirm_buy',
			'market_expired',
			'market_empty_offers',
			'market_empty_fleet',
			'market_empty_offers_hint',
			'market_empty_filtered',
			'market_history_title',
			'market_history_empty',
			'market_reason_no_slots',
			'market_reason_ships',
			'market_reason_resources',
			'market_p_msg_own_offer',
			'market_p_msg_cancelled',
			'market_p_msg_cannot_cancel',
			'market_p_msg_bad_token',
			'market_p_msg_bought',
			'market_p_msg_paid_with',
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

	public function testConfirmBuyHasSixPositionalPlaceholdersInEveryLanguage(): void
	{
		foreach (self::languageProvider() as [$lang]) {
			$lng = new Language($lang);
			$lng->includeData(['INGAME']);
			$text = (string) $lng['market_confirm_buy'];
			for ($i = 1; $i <= 6; $i++) {
				$this->assertStringContainsString('%' . $i . '$s', $text, "{$lang} market_confirm_buy missing %{$i}\$s");
			}
			$this->assertSame(1, substr_count((string) $lng['market_p_msg_bought'], '%s'), $lang);
			$this->assertSame(1, substr_count((string) $lng['market_how_step_1'], '%s'), $lang);
			$this->assertSame(1, substr_count((string) $lng['market_sell_cta_hint'], '%s'), $lang);
			$this->assertSame(1, substr_count((string) $lng['market_my_offers'], '%s'), $lang);
		}
	}
}
