<?php

namespace HiveNova\Core;

/**
 * Email registration verification helpers.
 *
 * Verify links must resolve regardless of the visitor's uni cookie or path —
 * the pending row already stores the target universe.
 */
class EmailRegistrationService
{
	/**
	 * @return array<string, mixed>|false
	 */
	public static function findPendingValidation(int $validationID, string $validationKey)
	{
		if ($validationID <= 0 || $validationKey === '') {
			return false;
		}

		$sql = 'SELECT * FROM %%USERS_VALID%%
			WHERE validationID = :validationID
			AND validationKey = :validationKey;';

		$row = Database::get()->selectSingle($sql, [
			':validationID'  => $validationID,
			':validationKey' => $validationKey,
		]);

		return empty($row) ? false : $row;
	}

	/**
	 * Absolute verify URL that includes the universe path and query fallback.
	 */
	public static function buildVerifyUrl(int $universeId, int $validationID, string $validationKey): string
	{
		$universeId = max(1, $universeId);
		$query = http_build_query([
			'page' => 'vertify',
			'i'    => $validationID,
			'k'    => $validationKey,
			'uni'  => $universeId,
		], '', '&', PHP_QUERY_RFC3986);

		return PROTOCOL.HTTP_HOST.HTTP_BASE.'uni'.$universeId.'/index.php?'.$query;
	}
}
