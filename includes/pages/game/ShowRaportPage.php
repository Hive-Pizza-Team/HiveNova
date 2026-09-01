<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\BattleShareComposer;
use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;

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

class ShowRaportPage extends AbstractGamePage
{
	public static $requireModule = 0;
	
	protected $disableEcoSystem = true;

	function __construct() 
	{
		parent::__construct();
	}
	
	private function resolveParticipantNames(array $combatReport, string $idList): string
	{
		if ($idList === '') {
			return '';
		}

		$names = [];
		foreach (explode(',', $idList) as $rawId) {
			$id = (int) trim($rawId);
			if ($id <= 0) {
				continue;
			}
			if (!empty($combatReport['players'][$id]['name'])) {
				$names[] = (string) $combatReport['players'][$id]['name'];
			}
		}

		return implode(' & ', $names);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function battleShareAssignVars(
		array $combatReport,
		string $raportId,
		string $attackerName,
		string $defenderName,
		string $formattedTime,
		int $rawTime,
	): array {
		global $USER;

		$shareContext = (new BattleShareComposer())->compose(
			$combatReport + ['time' => $rawTime],
			$raportId,
			(int) $USER['id'],
			(string) ($USER['hive_account'] ?? ''),
			(int) Config::get()->ref_active === 1,
			PROTOCOL . HTTP_HOST . HTTP_ROOT,
			$attackerName,
			$defenderName,
			$formattedTime,
			$this->battleShareLabels()
		);

		return [
			'canShareToHive' => $shareContext['canShare'],
			'shareDraft' => $shareContext['draft'],
			'shareDraftJson' => $shareContext['draft'] !== null
				? json_encode($shareContext['draft'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)
				: '',
			'suggestedCommunities' => $shareContext['suggestedCommunities'],
			'suggestedCommunitiesJson' => json_encode(
				$shareContext['suggestedCommunities'],
				JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
			),
			'hiveAccount' => (string) ($USER['hive_account'] ?? ''),
		];
	}

	private function battleShareLabels(): array
	{
		global $LNG;

		$gameName = trim((string) Config::get()->game_name);
		if ($gameName === '') {
			$gameName = 'Game';
		}

		return [
			'result_attacker' => $LNG['sys_attacker_won'],
			'result_defender' => $LNG['sys_defender_won'],
			'result_draw'     => $LNG['sys_both_won'],
			'result_label'    => $LNG['battle_share_result_label'],
			'time_label'      => $LNG['sys_br_time'],
			'attacker_lost'   => $LNG['sys_attacker_lostunits'],
			'defender_lost'   => $LNG['sys_defender_lostunits'],
			'debris'          => $LNG['debree_field_1'],
			'steal'           => $LNG['sys_stealed_ressources'],
			'vs'              => $LNG['battle_share_vs'],
			'game_name'       => $gameName,
			'title_format'    => $LNG['battle_share_title'],
			'cta'             => sprintf($LNG['battle_share_cta'], $gameName),
			'footer'          => sprintf($LNG['battle_share_footer'], $gameName),
			'resource_901'    => $LNG['tech'][901],
			'resource_902'    => $LNG['tech'][902],
			'resource_903'    => $LNG['tech'][903],
		];
	}

	private function isStealUnprofitable(array $combatReport): bool
	{
		if ($combatReport['result'] !== 'a') return false;
		if (!isset($combatReport['fuel'], $combatReport['steal'])) return false;
		if ($combatReport['fuel'] <= 0) return false;
		return array_sum($combatReport['steal']) < $combatReport['fuel'];
	}

	private function BCWrapperPreRev2321($combatReport)
	{
		if(isset($combatReport['moon']['desfail']))
		{
			$combatReport['moon']	= array(
				'moonName'				=> $combatReport['moon']['name'],
				'moonChance'			=> $combatReport['moon']['chance'],
				'moonDestroySuccess'	=> !$combatReport['moon']['desfail'],
				'fleetDestroyChance'	=> $combatReport['moon']['chance2'],
				'fleetDestroySuccess'	=> !$combatReport['moon']['fleetfail']
			);			
		}
		elseif(isset($combatReport['moon'][0]))
		{
			$combatReport['moon']	= array(
				'moonName'				=> $combatReport['moon'][1],
				'moonChance'			=> $combatReport['moon'][0],
				'moonDestroySuccess'	=> !$combatReport['moon'][2],
				'fleetDestroyChance'	=> $combatReport['moon'][3],
				'fleetDestroySuccess'	=> !$combatReport['moon'][4]
			);			
		}
		
		if(isset($combatReport['simu']))
		{
			$combatReport['additionalInfo'] = $combatReport['simu'];
		}
		
		if(isset($combatReport['debris'][0]))
		{
            $combatReport['debris'] = array(
                901	=> $combatReport['debris'][0],
                902	=> $combatReport['debris'][1]
            );
		}
		
		if (!empty($combatReport['steal']['metal']))
		{
			$combatReport['steal'] = array(
				901	=> $combatReport['steal']['metal'],
				902	=> $combatReport['steal']['crystal'],
				903	=> $combatReport['steal']['deuterium']
			);
		}
		
		return $combatReport;
	}
	
	function battlehall() 
	{
		global $LNG, $USER;
		
		$LNG->includeData(array('FLEET', 'TECH'));
		$this->setWindow('popup');

		$db = Database::get();

		$RID		= HTTP::_GP('raport', '');

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
			':reportID'	=> $RID
		));

			
		if(empty($reportData)) {
			$this->printMessage($LNG['sys_raport_not_found']);
		return;
		}
		
		$combatReport = safe_unserialize($reportData['raport']);
		if (!is_array($combatReport)) {
			$this->printMessage($LNG['sys_raport_not_found']);
			return;
		}

		$rawTime = (int) ($combatReport['time'] ?? 0);
		$combatReport = $this->BCWrapperPreRev2321($combatReport);
		$combatReport['stealUnprofitable'] = $this->isStealUnprofitable($combatReport);
		$formattedTime = _date($LNG['php_tdformat'], $rawTime, $USER['timezone']);
		$combatReport['time'] = $formattedTime;

		$attackerName = (string) ($reportData['attacker'] ?? '');
		$defenderName = (string) ($reportData['defender'] ?? '');

		$this->assign(array(
			'Raport'	=> $combatReport,
			'Info'		=> array($reportData["attacker"], $reportData["defender"]),
			'pageTitle'	=> $LNG['lm_topkb'],
			'hideSidebarMenu' => true,
		) + $this->battleShareAssignVars(
			$combatReport,
			$RID,
			$attackerName,
			$defenderName,
			$formattedTime,
			$rawTime
		));
		
		$this->display('shared.mission.raport.tpl');
	}
	
	function show() 
	{
		global $LNG, $USER;
		
		$LNG->includeData(array('FLEET', 'TECH'));		
		$this->setWindow('popup');

		$db = Database::get();

		$RID		= HTTP::_GP('raport', '');

		$sql = "SELECT raport,attacker,defender FROM %%RW%% WHERE rid = :reportID;";
		$reportData = $db->selectSingle($sql, array(
			':reportID'	=> $RID
		));

		if(empty($reportData)) {
			$this->printMessage($LNG['sys_raport_not_found']);
		return;
		}
		
		// empty is BC for pre r2484
		$isAttacker = empty($reportData['attacker']) || in_array($USER['id'], explode(",", (string) $reportData['attacker']));
		$isDefender = empty($reportData['defender']) || in_array($USER['id'], explode(",", (string) $reportData['defender']));

		if(empty($reportData)) {
			$this->printMessage($LNG['sys_raport_not_found']);
		return;
		}

		$combatReport			= safe_unserialize($reportData['raport']);
		if (!is_array($combatReport)) {
			$this->printMessage($LNG['sys_raport_not_found']);
			return;
		}
		if($isAttacker && !$isDefender && $combatReport['result'] == 'r' && count($combatReport['rounds'] ?? []) <= 2) {
			$this->printMessage($LNG['sys_raport_lost_contact']);
		}

		$combatReport			= $this->BCWrapperPreRev2321($combatReport);
		$combatReport['stealUnprofitable'] = $this->isStealUnprofitable($combatReport);

		$rawTime = (int) ($combatReport['time'] ?? 0);
		$formattedTime = _date($LNG['php_tdformat'], $rawTime, $USER['timezone']);
		$combatReport['time'] = $formattedTime;

		$attackerName = $this->resolveParticipantNames($combatReport, (string) ($reportData['attacker'] ?? ''));
		$defenderName = $this->resolveParticipantNames($combatReport, (string) ($reportData['defender'] ?? ''));

		$this->assign(array(
			'Raport'	=> $combatReport,
			'pageTitle'	=> $LNG['sys_mess_attack_report'],
			'hideSidebarMenu' => true,
		) + $this->battleShareAssignVars(
			$combatReport,
			$RID,
			$attackerName,
			$defenderName,
			$formattedTime,
			$rawTime
		));
		
		$this->display('shared.mission.raport.tpl');
	}
}
