<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MergeTrainTranslationsTest extends TestCase
{
	private const NON_EN_LANGS = ['de', 'es', 'fr', 'pl', 'pt', 'ru', 'tr'];

	/**
	 * Representative strings from #768 (#700, #707, #706, #705, #690, #698, #666, #657, #662)
	 * that must not remain English copies in non-English locales.
	 *
	 * @var array<string, list<string>>
	 */
	private const TRANSLATED_KEYS = [
		'PUBLIC.php' => [
			'battleHallCtaHook',
			'battleHallCtaButton',
			'metaTitleBattleReport',
			'metaDescriptionBattleReport',
			'battlePublicReportSummary',
			'metaTitleBattleHallUni',
			'metaDescriptionBattleHallUni',
		],
		'INGAME.php' => [
			'lm_medals',
			'md_title',
			'md_empty_not_found',
			'md_empty_no_hive',
			'md_empty_none',
			'md_cosmetic_note',
			'op_hive_upgrade_title',
			'op_hive_upgrade_intro',
			'op_hive_create_submit',
			'op_hive_create_pending',
			'op_hive_create_approved',
			'lm_universe_switch',
			'us_title',
			'us_intro',
			'us_switched',
			'us_confirm',
			'push_fleet_return_title',
			'push_fleet_return_body',
			'push_fleet_return_body_many',
			'push_queue_empty_title',
			'push_social_pm_title',
			'cm_dir_defensive_desc',
			'cm_suggest_defensive',
		],
		'ADMIN.php' => [
			'mu_hive_create_requests',
			'hive_create_admin_title',
			'hive_create_admin_help',
			'hive_create_admin_approve',
			'hive_create_admin_reject',
			'hive_create_admin_empty',
			'se_seo_canonical_origin',
			'se_seo_canonical_origin_info',
			'mip_missing_user',
		],
		'CUSTOM.php' => [
			'fb_title',
			'fb_intro',
			'fb_description_placeholder',
			'fb_capture_screenshot',
			'fb_send',
			'fb_success',
			'fb_error_empty',
			'fb_error_rate',
			'fb_success_no_screenshot',
		],
	];

	public function testMergeTrainKeysAreTranslatedAndKeepSprintfPlaceholders(): void
	{
		$root = __DIR__ . '/../../language';

		foreach (self::TRANSLATED_KEYS as $file => $keys) {
			$en = $this->loadLangFile($root . '/en/' . $file);

			foreach (self::NON_EN_LANGS as $lang) {
				$loc = $this->loadLangFile($root . '/' . $lang . '/' . $file);

				foreach ($keys as $key) {
					$this->assertArrayHasKey($key, $en, "en/$file missing $key");
					$this->assertArrayHasKey($key, $loc, "$lang/$file missing $key");
					$this->assertNotSame(
						$en[$key],
						$loc[$key],
						"$lang/$file key '$key' is still an English copy"
					);

					$enSpecs = $this->extractPlaceholders((string) $en[$key]);
					$locSpecs = $this->extractPlaceholders((string) $loc[$key]);
					$this->assertSame(
						$enSpecs,
						$locSpecs,
						"$lang/$file key '$key' sprintf placeholders differ from en"
					);
				}
			}
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function loadLangFile(string $path): array
	{
		$LNG = [];
		require $path;
		return $LNG;
	}

	/**
	 * @return list<string>
	 */
	private function extractPlaceholders(string $text): array
	{
		preg_match_all('/%(?:\d+\$)?[sd]/', $text, $matches);
		return $matches[0];
	}
}
