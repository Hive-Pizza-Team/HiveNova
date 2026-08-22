<?php

namespace HiveNova\Core;

/**
 * Wipe seasonal-universe progress while keeping accounts and Hive links.
 */
class SeasonWipeService
{
	public function __construct(
		private readonly string $planetSetSql = '',
		private readonly string $userSetSql = '',
	) {
	}

	public static function fromGlobals(?array $reslist = null, ?array $resource = null, ?Config $config = null): self
	{
		$reslist ??= $GLOBALS['reslist'] ?? [];
		$resource ??= $GLOBALS['resource'] ?? [];
		$config ??= Config::get();

		$planet = [];
		foreach (['build', 'fleet', 'defense', 'missile'] as $group) {
			foreach ($reslist[$group] ?? [] as $id) {
				if (isset($resource[$id])) {
					$planet[] = '`' . $resource[$id] . '` = \'0\'';
				}
			}
		}
		$planet[] = '`b_building` = \'0\'';
		$planet[] = '`b_building_id` = \'\'';
		$planet[] = '`b_hangar` = \'0\'';
		$planet[] = '`b_hangar_id` = \'\'';
		$planet[] = '`field_current` = \'0\'';
		$planet[] = '`id_luna` = \'0\'';
		$planet[] = '`metal` = :metal';
		$planet[] = '`crystal` = :crystal';
		$planet[] = '`deuterium` = :deuterium';

		$user = [];
		foreach (['tech', 'officier', 'dmfunc'] as $group) {
			foreach ($reslist[$group] ?? [] as $id) {
				if (isset($resource[$id])) {
					$user[] = '`' . $resource[$id] . '` = \'0\'';
				}
			}
		}
		$user[] = '`b_tech_planet` = \'0\'';
		$user[] = '`b_tech` = \'0\'';
		$user[] = '`b_tech_id` = \'0\'';
		$user[] = '`b_tech_queue` = \'\'';
		$user[] = '`ally_id` = \'0\'';
		$user[] = '`ally_register_time` = \'0\'';
		$user[] = '`ally_rank_id` = \'0\'';
		$user[] = '`darkmatter` = :darkmatter';

		return new self(implode(', ', $planet), implode(', ', $user));
	}

	public function wipe(int $universe, Config $config): void
	{
		$db = Database::get();
		$paramsUni = [':uni' => $universe];

		$db->beginTransaction();
		try {
			$db->delete(
				'DELETE FROM %%FLEETS_EVENT%% WHERE `fleetID` IN (SELECT `fleet_id` FROM %%FLEETS%% WHERE `fleet_universe` = :uni)',
				$paramsUni
			);
			$db->delete('DELETE FROM %%FLEETS%% WHERE `fleet_universe` = :uni', $paramsUni);
			$db->delete(
				'DELETE FROM %%PLANETS%% WHERE `universe` = :uni AND `id` NOT IN (SELECT `id_planet` FROM %%USERS%% WHERE `universe` = :uni2)',
				[':uni' => $universe, ':uni2' => $universe]
			);

			$planetSql = $this->planetSetSql !== '' ? $this->planetSetSql : '`metal` = :metal, `crystal` = :crystal, `deuterium` = :deuterium';
			$db->update(
				'UPDATE %%PLANETS%% SET ' . $planetSql . ' WHERE `universe` = :uni',
				[
					':uni'       => $universe,
					':metal'     => (int) $config->metal_start,
					':crystal'   => (int) $config->crystal_start,
					':deuterium' => (int) $config->deuterium_start,
				]
			);

			$userSql = $this->userSetSql !== '' ? $this->userSetSql : '`darkmatter` = :darkmatter';
			$db->update(
				'UPDATE %%USERS%% SET ' . $userSql . ' WHERE `universe` = :uni',
				[
					':uni'        => $universe,
					':darkmatter' => (int) $config->darkmatter_start,
				]
			);

			$db->delete('DELETE FROM %%STATPOINTS%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%TOPKB%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%NOTES%% WHERE `universe` = :uni', $paramsUni);
			$db->commit();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}
	}

	public function planetSetSql(): string
	{
		return $this->planetSetSql;
	}

	public function userSetSql(): string
	{
		return $this->userSetSql;
	}
}
