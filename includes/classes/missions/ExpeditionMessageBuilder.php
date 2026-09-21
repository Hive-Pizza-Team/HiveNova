<?php

namespace HiveNova\Mission;

class ExpeditionMessageBuilder
{
	/**
	 * Inbox subject in the same shape as spy/combat reports: "Expedition report [G:S:P]".
	 *
	 * @param array<string, mixed> $fleet
	 */
	public static function reportSubject(string $reportLabel, string $coordFormat, array $fleet): string
	{
		return $reportLabel . ' ' . self::plainCoordinates($coordFormat, $fleet);
	}

	/**
	 * Destination coordinates as `[galaxy:system:planet]`.
	 *
	 * @param array<string, mixed> $fleet
	 */
	public static function plainCoordinates(string $coordFormat, array $fleet): string
	{
		return sprintf(
			$coordFormat,
			$fleet['fleet_end_galaxy'] ?? 0,
			$fleet['fleet_end_system'] ?? 0,
			$fleet['fleet_end_planet'] ?? 0
		);
	}

	/**
	 * Prefix a result/return body with a clickable destination, matching other fleet messages.
	 *
	 * @param array<string, mixed> $fleet
	 */
	public static function withDestination(string $message, array $fleet, string $labelFormat): string
	{
		$header = sprintf($labelFormat, GetTargetAddressLink($fleet, ''));

		return $header . '<br>' . $message;
	}
}
