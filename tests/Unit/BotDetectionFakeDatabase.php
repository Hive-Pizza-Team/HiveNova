<?php

use HiveNova\Core\BotDetectionService;

require_once __DIR__ . '/../Support/FakeDatabase.php';

/**
 * Fake DB that emulates BotDetectionService window-function SQL in PHP.
 */
class BotDetectionFakeDatabase extends FakeDatabase
{
	/** @var list<array{user_id: int, username: string, event_time: int, source?: string}> */
	public array $botDetectionEvents = [];

	/** @var list<array{id: int}> */
	public array $adminUsers = [];

	/** @var array<int, array{bana?: int, urlaubs_modus?: int, email?: string, authlevel?: int}> */
	public array $botDetectionUsers = [];

	/** @var array<int, string> */
	public array $botDetectionState = [];

	public function select($qry, array $params = [])
	{
		if (str_contains($qry, 'WITH events AS')) {
			return $this->findSuspectsFromEvents($params);
		}

		if (str_contains($qry, 'FROM %%USERS%%') && str_contains($qry, 'authlevel')) {
			return $this->adminUsers;
		}

		return parent::select($qry, $params);
	}

	public function selectSingle($qry, array $params = [], $field = false)
	{
		if (str_contains($qry, '%%BOT_DETECTION_STATE%%')) {
			$uni = (int) ($params[':universe'] ?? 0);
			if (!isset($this->botDetectionState[$uni])) {
				return $field ? null : [];
			}

			$row = ['last_digest_hash' => $this->botDetectionState[$uni]];

			return $field ? ($row[$field] ?? null) : $row;
		}

		return parent::selectSingle($qry, $params, $field);
	}

	public function insert($qry, array $params = [])
	{
		if (str_contains($qry, '%%BOT_DETECTION_STATE%%')) {
			$this->botDetectionState[(int) $params[':universe']] = (string) $params[':hash'];

			return;
		}

		parent::insert($qry, $params);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function findSuspectsFromEvents(array $params): array
	{
		$cutoff         = (int) ($params[':cutoff'] ?? 0);
		$now            = (int) ($params[':now'] ?? TIMESTAMP);
		$minActions     = (int) ($params[':minActions'] ?? BotDetectionService::MIN_ACTIONS);
		$sleepThreshold = (int) ($params[':sleepThreshold'] ?? BotDetectionService::SLEEP_THRESHOLD);
		$npcEmail       = (string) ($params[':npcEmail'] ?? BotDetectionService::NPC_BOT_EMAIL);
		$authUsr        = (int) ($params[':authUsr'] ?? AUTH_USR);

		$byUser = [];
		foreach ($this->botDetectionEvents as $row) {
			$eventTime = (int) $row['event_time'];
			if ($eventTime < $cutoff) {
				continue;
			}

			$userId = (int) $row['user_id'];
			if (!isset($byUser[$userId])) {
				$byUser[$userId] = [
					'username'       => (string) $row['username'],
					'times'          => [],
					'fleet_count'    => 0,
					'building_count' => 0,
					'research_count' => 0,
					'shipyard_count' => 0,
				];
			}

			$source = (string) ($row['source'] ?? 'fleet');
			$byUser[$userId]['times'][] = $eventTime;
			if ($source === 'fleet') {
				$byUser[$userId]['fleet_count']++;
			} elseif ($source === 'building') {
				$byUser[$userId]['building_count']++;
			} elseif ($source === 'research') {
				$byUser[$userId]['research_count']++;
			} elseif ($source === 'shipyard') {
				$byUser[$userId]['shipyard_count']++;
			}
		}

		$suspects = [];
		foreach ($byUser as $userId => $data) {
			sort($data['times']);
			$count = count($data['times']);
			if ($count < $minActions) {
				continue;
			}

			$userMeta = $this->botDetectionUsers[$userId] ?? [];
			if ((int) ($userMeta['bana'] ?? 0) !== 0) {
				continue;
			}
			if ((int) ($userMeta['urlaubs_modus'] ?? 0) !== 0) {
				continue;
			}
			if (($userMeta['email'] ?? '') === $npcEmail) {
				continue;
			}
			if ((int) ($userMeta['authlevel'] ?? AUTH_USR) !== $authUsr) {
				continue;
			}

			$maxGap = BotDetectionService::computeMaxGapSeconds($data['times'], $cutoff, $now);
			if ($maxGap >= $sleepThreshold) {
				continue;
			}

			$suspects[] = [
				'id'              => $userId,
				'username'        => $data['username'],
				'max_gap_seconds' => $maxGap,
				'total_actions'   => $count,
				'fleet_count'     => $data['fleet_count'],
				'building_count'  => $data['building_count'],
				'research_count'  => $data['research_count'],
				'shipyard_count'  => $data['shipyard_count'],
			];
		}

		usort(
			$suspects,
			static fn (array $a, array $b): int => $a['max_gap_seconds'] <=> $b['max_gap_seconds']
		);

		return $suspects;
	}
}
