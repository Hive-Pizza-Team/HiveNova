<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\BattleHallService;
use HiveNova\Core\Config;
use HiveNova\Core\PublicBattleSeo;
use HiveNova\Core\Universe;

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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

		$universeId = (int) Universe::current();
		$universeName = (string) Config::get($universeId)->uni_name;
		$gameName = (string) Config::get()->game_name;
		$basePath = $this->publicBasePath();

		$top = (new BattleHallService())->listTopBattles($universeId);

		$hallList = [];
		foreach ($top as $hallRow) {
			$rid = (string) $hallRow['rid'];
			$hallList[] = [
				'result'   => $hallRow['result'],
				'time'     => _date($LNG['php_tdformat'], $hallRow['time']),
				'units'    => $hallRow['units'],
				'rid'      => $rid,
				'attacker' => $hallRow['attacker'],
				'defender' => $hallRow['defender'],
				'reportUrl'=> PublicBattleSeo::reportUrl($rid, $LNG->getLanguage()),
			];
		}

		$this->overrideSeo([
			'documentTitle'   => PublicBattleSeo::hallDocumentTitle($gameName, $universeName, $LNG),
			'metaDescription' => PublicBattleSeo::hallMetaDescription($gameName, $universeName, $LNG),
			'pageHeading'     => $LNG['siteTitleBattleHall'] ?? 'Battle Hall',
			'ogImageUrl'      => PublicBattleSeo::ogImageUrl($basePath),
			'ogImageWidth'    => PublicBattleSeo::OG_IMAGE_WIDTH,
			'ogImageHeight'   => PublicBattleSeo::OG_IMAGE_HEIGHT,
		]);

		$this->assign([
			'universeSelect' => $this->getUniverseSelector(),
			'hallList'       => $hallList,
			'signupCtaUrl'   => PublicBattleSeo::signupCtaUrl(PublicBattleSeo::SRC_HALL),
			'signupCtaLabel' => $LNG['battleHall_cta'] ?? 'Play Moon free',
			'signupCtaBlurb' => $LNG['battleHall_cta_blurb'] ?? '',
			'universeName'   => $universeName,
		]);
		$this->display('page.battleHall.default.tpl');
	}
}
