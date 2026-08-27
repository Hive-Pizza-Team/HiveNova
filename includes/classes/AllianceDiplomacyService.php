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
}
