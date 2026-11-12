<?php

namespace HiveNova\Core;

/**
 * Public medal inventory for logged-in players (#630).
 *
 * Primary data is season_sbt_grants. Optional nft_tracker verification is
 * soft-fail only and never invents medals.
 */
class SeasonSbtInventoryService
{
	/** @var callable(int, int): ?array{id: int, username: string, hive_account: string, register_time: int} */
	private $userById;

	/** @var callable(string, int): ?array{id: int, username: string, hive_account: string, register_time: int} */
	private $userByHive;

	public function __construct(
		private readonly SeasonSbtGrantStore $store = new ArraySeasonSbtGrantStore(),
		private readonly ?NftTrackerClient $tracker = null,
		?callable $userById = null,
		?callable $userByHive = null,
		private readonly bool $verifyOnChain = true,
	) {
		$this->userById = $userById ?? static function (int $userId, int $universe): ?array {
			if ($userId <= 0) {
				return null;
			}
			$row = Database::get()->selectSingle(
				'SELECT `id`, `username`, `hive_account`, `register_time`
				FROM %%USERS%%
				WHERE `id` = :id AND `universe` = :universe
				LIMIT 1;',
				[':id' => $userId, ':universe' => $universe]
			);

			return is_array($row) ? $row : null;
		};
		$this->userByHive = $userByHive ?? static function (string $hive, int $universe): ?array {
			$hive = strtolower(trim($hive));
			if ($hive === '' || !HiveUtil::isAccountValid($hive)) {
				return null;
			}
			$row = Database::get()->selectSingle(
				'SELECT `id`, `username`, `hive_account`, `register_time`
				FROM %%USERS%%
				WHERE `hive_account` = :hive AND `universe` = :universe
				LIMIT 1;',
				[':hive' => $hive, ':universe' => $universe]
			);

			return is_array($row) ? $row : null;
		};
	}

	/**
	 * @param array<string, string> $copy
	 * @return array{
	 *     owner: array{userId: int, username: string, hiveAccount: string, registerTime: int, memberSinceDate: string, isSelf: bool},
	 *     medals: list<array<string, mixed>>,
	 *     badges: list<array{id: string, kind: string, label: string, tooltip: string}>,
	 *     legend: list<array{id: string, label: string, badges: list<array{id: string, kind: string, label: string, tooltip: string}}>},
	 *     emptyReason: string,
	 *     trackerChecked: bool
	 * }
	 */
	public function inventory(
		int $universe,
		int $viewerId,
		?int $targetUserId,
		?string $targetHive,
		array $copy = [],
		array $monthNames = [],
	): array {
		$owner = $this->resolveOwner($universe, $viewerId, $targetUserId, $targetHive);
		$hiveAccount = (string) ($owner['hiveAccount'] ?? '');
		$memberSinceDate = PrestigeBadgeService::memberSinceLabel(
			(int) ($owner['registerTime'] ?? 0),
			$monthNames
		);

		$grants = [];
		if ((int) $owner['userId'] > 0) {
			$grants = $this->store->listForUser((int) $owner['userId']);
		}
		if ($grants === [] && $hiveAccount !== '') {
			$grants = $this->store->listForHive($hiveAccount);
		}

		$trackerChecked = false;
		$scoredMedals = [];
		foreach ($grants as $grant) {
			$medal = $this->presentMedal($grant, $copy);
			if ($this->verifyOnChain && $this->tracker !== null) {
				$verified = $this->softVerify($grant);
				if ($verified !== null) {
					$medal['verified'] = $verified;
					$trackerChecked = true;
				}
			}
			$scoredMedals[] = [
				'score' => SeasonSbtBadgeMapper::priorityScore($grant),
				'medal' => $medal,
			];
		}
		usort($scoredMedals, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
		$medals = array_map(static fn (array $row): array => $row['medal'], $scoredMedals);

		$emptyReason = '';
		if ($medals === []) {
			if ((int) $owner['userId'] <= 0 && $hiveAccount === '') {
				$emptyReason = (string) ($copy['empty_not_found'] ?? 'Player not found.');
			} elseif ($hiveAccount === '') {
				$emptyReason = (string) ($copy['empty_no_hive'] ?? 'This commander has no linked Hive account yet.');
			} else {
				$emptyReason = (string) ($copy['empty_none'] ?? 'No season medals yet.');
			}
		}

		$badgeCopy = [
			'winner'      => (string) ($copy['badge_winner'] ?? 'Uni3 %s Winner'),
			'top'         => (string) ($copy['badge_top'] ?? 'Uni3 %s Top placement'),
			'participant' => (string) ($copy['badge_participant'] ?? 'Uni3 %s Participant'),
			'feat'        => (string) ($copy['badge_feat'] ?? 'Uni3 %s Feat'),
		];

		return [
			'owner' => [
				'userId'          => (int) $owner['userId'],
				'username'        => (string) $owner['username'],
				'hiveAccount'     => $hiveAccount,
				'registerTime'    => (int) ($owner['registerTime'] ?? 0),
				'memberSinceDate' => $memberSinceDate,
				'isSelf'          => (int) $owner['userId'] > 0 && (int) $owner['userId'] === $viewerId,
			],
			'medals'         => $medals,
			'badges'         => SeasonSbtBadgeMapper::badgesFromGrants($grants, $badgeCopy),
			'legend'         => self::legend($copy),
			'emptyReason'    => $emptyReason,
			'trackerChecked' => $trackerChecked,
		];
	}

	/**
	 * @return list<array{id: string, label: string, badges: list<array{id: string, kind: string, label: string, tooltip: string}>}>
	 */
	public static function legend(array $copy = []): array
	{
		$items = [
			['id' => 'season_winner', 'label' => (string) ($copy['legend_winner'] ?? 'Season winner')],
			['id' => 'season_top', 'label' => (string) ($copy['legend_top'] ?? 'Season top placement')],
			['id' => 'season_participant', 'label' => (string) ($copy['legend_participant'] ?? 'Season participant')],
			['id' => 'season_feat', 'label' => (string) ($copy['legend_feat'] ?? 'Season feat')],
		];
		foreach ($items as &$item) {
			$badge = [
				'id'      => $item['id'],
				'kind'    => 'season',
				'label'   => $item['label'],
				'tooltip' => $item['label'],
			];
			$item['badges'] = [$badge];
		}
		unset($item);

		return $items;
	}

	/**
	 * @return array{userId: int, username: string, hiveAccount: string, registerTime: int}
	 */
	private function resolveOwner(int $universe, int $viewerId, ?int $targetUserId, ?string $targetHive): array
	{
		$userId = $targetUserId !== null && $targetUserId > 0 ? $targetUserId : 0;
		$hive = strtolower(trim((string) $targetHive));

		if ($userId <= 0 && $hive === '') {
			$userId = $viewerId;
		}

		$row = null;
		if ($userId > 0) {
			$row = ($this->userById)($userId, $universe);
		}
		if ($row === null && $hive !== '') {
			$row = ($this->userByHive)($hive, $universe);
		}

		if (is_array($row)) {
			return [
				'userId'        => (int) ($row['id'] ?? 0),
				'username'      => (string) ($row['username'] ?? ''),
				'hiveAccount'   => PrestigeBadgeService::linkedHiveAccount($row),
				'registerTime'  => (int) ($row['register_time'] ?? 0),
			];
		}

		return [
			'userId'       => 0,
			'username'     => $hive !== '' ? '@'.$hive : '',
			'hiveAccount'  => HiveUtil::isAccountValid($hive) ? $hive : '',
			'registerTime' => 0,
		];
	}

	/**
	 * @param array<string, mixed> $grant
	 * @param array<string, string> $copy
	 * @return array<string, mixed>
	 */
	private function presentMedal(array $grant, array $copy): array
	{
		$tier = (string) ($grant['tier'] ?? '');
		$seasonId = (string) ($grant['season_id'] ?? '');
		$rank = $grant['rank'] ?? null;
		$badge = SeasonSbtBadgeMapper::badgeFromGrant($grant, [
			'winner'      => (string) ($copy['badge_winner'] ?? 'Uni3 %s Winner'),
			'top'         => (string) ($copy['badge_top'] ?? 'Uni3 %s Top placement'),
			'participant' => (string) ($copy['badge_participant'] ?? 'Uni3 %s Participant'),
			'feat'        => (string) ($copy['badge_feat'] ?? 'Uni3 %s Feat'),
		]);

		$tierLabel = match ($tier) {
			SeasonSbtGrantStore::TIER_RANK => (string) ($copy['tier_rank'] ?? 'Rank'),
			SeasonSbtGrantStore::TIER_FEAT => (string) ($copy['tier_feat'] ?? 'Feat'),
			default => (string) ($copy['tier_participant'] ?? 'Participant'),
		};

		return [
			'seasonId'     => $seasonId,
			'seasonLabel'  => SeasonSbtBadgeMapper::seasonLabel($seasonId),
			'tier'         => $tier,
			'tierLabel'    => $tierLabel,
			'rank'         => $rank !== null ? (int) $rank : null,
			'featId'       => (string) ($grant['feat_id'] ?? ''),
			'hiveUser'     => (string) ($grant['hive_user'] ?? ''),
			'gameName'     => (string) ($grant['game_name'] ?? ''),
			'imageUrl'     => (string) ($grant['image_url'] ?? ''),
			'recapUrl'     => (string) ($grant['recap_url'] ?? ''),
			'nftNamespace' => (string) ($grant['nft_namespace'] ?? ''),
			'nftSymbol'    => (string) ($grant['nft_symbol'] ?? ''),
			'instanceId'   => (string) ($grant['instance_id'] ?? ''),
			'trxId'        => (string) ($grant['trx_id'] ?? ''),
			'retroactive'  => !empty($grant['retroactive']),
			'issuedAt'     => (int) ($grant['issued_at'] ?? 0),
			'title'        => $badge['label'] ?? SeasonSbtBadgeMapper::seasonLabel($seasonId),
			'badgeId'      => $badge['id'] ?? 'season_participant',
			'badges'       => [$badge ?? [
				'id'      => 'season_participant',
				'kind'    => 'season',
				'label'   => SeasonSbtBadgeMapper::seasonLabel($seasonId),
				'tooltip' => SeasonSbtBadgeMapper::seasonLabel($seasonId),
			]],
			'verified'     => null,
		];
	}

	/**
	 * @param array<string, mixed> $grant
	 */
	private function softVerify(array $grant): ?bool
	{
		if ($this->tracker === null) {
			return null;
		}
		$namespace = (string) ($grant['nft_namespace'] ?? '');
		$symbol = (string) ($grant['nft_symbol'] ?? '');
		$holder = (string) ($grant['hive_user'] ?? '');
		if ($namespace === '' || $symbol === '' || $holder === '') {
			return null;
		}

		$rows = $this->tracker->nftsForHolder($namespace, $symbol, $holder);
		if ($rows === []) {
			// Soft-fail: empty may mean API down or truly none — only claim false when
			// the client returned a decoded empty list after a successful fetch.
			// NftTrackerClient returns [] for both miss and empty; treat as unchecked.
			return null;
		}

		$instanceId = (string) ($grant['instance_id'] ?? '');
		if ($instanceId === '') {
			return true;
		}
		foreach ($rows as $row) {
			$id = (string) ($row['id'] ?? $row['instance_id'] ?? $row['nft_id'] ?? '');
			if ($id !== '' && $id === $instanceId) {
				return true;
			}
		}

		return false;
	}
}
