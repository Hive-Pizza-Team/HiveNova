<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class I18nGapsTranslationsTest extends TestCase
{
	private const NON_EN_LANGS = ['de', 'es', 'fr', 'pl', 'pt', 'ru', 'tr'];

	/**
	 * User-facing strings from the NovaDev i18n-gap pass that must not remain
	 * English copies in non-English locales. Filter labels that are naturally
	 * identical in a locale (e.g. es/pt "Imperial") are omitted here.
	 *
	 * @var list<string>
	 */
	private const INGAME_KEYS = [
		'bd_queue_empty',
		'pl_member_since_label',
		'pl_member_since',
		'op_push_notifications',
		'op_push_disable',
		'op_install_app_title',
		'op_install_app_desc',
		'whats_new_settings_label',
		'whats_new_settings_link',
		'whats_new_title',
		'whats_new_dialog_close',
		'whats_new_dialog_got_it',
		'bd_filter_mining',
		'bd_filter_other',
		'bd_filter_all',
		'mg_compose_recipient',
		'mg_unread',
	];

	public function testGapKeysAreTranslatedAndKeepSprintfPlaceholders(): void
	{
		$root = __DIR__ . '/../../language';
		$en = $this->loadLangFile($root . '/en/INGAME.php');

		foreach (self::NON_EN_LANGS as $lang) {
			$loc = $this->loadLangFile($root . '/' . $lang . '/INGAME.php');

			foreach (self::INGAME_KEYS as $key) {
				$this->assertArrayHasKey($key, $en, "en/INGAME.php missing $key");
				$this->assertArrayHasKey($key, $loc, "$lang/INGAME.php missing $key");
				$this->assertNotSame(
					$en[$key],
					$loc[$key],
					"$lang/INGAME.php key '$key' is still an English copy"
				);

				$enSpecs = $this->extractPlaceholders((string) $en[$key]);
				$locSpecs = $this->extractPlaceholders((string) $loc[$key]);
				$this->assertSame(
					$enSpecs,
					$locSpecs,
					"$lang/INGAME.php key '$key' sprintf placeholders differ from en"
				);
			}
		}
	}

	public function testGermanNextLevelIsTranslated(): void
	{
		$de = $this->loadLangFile(__DIR__ . '/../../language/de/INGAME.php');
		$en = $this->loadLangFile(__DIR__ . '/../../language/en/INGAME.php');
		$this->assertNotSame($en['bd_next_level'], $de['bd_next_level']);
		$this->assertSame('Nächste Stufe:', $de['bd_next_level']);
	}

	public function testResource903LocalizedName(): void
	{
		$expected = [
			'en' => 'Uranium',
			'de' => 'Uran',
			'es' => 'Uranio',
			'fr' => 'Uranium',
			'pl' => 'Uran',
			'pt' => 'Urânio',
			'ru' => 'Уран',
			'tr' => 'Uranyum',
		];

		foreach ($expected as $lang => $name) {
			$tech = $this->loadLangFile(__DIR__ . '/../../language/' . $lang . '/TECH.php');
			$this->assertArrayHasKey('tech', $tech, "$lang/TECH.php missing tech");
			$this->assertArrayHasKey(903, $tech['tech'], "$lang/TECH.php missing tech 903");
			$this->assertSame($name, $tech['tech'][903], "$lang resource 903 name");
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
