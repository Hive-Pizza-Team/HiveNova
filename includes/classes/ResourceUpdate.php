<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\Config;
use HiveNova\Core\Cache;
use HiveNova\Core\Language;
use HiveNova\Core\BuildFunctions;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\ResourceCalculator;

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

class ResourceUpdate
{

	/**
	 * reference of the config object
	 * @var Config
	 */
	private $config			= NULL;

	private $isGlobalMode 	= NULL;
	private $TIME			= NULL;
	private $HASH			= NULL;
	private $ProductionTime	= NULL;

	private $PLANET			= array();
	private $USER			= array();
	private $Builded		= array();
	private $Build			= true;
	private $Tech			= true;
	private array $resource	= [];
	private array $reslist	= [];

	/**
	 * Request-scoped resource snapshots captured when a planet/user is first
	 * bound to a ResourceUpdate. SavePlanetToDB writes resource deltas against
	 * these so concurrent SQL increments (claims, fleet returns, steals) are
	 * not clobbered by an absolute overwrite.
	 *
	 * @var array<int, array{metal: float, crystal: float, deuterium: float}>
	 */
	private static array $planetResBaseline = [];

	/** @var array<int, float> */
	private static array $userDarkmatterBaseline = [];

	function __construct($Build = true, $Tech = true)
	{
		$this->Build	= $Build;
		$this->Tech		= $Tech;
	}

	public function setResourceData(array $resource, array $reslist): void
	{
		$this->resource = $resource;
		$this->reslist  = $reslist;
	}

	public function setData($USER, $PLANET)
	{
		$this->USER		= $USER;
		$this->PLANET	= $PLANET;
		$this->config	= Config::get($USER['universe']);
		$this->ensureResourceBaselines();
	}

	/**
	 * Shift the save baseline after an external relative SQL update that was
	 * also applied to the in-memory planet (fleet send, marketplace buy,
	 * directive claim). Without this, SavePlanetToDB would re-apply the same
	 * delta.
	 */
	public static function adjustPlanetResourceBaseline(int $planetId, float $metal, float $crystal, float $deuterium): void
	{
		if (!isset(self::$planetResBaseline[$planetId])) {
			return;
		}
		self::$planetResBaseline[$planetId]['metal'] += $metal;
		self::$planetResBaseline[$planetId]['crystal'] += $crystal;
		self::$planetResBaseline[$planetId]['deuterium'] += $deuterium;
	}

	public static function adjustUserDarkmatterBaseline(int $userId, float $darkmatter): void
	{
		if (!isset(self::$userDarkmatterBaseline[$userId])) {
			return;
		}
		self::$userDarkmatterBaseline[$userId] += $darkmatter;
	}

	/**
	 * @return array{metal: float, crystal: float, deuterium: float}|null
	 */
	public static function peekPlanetResourceBaseline(int $planetId): ?array
	{
		return self::$planetResBaseline[$planetId] ?? null;
	}

	/** @internal tests */
	public static function resetResourceBaselinesForTests(): void
	{
		self::$planetResBaseline = [];
		self::$userDarkmatterBaseline = [];
	}

	private function ensureResourceBaselines(): void
	{
		if (!is_array($this->PLANET) || !is_array($this->USER)) {
			return;
		}
		if (!isset($this->PLANET['id'], $this->USER['id'])) {
			return;
		}

		$planetId = (int) $this->PLANET['id'];
		$userId = (int) $this->USER['id'];

		if (!isset(self::$planetResBaseline[$planetId])) {
			self::$planetResBaseline[$planetId] = [
				'metal' => (float) ($this->PLANET['metal'] ?? 0),
				'crystal' => (float) ($this->PLANET['crystal'] ?? 0),
				'deuterium' => (float) ($this->PLANET['deuterium'] ?? 0),
			];
		}
		if (!isset(self::$userDarkmatterBaseline[$userId])) {
			self::$userDarkmatterBaseline[$userId] = (float) ($this->USER['darkmatter'] ?? 0);
		}
	}

	private function refreshResourceBaselinesFromMemory(array $USER, array $PLANET): void
	{
		$planetId = (int) $PLANET['id'];
		$userId = (int) $USER['id'];
		self::$planetResBaseline[$planetId] = [
			'metal' => (float) ($PLANET['metal'] ?? 0),
			'crystal' => (float) ($PLANET['crystal'] ?? 0),
			'deuterium' => (float) ($PLANET['deuterium'] ?? 0),
		];
		self::$userDarkmatterBaseline[$userId] = (float) ($USER['darkmatter'] ?? 0);
	}

	public function getData()
	{
		return array($this->USER, $this->PLANET);
	}
	
	public function ReturnVars() {
		if($this->isGlobalMode)
		{
			$GLOBALS['USER']	= $this->USER;
			$GLOBALS['PLANET']	= $this->PLANET;
			return true;
		} else {
			return array($this->USER, $this->PLANET);
		}
	}
	
	public function CreateHash() {
		$Hash	= array();
		foreach($this->reslist['prod'] as $ID) {
			$Hash[]	= $this->PLANET[$this->resource[$ID]];
			$Hash[]	= $this->PLANET[$this->resource[$ID].'_porcent'];
		}

		$ressource	= array_merge(array(), $this->reslist['resstype'][1], $this->reslist['resstype'][2]);
		foreach($ressource as $ID) {
			$Hash[]	= $this->config->{$this->resource[$ID].'_basic_income'};
		}
		
		$Hash[]	= $this->config->resource_multiplier;
		$Hash[]	= $this->config->storage_multiplier;
		$Hash[]	= $this->config->energySpeed;
		$Hash[]	= $this->USER['factor']['Resource'];
		$Hash[]	= $this->USER['factor']['Energy'];
		$Hash[]	= $this->PLANET[$this->resource[22]];
		$Hash[]	= $this->PLANET[$this->resource[23]];
		$Hash[]	= $this->PLANET[$this->resource[24]];
		$Hash[]	= $this->USER[$this->resource[131]];
		$Hash[]	= $this->USER[$this->resource[132]];
		$Hash[]	= $this->USER[$this->resource[133]];
		// Inactivity forces 100% production in reBuildCache; include it so the
		// cache rebuilds when a player crosses the inactive threshold (or returns).
		$Hash[]	= \isInactive($this->USER) ? 1 : 0;
		$Hash[]	= $this->PLANET['planet'];
		$Hash[]	= $this->PLANET['temp_max'];
		return md5(implode("::", $Hash));
	}
	
	public function CalcResource($USER = NULL, $PLANET = NULL, $SAVE = false, $TIME = NULL, $HASH = true)
	{			
		$this->isGlobalMode	= !isset($USER, $PLANET) ? true : false;
		$this->USER			= $this->isGlobalMode ? $GLOBALS['USER'] : $USER;
		$this->PLANET		= $this->isGlobalMode ? $GLOBALS['PLANET'] : $PLANET;
		$this->TIME			= is_null($TIME) ? TIMESTAMP : $TIME;

		if (!is_array($this->USER) || !is_array($this->PLANET)) {
			return $this->ReturnVars();
		}

		$this->ensureResourceBaselines();
		$this->config		= Config::get($this->USER['universe']);
		
		// Vacation freezes production for active players, but inactive accounts
		// should still accrue (bash protection is already lifted for them).
		if(isVacationMode($this->USER) && !\isInactive($this->USER))
			return $this->ReturnVars();
			
		if($this->Build)
		{
			$this->ShipyardQueue();
			if($this->Tech == true && $this->USER['b_tech'] != 0 && $this->USER['b_tech'] < $this->TIME)
				$this->ResearchQueue();
			if($this->PLANET['b_building'] != 0)
				$this->BuildingQueue();
		}
		
		$this->UpdateResource($this->TIME, $HASH);
			
		if($SAVE === true)
			$this->SavePlanetToDB($this->USER, $this->PLANET);
			
		return $this->ReturnVars();
	}
	
	public function UpdateResource($TIME, $HASH = false)
	{
		$this->ProductionTime  			= ($TIME - $this->PLANET['last_update']);
		
		if($this->ProductionTime > 0)
		{
			$this->PLANET['last_update']	= $TIME;
			if($HASH === false) {
				$this->ReBuildCache();
			} else {
				$this->HASH		= $this->CreateHash();

				if($this->PLANET['eco_hash'] !== $this->HASH) {
					$this->PLANET['eco_hash'] = $this->HASH;
					$this->ReBuildCache();
				}
			}
			$this->ExecCalc();
		}
	}
	
	private function ExecCalc(): void
	{
		$calc = new ResourceCalculator(
			$this->USER, $this->PLANET, $this->config,
			$this->resource, $this->reslist, (float) $this->ProductionTime
		);
		$calc->execCalc();
		$this->PLANET = $calc->getPlanet();
	}

	public static function getProd($Calculation, $Element = false): string
	{
		return ResourceCalculator::getProd((string) $Calculation, $Element);
	}

	public static function getNetworkLevel($USER, $PLANET): array
	{
		return ResourceCalculator::getNetworkLevel($USER, $PLANET);
	}

	public function ReBuildCache(): void
	{
		$calc = new ResourceCalculator(
			$this->USER, $this->PLANET, $this->config,
			$this->resource, $this->reslist, (float) $this->ProductionTime
		);
		$calc->reBuildCache();
		$this->PLANET = $calc->getPlanet();
	}
	
	private function ShipyardQueue()
	{
		$BuildQueue 	= safe_unserialize($this->PLANET['b_hangar_id']);
		if (!is_array($BuildQueue) || $BuildQueue === array()) {
			$this->PLANET['b_hangar'] = 0;
			$this->PLANET['b_hangar_id'] = '';
			return false;
		}

		$this->PLANET['b_hangar'] 	+= ($this->TIME - $this->PLANET['last_update']);
		$BuildArray					= array();
		foreach($BuildQueue as $Item)
		{
			if (!is_array($Item) || !isset($Item[0], $Item[1])) {
				continue;
			}
			$AcumTime			= BuildFunctions::getBuildingTime($this->USER, $this->PLANET, $Item[0]);
			$BuildArray[] 		= array($Item[0], $Item[1], $AcumTime);
		}

		$NewQueue	= array();
		$Done		= false;
		foreach($BuildArray as $Item)
		{
			$Element   = $Item[0];
			$Count     = $Item[1];

			if($Done == false) {
				$BuildTime = $Item[2];
				$Element   = (int)$Element;
				if($BuildTime == 0) {			
					if(!isset($this->Builded[$Element]))
						$this->Builded[$Element] = 0;
						
					$this->Builded[$Element]			+= $Count;
					$this->PLANET[$this->resource[$Element]]	+= $Count;
					continue;					
				}
				
				$Build			= max(min(floor($this->PLANET['b_hangar'] / $BuildTime), $Count), 0);

				if($Build == 0) {
					$NewQueue[]	= array($Element, $Count);
					$Done		= true;
					continue;
				}
				
				if(!isset($this->Builded[$Element]))
					$this->Builded[$Element] = 0;
				
				$this->Builded[$Element]			+= $Build;
				$this->PLANET['b_hangar']			-= $Build * $BuildTime;
				$this->PLANET[$this->resource[$Element]]	+= $Build;
				$Count								-= $Build;
				
				if ($Count == 0)
					continue;
				else
					$Done	= true;
			}	
			$NewQueue[]	= array($Element, $Count);
		}
		$this->PLANET['b_hangar_id']	= !empty($NewQueue) ? serialize($NewQueue) : '';

		return true;
	}
	
	private function BuildingQueue() 
	{
		while($this->CheckPlanetBuildingQueue())
			$this->SetNextQueueElementOnTop();
	}
	
	private function CheckPlanetBuildingQueue()
	{
		if (empty($this->PLANET['b_building_id']) || $this->PLANET['b_building'] > $this->TIME)
			return false;
		
		$CurrentQueue	= safe_unserialize($this->PLANET['b_building_id']);

		$Element      	= $CurrentQueue[0][0];
		$BuildEndTime 	= $CurrentQueue[0][3];
		$BuildMode    	= $CurrentQueue[0][4];
		
		if(!isset($this->Builded[$Element]))
			$this->Builded[$Element] = 0;
		
		if ($BuildMode == 'build')
		{
			$this->PLANET['field_current']		+= 1;
			$this->PLANET[$this->resource[$Element]]	+= 1;
			$this->Builded[$Element]			+= 1;
		}
		else
		{
			$this->PLANET['field_current'] 		-= 1;
			$this->PLANET[$this->resource[$Element]] 	-= 1;
			$this->Builded[$Element]			-= 1;
		}
	

		array_shift($CurrentQueue);
		$OnHash	= in_array($Element, $this->reslist['prod']);
		$this->UpdateResource($BuildEndTime, !$OnHash);			
			
		if (count($CurrentQueue) == 0) {
			$this->PLANET['b_building']    	= 0;
			$this->PLANET['b_building_id'] 	= '';

			return false;
		} else {
			$this->PLANET['b_building_id'] 	= serialize($CurrentQueue);
			return true;
		}
	}	

	public function SetNextQueueElementOnTop()
	{
		global $LNG;

		if (empty($this->PLANET['b_building_id'])) {
			$this->PLANET['b_building']    = 0;
			$this->PLANET['b_building_id'] = '';
			return false;
		}

		$CurrentQueue 	= safe_unserialize($this->PLANET['b_building_id']);
		$Loop       	= true;

		$BuildEndTime	= 0;
		$NewQueue		= '';

		while ($Loop === true)
		{
			$ListIDArray		= $CurrentQueue[0];
			$Element			= $ListIDArray[0];
			$Level				= $ListIDArray[1];
			$BuildMode			= $ListIDArray[4];
			$ForDestroy			= ($BuildMode == 'destroy') ? true : false;
			$costResources		= BuildFunctions::getElementPrice($this->USER, $this->PLANET, $Element, $ForDestroy, $Level);
			$BuildTime			= BuildFunctions::getBuildingTime($this->USER, $this->PLANET, $Element, $costResources);
			$HaveResources		= BuildFunctions::isElementBuyable($this->USER, $this->PLANET, $Element, $costResources);
			$BuildEndTime		= $this->PLANET['b_building'] + $BuildTime;
			$CurrentQueue[0]	= array($Element, $Level, $BuildTime, $BuildEndTime, $BuildMode);
			$HaveNoMoreLevel	= false;
				
			if($ForDestroy && $this->PLANET[$this->resource[$Element]] == 0) {
				$HaveResources  = false;
				$HaveNoMoreLevel = true;
			}

			if($HaveResources === true) {
				if(isset($costResources[901])) { $this->PLANET[$this->resource[901]]	-= $costResources[901]; }
				if(isset($costResources[902])) { $this->PLANET[$this->resource[902]]	-= $costResources[902]; }
				if(isset($costResources[903])) { $this->PLANET[$this->resource[903]]	-= $costResources[903]; }
				if(isset($costResources[921])) { $this->USER[$this->resource[921]]	-= $costResources[921]; }
				$NewQueue               	= serialize($CurrentQueue);
				$Loop                  		= false;
			} else {
				if($this->USER['hof'] == 1){
					if ($HaveNoMoreLevel) {
						$Message     = sprintf($LNG['sys_nomore_level'], $LNG['tech'][$Element]);
					} else {
						$Message     = self::formatNotEnoughResourcesMessage($this->PLANET, $Element, $costResources);
					}

					PlayerUtil::sendMessage($this->USER['id'], 0,$LNG['sys_buildlist'], 99,
						$LNG['sys_buildlist_fail'], $Message, $this->TIME);
				}

				array_shift($CurrentQueue);
					
				if (count($CurrentQueue) == 0) {
					$BuildEndTime  = 0;
					$NewQueue      = '';
					$Loop          = false;
				} else {
					$BaseTime			= $BuildEndTime - $BuildTime;
					$NewQueue			= array();
					foreach($CurrentQueue as $ListIDArray)
					{
						$ListIDArray[2]		= BuildFunctions::getBuildingTime($this->USER, $this->PLANET, $ListIDArray[0], NULL, $ListIDArray[4] == 'destroy');
						$BaseTime			+= $ListIDArray[2];
						$ListIDArray[3]		= $BaseTime;
						$NewQueue[]			= $ListIDArray;
					}
					$CurrentQueue	= $NewQueue;
				}
			}
		}
			
		$this->PLANET['b_building']    = $BuildEndTime;
		$this->PLANET['b_building_id'] = $NewQueue;

		return true;
	}

	/**
	 * Build the "not enough resources" inbox message for a failed queue start.
	 * When the element costs energy (911), append available vs required energy —
	 * the base string only lists metal/crystal/deuterium and otherwise hides
	 * energy shortfalls (e.g. Terraformer).
	 */
	public static function formatNotEnoughResourcesMessage(array $planet, $element, array $costResources): string
	{
		global $LNG;

		if (empty($LNG)) {
			$LNG = new Language('en');
			$LNG->includeData(array('L18N', 'INGAME', 'TECH', 'CUSTOM'));
		}

		foreach ([901, 902, 903, 911] as $resId) {
			if (!isset($costResources[$resId])) {
				$costResources[$resId] = 0;
			}
		}

		$Message = sprintf(
			$LNG['sys_notenough_money'],
			$planet['name'],
			$planet['id'],
			$planet['galaxy'],
			$planet['system'],
			$planet['planet'],
			$LNG['tech'][$element],
			pretty_number($planet['metal']),
			$LNG['tech'][901],
			pretty_number($planet['crystal']),
			$LNG['tech'][902],
			pretty_number($planet['deuterium']),
			$LNG['tech'][903],
			pretty_number($costResources[901]),
			$LNG['tech'][901],
			pretty_number($costResources[902]),
			$LNG['tech'][902],
			pretty_number($costResources[903]),
			$LNG['tech'][903]
		);

		if ($costResources[911] > 0) {
			$Message .= sprintf(
				$LNG['sys_notenough_money_energy'],
				pretty_number($planet['energy'] ?? 0),
				$LNG['tech'][911],
				pretty_number($costResources[911]),
				$LNG['tech'][911]
			);
		}

		return $Message;
	}
		
	private function ResearchQueue()
	{
		while($this->CheckUserTechQueue())
			$this->SetNextQueueTechOnTop();
	}
	
	private function CheckUserTechQueue()
	{
		if (empty($this->USER['b_tech_id']) || $this->USER['b_tech'] > $this->TIME)
			return false;

		if(!isset($this->Builded[$this->USER['b_tech_id']]))
			$this->Builded[$this->USER['b_tech_id']]	= 0;

		$this->Builded[$this->USER['b_tech_id']]					+= 1;
		$this->USER[$this->resource[$this->USER['b_tech_id']]]		+= 1;
	

		$CurrentQueue	= safe_unserialize($this->USER['b_tech_queue']);
		if (!is_array($CurrentQueue)) {
			$this->USER['b_tech']			= 0;
			$this->USER['b_tech_id']		= 0;
			$this->USER['b_tech_planet']	= 0;
			$this->USER['b_tech_queue']		= '';
			return false;
		}
		array_shift($CurrentQueue);		
			
		$this->USER['b_tech_id']		= 0;
		if (count($CurrentQueue) == 0) {
			$this->USER['b_tech'] 			= 0;
			$this->USER['b_tech_id']		= 0;
			$this->USER['b_tech_planet']	= 0;
			$this->USER['b_tech_queue']		= '';
			return false;
		} else {
			$this->USER['b_tech_queue'] 	= serialize(array_values($CurrentQueue));
			return true;
		}
	}	
	
	public function SetNextQueueTechOnTop()
	{
		global $LNG;

		if (empty($this->USER['b_tech_queue'])) {
			$this->USER['b_tech'] 			= 0;
			$this->USER['b_tech_id']		= 0;
			$this->USER['b_tech_planet']	= 0;
			$this->USER['b_tech_queue']		= '';
			return false;
		}

		$CurrentQueue 	= safe_unserialize($this->USER['b_tech_queue']);
		if (!is_array($CurrentQueue) || empty($CurrentQueue)) {
			$this->USER['b_tech']			= 0;
			$this->USER['b_tech_id']		= 0;
			$this->USER['b_tech_planet']	= 0;
			$this->USER['b_tech_queue']		= '';
			return false;
		}
		$Loop       	= true;
		while ($Loop == true)
		{
			$ListIDArray        = $CurrentQueue[0];
			$isAnotherPlanet	= $ListIDArray[4] != $this->PLANET['id'];
			if($isAnotherPlanet)
			{
				$sql	= 'SELECT * FROM %%PLANETS%% WHERE id = :planetId;';
				$PLANET	= Database::get()->selectSingle($sql, array(
					':planetId'	=> $ListIDArray[4],
				));

				if (empty($PLANET)) {
					array_shift($CurrentQueue);
					if (count($CurrentQueue) == 0) {
						$this->USER['b_tech']			= 0;
						$this->USER['b_tech_id']		= 0;
						$this->USER['b_tech_planet']	= 0;
						$this->USER['b_tech_queue']		= '';
						$Loop							= false;
					} else {
						$this->USER['b_tech_queue'] = serialize(array_values($CurrentQueue));
					}
					continue;
				}

				$RPLANET 		= new ResourceUpdate(true, false);
				$RPLANET->setResourceData($this->resource, $this->reslist);
				list(, $PLANET)	= $RPLANET->CalcResource($this->USER, $PLANET, false, $this->USER['b_tech']);
			}
			else
			{
				$PLANET	= $this->PLANET;
			}

			$PLANET[$this->resource[31].'_inter']	= self::getNetworkLevel($this->USER, $PLANET);
			
			$Element            = $ListIDArray[0];
			$Level              = $ListIDArray[1];
			$costResources		= BuildFunctions::getElementPrice($this->USER, $PLANET, $Element, false, $Level);
			$BuildTime			= BuildFunctions::getBuildingTime($this->USER, $PLANET, $Element, $costResources);
			$HaveResources		= BuildFunctions::isElementBuyable($this->USER, $PLANET, $Element, $costResources);
			$BuildEndTime       = $this->USER['b_tech'] + $BuildTime;
			$CurrentQueue[0]	= array($Element, $Level, $BuildTime, $BuildEndTime, $PLANET['id']);
			
			if($HaveResources == true) {
				if(isset($costResources[901])) { $PLANET[$this->resource[901]]		-= $costResources[901]; }
				if(isset($costResources[902])) { $PLANET[$this->resource[902]]		-= $costResources[902]; }
				if(isset($costResources[903])) { $PLANET[$this->resource[903]]		-= $costResources[903]; }
				if(isset($costResources[921])) { $this->USER[$this->resource[921]]	-= $costResources[921]; }
				$this->USER['b_tech_id']		= $Element;
				$this->USER['b_tech']      		= $BuildEndTime;
				$this->USER['b_tech_planet']	= $PLANET['id'];
				$this->USER['b_tech_queue'] 	= serialize($CurrentQueue);

				$Loop                  			= false;
			} else {
				if($this->USER['hof'] == 1){
					$Message = self::formatNotEnoughResourcesMessage($PLANET, $Element, $costResources);
					PlayerUtil::sendMessage($this->USER['id'], 0,$LNG['sys_techlist'], 99, $LNG['sys_buildlist_fail'], $Message, $this->TIME);
				}

				array_shift($CurrentQueue);
					
				if (count($CurrentQueue) == 0) {
					$this->USER['b_tech'] 			= 0;
					$this->USER['b_tech_id']		= 0;
					$this->USER['b_tech_planet']	= 0;
					$this->USER['b_tech_queue']		= '';
					
					$Loop                  			= false;
				} else {
					$BaseTime						= $BuildEndTime - $BuildTime;
					$NewQueue						= array();
					foreach($CurrentQueue as $ListIDArray)
					{
						$ListIDArray[2]				= BuildFunctions::getBuildingTime($this->USER, $PLANET, $ListIDArray[0]);
						$BaseTime					+= $ListIDArray[2];
						$ListIDArray[3]				= $BaseTime;
						$NewQueue[]					= $ListIDArray;
					}
					$CurrentQueue					= $NewQueue;
				}
			}
				
			if($isAnotherPlanet)
			{
				$RPLANET->SavePlanetToDB($this->USER, $PLANET);
				$RPLANET		= NULL;
				unset($RPLANET);
			}
			else
			{
				$this->PLANET	= $PLANET;
			}
		}

		return true;
	}
	
	public function SavePlanetToDB($USER = NULL, $PLANET = NULL)
	{
		if(is_null($USER))
			global $USER;

		if(is_null($PLANET))
			global $PLANET;

		$buildQueries	= array();

		$planetId = (int) $PLANET['id'];
		$userId = (int) $USER['id'];
		$hasPlanetBaseline = isset(self::$planetResBaseline[$planetId]);
		$hasUserBaseline = isset(self::$userDarkmatterBaseline[$userId]);
		// Do not call ensureResourceBaselines() here: capturing from the
		// already-mutated memory would make the delta always zero.

		$planetBaseline = $hasPlanetBaseline ? self::$planetResBaseline[$planetId] : null;
		$dmBaseline = $hasUserBaseline ? self::$userDarkmatterBaseline[$userId] : null;

		$params	= array(
			':userId'				=> $USER['id'],
			':planetId'				=> $PLANET['id'],
			':ecoHash'				=> $PLANET['eco_hash'],
			':lastUpdateTime'		=> $PLANET['last_update'],
			':b_building'			=> $PLANET['b_building'],
			':b_building_id' 		=> $PLANET['b_building_id'],
			':field_current' 		=> $PLANET['field_current'],
			':b_hangar_id'			=> $PLANET['b_hangar_id'],
			':metal_perhour'		=> $PLANET['metal_perhour'],
			':crystal_perhour'		=> $PLANET['crystal_perhour'],
			':deuterium_perhour'	=> $PLANET['deuterium_perhour'],
			':metal_max'			=> $PLANET['metal_max'],
			':crystal_max'			=> $PLANET['crystal_max'],
			':deuterium_max'		=> $PLANET['deuterium_max'],
			':energy_used'			=> $PLANET['energy_used'],
			':energy'				=> $PLANET['energy'],
			':b_hangar'				=> $PLANET['b_hangar'],
			':b_tech'				=> $USER['b_tech'],
			':b_tech_id'			=> $USER['b_tech_id'],
			':b_tech_planet'		=> $USER['b_tech_planet'],
			':b_tech_queue'			=> $USER['b_tech_queue']
		);

		if ($hasPlanetBaseline) {
			$params[':metalDelta'] = (int) floor((float) $PLANET['metal'] - (float) $planetBaseline['metal']);
			$params[':crystalDelta'] = (int) floor((float) $PLANET['crystal'] - (float) $planetBaseline['crystal']);
			$params[':deuteriumDelta'] = (int) floor((float) $PLANET['deuterium'] - (float) $planetBaseline['deuterium']);
			$metalSql = 'p.metal = GREATEST(0, p.metal + :metalDelta)';
			$crystalSql = 'p.crystal = GREATEST(0, p.crystal + :crystalDelta)';
			$deuteriumSql = 'p.deuterium = GREATEST(0, p.deuterium + :deuteriumDelta)';
		} else {
			$params[':metal'] = $PLANET['metal'];
			$params[':crystal'] = $PLANET['crystal'];
			$params[':deuterium'] = $PLANET['deuterium'];
			$metalSql = 'p.metal = :metal';
			$crystalSql = 'p.crystal = :crystal';
			$deuteriumSql = 'p.deuterium = :deuterium';
		}

		if ($hasUserBaseline) {
			$params[':darkmatterDelta'] = (float) $USER['darkmatter'] - (float) $dmBaseline;
			$darkmatterSql = 'u.darkmatter = GREATEST(0, u.darkmatter + :darkmatterDelta)';
		} else {
			$params[':darkmatter'] = $USER['darkmatter'];
			$darkmatterSql = 'u.darkmatter = :darkmatter';
		}

		if (!empty($this->Builded))
		{
			foreach($this->Builded as $Element => $Count)
			{
				$Element	= (int) $Element;
				
				if(empty($this->resource[$Element]) || empty($Count)) {
					continue;
				}

				if(in_array($Element, $this->reslist['one']))
				{
					$buildQueries[]							= ', p.'.$this->resource[$Element].' = :'.$this->resource[$Element];
					$params[':'.$this->resource[$Element]]	= '1';
				}
				elseif(isset($PLANET[$this->resource[$Element]]))
				{
					$buildQueries[]							= ', p.'.$this->resource[$Element].' = p.'.$this->resource[$Element].' + :'.$this->resource[$Element];
					$params[':'.$this->resource[$Element]]	= floatToString($Count);
				}
				elseif(isset($USER[$this->resource[$Element]]))
				{
					$buildQueries[]							= ', u.'.$this->resource[$Element].' = u.'.$this->resource[$Element].' + :'.$this->resource[$Element];
					$params[':'.$this->resource[$Element]]	= floatToString($Count);
				}
			}
		}

		$sql = 'UPDATE %%PLANETS%% as p,%%USERS%% as u SET
		'.$metalSql.',
		'.$crystalSql.',
		'.$deuteriumSql.',
		p.eco_hash			= :ecoHash,
		p.last_update		= :lastUpdateTime,
		p.b_building		= :b_building,
		p.b_building_id 	= :b_building_id,
		p.field_current 	= :field_current,
		p.b_hangar_id		= :b_hangar_id,
		p.metal_perhour		= :metal_perhour,
		p.crystal_perhour	= :crystal_perhour,
		p.deuterium_perhour	= :deuterium_perhour,
		p.metal_max			= :metal_max,
		p.crystal_max		= :crystal_max,
		p.deuterium_max		= :deuterium_max,
		p.energy_used		= :energy_used,
		p.energy			= :energy,
		p.b_hangar			= :b_hangar,
		'.$darkmatterSql.',
		u.b_tech			= :b_tech,
		u.b_tech_id			= :b_tech_id,
		u.b_tech_planet		= :b_tech_planet,
		u.b_tech_queue		= :b_tech_queue
		'.implode("\n", $buildQueries).'
		WHERE p.id = :planetId AND u.id = :userId;';

		Database::get()->update($sql, $params);

		\HiveNova\Core\AchievementHooks::afterBuildCompleted($this->Builded, $USER, $PLANET);
		\HiveNova\Core\DirectiveHooks::afterBuildCompleted($this->Builded, $USER);

		$this->Builded	= array();
		$this->refreshResourceBaselinesFromMemory($USER, $PLANET);

		return array($USER, $PLANET);
	}
}
