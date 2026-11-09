<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Config;
use HiveNova\Core\HTTP;
use HiveNova\Core\Language;
use HiveNova\Core\PublicBattleReportService;
use HiveNova\Core\PublicBattleSeo;
use HiveNova\Core\PublicSeo;

/**
 * Public, indexable battle report (login-side). Only TOPKB / Battle Hall fights.
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
		$report = (new PublicBattleReportService())->findPublicReport($rid);

		if ($report === null) {
			$this->seoAllowIndex = false;
			$this->printMessage($LNG['battleReport_not_found'] ?? 'Battle report not found.');
			return;
		}

		$LNG->includeData(['FLEET', 'TECH']);

		$gameName = (string) Config::get()->game_name;
		$lang = $LNG->getLanguage();
		$languages = Language::getAllowedLangs(false);
		$formattedTime = _date($LNG['php_tdformat'], $report['time']);

		$combatReport = $report['combatReport'];
		$combatReport['time'] = $formattedTime;

		$documentTitle = PublicBattleSeo::reportDocumentTitle(
			$report['attacker'],
			$report['defender'],
			$report['units'],
			$gameName,
			$LNG
		);
		$metaDescription = PublicBattleSeo::reportMetaDescription(
			$report['attacker'],
			$report['defender'],
			$report['result'],
			$report['units'],
			$formattedTime,
			is_array($combatReport['debris'] ?? null) ? $combatReport['debris'] : [],
			$gameName,
			$LNG
		);

		$basePath = $this->publicBasePath();
		$canonicalUrl = PublicBattleSeo::reportCanonicalUrl($basePath, $report['rid'], $lang);
		$hreflangUrls = PublicBattleSeo::reportHreflangUrls($basePath, $report['rid'], $languages);

		$this->overrideSeo([
			'documentTitle'   => $documentTitle,
			'metaDescription' => $metaDescription,
			'canonicalUrl'    => $canonicalUrl,
			'hreflangUrls'    => $hreflangUrls,
			'pageHeading'     => $documentTitle,
			'robotsContent'   => PublicSeo::robotsContent('battle', true),
			'ogImageUrl'      => PublicBattleSeo::ogImageUrl($basePath),
			'ogImageWidth'    => PublicBattleSeo::OG_IMAGE_WIDTH,
			'ogImageHeight'   => PublicBattleSeo::OG_IMAGE_HEIGHT,
			'seoPage'         => 'battle',
		]);

		$this->assign([
			'Raport'         => $combatReport,
			'Info'           => [$report['attacker'], $report['defender']],
			'hallUnits'      => $report['units'],
			'reportRid'      => $report['rid'],
			'hallListUrl'    => PublicSeo::canonicalUrl($basePath, 'battleHall', $lang),
			'signupCtaUrl'   => PublicBattleSeo::signupCtaUrl(PublicBattleSeo::SRC_REPORT),
			'signupCtaLabel' => $LNG['battleHall_cta'] ?? 'Play Moon free',
			'signupCtaBlurb' => $LNG['battleReport_cta_blurb'] ?? ($LNG['battleHall_cta_blurb'] ?? ''),
			'backToHallLabel'=> $LNG['battleReport_back'] ?? 'Back to Battle Hall',
		]);

		$this->display('page.battle.default.tpl');
	}
}
