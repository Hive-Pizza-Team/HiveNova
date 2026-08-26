<?php

declare(strict_types=1);

use HiveNova\Core\Language;
use PHPUnit\Framework\TestCase;

class ExpeditionLanguageKeysTest extends TestCase
{
	/** @return array<int, string> */
	private static function expeditionMessageKeys(): array
	{
		$keys = [
			'sys_expe_report',
			'sys_expe_choice_pending',
			'cm_pending_choice',
			'sys_expe_found_ships_nothing',
			'sys_expe_attackname_1',
			'sys_expe_attackname_2',
			'sys_expe_back_home',
			'sys_expe_back_home_without_dm',
			'sys_expe_back_home_with_dm',
		];

		foreach (range(1, 9) as $i) {
			$keys[] = 'sys_expe_nothing_' . $i;
		}
		foreach (range(1, 2) as $i) {
			$keys[] = 'sys_expe_depleted_not_' . $i;
		}
		foreach (range(1, 3) as $i) {
			$keys[] = 'sys_expe_depleted_min_' . $i;
			$keys[] = 'sys_expe_depleted_med_' . $i;
			$keys[] = 'sys_expe_depleted_max_' . $i;
			$keys[] = 'sys_expe_found_ress_2_' . $i;
			$keys[] = 'sys_expe_found_ships_logbook_' . $i;
			$keys[] = 'sys_expe_found_dm_2_' . $i;
			$keys[] = 'sys_expe_time_fast_' . $i;
			$keys[] = 'sys_expe_attack_1_2_' . $i;
			$keys[] = 'sys_expe_attack_1_3_' . $i;
			$keys[] = 'sys_expe_attack_2_2_' . $i;
			$keys[] = 'sys_expe_attack_2_3_' . $i;
		}
		foreach (range(1, 2) as $i) {
			$keys[] = 'sys_expe_found_ships_2_' . $i;
		}
		foreach (range(1, 4) as $i) {
			$keys[] = 'sys_expe_found_ress_1_' . $i;
			$keys[] = 'sys_expe_found_ships_1_' . $i;
			$keys[] = 'sys_expe_found_ress_logbook_' . $i;
			$keys[] = 'sys_expe_lost_fleet_' . $i;
			$keys[] = 'sys_expe_attack_2_1_' . $i;
		}
		foreach (range(1, 5) as $i) {
			$keys[] = 'sys_expe_found_dm_1_' . $i;
			$keys[] = 'sys_expe_attack_1_1_' . $i;
		}
		foreach (range(1, 6) as $i) {
			$keys[] = 'sys_expe_time_slow_' . $i;
		}
		foreach (range(1, 2) as $i) {
			$keys[] = 'sys_expe_found_ress_3_' . $i;
			$keys[] = 'sys_expe_found_ships_3_' . $i;
			$keys[] = 'sys_expe_found_dm_3_' . $i;
		}

		return $keys;
	}

	public function testMissionCaseExpeditionKeysResolveInEnglishFleet(): void
	{
		$lng = new Language('en');
		$lng->includeData(['FLEET']);

		foreach (self::expeditionMessageKeys() as $key) {
			$value = $lng[$key];
			$this->assertNotSame($key, $value, "Missing or unresolved language key: {$key}");
			$this->assertNotSame('', trim((string) $value), "Empty language string for key: {$key}");
		}
	}

	public function testExpeditionReturnMessagesRetainSprintfPlaceholders(): void
	{
		$lng = new Language('en');
		$lng->includeData(['FLEET']);

		$this->assertSame(8, substr_count((string) $lng['sys_expe_back_home'], '%s'));
		$this->assertSame(3, substr_count((string) $lng['sys_expe_back_home_with_dm'], '%s'));
	}
}
