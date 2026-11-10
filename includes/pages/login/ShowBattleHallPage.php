<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\BattleHallService;
use HiveNova\Core\Config;
use HiveNova\Core\PublicSeo;
use HiveNova\Core\Universe;

/**
 * Public Battle Hall — indexable Hall of Fame with signup CTAs (#645).
 */
class ShowBattleHallPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $LNG;

		$universe = (int) Universe::current();
		$uniConfig = Config::get($universe);
		$uniName = (string) ($uniConfig->uni_name ?? ('Universe '.$universe));
		$gameName = (string) Config::get()->game_name;
		$seoBase = PublicSeo::canonicalBasePath();
		$lang = $LNG->getLanguage();

		$hallRaw = (new BattleHallService())->listTopBattles($universe, 100);

		$hallList = [];
		foreach ($hallRaw as $hallRow) {
			$hallList[] = [
				'result'    => $hallRow['result'],
				'time'      => _date($LNG['php_tdformat'], $hallRow['time']),
				'units'     => $hallRow['units'],
				'rid'       => $hallRow['rid'],
				'attacker'  => $hallRow['attacker'],
				'defender'  => $hallRow['defender'],
				'reportUrl' => PublicSeo::battleReportUrl($seoBase, (string) $hallRow['rid'], $lang),
			];
		}

		$this->seoDocumentTitle = PublicSeo::battleHallDocumentTitle($uniName, $gameName, $LNG);
		$this->seoMetaDescription = PublicSeo::battleHallMetaDescription($uniName, $gameName, $LNG);
		$this->setSeoMeta([
			'pageHeading' => $this->seoDocumentTitle,
		]);

		$this->assign([
			'universeSelect' => $this->getUniverseSelector(),
			'hallList'       => $hallList,
			'ctaHref'        => PublicSeo::signupCtaUrl('battlehall'),
		]);
		$this->display('page.battleHall.default.tpl');
	}
}
