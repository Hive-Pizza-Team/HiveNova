<?php

namespace HiveNova\Core;

use Throwable;

/**
 * Cosmetic profile prestige: Member since, PeakD Hive Power bees, PIZZA stake ranks.
 *
 * Stake badges require an existing linked hive_account. Lookups fail soft: a down
 * Hive or Hive-Engine API omits that badge and still leaves Member since.
 * Season winner glyphs wait on season_sbt_grants (#631 B2); the grant source
 * hook is empty until that table exists.
 *
 * Hive Power bands (min inclusive, max exclusive except Queen Bee):
 * worker 2500, honey 5000, bumblebee 15000, yellowjacket 50000,
 * giant hornet 200000, killer bee 1000000, queen 5000000+.
 *
 * PIZZA stake bands (staked balance, highest tier only):
 * 20, 200, 500, 1000, 3000, 5000, 10000, 25000, 50000, 100000.
 */
class PrestigeBadgeService
{
	public const NAMEPLATE_CAP = 4;
	public const PIZZA_SYMBOL = 'PIZZA';

	/** @var list<array{id: string, label: string, min: int, max: int|null}> */
	public const HIVE_POWER_TIERS = [
		['id' => 'worker_bee', 'label' => 'Worker Bee', 'min' => 2500, 'max' => 5000],
		['id' => 'honey_bee', 'label' => 'Honey Bee', 'min' => 5000, 'max' => 15000],
		['id' => 'bumblebee', 'label' => 'Bumblebee', 'min' => 15000, 'max' => 50000],
		['id' => 'yellowjacket', 'label' => 'Yellowjacket', 'min' => 50000, 'max' => 200000],
		['id' => 'giant_hornet', 'label' => 'Giant Hornet', 'min' => 200000, 'max' => 1000000],
		['id' => 'killer_bee', 'label' => 'Killer Bee', 'min' => 1000000, 'max' => 5000000],
		['id' => 'queen_bee', 'label' => 'Queen Bee', 'min' => 5000000, 'max' => null],
	];

	/** @var list<array{id: string, label: string, min: int}> */
	public const PIZZA_STAKE_TIERS = [
		['id' => 'pizza_driver_l1', 'label' => 'Delivery Driver Lv 1', 'min' => 20],
		['id' => 'pizza_driver_l2', 'label' => 'Level Two Driver', 'min' => 200],
		['id' => 'pizza_senior', 'label' => 'Senior Delivery Driver', 'min' => 500],
		['id' => 'pizza_zupervisor', 'label' => 'Zupervisor', 'min' => 1000],
		['id' => 'pizza_shift_manager', 'label' => 'Shift Manager', 'min' => 3000],
		['id' => 'pizza_champions', 'label' => 'Champions Club', 'min' => 5000],
		['id' => 'pizza_baron', 'label' => 'Za Baron', 'min' => 10000],
		['id' => 'pizza_big_cheese', 'label' => 'The Big Cheese', 'min' => 25000],
		['id' => 'pizza_50k', 'label' => 'Pizza Power 50k', 'min' => 50000],
		['id' => 'pizza_grand_barony', 'label' => 'The Grand Barony', 'min' => 100000],
	];

	/** @var list<string> */
	private const FALLBACK_MONTHS = [
		'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
	];

	private readonly HiveEngineClient $engine;

	/** @var callable(string, string): mixed */
	private $hiveRpc;

	private readonly SeasonGrantBadgeSource $seasonGrants;

	public function __construct(
		?HiveEngineClient $engine = null,
		?callable $hiveRpc = null,
		?SeasonGrantBadgeSource $seasonGrants = null,
		private readonly ?string $cacheDir = null,
	) {
		$this->engine = $engine ?? new HiveEngineClient();
		$this->hiveRpc = $hiveRpc ?? static function (string $method, string $params): mixed {
			return HiveUtil::rpcCall($method, $params, 1);
		};
		$this->seasonGrants = $seasonGrants ?? new NullSeasonGrantBadgeSource();
	}

	/**
	 * Highest PeakD bee at this Hive Power, or null below 2,500 HP.
	 *
	 * @return array{id: string, label: string, min: int, max: int|null}|null
	 */
	public static function hivePowerTier(float $hivePower): ?array
	{
		if (!is_finite($hivePower)) {
			return null;
		}

		$hivePower = round($hivePower, 3);
		$match = null;
		foreach (self::HIVE_POWER_TIERS as $tier) {
			if ($hivePower < $tier['min']) {
				break;
			}
			if ($tier['max'] !== null && $hivePower >= $tier['max']) {
				continue;
			}
			$match = $tier;
		}

		return $match;
	}

	/**
	 * Highest Discord staker rank for staked PIZZA, or null below 20.
	 *
	 * @return array{id: string, label: string, min: int}|null
	 */
	public static function pizzaStakeTier(float $stake): ?array
	{
		if (!is_finite($stake)) {
			return null;
		}

		$stake = round($stake, 3);
		$match = null;
		foreach (self::PIZZA_STAKE_TIERS as $tier) {
			if ($stake < $tier['min']) {
				break;
			}
			$match = $tier;
		}

		return $match;
	}

	/**
	 * @param list<string> $monthNames Localized month abbreviations, index 0 = January
	 */
	public static function memberSinceLabel(int $registerTime, array $monthNames = []): string
	{
		if ($registerTime <= 0) {
			return '';
		}

		$monthIndex = (int) gmdate('n', $registerTime) - 1;
		$year = gmdate('Y', $registerTime);
		$month = $monthNames[$monthIndex] ?? '';
		if (!is_string($month) || $month === '') {
			$month = self::FALLBACK_MONTHS[$monthIndex] ?? gmdate('M', $registerTime);
		}

		return $month.' '.$year;
	}

	public static function linkedHiveAccount(array $user): string
	{
		$account = strtolower(trim((string) ($user['hive_account'] ?? '')));
		if ($account === '' || !HiveUtil::isAccountValid($account)) {
			return '';
		}

		return $account;
	}

	/**
	 * "123.456789 VESTS" / "1000.000 HIVE" / a bare number.
	 */
	public static function parseChainAmount(mixed $value): ?float
	{
		if (is_int($value) || is_float($value)) {
			$number = (float) $value;

			return is_finite($number) ? $number : null;
		}
		if (!is_string($value) || !preg_match('/^\s*(-?\d+(?:\.\d+)?)/', $value, $match)) {
			return null;
		}

		return (float) $match[1];
	}

	/**
	 * Owned Hive Power from vesting_shares and dynamic global properties.
	 * Liquid HIVE and delegations are ignored.
	 *
	 * @param array<string, mixed> $account
	 * @param array<string, mixed> $props
	 */
	public static function hivePowerFromAccount(array $account, array $props): ?float
	{
		$vests = self::parseChainAmount($account['vesting_shares'] ?? null);
		$totalVests = self::parseChainAmount($props['total_vesting_shares'] ?? null);
		$fund = self::parseChainAmount($props['total_vesting_fund_hive'] ?? ($props['total_vesting_fund_steem'] ?? null));
		if ($vests === null || $totalVests === null || $fund === null || $totalVests <= 0 || $vests < 0 || $fund < 0) {
			return null;
		}

		$hivePower = ($vests / $totalVests) * $fund;
		if (!is_finite($hivePower)) {
			return null;
		}

		return round($hivePower, 3);
	}

	public static function formatAmount(float $amount): string
	{
		$rounded = round($amount, 3);
		if (abs($rounded - round($rounded)) < 0.0000001) {
			return number_format($rounded, 0, '.', ',');
		}

		return number_format($rounded, 3, '.', ',');
	}

	/**
	 * @param list<array<string, mixed>> $badges
	 * @return list<array{id: string, kind: string, label: string, tooltip: string}>
	 */
	public static function capBadges(array $badges, int $cap = self::NAMEPLATE_CAP): array
	{
		$cap = max(0, $cap);
		$kept = [];
		foreach ($badges as $badge) {
			if (count($kept) >= $cap) {
				break;
			}
			if (!is_array($badge)) {
				continue;
			}
			$normalized = self::normalizeBadge($badge);
			if ($normalized !== null) {
				$kept[] = $normalized;
			}
		}

		return $kept;
	}

	/**
	 * @param array<string, mixed> $badge
	 * @return array{id: string, kind: string, label: string, tooltip: string}|null
	 */
	public static function normalizeBadge(array $badge): ?array
	{
		$id = trim((string) ($badge['id'] ?? ''));
		$label = trim((string) ($badge['label'] ?? ''));
		if ($id === '' || $label === '') {
			return null;
		}

		$kind = trim((string) ($badge['kind'] ?? 'season'));
		if ($kind === '') {
			$kind = 'season';
		}
		$tooltip = trim((string) ($badge['tooltip'] ?? ''));
		if ($tooltip === '') {
			$tooltip = $label;
		}

		return [
			'id'      => $id,
			'kind'    => $kind,
			'label'   => $label,
			'tooltip' => $tooltip,
		];
	}

	/**
	 * Profile block. Member since is independent of the Hive link.
	 *
	 * @param array<string, mixed> $user
	 * @param array<string, mixed> $copy member_since, hive_linked, hp_range, hp_open, pizza_open, months
	 * @return array{
	 *     memberSince: string,
	 *     memberSinceDate: string,
	 *     hiveAccount: string,
	 *     hiveLine: string,
	 *     badges: list<array{id: string, kind: string, label: string, tooltip: string}>
	 * }
	 */
	public function forProfile(array $user, array $copy = [], ?int $now = null): array
	{
		$months = $copy['months'] ?? [];
		if (!is_array($months)) {
			$months = [];
		}

		$memberSinceDate = self::memberSinceLabel((int) ($user['register_time'] ?? 0), $months);
		$memberSince = '';
		if ($memberSinceDate !== '') {
			$memberSince = self::fill(
				(string) ($copy['member_since'] ?? 'Member since %s'),
				[$memberSinceDate],
				'Member since %s'
			);
		}

		$hiveAccount = self::linkedHiveAccount($user);
		$hiveLine = '';
		if ($hiveAccount !== '') {
			$hiveLine = self::fill(
				(string) ($copy['hive_linked'] ?? 'Hive: @%s'),
				[$hiveAccount],
				'Hive: @%s'
			);
		}

		$badges = [];
		if ($hiveAccount !== '') {
			$metrics = $this->metrics($hiveAccount, $now);
			if ($metrics['hp'] !== null) {
				$tier = self::hivePowerTier($metrics['hp']);
				if ($tier !== null) {
					$badges[] = $this->hivePowerBadge($tier, $copy);
				}
			}
			if ($metrics['pizza'] !== null) {
				$tier = self::pizzaStakeTier($metrics['pizza']);
				if ($tier !== null) {
					$badges[] = $this->pizzaBadge($tier, $copy);
				}
			}
		}

		$userId = (int) ($user['id'] ?? 0);
		if ($userId > 0) {
			$badges = array_merge($badges, $this->seasonBadges($userId));
		}

		return [
			'memberSince'     => $memberSince,
			'memberSinceDate' => $memberSinceDate,
			'hiveAccount'     => $hiveAccount,
			'hiveLine'        => $hiveLine,
			'badges'          => self::capBadges($badges),
		];
	}

	/**
	 * @return array{hp: float|null, pizza: float|null}
	 */
	public function metrics(string $account, ?int $now = null): array
	{
		return PrestigeChainCache::resolve(
			$account,
			function () use ($account): array {
				return [
					'hp'    => $this->fetchHivePower($account),
					'pizza' => $this->fetchPizzaStake($account),
				];
			},
			$this->cacheDir,
			$now
		);
	}

	/**
	 * @param array{id: string, label: string, min: int, max: int|null} $tier
	 * @param array<string, mixed> $copy
	 * @return array{id: string, kind: string, label: string, tooltip: string}
	 */
	private function hivePowerBadge(array $tier, array $copy): array
	{
		$label = $tier['label'];
		$min = self::formatAmount((float) $tier['min']);
		if ($tier['max'] === null) {
			$tooltip = self::fill(
				(string) ($copy['hp_open'] ?? '%1$s — %2$s+ HP'),
				[$label, $min],
				'%1$s — %2$s+ HP'
			);
		} else {
			$tooltip = self::fill(
				(string) ($copy['hp_range'] ?? '%1$s — %2$s–%3$s HP'),
				[$label, $min, self::formatAmount((float) $tier['max'])],
				'%1$s — %2$s–%3$s HP'
			);
		}

		return [
			'id'      => $tier['id'],
			'kind'    => 'hive_power',
			'label'   => $label,
			'tooltip' => $tooltip,
		];
	}

	/**
	 * @param array{id: string, label: string, min: int} $tier
	 * @param array<string, mixed> $copy
	 * @return array{id: string, kind: string, label: string, tooltip: string}
	 */
	private function pizzaBadge(array $tier, array $copy): array
	{
		return [
			'id'      => $tier['id'],
			'kind'    => 'pizza_stake',
			'label'   => $tier['label'],
			'tooltip' => self::fill(
				(string) ($copy['pizza_open'] ?? '%1$s — %2$s+ PIZZA staked'),
				[$tier['label'], self::formatAmount((float) $tier['min'])],
				'%1$s — %2$s+ PIZZA staked'
			),
		];
	}

	/**
	 * @return list<array{id: string, kind: string, label: string, tooltip: string}>
	 */
	private function seasonBadges(int $userId): array
	{
		try {
			$rows = $this->seasonGrants->badgesForUser($userId);
		} catch (Throwable) {
			return [];
		}

		$badges = [];
		foreach ($rows as $row) {
			if (!is_array($row)) {
				continue;
			}
			$normalized = self::normalizeBadge($row);
			if ($normalized !== null) {
				$badges[] = $normalized;
			}
		}

		return $badges;
	}

	private function fetchHivePower(string $account): ?float
	{
		try {
			$params = json_encode([[$account]], JSON_UNESCAPED_SLASHES);
			$accounts = ($this->hiveRpc)('condenser_api.get_accounts', is_string($params) ? $params : '[]');
			if (!is_array($accounts) || HiveUtil::isRpcError($accounts)) {
				return null;
			}
			if ($accounts === [] || !isset($accounts[0]) || !is_array($accounts[0])) {
				return 0.0;
			}

			$props = ($this->hiveRpc)('condenser_api.get_dynamic_global_properties', '[]');
			if (!is_array($props) || HiveUtil::isRpcError($props)) {
				return null;
			}

			return self::hivePowerFromAccount($accounts[0], $props);
		} catch (Throwable) {
			return null;
		}
	}

	private function fetchPizzaStake(string $account): ?float
	{
		try {
			return $this->engine->tokenStake($account, self::PIZZA_SYMBOL);
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * @param list<float|int|string> $args
	 */
	private static function fill(string $format, array $args, string $fallback): string
	{
		try {
			$text = vsprintf($format, $args);
			if (is_string($text) && $text !== '') {
				return $text;
			}
		} catch (Throwable) {
			$text = false;
		}

		$text = vsprintf($fallback, $args);

		return is_string($text) ? $text : (string) ($args[0] ?? '');
	}
}
