<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\Language;
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
		$seoBase = PublicSeo::canonicalBasePath();
		$lang = $LNG->getLanguage();

		if ($payload === null) {
			$this->seoAllowIndex = false;
			$this->assign([
				'reportFound' => false,
				'ctaHref'     => PublicSeo::signupCtaUrl('battlereport'),
				'hallHref'    => PublicSeo::canonicalUrl($seoBase, 'battleHall', $lang),
			]);
			$this->display('page.battle.default.tpl');
			return;
		}

		$hall = $payload['hall'];
		$attacker = (string) $hall['attacker'];
		$defender = (string) $hall['defender'];
		$unitsCompact = PublicBattleReportService::formatCompactUnits($hall['units']);
		$when = _date($LNG['php_tdformat'], (int) $hall['time']);
		$gameName = (string) Config::get()->game_name;

		$this->seoDocumentTitle = PublicSeo::battleReportDocumentTitle(
			$attacker,
			$defender,
			$unitsCompact,
			$gameName,
			$LNG
		);
		$this->seoMetaDescription = PublicSeo::battleReportMetaDescription(
			$attacker,
			$defender,
			$unitsCompact,
			$when,
			$gameName,
			$LNG
		);
		$this->seoCanonicalUrl = PublicSeo::battleReportUrl($seoBase, (string) $hall['rid'], $lang);
		$this->setSeoMeta([
			'hreflangUrls' => PublicSeo::hreflangUrls(
				$seoBase,
				'battle',
				Language::getAllowedLangs(false),
				'en',
				['rid' => (string) $hall['rid']]
			),
			'pageHeading' => $this->seoDocumentTitle,
		]);

		$this->assign([
			'reportFound'  => true,
			'hall'         => $hall,
			'combat'       => $payload['combat'],
			'attacker'     => $attacker,
			'defender'     => $defender,
			'unitsCompact' => $unitsCompact,
			'battleTime'   => $when,
			'ctaHref'      => PublicSeo::signupCtaUrl('battlereport'),
			'hallHref'     => PublicSeo::canonicalUrl($seoBase, 'battleHall', $lang),
		]);
		$this->display('page.battle.default.tpl');
	}
}
