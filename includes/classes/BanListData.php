<?php

namespace HiveNova\Core;

class BanListData
{
	/**
	 * @return array{banList: list<array<string, mixed>>, banCount: int, page: int, maxPage: int}
	 */
	public static function fetch(int $universe, int $page, string $dateFormat, string $timezone, string $writeMailTemplate): array
	{
		$db = Database::get();

		$sql = 'SELECT COUNT(*) as count FROM %%BANNED%% WHERE universe = :universe ORDER BY time DESC;';
		$banCount = (int) $db->selectSingle($sql, array(
			':universe' => $universe,
		), 'count');

		$maxPage = (int) ceil($banCount / BANNED_USERS_PER_PAGE);
		$page = max(1, min($page, $maxPage));

		$sql = 'SELECT * FROM %%BANNED%% WHERE universe = :universe ORDER BY time DESC LIMIT :offset, :limit;';
		$banResult = $db->select($sql, array(
			':universe' => $universe,
			':offset' => (($page - 1) * BANNED_USERS_PER_PAGE),
			':limit' => BANNED_USERS_PER_PAGE,
		));

		$banList = array();
		foreach ($banResult as $banRow) {
			$banList[] = array(
				'player' => $banRow['who'],
				'theme' => $banRow['theme'],
				'from' => _date($dateFormat, $banRow['time'], $timezone),
				'to' => _date($dateFormat, $banRow['longer'], $timezone),
				'admin' => $banRow['author'],
				'mail' => $banRow['email'],
				'info' => sprintf($writeMailTemplate, $banRow['author']),
			);
		}

		return array(
			'banList' => $banList,
			'banCount' => $banCount,
			'page' => $page,
			'maxPage' => $maxPage,
		);
	}
}
