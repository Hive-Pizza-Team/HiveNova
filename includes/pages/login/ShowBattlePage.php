<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\PublicBattleReportService;
use HiveNova\Core\PublicSeo;

/**
 * Public (login) battle report — indexable share/CTA surface for Battle Hall (#645).
 */
class ShowBattlePage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $LNG;

		$rid = trim((string) HTTP::_GP('rid', ''));
		$service = new PublicBattleReportService();
		$payload = $rid !== '' ? $service->loadPublicReport($rid) : null;

		if ($payload === null) {
			$this->seoAllowIndex = false;
			$this->assign([
				'reportFound' => false,
				'ctaHref'     => 'index.php?page=register&ref=1&src=battlereport',
			]);
			$this->display('page.battle.default.tpl');
			return;
		}

		$hall = $payload['hall'];
		$attacker = (string) $hall['attacker'];
		$defender = (string) $hall['defender'];
		$unitsCompact = PublicBattleReportService::formatCompactUnits($hall['units']);
		$when = _date($LNG['php_tdformat'], (int) $hall['time']);

		$titleTpl = (string) ($LNG['metaTitleBattleReport'] ?? '%s vs %s: %s units | %s Battle Hall');
		$gameName = (string) Config::get()->game_name;
		$attackerLabel = $attacker !== '' ? $attacker : '?';
		$defenderLabel = $defender !== '' ? $defender : '?';
		$this->seoDocumentTitle = sprintf($titleTpl, $attackerLabel, $defenderLabel, $unitsCompact, $gameName);
		$descTpl = (string) ($LNG['metaDescriptionBattleReport'] ?? 'Battle report: %s vs %s (%s units) on %s. Play free on the frontier.');
		$this->seoMetaDescription = sprintf($descTpl, $attackerLabel, $defenderLabel, $unitsCompact, $gameName);

		$basePath = PROTOCOL.HTTP_HOST.HTTP_BASE;
		$baseCanonical = PublicSeo::canonicalUrl($basePath, 'battle', $LNG->getLanguage());
		$sep = str_contains($baseCanonical, '?') ? '&' : '?';
		$this->seoCanonicalUrl = $baseCanonical.$sep.'rid='.rawurlencode($hall['rid']);

		$this->assign([
			'reportFound'  => true,
			'hall'         => $hall,
			'combat'       => $payload['combat'],
			'attacker'     => $attacker,
			'defender'     => $defender,
			'unitsCompact' => $unitsCompact,
			'battleTime'   => $when,
			'ctaHref'      => 'index.php?page=register&ref=1&src=battlereport',
			'hallHref'     => 'index.php?page=battleHall',
		]);
		$this->display('page.battle.default.tpl');
	}
}
