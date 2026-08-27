<?php

namespace HiveNova\Core;

class AllianceDiplomacyService
{
	/**
	 * @return array<int, array<int, array<int, string>>>
	 */
	public static function buildDiplomaticList(int $allianceId): array
	{
		$diplomaticList = array(
			0 => array(1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array(), 6 => array()),
			1 => array(1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array(), 6 => array()),
			2 => array(1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array(), 6 => array()),
		);

		$sql = 'SELECT d.id, d.level, d.accept, d.owner_1, d.owner_2, a.ally_name FROM %%DIPLO%% d
		INNER JOIN %%ALLIANCE%% a ON IF(:allianceId = d.owner_1, a.id = d.owner_2, a.id = d.owner_1)
		WHERE owner_1 = :allianceId OR owner_2 = :allianceId';

		$diplomaticResult = Database::get()->select($sql, array(
			':allianceId' => $allianceId,
		));

		foreach ($diplomaticResult as $diplomaticRow) {
			$own = $diplomaticRow['owner_1'] == $allianceId;
			if ($diplomaticRow['accept'] == 1) {
				$diplomaticList[0][$diplomaticRow['level']][$diplomaticRow['id']] = $diplomaticRow['ally_name'];
			} elseif ($own) {
				$diplomaticList[2][$diplomaticRow['level']][$diplomaticRow['id']] = $diplomaticRow['ally_name'];
			} else {
				$diplomaticList[1][$diplomaticRow['level']][$diplomaticRow['id']] = $diplomaticRow['ally_name'];
			}
		}

		return $diplomaticList;
	}

	public static function accept(int $allianceId, int $diploId): void
	{
		Database::get()->update(
			'UPDATE %%DIPLO%% SET accept = 1 WHERE id = :id AND owner_2 = :allianceId;',
			array(
				':allianceId' => $allianceId,
				':id' => $diploId,
			)
		);
	}

	public static function delete(int $allianceId, int $diploId): void
	{
		Database::get()->delete(
			'DELETE FROM %%DIPLO%% WHERE id = :id AND (owner_1 = :allianceId OR owner_2 = :allianceId);',
			array(
				':allianceId' => $allianceId,
				':id' => $diploId,
			)
		);
	}

	/**
	 * @return array{ids: list<int>, names: list<string>}
	 */
	public static function listOtherAlliances(int $allianceId, int $universe): array
	{
		$rows = Database::get()->select(
			'SELECT ally_tag, ally_name, id FROM %%ALLIANCE%% WHERE id != :allianceId AND ally_universe = :universe ORDER BY ally_tag ASC;',
			array(
				':allianceId' => $allianceId,
				':universe' => $universe,
			)
		);

		$ids = array();
		$names = array();
		foreach ($rows as $row) {
			$ids[] = $row['id'];
			$names[] = $row['ally_name'];
		}

		return array('ids' => $ids, 'names' => $names);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function findTargetAlliance(int $allianceId, int $targetId, int $universe): ?array
	{
		$row = Database::get()->selectSingle(
			'SELECT id, ally_name, ally_owner, ally_tag, (SELECT level FROM %%DIPLO%% WHERE (owner_1 = :id AND owner_2 = :allianceId) OR (owner_2 = :id AND owner_1 = :allianceId)) as diplo FROM %%ALLIANCE%% WHERE ally_universe = :universe AND id = :id;',
			array(
				':allianceId' => $allianceId,
				':id' => $targetId,
				':universe' => $universe,
			)
		);

		return is_array($row) ? $row : null;
	}

	public static function createRequest(int $allianceId, int $targetAllianceId, int $level, string $text, int $universe): void
	{
		if (strlen($text) > 255) {
			$text = substr($text, 0, 255);
		}

		Database::get()->insert(
			'INSERT INTO %%DIPLO%% SET owner_1 = :allianceId, owner_2 = :allianceTargetID, level = :level, accept = 0, accept_text = :text, universe = :universe',
			array(
				':allianceId' => $allianceId,
				':allianceTargetID' => $targetAllianceId,
				':level' => $level,
				':text' => $text,
				':universe' => $universe,
			)
		);
	}
}
