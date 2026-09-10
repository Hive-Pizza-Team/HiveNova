<?php

declare(strict_types=1);

use HiveNova\Core\ImperiumView;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class ImperiumViewTest extends TestCase
{
	public function test_energy_available_matches_top_bar_used_plus_produced(): void
	{
		// energy_used is stored as a negative consumption total.
		$this->assertSame(9039.0, ImperiumView::energyAvailable(34297, -25258));
		$this->assertSame(0.0, ImperiumView::energyAvailable(0, 0));
		$this->assertSame(100.0, ImperiumView::energyAvailable('100', '0'));
	}

	public function test_encode_matrix_json_is_object_with_sections(): void
	{
		$json = ImperiumView::encodeMatrixJson([
			'colspan'   => 3,
			'planetIds' => ['10'],
			'sections'  => [
				'build' => [
					['id' => 1, 'name' => 'Ore Extractor', 'total' => 5, 'values' => ['10' => 5]],
				],
			],
		]);

		$this->assertStringStartsWith('{', $json);
		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertSame(3, $decoded['colspan']);
		$this->assertSame('Ore Extractor', $decoded['sections']['build'][0]['name']);
		$this->assertSame(5, $decoded['sections']['build'][0]['values']['10']);
	}

	public function test_encode_matrix_json_escapes_script_breakers(): void
	{
		$json = ImperiumView::encodeMatrixJson([
			'colspan'   => 2,
			'planetIds' => [],
			'sections'  => [
				'tech' => [
					['id' => 1, 'name' => '</script><img>', 'total' => 1, 'values' => []],
				],
			],
		]);

		$this->assertStringNotContainsString('</script>', $json);
		$decoded = json_decode($json, true);
		$this->assertSame('</script><img>', $decoded['sections']['tech'][0]['name']);
	}

	public function test_tech_names_from_language_array_and_missing(): void
	{
		$this->assertSame([1 => 'Mine'], ImperiumView::techNamesFromLanguage(['tech' => [1 => 'Mine']]));
		$this->assertSame([], ImperiumView::techNamesFromLanguage(['tech' => 'nope']));
		$this->assertSame([], ImperiumView::techNamesFromLanguage(null));
	}

	public function test_tech_names_from_array_access_language(): void
	{
		$lng = new class implements \ArrayAccess {
			public function offsetExists(mixed $offset): bool
			{
				return $offset === 'tech';
			}

			public function offsetGet(mixed $offset): mixed
			{
				return $offset === 'tech' ? [1 => 'Mine'] : null;
			}

			public function offsetSet(mixed $offset, mixed $value): void
			{
			}

			public function offsetUnset(mixed $offset): void
			{
			}
		};

		$this->assertSame([1 => 'Mine'], ImperiumView::techNamesFromLanguage($lng));
	}
}
