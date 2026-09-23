<?php

namespace HiveNova\Core;

/**
 * Invitees join an ACS by sending a fleet into the existing group (mission 2).
 * Membership is written when the attacker invites them; this service turns that
 * membership into a fleet-step target and refuses a silent solo-attack fallback.
 */
class AcsJoinService
{
	public static function inviteMessage(
		string $playerWord,
		string $inviterName,
		string $invitedSuffix,
		string $joinLabel,
		int $acsId
	): string {
		$href = 'game.php?page=fleetTable&amp;joinAcs=' . max(0, $acsId);

		return $playerWord . $inviterName . $invitedSuffix
			. '<br><br><a href="' . $href . '">' . $joinLabel . '</a>';
	}

	/**
	 * Posted group wins. Otherwise use the open invite aimed at this target.
	 */
	public static function resolveGroup(int $explicitGroup, int $invitedGroupAtTarget): int
	{
		if ($explicitGroup > 0) {
			return $explicitGroup;
		}

		return max(0, $invitedGroupAtTarget);
	}

	/**
	 * Clicking an ACS row or the invite link forces mission 2.
	 * A coordinate match with no mission chosen does too, so the selector
	 * offers ACS instead of leaving Attack checked by default.
	 * An explicit non-ACS mission (galaxy Attack) stays selected.
	 */
	public static function missionAfterGroup(int $explicitGroup, int $resolvedGroup, int $requestedMission): int
	{
		if ($explicitGroup > 0) {
			return FLEET_MISSION_ACS;
		}

		if ($resolvedGroup > 0 && ($requestedMission === 0 || $requestedMission === FLEET_MISSION_ACS)) {
			return FLEET_MISSION_ACS;
		}

		return $requestedMission;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function listForUser(int $userId): array
	{
		if ($userId <= 0 || !isModuleAvailable(MODULE_MISSION_ACS)) {
			return [];
		}

		$sql = 'SELECT acs.id, acs.name, planet.galaxy, planet.system, planet.planet, planet.planet_type
			FROM %%USERS_ACS%% member
			INNER JOIN %%AKS%% acs ON member.acsID = acs.id
			INNER JOIN %%PLANETS%% planet ON planet.id = acs.target
			WHERE member.userID = :userID
			AND :maxFleets > (
				SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = member.acsID
			);';

		return Database::get()->select($sql, [
			':userID' => $userId,
			':maxFleets' => self::maxFleets(),
		]);
	}

	/**
	 * Newest joinable ACS this player was invited to at the destination planet.
	 */
	public static function invitedGroupAtTarget(int $userId, int $targetPlanetId): int
	{
		if ($userId <= 0 || $targetPlanetId <= 0 || !isModuleAvailable(MODULE_MISSION_ACS)) {
			return 0;
		}

		$sql = 'SELECT acs.id
			FROM %%USERS_ACS%% member
			INNER JOIN %%AKS%% acs ON member.acsID = acs.id
			WHERE member.userID = :userID
			AND acs.target = :targetId
			AND :maxFleets > (
				SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = acs.id
			)
			ORDER BY acs.id DESC
			LIMIT 1;';

		$id = Database::get()->selectSingle($sql, [
			':userID' => $userId,
			':targetId' => $targetPlanetId,
			':maxFleets' => self::maxFleets(),
		], 'id');

		return (int) $id;
	}

	/**
	 * @return array{id: int, ankunft: int}|null
	 */
	public static function lockJoin(int $userId, int $acsId, int $targetPlanetId): ?array
	{
		if ($userId <= 0 || $acsId <= 0 || $targetPlanetId <= 0 || !isModuleAvailable(MODULE_MISSION_ACS)) {
			return null;
		}

		$sql = 'SELECT acs.id, acs.ankunft
			FROM %%USERS_ACS%% member
			INNER JOIN %%AKS%% acs ON member.acsID = acs.id
			WHERE member.userID = :userID
			AND member.acsID = :acsId
			AND acs.target = :targetId
			AND :maxFleets > (
				SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = :fleetGroup
			);';

		$row = Database::get()->selectSingle($sql, [
			':userID' => $userId,
			':acsId' => $acsId,
			':targetId' => $targetPlanetId,
			':fleetGroup' => $acsId,
			':maxFleets' => self::maxFleets(),
		]);

		if (!is_array($row) || empty($row['id'])) {
			return null;
		}

		return [
			'id' => (int) $row['id'],
			'ankunft' => (int) ($row['ankunft'] ?? 0),
		];
	}

	/**
	 * Fleet-table target for an invite link. Null when the player cannot join.
	 *
	 * @return array{id: int, name: string, galaxy: int, system: int, planet: int, planet_type: int}|null
	 */
	public static function targetForMember(int $userId, int $acsId): ?array
	{
		if ($userId <= 0 || $acsId <= 0 || !isModuleAvailable(MODULE_MISSION_ACS)) {
			return null;
		}

		$sql = 'SELECT acs.id, acs.name, planet.galaxy, planet.system, planet.planet, planet.planet_type
			FROM %%USERS_ACS%% member
			INNER JOIN %%AKS%% acs ON member.acsID = acs.id
			INNER JOIN %%PLANETS%% planet ON planet.id = acs.target
			WHERE member.userID = :userID
			AND member.acsID = :acsId
			AND :maxFleets > (
				SELECT COUNT(*) FROM %%FLEETS%% WHERE fleet_group = :fleetGroup
			);';

		$row = Database::get()->selectSingle($sql, [
			':userID' => $userId,
			':acsId' => $acsId,
			':fleetGroup' => $acsId,
			':maxFleets' => self::maxFleets(),
		]);

		if (!is_array($row) || empty($row['id'])) {
			return null;
		}

		return [
			'id' => (int) $row['id'],
			'name' => (string) ($row['name'] ?? ''),
			'galaxy' => (int) $row['galaxy'],
			'system' => (int) $row['system'],
			'planet' => (int) $row['planet'],
			'planet_type' => (int) $row['planet_type'],
		];
	}

	private static function maxFleets(): int
	{
		return (int) Config::get()->max_fleets_per_acs;
	}
}
