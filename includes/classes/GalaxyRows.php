<?php

namespace HiveNova\Core;

use HiveNova\Page\Game\ShowPhalanxPage;

use HiveNova\Core\Database;
use HiveNova\Core\Universe;
use HiveNova\Core\FleetFunctions;

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



class GalaxyRows
{
	private $Galaxy;
	private $System;
	private $galaxyData;
	private $galaxyRow;
	
	const PLANET_DESTROYED = false;
	
	function __construct() {
		
	}
	
	public function setGalaxy($Galaxy) {
		$this->Galaxy	= $Galaxy;
		return $this;
	}
	
	public function setSystem($System) {
		$this->System	= $System;
		return $this;
	}
	
	public function getGalaxyData()
	{
		global $USER;

		$inventorySelect = $this->getGalaxyInventorySelectSql();

        $sql	= 'SELECT SQL_BIG_RESULT DISTINCT
		p.galaxy, p.system, p.planet, p.id, p.id_owner, p.name, p.image, p.last_update, p.diameter, p.temp_min, p.temp_max, p.field_current, p.field_max, p.destruyed, p.der_metal, p.der_crystal, p.id_luna,
		u.id as userid, u.ally_id, u.username, u.onlinetime, u.urlaubs_modus, u.banaday, 
		m.id as m_id, m.diameter as m_diameter, m.name as m_name, m.temp_min as m_temp_min, m.temp_max as m_temp_max, m.mondbasis as m_mondbasis, m.last_update as m_last_update,
		s.total_points, s.total_rank, 
		a.id as allyid, a.ally_tag, a.ally_web, a.ally_members, a.ally_name, 
		allys.total_rank as ally_rank,
		COUNT(buddy.id) as buddy,
		d.level as diploLevel'.$inventorySelect.'
		FROM %%PLANETS%% p
		LEFT JOIN %%USERS%% u ON p.id_owner = u.id
		LEFT JOIN %%PLANETS%% m ON m.id = p.id_luna
		LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = :statTypeUser
		LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
		LEFT JOIN %%DIPLO%% as d ON d.accept = 1 AND ((d.owner_1 = :allianceId AND d.owner_2 = a.id) OR (d.owner_1 = a.id AND d.owner_2 = :allianceId))
		LEFT JOIN %%STATPOINTS%% allys ON allys.stat_type = :statTypeAlliance AND allys.id_owner = a.id
		LEFT JOIN %%BUDDY%% buddy ON ((buddy.sender = :userId AND buddy.owner = u.id) OR (buddy.sender = u.id AND buddy.owner = :userId))
			AND NOT EXISTS (SELECT 1 FROM %%BUDDY_REQUEST%% br WHERE br.id = buddy.id)
		WHERE p.universe = :universe AND p.galaxy = :galaxy AND p.system = :system AND p.planet_type = :planetTypePlanet
		GROUP BY p.id;';

		$galaxyResult	= Database::get()->select($sql, array(
			':statTypeUser' 	=> 1,
			':statTypeAlliance' => 2,
			':allianceId'		=> $USER['ally_id'],
			':userId'			=> $USER['id'],
			':universe'			=> Universe::current(),
			':galaxy'			=> $this->Galaxy,
			':system'			=> $this->System,
			':planetTypePlanet'	=> 1,
	  	));

		foreach ($galaxyResult as $galaxyRow)
		{
        	$this->galaxyRow = $galaxyRow;

			if ($this->galaxyRow['destruyed'] != 0)
			{
                $this->galaxyData[$this->galaxyRow['planet']]	= self::PLANET_DESTROYED;
				continue;
			}
			
			$this->galaxyData[$this->galaxyRow['planet']]	= array();
			
			$this->isOwnPlanet();
			$this->setLastActivity();
			
			$this->getAllowedMissions();
			
			$this->getPlayerData();
			$this->getPlanetData();
			$this->getAllianceData();
			$this->getDebrisData();
			$this->getMoonData();
			$this->getActionButtons();
		}
		
		return $this->galaxyData;
	}

	/** 
	 * Retrieves formatted system control statistics for the specified galactic coordinates. 
	 * @param int $galaxy The target galaxy number
	 * @param int $system The target star system number
	 * @return string Formatted control data
	*/
	public function getSystemControlData($galaxy, $system) {
		$controlSql = 'SELECT ally_name
			FROM (
				SELECT ally_name, COUNT(*) planet_count
				FROM %%ALLIANCE%% a
				LEFT JOIN %%USERS%% u ON a.id = u.ally_id
				LEFT JOIN %%PLANETS%% p ON u.id = p.id_owner
				WHERE p.galaxy = :galaxy
				AND p.system = :system
				AND p.universe = :universe
				GROUP BY ally_name
			) AS sub
			WHERE planet_count = (
				SELECT MAX(planet_count)
				FROM (
					SELECT COUNT(*) planet_count
					FROM %%ALLIANCE%% a
					LEFT JOIN %%USERS%% u ON a.id = u.ally_id
					LEFT JOIN %%PLANETS%% p ON u.id = p.id_owner
					WHERE p.galaxy = :galaxy
					AND p.system = :system
					AND p.universe = :universe
					GROUP BY ally_name
				) AS max_sub
			)
			ORDER BY ally_name;';

		$controllingAlliances = Database::get()->select($controlSql, array(
			':galaxy'	=> $galaxy,
			':system'	=> $system,
			':universe' => Universe::current()
		));

		if (count($controllingAlliances) === 1 && isset($controllingAlliances[0]['ally_name'])) {
			$controllingAlliance = $controllingAlliances[0]['ally_name'];
		} else {
			$controllingAlliance = "-";
		}

		return $controllingAlliance;
	}
	
	protected function setLastActivity()
	{
		global $LNG;
		
		$lastActivity	= floor((TIMESTAMP - max($this->galaxyRow['last_update'], $this->galaxyRow['m_last_update'])) / 60);
		
		if ($lastActivity < 15) {
			$this->galaxyData[$this->galaxyRow['planet']]['lastActivity']	= $LNG['gl_activity'];
		} elseif($lastActivity < 60) {
			$this->galaxyData[$this->galaxyRow['planet']]['lastActivity']	= sprintf($LNG['gl_activity_inactive'], $lastActivity);
		} else {
			$this->galaxyData[$this->galaxyRow['planet']]['lastActivity']	= '';
		}
	}
	
	protected function isOwnPlanet()
	{
		global $USER;
		
		$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet']	= $this->galaxyRow['id_owner'] == $USER['id'];
	}
	
	protected function getAllowedMissions()
	{
		global $PLANET, $resource;
		
		$this->galaxyData[$this->galaxyRow['planet']]['missions']	= array(
			1	=> !$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && isModuleAvailable(MODULE_MISSION_ATTACK),
			3	=> isModuleAvailable(MODULE_MISSION_TRANSPORT),
			4	=> $this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && isModuleAvailable(MODULE_MISSION_STATION),
			5	=> !$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && isModuleAvailable(MODULE_MISSION_HOLD),
			6	=> !$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && isModuleAvailable(MODULE_MISSION_SPY),
			8	=> isModuleAvailable(MODULE_MISSION_RECYCLE),
			9	=> !$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && $PLANET[$resource[214]] > 0 && isModuleAvailable(MODULE_MISSION_DESTROY),
			10	=> !$this->galaxyData[$this->galaxyRow['planet']]['ownPlanet'] && $PLANET[$resource[503]] > 0 && isModuleAvailable(MODULE_MISSION_ATTACK) && isModuleAvailable(MODULE_MISSILEATTACK) && $this->inMissileRange(),
		);
	}

	protected function inMissileRange()
	{
		global $USER, $PLANET, $resource;
		
		if ($this->galaxyRow['galaxy'] != $PLANET['galaxy'])
			return false;
		
		$Range		= FleetFunctions::GetMissileRange($USER[$resource[117]]);
		$systemMin	= $PLANET['system'] - $Range;
		$systemMax	= $PLANET['system'] + $Range;
		
		return $this->galaxyRow['system'] >= $systemMin && $this->galaxyRow['system'] <= $systemMax;
	}
	
	protected function getActionButtons()
	{
		global $USER;
        if($this->galaxyData[$this->galaxyRow['planet']]['ownPlanet']) {
            $this->galaxyData[$this->galaxyRow['planet']]['action'] = false;
        } else {
            $this->galaxyData[$this->galaxyRow['planet']]['action'] = array(
                'esp'		=> $USER['settings_esp'] == 1 && $this->galaxyData[$this->galaxyRow['planet']]['missions'][6],
                'message'	=> $USER['settings_wri'] == 1 && isModuleAvailable(MODULE_MESSAGES),
                'buddy'		=> $USER['settings_bud'] == 1 && isModuleAvailable(MODULE_BUDDYLIST) && $this->galaxyRow['buddy'] == 0,
                'missle'	=> $USER['settings_mis'] == 1 && $this->galaxyData[$this->galaxyRow['planet']]['missions'][10],
            );
        }
	}

	protected function getPlayerData()
	{
		global $USER, $LNG;

		$IsNoobProtec		= CheckNoobProtec($USER, $this->galaxyRow, $this->galaxyRow);
		$Class		 		= userStatus($this->galaxyRow, $IsNoobProtec);
		
        $this->galaxyData[$this->galaxyRow['planet']]['user']	= array(
			'id'			=> $this->galaxyRow['userid'],
			'username'		=> htmlspecialchars((string) $this->galaxyRow['username'], ENT_QUOTES, "UTF-8"),
			'rank'			=> $this->galaxyRow['total_rank'],
			'points'		=> pretty_number($this->galaxyRow['total_points']),
			'playerrank'	=> isModuleAvailable(25)?sprintf($LNG['gl_in_the_rank'], htmlspecialchars((string) $this->galaxyRow['username'],ENT_QUOTES,"UTF-8"), $this->galaxyRow['total_rank']):htmlspecialchars((string) $this->galaxyRow['username'],ENT_QUOTES,"UTF-8"),
			'class'			=> $Class,
			'isBuddy'		=> $this->galaxyRow['buddy'] == 0,
		);
	}
	
	protected function getAllianceData()
	{
		global $USER, $LNG;
		if(empty($this->galaxyRow['allyid'])) {
			$this->galaxyData[$this->galaxyRow['planet']]['alliance']	= false;
		} else {
			$Class	= array();
			switch($this->galaxyRow['diploLevel'])
			{
				case 1:
					$Class  = array('friend');
					break;
				case 2:
					$Class	= array('member');
					break;
				case 3:
					$Class  = array('trade');
					break;
				case 4:
					$Class	= array('nap');
					break;
				case 5:
					$Class	= array('enemy');
					break;
			}
			
			if($USER['ally_id'] == $this->galaxyRow['ally_id'])
			{
				$Class	= array('member');
			}
			
			$this->galaxyData[$this->galaxyRow['planet']]['alliance']	= array(
				'id'		=> $this->galaxyRow['allyid'],
				'name'		=> htmlspecialchars((string) $this->galaxyRow['ally_name'], ENT_QUOTES, "UTF-8"),
				'member'	=> sprintf(($this->galaxyRow['ally_members'] == 1) ? $LNG['gl_member_add'] : $LNG['gl_member'], $this->galaxyRow['ally_members']),
				'web'		=> $this->galaxyRow['ally_web'],
				'tag'		=> $this->galaxyRow['ally_tag'],
				'rank'		=> $this->galaxyRow['ally_rank'],
				'class'		=> $Class,
			);
		}
	}

	protected function getDebrisData()
	{
		$total		= $this->galaxyRow['der_metal'] + $this->galaxyRow['der_crystal'];
		if($total == 0) {
			$this->galaxyData[$this->galaxyRow['planet']]['debris']	= false;
		} else {
			$this->galaxyData[$this->galaxyRow['planet']]['debris']	= array(
				'metal'			=> $this->galaxyRow['der_metal'],
				'crystal'		=> $this->galaxyRow['der_crystal'],
			);
		}
	}

	protected function hasSharedPlanetVizIntel(): bool
	{
		global $USER;

		if ($this->galaxyData[$this->galaxyRow['planet']]['ownPlanet']) {
			return true;
		}

		if (empty($this->galaxyRow['id_owner']) || (int) $this->galaxyRow['id_owner'] === (int) $USER['id']) {
			return false;
		}

		if ((int) ($this->galaxyRow['buddy'] ?? 0) > 0) {
			return true;
		}

		if (
			!empty($USER['ally_id'])
			&& !empty($this->galaxyRow['ally_id'])
			&& (int) $USER['ally_id'] === (int) $this->galaxyRow['ally_id']
		) {
			return true;
		}

		if (
			!empty($USER['ally_id'])
			&& !empty($this->galaxyRow['allyid'])
			&& (int) ($this->galaxyRow['diploLevel'] ?? 0) === 1
		) {
			return true;
		}

		return false;
	}

	protected function getColonizeSlotStatus(int $position): array
	{
		global $USER;

		if (!PlayerUtil::allowPlanetPosition($position, $USER)) {
			return array(
				'canColonize'             => false,
				'colonizeBlockedReason'   => 'astro',
			);
		}

		if (!PlayerUtil::hasColonizationCapacity($USER)) {
			return array(
				'canColonize'             => false,
				'colonizeBlockedReason'   => 'cap',
			);
		}

		return array(
			'canColonize'             => true,
			'colonizeBlockedReason'   => null,
		);
	}

	protected function getMoonData()
	{		
		global $THEME;

		if(!isset($this->galaxyRow['m_id'])) {
			$this->galaxyData[$this->galaxyRow['planet']]['moon']	= false;
		} else {
			$themePath = $THEME->getTheme();
			$vizEnabled = str_contains($themePath, '/hive/');
			$this->galaxyData[$this->galaxyRow['planet']]['moon']	= array(
				'id'		=> $this->galaxyRow['m_id'],
				'name'		=> htmlspecialchars((string) $this->galaxyRow['m_name'], ENT_QUOTES, "UTF-8"),
				'temp_min'	=> $this->galaxyRow['m_temp_min'], 
				'temp_max'	=> $this->galaxyRow['m_temp_max'],
				'diameter'	=> $this->galaxyRow['m_diameter'],
				'vizRef'	=> $vizEnabled ? ('moon:' . (int) $this->galaxyRow['m_id']) : '',
			);
		}
	}

	protected function getPlanetData()
	{
		global $THEME;

		$themePath = $THEME->getTheme();
		$vizEnabled = str_contains($themePath, '/hive/');
		$this->galaxyData[$this->galaxyRow['planet']]['planet']	= array(
			'id'			=> $this->galaxyRow['id'],
			'name'			=> htmlspecialchars((string) $this->galaxyRow['name'], ENT_QUOTES, "UTF-8"),
			'image'			=> $this->galaxyRow['image'],
			'phalanx'		=> isModuleAvailable(MODULE_PHALANX) && ShowPhalanxPage::allowPhalanx($this->galaxyRow['galaxy'], $this->galaxyRow['system']),
			'vizRef'		=> $vizEnabled ? ('planet:' . (int) $this->galaxyRow['id']) : '',
		);
	}

	protected function getGalaxyInventorySelectSql(): string
	{
		$columns = $this->getPlanetInventoryColumns();
		if (!$columns) {
			return '';
		}

		$parts = array();
		foreach ($columns as $column) {
			$parts[] = 'p.' . $column;
		}

		return ', ' . implode(', ', $parts);
	}

	protected function getPlanetInventoryColumns(): array
	{
		global $resource, $reslist;

		$columns = array();
		foreach (array('build', 'fleet', 'defense') as $category) {
			if (empty($reslist[$category])) {
				continue;
			}
			foreach ($reslist[$category] as $elementID) {
				if (empty($resource[$elementID])) {
					continue;
				}
				$columns[] = $resource[$elementID];
			}
		}

		return $columns;
	}

	protected function resolveInventoryRow(bool $shareIntel): array
	{
		return $this->galaxyRow;
	}

	protected function buildCountMap(array $elementIds, array $sourceRow = null)
	{
		global $resource;

		$sourceRow = $sourceRow ?? $this->galaxyRow;
		$map = array();
		foreach ($elementIds as $elementID) {
			if (empty($resource[$elementID])) {
				continue;
			}
			$column = $resource[$elementID];
			$value = (int) ($sourceRow[$column] ?? 0);
			if ($value > 0) {
				$map[$elementID] = $value;
			}
		}

		return $map ?: new \stdClass();
	}

	protected function buildPlanetVizJson($shareIntel, $dpath, $galaxyPreview = false)
	{
		global $resource, $reslist;

		$inventoryRow = $shareIntel ? $this->resolveInventoryRow(true) : $this->galaxyRow;
		$fieldsCurrent = 0;
		$fieldsMax = 0;
		if ($shareIntel) {
			$fieldsCurrent = (int) $this->galaxyRow['field_current'];
			$planetForFields = array(
				'field_max' => (int) $this->galaxyRow['field_max'],
				$resource[33] => (int) ($inventoryRow[$resource[33]] ?? 0),
				$resource[41] => 0,
			);
			$fieldsMax = max(1, (int) CalculateMaxPlanetFields($planetForFields));
		}

		$moon = null;
		if (!$galaxyPreview && !empty($this->galaxyRow['m_id'])) {
			$moon = array(
				'id'       => (int) $this->galaxyRow['m_id'],
				'name'     => (string) $this->galaxyRow['m_name'],
				'diameter' => (int) $this->galaxyRow['m_diameter'],
			);
		}

		$debrisTotal = (int) $this->galaxyRow['der_metal'] + (int) $this->galaxyRow['der_crystal'];
		$debris = null;
		if ($debrisTotal > 0) {
			$debris = array(
				'metal'   => (int) $this->galaxyRow['der_metal'],
				'crystal' => (int) $this->galaxyRow['der_crystal'],
			);
		}

		$payload = array(
			'shareIntel' => (bool) $shareIntel,
			'texture'   => $this->galaxyRow['image'],
			'type'      => 1,
			'tempMin'   => (int) $this->galaxyRow['temp_min'],
			'tempMax'   => (int) $this->galaxyRow['temp_max'],
			'diameter'  => (int) $this->galaxyRow['diameter'],
			'fields'    => array(
				'current' => $fieldsCurrent,
				'max'     => $fieldsMax,
			),
			'galaxy'    => (int) $this->galaxyRow['galaxy'],
			'system'    => (int) $this->galaxyRow['system'],
			'planet'    => (int) $this->galaxyRow['planet'],
			'buildings' => $shareIntel ? $this->buildCountMap($reslist['build'], $inventoryRow) : new \stdClass(),
			'fleet'     => $shareIntel ? $this->buildCountMap($reslist['fleet'], $inventoryRow) : new \stdClass(),
			'defense'   => ($shareIntel && !$galaxyPreview) ? $this->buildCountMap($reslist['defense'], $inventoryRow) : new \stdClass(),
			'queue'     => array(
				'building' => 0,
				'hangar'   => 0,
			),
			'moon'      => $moon,
			'debris'    => $debris,
			'dpath'     => $dpath,
		);

		return json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}

	protected function buildMoonVizJson($shareIntel, $dpath, $galaxyPreview = false)
	{
		$tempMin = (int) $this->galaxyRow['m_temp_min'];
		$tempMax = (int) $this->galaxyRow['m_temp_max'];
		if ($tempMax < $tempMin) {
			$tempMax = $tempMin;
		}

		$moonBaseLevel = (int) ($this->galaxyRow['m_mondbasis'] ?? 0);
		$buildings = ($shareIntel && $moonBaseLevel > 0) ? array(41 => $moonBaseLevel) : new \stdClass();

		$payload = array(
			'shareIntel' => (bool) $shareIntel,
			'texture'   => 'mond',
			'type'      => 3,
			'tempMin'   => $tempMin,
			'tempMax'   => $tempMax,
			'diameter'  => (int) $this->galaxyRow['m_diameter'],
			'fields'    => array(
				'current' => 0,
				'max'     => 1,
			),
			'galaxy'    => (int) $this->galaxyRow['galaxy'],
			'system'    => (int) $this->galaxyRow['system'],
			'planet'    => (int) $this->galaxyRow['planet'],
			'buildings' => $buildings,
			'fleet'     => new \stdClass(),
			'defense'   => new \stdClass(),
			'queue'     => array(
				'building' => 0,
				'hangar'   => 0,
			),
			'moon'      => null,
			'debris'    => null,
			'dpath'     => $dpath,
		);

		return json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}

	public function fillUncolonizedSlots(array &$galaxyData, int $maxPlanets, int $galaxy, int $system, string $themePath): void
	{
		$vizEnabled = str_contains($themePath, '/hive/');
		for ($position = 1; $position <= $maxPlanets; $position++) {
			if (isset($galaxyData[$position])) {
				continue;
			}
			$colonizeStatus = $this->getColonizeSlotStatus($position);
			$galaxyData[$position] = array(
				'uncolonized'             => true,
				'canColonize'             => $colonizeStatus['canColonize'],
				'colonizeBlockedReason'   => $colonizeStatus['colonizeBlockedReason'],
				'planet'                  => array(
					'image'   => 'unknown',
					'vizRef'  => $vizEnabled
						? ('slot:' . $galaxy . ':' . $system . ':' . $position)
						: '',
				),
			);
		}
	}

	public function resolveVizJsonRef(string $ref, string $themePath): ?array
	{
		if (!str_contains($themePath, '/hive/')) {
			return null;
		}

		if (preg_match('/^slot:(\d+):(\d+):(\d+)$/', $ref, $matches)) {
			$json = $this->buildUncolonizedPlanetVizJson(
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3],
				$themePath
			);

			return json_decode($json, true);
		}

		if (preg_match('/^planet:(\d+)$/', $ref, $matches)) {
			return $this->buildVizPayloadForContextRow((int) $matches[1], 'planet', $themePath);
		}

		if (preg_match('/^moon:(\d+)$/', $ref, $matches)) {
			return $this->buildVizPayloadForContextRow((int) $matches[1], 'moon', $themePath);
		}

		return null;
	}

	protected function buildVizPayloadForContextRow(int $entityId, string $type, string $themePath): ?array
	{
		global $USER;

		$row = $this->fetchGalaxyContextRow($entityId, $type);
		if (!$row) {
			return null;
		}

		$this->galaxyRow = $row;
		$this->galaxyData = array(
			$row['planet'] => array(
				'ownPlanet' => (int) $row['id_owner'] === (int) $USER['id'],
			),
		);

		$shareIntel = $this->hasSharedPlanetVizIntel();
		$json = $type === 'moon'
			? $this->buildMoonVizJson($shareIntel, $themePath, true)
			: $this->buildPlanetVizJson($shareIntel, $themePath, true);

		return json_decode($json, true);
	}

	protected function fetchGalaxyContextRow(int $entityId, string $type): ?array
	{
		global $USER;

		if ($entityId <= 0) {
			return null;
		}

		$inventorySelect = $this->getGalaxyInventorySelectSql();
		$where = $type === 'moon' ? 'm.id = :entityId' : 'p.id = :entityId';

		$sql = 'SELECT SQL_BIG_RESULT DISTINCT
		p.galaxy, p.system, p.planet, p.id, p.id_owner, p.name, p.image, p.last_update, p.diameter, p.temp_min, p.temp_max, p.field_current, p.field_max, p.destruyed, p.der_metal, p.der_crystal, p.id_luna,
		u.id as userid, u.ally_id, u.username, u.onlinetime, u.urlaubs_modus, u.banaday,
		m.id as m_id, m.diameter as m_diameter, m.name as m_name, m.temp_min as m_temp_min, m.temp_max as m_temp_max, m.mondbasis as m_mondbasis, m.last_update as m_last_update,
		s.total_points, s.total_rank,
		a.id as allyid, a.ally_tag, a.ally_web, a.ally_members, a.ally_name,
		allys.total_rank as ally_rank,
		COUNT(buddy.id) as buddy,
		d.level as diploLevel'.$inventorySelect.'
		FROM %%PLANETS%% p
		LEFT JOIN %%USERS%% u ON p.id_owner = u.id
		LEFT JOIN %%PLANETS%% m ON m.id = p.id_luna
		LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id AND s.stat_type = :statTypeUser
		LEFT JOIN %%ALLIANCE%% a ON a.id = u.ally_id
		LEFT JOIN %%DIPLO%% as d ON d.accept = 1 AND ((d.owner_1 = :allianceId AND d.owner_2 = a.id) OR (d.owner_1 = a.id AND d.owner_2 = :allianceId))
		LEFT JOIN %%STATPOINTS%% allys ON allys.stat_type = :statTypeAlliance AND allys.id_owner = a.id
		LEFT JOIN %%BUDDY%% buddy ON ((buddy.sender = :userId AND buddy.owner = u.id) OR (buddy.sender = u.id AND buddy.owner = :userId))
			AND NOT EXISTS (SELECT 1 FROM %%BUDDY_REQUEST%% br WHERE br.id = buddy.id)
		WHERE p.universe = :universe AND p.planet_type = :planetTypePlanet AND '.$where.'
		GROUP BY p.id
		LIMIT 1;';

		$row = Database::get()->selectSingle($sql, array(
			':statTypeUser'       => 1,
			':statTypeAlliance'   => 2,
			':allianceId'         => $USER['ally_id'],
			':userId'             => $USER['id'],
			':universe'           => Universe::current(),
			':planetTypePlanet'   => $type === 'moon' ? 1 : 1,
			':entityId'           => $entityId,
		));

		return is_array($row) ? $row : null;
	}

	public function buildUncolonizedPlanetVizJson(int $galaxy, int $system, int $planet, string $dpath): string
	{
		$payload = array(
			'shareIntel' => false,
			'vizState'  => 'unknown',
			'texture'   => '',
			'type'      => 1,
			'tempMin'   => 0,
			'tempMax'   => 0,
			'diameter'  => 0,
			'fields'    => array(
				'current' => 0,
				'max'     => 0,
			),
			'galaxy'    => $galaxy,
			'system'    => $system,
			'planet'    => $planet,
			'buildings' => new \stdClass(),
			'fleet'     => new \stdClass(),
			'defense'   => new \stdClass(),
			'queue'     => array(
				'building' => 0,
				'hangar'   => 0,
			),
			'moon'      => null,
			'debris'    => null,
			'dpath'     => $dpath,
		);

		return json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}
}
