<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\DatabaseSeasonGrantBadgeSource;
use HiveNova\Core\DatabaseSeasonSbtGrantStore;
use HiveNova\Core\HTTP;
use HiveNova\Core\NftTrackerClient;
use HiveNova\Core\PrestigeBadgeService;
use HiveNova\Core\SeasonSbtInventoryService;
use HiveNova\Core\Universe;

/**
 * Public season medal inventory (#630). Login required via game.php; cosmetics only.
 */
class ShowMedalsPage extends AbstractGamePage
{
	public static $requireModule = 0;

	protected $disableEcoSystem = true;

	public function __construct()
	{
		parent::__construct();
	}

	public function show(): void
	{
		global $USER, $LNG;

		$targetId = HTTP::_GP('id', 0);
		$hive = trim((string) HTTP::_GP('hive', ''));
		if ($hive === '') {
			$hive = trim((string) HTTP::_GP('hive_account', ''));
		}

		$store = new DatabaseSeasonSbtGrantStore();
		$badgeCopy = [
			'winner'      => (string) ($LNG['md_badge_winner'] ?? 'Uni3 %s Winner'),
			'top'         => (string) ($LNG['md_badge_top'] ?? 'Uni3 %s Top placement'),
			'participant' => (string) ($LNG['md_badge_participant'] ?? 'Uni3 %s Participant'),
			'feat'        => (string) ($LNG['md_badge_feat'] ?? 'Uni3 %s Feat'),
		];
		$service = new SeasonSbtInventoryService(
			$store,
			new NftTrackerClient(),
			null,
			null,
			true
		);

		$months = $LNG['months'] ?? [];
		$view = $service->inventory(
			Universe::current(),
			(int) $USER['id'],
			$targetId > 0 ? $targetId : null,
			$hive !== '' ? $hive : null,
			[
				'empty_not_found'    => (string) ($LNG['md_empty_not_found'] ?? 'Player not found.'),
				'empty_no_hive'      => (string) ($LNG['md_empty_no_hive'] ?? 'This commander has no linked Hive account yet.'),
				'empty_none'         => (string) ($LNG['md_empty_none'] ?? 'No season medals yet.'),
				'badge_winner'       => $badgeCopy['winner'],
				'badge_top'          => $badgeCopy['top'],
				'badge_participant'  => $badgeCopy['participant'],
				'badge_feat'         => $badgeCopy['feat'],
				'legend_winner'      => (string) ($LNG['md_legend_winner'] ?? 'Season winner'),
				'legend_top'         => (string) ($LNG['md_legend_top'] ?? 'Season top placement'),
				'legend_participant' => (string) ($LNG['md_legend_participant'] ?? 'Season participant'),
				'legend_feat'        => (string) ($LNG['md_legend_feat'] ?? 'Season feat'),
				'tier_rank'          => (string) ($LNG['md_tier_rank'] ?? 'Rank'),
				'tier_feat'          => (string) ($LNG['md_tier_feat'] ?? 'Feat'),
				'tier_participant'   => (string) ($LNG['md_tier_participant'] ?? 'Participant'),
			],
			is_array($months) ? $months : []
		);

		$prestige = (new PrestigeBadgeService(
			null,
			null,
			new DatabaseSeasonGrantBadgeSource($store, $badgeCopy)
		))->forProfile([
			'id'            => $view['owner']['userId'],
			'username'      => $view['owner']['username'],
			'hive_account'  => $view['owner']['hiveAccount'],
			'register_time' => $view['owner']['registerTime'],
		], [
			'member_since' => (string) ($LNG['pl_member_since'] ?? 'Member since %s'),
			'hive_linked'  => (string) ($LNG['pl_hive_linked'] ?? 'Hive: @%s'),
			'hp_range'     => (string) ($LNG['pl_prestige_hp_range'] ?? '%1$s — %2$s–%3$s HP'),
			'hp_open'      => (string) ($LNG['pl_prestige_hp_open'] ?? '%1$s — %2$s+ HP'),
			'pizza_open'   => (string) ($LNG['pl_prestige_pizza_open'] ?? '%1$s — %2$s+ PIZZA staked'),
			'months'       => is_array($months) ? $months : [],
		]);

		$this->assign([
			'medalsView' => $view,
			'prestige'   => $prestige,
			'lookupId'   => $targetId,
			'lookupHive' => $hive,
		]);

		$this->display('page.medals.default.tpl');
	}
}
