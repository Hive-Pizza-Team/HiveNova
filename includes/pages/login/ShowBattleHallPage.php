<?php

namespace HiveNova\Page\Login;

use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\PublicBattleHallService;
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

		$rid = trim((string) HTTP::_GP('raport', ''));
		if ($rid !== '') {
			$this->showPublicReport($rid);
			return;
		}

		$db = Database::get();

		$sql = "SELECT *, (
			SELECT DISTINCT
			IF(%%TOPKB_USERS%%.username = '', GROUP_CONCAT(%%USERS%%.username SEPARATOR ' & '), GROUP_CONCAT(%%TOPKB_USERS%%.username SEPARATOR ' & '))
			FROM %%TOPKB_USERS%%
			LEFT JOIN %%USERS%% ON uid = %%USERS%%.id
			WHERE %%TOPKB_USERS%%.`rid` = %%TOPKB%%.`rid` AND `role` = 1
		) as `attacker`,
		(
			SELECT DISTINCT
			IF(%%TOPKB_USERS%%.username = '', GROUP_CONCAT(%%USERS%%.username SEPARATOR ' & '), GROUP_CONCAT(%%TOPKB_USERS%%.username SEPARATOR ' & '))
			FROM %%TOPKB_USERS%% INNER JOIN %%USERS%% ON uid = id
			WHERE %%TOPKB_USERS%%.`rid` = %%TOPKB%%.`rid` AND `role` = 2
		) as `defender`
		FROM %%TOPKB%% WHERE `universe` = :universe ORDER BY units DESC LIMIT 100;";

		$hallRaw = $db->select($sql, array(
			':universe'	=> Universe::current(),
		));

		$hallRows = array();
		foreach($hallRaw as $hallRow) {
			$hallRows[]	= array(
				'result'	=> $hallRow['result'],
				'time'		=> _date($LNG['php_tdformat'], $hallRow['time']),
				'units'		=> $hallRow['units'],
				'rid'		=> $hallRow['rid'],
				'attacker'	=> $hallRow['attacker'],
				'defender'	=> $hallRow['defender'],
			);
		}

		$lang = (string) $LNG->getLanguage();
		$hallList = PublicBattleHallService::decorateHallList($hallRows, $lang);
		$universeSelect	= $this->getUniverseSelector();
		
		$this->assign(array(
			'universeSelect'	=> $universeSelect,
			'hallList'			=> $hallList,
			'registerCtaUrl'	=> PublicBattleHallService::registerCtaUrl($lang),
			'registerCtaLabel'	=> $LNG['bh_public_register_cta'],
			'registerCtaLead'	=> $LNG['bh_public_register_lead'],
		));
		$this->display('page.battleHall.default.tpl');
	}

	/**
	 * Indexable public combat report under the lobby host (not game.php).
	 */
	private function showPublicReport(string $rid): void
	{
		global $LNG;

		$db = Database::get();

		$sql = "SELECT 
			raport, time,
			(
				SELECT
				GROUP_CONCAT(username SEPARATOR ' & ') as attacker
				FROM %%USERS%%
				WHERE id IN (SELECT uid FROM %%TOPKB_USERS%% WHERE %%TOPKB_USERS%%.rid = %%RW%%.rid AND role = 1)
			) as attacker,
			(
				SELECT
				GROUP_CONCAT(username SEPARATOR ' & ') as defender
				FROM %%USERS%%
				WHERE id IN (SELECT uid FROM %%TOPKB_USERS%% WHERE %%TOPKB_USERS%%.rid = %%RW%%.rid AND role = 2)
			) as defender
			FROM %%RW%%
			WHERE rid = :reportID;";
		$reportData = $db->selectSingle($sql, array(
			':reportID'	=> $rid,
		));

		if (empty($reportData)) {
			HTTP::redirectTo('index.php?page=battleHall');
			return;
		}

		$combatReport = safe_unserialize($reportData['raport']);
		if (!is_array($combatReport)) {
			HTTP::redirectTo('index.php?page=battleHall');
			return;
		}

		$lang = (string) $LNG->getLanguage();
		$attacker = (string) ($reportData['attacker'] ?? '');
		$defender = (string) ($reportData['defender'] ?? '');
		$vsLabel = trim($attacker.' VS '.$defender);
		if ($vsLabel === 'VS') {
			$vsLabel = $LNG['siteTitleBattleHall'];
		}
		$pageTitle = sprintf($LNG['bh_public_report_title'], $vsLabel);

		$rawTime = (int) ($combatReport['time'] ?? $reportData['time'] ?? 0);
		$combatReport['time'] = _date($LNG['php_tdformat'], $rawTime);

		$this->assign(array(
			'Raport'           => $combatReport,
			'Info'             => array($attacker, $defender),
			'pageTitle'        => $pageTitle,
			'registerCtaUrl'   => PublicBattleHallService::registerCtaUrl($lang),
			'registerCtaLabel' => $LNG['bh_public_register_cta'],
			'registerCtaLead'  => $LNG['bh_public_register_lead'],
			'hallIndexUrl'     => 'index.php?page=battleHall'.($lang !== '' && $lang !== 'en' ? '&lang='.rawurlencode($lang) : ''),
			'hallIndexLabel'   => $LNG['siteTitleBattleHall'],
			'fullReportUrl'    => 'game.php?page=raport&mode=battlehall&raport='.rawurlencode($rid),
			'fullReportLabel'  => $LNG['bh_public_full_report'],
		));
		$this->display('page.battleHall.report.tpl');
	}
}
