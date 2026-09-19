<?php

/**
 * Shared player/planet state that unlocks every Empire Directive.
 */
trait DirectiveUnlockFixtures
{
	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	protected function directiveUnlockedUser(array $overrides = []): array
	{
		return array_replace([
			'combustion_tech' => 6,
			'shielding_tech' => 2,
			'astrophysics_tech' => 1,
		], $overrides);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	protected function directiveUnlockedPlanet(array $overrides = []): array
	{
		return array_replace([
			'hangar' => 4,
		], $overrides);
	}
}
