<?php

namespace HiveNova\Core;

/**
 * Maps planet temperature to one of five visual variants per biome family.
 * Variant 01 = hottest end of the family range, 05 = coldest.
 */
class PlanetImageUtil
{
	public const VARIANT_COUNT = 5;

	/** @var array<string, array{0: int, 1: int}> */
	private const FAMILY_TEMP_RANGES = [
		'trocken'     => [120, 260],
		'wuesten'     => [120, 260],
		'dschjungel'  => [50, 110],
		'normaltemp'  => [-10, 80],
		'wasser'      => [-10, 60],
		'eis'         => [-130, -10],
	];

	public static function familyRange(string $familyKey): array
	{
		return self::FAMILY_TEMP_RANGES[$familyKey] ?? [-10, 80];
	}

	public static function variantFromTemperature(int $avgTemp, int $rangeMin, int $rangeMax): int
	{
		if ($rangeMax <= $rangeMin) {
			return (int) ceil(self::VARIANT_COUNT / 2);
		}

		$span = $rangeMax - $rangeMin;
		$t = max(0.0, min(1.0, ($avgTemp - $rangeMin) / $span));
		$bucket = (int) floor($t * self::VARIANT_COUNT);
		if ($bucket >= self::VARIANT_COUNT) {
			$bucket = self::VARIANT_COUNT - 1;
		}

		return self::VARIANT_COUNT - $bucket;
	}

	public static function buildImageName(string $familyKey, int $variant): string
	{
		$variant = max(1, min(self::VARIANT_COUNT, $variant));

		return $familyKey . 'planet' . sprintf('%02d', $variant);
	}

	public static function parseFamilyKey(string $image): ?string
	{
		if (preg_match('/^(trocken|wuesten|dschjungel|normaltemp|wasser|eis)planet/', $image, $matches) !== 1) {
			return null;
		}

		return $matches[1];
	}

	public static function remapLegacyImage(string $image, int $tempMin, int $tempMax): string
	{
		$familyKey = self::parseFamilyKey($image);
		if ($familyKey === null) {
			return $image;
		}

		$avgTemp = (int) floor(($tempMin + $tempMax) / 2);
		[$rangeMin, $rangeMax] = self::familyRange($familyKey);
		$variant = self::variantFromTemperature($avgTemp, $rangeMin, $rangeMax);

		return self::buildImageName($familyKey, $variant);
	}

	/**
	 * @return array{tempMin: int, tempMax: int}
	 */
	public static function catalogTempRangeForVariant(int $variant, int $rangeMin, int $rangeMax): array
	{
		$variant = max(1, min(self::VARIANT_COUNT, $variant));
		$span = $rangeMax - $rangeMin;
		$lowBand = self::VARIANT_COUNT - $variant;
		$tempMin = $rangeMin + (int) floor($span * $lowBand / self::VARIANT_COUNT);
		$tempMax = $variant === 1
			? $rangeMax
			: $rangeMin + (int) floor($span * ($lowBand + 1) / self::VARIANT_COUNT) - 1;

		return ['tempMin' => $tempMin, 'tempMax' => $tempMax];
	}
}
