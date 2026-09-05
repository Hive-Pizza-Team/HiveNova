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
		$planet[] = '`b_hangar_plus` = \'0\'';
		$planet[] = '`field_current` = \'0\'';
		$planet[] = '`id_luna` = \'0\'';
		$planet[] = '`destruyed` = \'0\'';
		$planet[] = '`der_metal` = \'0\'';
		$planet[] = '`der_crystal` = \'0\'';
		$planet[] = '`last_jump_time` = \'0\'';
		$planet[] = '`metal` = :metal';
		$planet[] = '`crystal` = :crystal';
		$planet[] = '`deuterium` = :deuterium';
		$planet[] = '`last_update` = :now';
		$planet[] = '`eco_hash` = \'\'';
		$planet[] = '`metal_perhour` = \'0\'';
		$planet[] = '`crystal_perhour` = \'0\'';
		$planet[] = '`deuterium_perhour` = \'0\'';
		$planet[] = '`energy` = \'0\'';
		$planet[] = '`energy_used` = \'0\'';

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
		$user[] = '`wons` = \'0\'';
		$user[] = '`loos` = \'0\'';
		$user[] = '`draws` = \'0\'';
		$user[] = '`kbmetal` = \'0\'';
		$user[] = '`kbcrystal` = \'0\'';
		$user[] = '`lostunits` = \'0\'';
		$user[] = '`desunits` = \'0\'';
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
				'DELETE FROM %%TRADES%% WHERE `seller_fleet_id` IN (SELECT `fleet_id` FROM %%FLEETS%% WHERE `fleet_universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%FLEETS_EVENT%% WHERE `fleetID` IN (SELECT `fleet_id` FROM %%FLEETS%% WHERE `fleet_universe` = :uni)',
				$paramsUni
			);
			$db->delete('DELETE FROM %%FLEETS%% WHERE `fleet_universe` = :uni', $paramsUni);
			$db->delete(
				'DELETE FROM %%PLANETS%% WHERE `universe` = :uni AND `planet_type` = \'3\'',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%PLANETS%% WHERE `universe` = :uni AND `id` NOT IN (
					SELECT `id_planet` FROM %%USERS%% WHERE `universe` = :uni2 AND `id_planet` > 0
				)',
				[':uni' => $universe, ':uni2' => $universe]
			);

			$planetSql = $this->planetSetSql !== '' ? $this->planetSetSql : '`metal` = :metal, `crystal` = :crystal, `deuterium` = :deuterium, `last_update` = :now';
			$planetParams = [
				':uni'       => $universe,
				':metal'     => (int) $config->metal_start,
				':crystal'   => (int) $config->crystal_start,
				':deuterium' => (int) $config->deuterium_start,
			];
			if (str_contains($planetSql, ':now')) {
				$planetParams[':now'] = defined('TIMESTAMP') ? TIMESTAMP : time();
			}
			$db->update(
				'UPDATE %%PLANETS%% SET ' . $planetSql . ' WHERE `universe` = :uni',
				$planetParams
			);

			$userSql = $this->userSetSql !== '' ? $this->userSetSql : '`darkmatter` = :darkmatter';
			$db->update(
				'UPDATE %%USERS%% SET ' . $userSql . ' WHERE `universe` = :uni',
				[
					':uni'        => $universe,
					':darkmatter' => (int) $config->darkmatter_start,
				]
			);

			$db->delete(
				'DELETE FROM %%TOPKB_USERS%% WHERE `rid` IN (SELECT `rid` FROM %%TOPKB%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%RW%% WHERE `rid` IN (SELECT `rid` FROM %%TOPKB%% WHERE `universe` = :uni)',
				$paramsUni
			);
			// Raports have no universe column. Combat hall rids come from TOPKB;
			// expedition reports and leftover combat HTML are tagged by attacker/defender user ids.
			$db->delete(
				'DELETE r FROM %%RW%% r
				INNER JOIN %%USERS%% u ON u.universe = :uni
					AND (FIND_IN_SET(u.id, r.attacker) OR FIND_IN_SET(u.id, r.defender))',
				$paramsUni
			);
			$db->delete('DELETE FROM %%TOPKB%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%STATPOINTS%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%RECORDS%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%NOTES%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%SALVAGE_PACKAGES%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%UNIVERSE_EVENTS%% WHERE `universe` = :uni', $paramsUni);
			$db->delete(
				'DELETE FROM %%MESSAGES%% WHERE `message_universe` = :uni
					OR `message_owner` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni2)',
				[':uni' => $universe, ':uni2' => $universe]
			);
			$db->delete('DELETE FROM %%BUDDY%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%DIPLO%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%LOG_BUILDINGS%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%LOG_RESEARCH%% WHERE `universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%LOG_SHIPYARD%% WHERE `universe` = :uni', $paramsUni);
			$db->delete(
				'DELETE FROM %%USER_DIRECTIVES%% WHERE `user_id` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%EXPEDITION_PENDING_CHOICES%% WHERE `user_id` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%USER_ACHIEVEMENTS%% WHERE `user_id` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%USER_ACHIEVEMENT_PROGRESS%% WHERE `user_id` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%ACHIEVEMENT_GRANTS%% WHERE `user_id` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%SHORTCUTS%% WHERE `ownerID` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%FREQUENT_LOCATIONS%% WHERE `ownerID` IN (SELECT `id` FROM %%USERS%% WHERE `universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%ALLIANCE_REQUEST%% WHERE `allianceID` IN (SELECT `id` FROM %%ALLIANCE%% WHERE `ally_universe` = :uni)',
				$paramsUni
			);
			$db->delete(
				'DELETE FROM %%ALLIANCE_RANK%% WHERE `allianceID` IN (SELECT `id` FROM %%ALLIANCE%% WHERE `ally_universe` = :uni)',
				$paramsUni
			);
			$db->delete('DELETE FROM %%ALLIANCE%% WHERE `ally_universe` = :uni', $paramsUni);
			$db->delete('DELETE FROM %%DIRECTIVE_PERIODS%% WHERE `universe` = :uni', $paramsUni);
			FeatService::resetForSeasonWipe($universe, $config);
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
