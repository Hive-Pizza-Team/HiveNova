<?php

namespace HiveNova\Core;

class AllianceRankService
{
	/**
	 * @param array<int|string, string> $availableRanks
	 * @param array<string, mixed> $rights
	 * @param array<string, mixed> $rankFlags
	 */
	public static function createRank(int $allianceId, string $rankName, array $rankFlags, array $availableRanks, array $rights): void
	{
		$sql = 'INSERT INTO %%ALLIANCE_RANK%% SET rankName = :rankName, allianceID = :allianceID';
		$params = array(
			':rankName' => $rankName,
			':allianceID' => $allianceId,
		);

		foreach ($rankFlags as $key => $value) {
			if (isset($availableRanks[$key]) && !empty($rights[$availableRanks[$key]])) {
				$col = $availableRanks[$key];
				$sql .= ', `' . $col . '` = :' . $col;
				$params[':' . $col] = $value == 1 ? 1 : 0;
			}
		}

		Database::get()->insert($sql, $params);
	}

	public static function deleteRank(int $allianceId, int $rankId): void
	{
		$db = Database::get();
		$db->delete(
			'DELETE FROM %%ALLIANCE_RANK%% WHERE rankID = :rankID AND allianceId = :allianceId;',
			array(
				':allianceId' => $allianceId,
				':rankID' => $rankId,
			)
		);
		$db->update(
			'UPDATE %%USERS%% SET ally_rank_id = 0 WHERE ally_rank_id = :rankID AND ally_id = :allianceId;',
			array(
				':allianceId' => $allianceId,
				':rankID' => $rankId,
			)
		);
	}

	/**
	 * @param array<int|string, array<string, mixed>> $rankData
	 * @param array<int|string, string> $availableRanks
	 * @param array<string, mixed> $rights
	 */
	public static function updateRanks(int $allianceId, array $rankData, array $availableRanks, array $rights): void
	{
		$db = Database::get();
		foreach ($rankData as $rankId => $rowData) {
			$sql = 'UPDATE %%ALLIANCE_RANK%% SET rankName = :rankName';
			$params = array(
				':rankName' => $rowData['rankName'],
				':allianceID' => $allianceId,
				':rankId' => $rankId,
			);

			unset($rowData['rankName']);

			foreach ($availableRanks as $key => $value) {
				if (isset($availableRanks[$key]) && !empty($rights[$availableRanks[$key]])) {
					$sql .= ', `' . $availableRanks[$key] . '` = :' . $availableRanks[$key];
					$params[':' . $availableRanks[$key]] = (isset($rowData[$key])) == 1 ? 1 : 0;
				}
			}

			$sql .= ' WHERE rankID = :rankId AND allianceID = :allianceID';
			$db->update($sql, $params);
		}
	}

	/**
	 * @param array<int|string, string> $availableRanks
	 * @param array<string, mixed> $rights
	 * @return array<int, array<string, mixed>>
	 */
	public static function loadAssignableRanks(int $allianceId, array $availableRanks, array $rights): array
	{
		$sql = 'SELECT rankID, ' . implode(', ', $availableRanks) . ' FROM %%ALLIANCE_RANK%% WHERE allianceID = :allianceId;';
		$rankResult = Database::get()->select($sql, array(
			':allianceId' => $allianceId,
		));

		$rankList = array();
		$rankList[0] = array_combine($availableRanks, array_fill(0, count($availableRanks), true));

		foreach ($rankResult as $rankRow) {
			$hasRankRight = true;
			foreach ($availableRanks as $rankName) {
				if (empty($rights[$rankName])) {
					$hasRankRight = false;
					break;
				}
			}

			if ($hasRankRight) {
				$rankList[$rankRow['rankID']] = $rankRow;
			}
		}

		return $rankList;
	}

	/**
	 * @param array<int|string, mixed> $userRanks
	 * @param array<int, array<string, mixed>> $rankList
	 */
	public static function reassignMemberRanks(int $allianceId, int $ownerId, array $userRanks, array $rankList): void
	{
		$db = Database::get();
		foreach ($userRanks as $userId => $rankId) {
			if ($userId == $ownerId || !isset($rankList[$rankId])) {
				continue;
			}

			$db->update(
				'UPDATE %%USERS%% SET ally_rank_id = :rankID WHERE id = :userId AND ally_id = :allianceId;',
				array(
					':allianceId' => $allianceId,
					':rankID' => (int) $rankId,
					':userId' => (int) $userId,
				)
			);
		}
	}
}
