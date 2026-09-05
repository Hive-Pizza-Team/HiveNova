<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\AuthLevel;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\ReferralStatsService;
use HiveNova\Core\Universe;

class ShowReferralsPage extends AbstractGamePage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $LNG, $USER;

		if (!AuthLevel::canViewReferralDashboard((int) ($USER['authlevel'] ?? 0))) {
			$this->printMessage($LNG['ref_stats_access_denied'], [[
				'label' => $LNG['sys_back'],
				'url'   => 'game.php?page=overview',
			]]);
			return;
		}

		$perPage = 50;
		$page = max(1, HTTP::_GP('p', 1));
		$offset = ($page - 1) * $perPage;
		$universe = (int) Universe::current();
		$config = Config::get($universe);
		$refMinPoints = (int) $config->ref_minpoints;

		$db = Database::get();
		$service = new ReferralStatsService();
		$summary = $service->getSummary($db, $universe, $refMinPoints);
		$totalReferrers = $service->countReferrers($db, $universe);
		$referrers = $service->getReferrerRows($db, $universe, $refMinPoints, $perPage, $offset);
		$recentRecruits = $service->getRecentRecruits($db, $universe, $refMinPoints, 25, 0);

		$pages = [];
		if ($totalReferrers > $perPage) {
			$numPages = (int) ceil($totalReferrers / $perPage);
			for ($i = 1; $i <= $numPages; $i++) {
				$pages[] = [
					'num'     => $i,
					'current' => ($i === $page),
					'url'     => 'game.php?page=referrals&p=' . $i,
				];
			}
		}

		$statusLabels = [
			ReferralStatsService::STATUS_PENDING => $LNG['ref_stats_status_pending'],
			ReferralStatsService::STATUS_READY   => $LNG['ref_stats_status_ready'],
			ReferralStatsService::STATUS_PAID    => $LNG['ref_stats_status_paid'],
		];

		foreach ($recentRecruits as &$recruit) {
			$recruit['register_time_fmt'] = _date(
				$LNG['php_tdformat'],
				(int) $recruit['register_time'],
				$USER['timezone']
			);
			$recruit['bonus_status_label'] = $statusLabels[$recruit['bonus_status']] ?? $recruit['bonus_status'];
		}
		unset($recruit);

		$this->assign([
			'summary'        => $summary,
			'referrers'      => $referrers,
			'recentRecruits' => $recentRecruits,
			'pages'          => $pages,
			'page'           => $page,
			'ref_active'     => (int) $config->ref_active === 1,
			'ref_minpoints'  => $refMinPoints,
			'universe'       => $universe,
		]);
		$this->display('page.referrals.default.tpl');
	}
}
