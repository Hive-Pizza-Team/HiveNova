<?php

namespace HiveNova\Page\Game;

use HiveNova\Core\Config;
use HiveNova\Core\Database;
use HiveNova\Core\HTTP;
use HiveNova\Core\PlayerUtil;
use HiveNova\Core\FleetFunctions;
use HiveNova\Core\MarketPlaceResource;
use HiveNova\Core\MarketPlaceService;
use HiveNova\Core\MarketAbuseRules;
use HiveNova\Core\MarketTradeLedger;
use HiveNova\Core\PushingAccusationQuery;

/**
 *  Steemnova
 *   by Adam "dotevo" Jordanek 2018
 *
 * For the full copyright and license information, please view the LICENSE
 *
 */

class ShowMarketPlacePage extends AbstractGamePage
{
	public static $requireModule = MODULE_MISSION_TRADE;

	/** Default deal-rating ratio (metal : crystal : deuterium); players can change it on the page. */
	private const DEFAULT_RATIO = MarketPlaceService::REFERENCE_RATIO;

	function __construct()
	{
		parent::__construct();
	}

	private function hasFreeSlot(array $USER): bool
	{
		return FleetFunctions::GetMaxFleetSlots($USER) > FleetFunctions::GetCurrentFleets($USER['id']);
	}

	/** @return array<int, array<string, mixed>> */
	private function pricelist(): array
	{
		return is_array($GLOBALS['pricelist'] ?? null) ? $GLOBALS['pricelist'] : array();
	}

	/**
	 * Pushing flag for the offer list, cached in the session for 10 minutes: the
	 * accusation query scans 14 days of fleet logs for the whole universe. The buy
	 * path always re-checks without the cache.
	 */
	private function listFlagged(array $USER): bool
	{
		$cache = $_SESSION['market_flagged'] ?? null;
		if (is_array($cache) && (int) ($cache['user'] ?? 0) === (int) $USER['id'] && TIMESTAMP - (int) ($cache['at'] ?? 0) < 600) {
			return (bool) ($cache['flagged'] ?? false);
		}
		$flagged = PushingAccusationQuery::isAccusedReceiver((int) $USER['id'], (int) $USER['universe']);
		$_SESSION['market_flagged'] = array('user' => (int) $USER['id'], 'at' => TIMESTAMP, 'flagged' => $flagged);
		return $flagged;
	}

	/**
	 * Player-facing text for a MarketAbuseRules::buyRuleKey() result.
	 */
	private function ruleMessage(string $key, float $pairTraded): string
	{
		global $LNG;
		if ($key === 'market_reason_daily_cap') {
			return sprintf(
				$LNG[$key],
				pretty_number_plain(MarketAbuseRules::dailyCapLeft($pairTraded)),
				pretty_number_plain(MarketAbuseRules::DAILY_CAP_VALUE)
			);
		}
		if ($key === 'market_reason_band') {
			return sprintf($LNG[$key], MarketAbuseRules::BAND_MIN, MarketAbuseRules::BAND_MAX);
		}
		return $LNG[$key] ?? $key;
	}

	/**
	 * attack / defensive / shield levels including officer factors.
	 *
	 * @return array{0: float|int, 1: float|int, 2: float|int}
	 */
	private function combatTechs(array $player): array
	{
		global $resource;
		$factor = $player['factor'] ?? getFactors($player);
		return array(
			$player[$resource[109]] * 10 + $factor['Attack'] * 100,
			$player[$resource[110]] * 10 + $factor['Defensive'] * 100,
			$player[$resource[111]] * 10 + $factor['Shield'] * 100,
		);
	}

	/**
	 * Accepted pact level between the buyer's and the seller's alliance (one value, war wins).
	 */
	private function diploLevel(int $buyerAllyId, int $sellerAllyId): ?int
	{
		if ($buyerAllyId === 0 || $sellerAllyId === 0) {
			return null;
		}
		$rows = Database::get()->select(
			'SELECT level, accept FROM %%DIPLO%%
			WHERE ((owner_1 = :ow AND owner_2 = :ow2) OR (owner_2 = :ow AND owner_1 = :ow2)) AND accept = 1;',
			array(':ow' => $buyerAllyId, ':ow2' => $sellerAllyId)
		);
		return MarketPlaceService::resolveDiploLevel($rows);
	}

	/**
	 * Per-ship cargo for the buyer, keyed by ship id.
	 *
	 * @return array<int, float>
	 */
	private function cargoCapacity(array $USER): array
	{
		$factor = 1 + $USER['factor']['ShipStorage'];
		return array(
			MarketPlaceService::SHIP_LIGHT_CARGO => MarketPlaceResource::shipHaulCapacity(MarketPlaceService::SHIP_LIGHT_CARGO, $USER, $factor),
			MarketPlaceService::SHIP_HEAVY_CARGO => MarketPlaceResource::shipHaulCapacity(MarketPlaceService::SHIP_HEAVY_CARGO, $USER, $factor),
		);
	}

	/** @return array<int, float> */
	private function cargoOnPlanet(array $PLANET): array
	{
		global $resource;
		return array(
			MarketPlaceService::SHIP_LIGHT_CARGO => (float) $PLANET[$resource[MarketPlaceService::SHIP_LIGHT_CARGO]],
			MarketPlaceService::SHIP_HEAVY_CARGO => (float) $PLANET[$resource[MarketPlaceService::SHIP_HEAVY_CARGO]],
		);
	}

	/**
	 * Payment flight for a pickup fleet, exactly as the buy path charges it:
	 * distance from this planet to the offer's trading zone.
	 *
	 * @param array<int, int> $fleet
	 * @return array{duration: int, fuel: float}
	 */
	private function paymentFlight(array $fleet, array $offerRow, array $USER, array $PLANET): array
	{
		if ($fleet === array()) {
			return array('duration' => 0, 'fuel' => 0.0);
		}
		$speedFactor = FleetFunctions::GetGameSpeedFactor();
		$distance = FleetFunctions::GetTargetDistance(
			array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']),
			array($offerRow['fleet_end_galaxy'], $offerRow['fleet_end_system'], $offerRow['fleet_end_planet'])
		);
		$speed = FleetFunctions::GetFleetMaxSpeed($fleet, $USER);
		if ($speed <= 0) {
			return array('duration' => 0, 'fuel' => 0.0);
		}
		$duration = FleetFunctions::GetMissionDuration(10, $speed, $distance, $speedFactor, $USER);
		$fuel = FleetFunctions::GetFleetConsumption($fleet, $duration, $distance, $USER, $speedFactor);
		return array('duration' => (int) $duration, 'fuel' => (float) $fuel);
	}

	/** @return array{901: float, 902: float, 903: float} */
	private function planetResources(array $PLANET): array
	{
		global $resource;
		return array(
			901 => (float) $PLANET[$resource[901]],
			902 => (float) $PLANET[$resource[902]],
			903 => (float) $PLANET[$resource[903]],
		);
	}

	private function getResourceTradeHistory(int $universe): array
	{
		$trades = MarketPlaceService::resourceTradeHistory(
			Database::get(),
			$universe,
			MarketPlaceResource::historyLimit()
		);
		return array_map(array($this, 'formatHistoryTime'), $trades);
	}

	private function getFleetTradeHistory(int $universe): array
	{
		global $LNG;
		$trades = MarketPlaceService::fleetTradeHistory(
			Database::get(),
			$universe,
			MarketPlaceResource::historyLimit()
		);
		foreach ($trades as $i => $trade) {
			$trades[$i]['ships'] = $this->shipList(FleetFunctions::unserialize($trade['fleet']), $LNG);
			$trades[$i] = $this->formatHistoryTime($trades[$i]);
		}
		return $trades;
	}

	private function formatHistoryTime(array $row): array
	{
		global $LNG, $USER;
		$ts = is_string($row['time'] ?? null) ? strtotime($row['time']) : false;
		$row['time_label'] = $ts ? _date($LNG['php_tdformat'], $ts, $USER['timezone'] ?? null) : '';
		return $row;
	}

	/** @return list<array{id: int, name: string, count: int}> */
	private function shipList(array $fleet, $LNG): array
	{
		$ships = array();
		foreach ($fleet as $shipId => $count) {
			$ships[] = array('id' => (int) $shipId, 'name' => $LNG['tech'][$shipId] ?? (string) $shipId, 'count' => (int) $count);
		}
		return $ships;
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	private function doBuy(): array
	{
		global $USER, $PLANET, $resource, $LNG;
		$FleetID  = HTTP::_GP('fleetID', 0);
		$payWith  = MarketPlaceService::normalizePayWith(HTTP::_GP('shipType', 0));
		$db = Database::get();
		$fail = static fn(string $message): array => array('ok' => false, 'message' => $message);

		$universe = (int) $USER['universe'];

		if (!$this->hasFreeSlot($USER)) {
			return $fail($LNG['fl_no_slots']);
		}

		//Get trade fleet
		$fleetResult = MarketPlaceService::findBuyableOffer($db, (int) $FleetID, $universe);
		if ($fleetResult === null) {
			return $fail($LNG['market_p_msg_not_found']);
		}

		// Never let a seller buy (and burn fuel on) their own offer.
		if (MarketPlaceService::isOwnOffer($fleetResult, (int) $USER['id'])) {
			return $fail($LNG['market_p_msg_own_offer']);
		}

		if ((int) $fleetResult['filter_visibility'] !== MarketPlaceService::VISIBILITY_ALL) {
			$level = $this->diploLevel((int) $USER['ally_id'], (int) $fleetResult['ally_id']);
			$key = MarketPlaceService::diploBlockKey((int) $fleetResult['filter_visibility'], $level, (int) $fleetResult['ally_id'], (int) $USER['ally_id']);
			if ($key !== null) {
				return $fail($LNG[$key]);
			}
		}

		if ($fleetResult['transaction_type'] == 1) {
			$key = MarketPlaceService::techBlockKey($this->combatTechs($USER), $this->combatTechs($fleetResult));
			if ($key !== null) {
				return $fail($LNG[$key]);
			}
		}

		if (MarketPlaceResource::techId((int) $fleetResult['ex_resource_type']) === null) {
			return $fail($LNG['market_p_msg_wrong_resource_type']);
		}

		// Anti-abuse: flagged pushing receivers, out-of-band (legacy) prices, daily cap per seller.
		$goodsValue = MarketTradeLedger::goodsValue($fleetResult, $this->pricelist(), self::DEFAULT_RATIO);
		$priceValue = MarketAbuseRules::priceValue((int) $fleetResult['ex_resource_type'], (float) $fleetResult['ex_resource_amount'], self::DEFAULT_RATIO);
		$sold = MarketTradeLedger::soldToBuyer($db, (int) $USER['id'], $this->pricelist(), self::DEFAULT_RATIO);
		$pairTraded = $sold[(int) $fleetResult['fleet_owner']] ?? 0.0;
		$ruleKey = MarketAbuseRules::buyRuleKey(
			PushingAccusationQuery::isAccusedReceiver((int) $USER['id'], (int) $fleetResult['fleet_universe']),
			MarketAbuseRules::dealRatio($goodsValue, $priceValue),
			$pairTraded,
			$goodsValue
		);
		if ($ruleKey !== null) {
			return $fail($this->ruleMessage($ruleKey, $pairTraded));
		}

		//-------------FLEET SIZE CALCULATION---------------
		$amount = (float) $fleetResult['ex_resource_amount'];
		$plan = MarketPlaceService::planPickupWithFuel($amount, $payWith, $this->cargoCapacity($USER), $this->cargoOnPlanet($PLANET),
			fn(array $fleet): float => $this->paymentFlight($fleet, $fleetResult, $USER, $PLANET)['fuel']);
		if ($plan['missing'] > 0) {
			return $fail($LNG['market_p_msg_more_ships_is_needed']);
		}
		$fleetArray = $plan['fleet'];

		$sql = "SELECT * FROM %%USERS%% WHERE id = :userId;";
		$USER_2   = $db->selectSingle($sql, array(
			':userId'       => $fleetResult['fleet_owner']
		));
		if (!is_array($USER_2)) {
			return $fail($LNG['market_p_msg_not_found']);
		}

		$SpeedFactor    	= FleetFunctions::GetGameSpeedFactor();
		$Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetResult['fleet_end_galaxy'], $fleetResult['fleet_end_system'], $fleetResult['fleet_end_planet']));
		$SpeedAllMin		= FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
		$Duration			= FleetFunctions::GetMissionDuration(10, $SpeedAllMin, $Distance, $SpeedFactor, $USER);
		$consumption		= FleetFunctions::GetFleetConsumption($fleetArray, $Duration, $Distance, $USER, $SpeedFactor);

		$fleetStartTime		= $Duration + TIMESTAMP;
		$fleetStayTime		= $fleetStartTime;
		$fleetEndTime		= $fleetStayTime + $Duration;

		$fleetResource	= MarketPlaceResource::amounts((int) $fleetResult['ex_resource_type'], $amount);

		if($PLANET[$resource[901]]-$fleetResource[901] < 0 ||
			$PLANET[$resource[902]]	-$fleetResource[902] <0 ||
			$PLANET[$resource[903]]	-$fleetResource[903] - $consumption < 0) {
			return $fail($LNG['market_p_msg_resources_error']);
		}

		$deutNeeded = $fleetResource[903] + $consumption;

		$db->beginTransaction();
		try {
			$lockedTrade = MarketPlaceService::lockBuyableOffer($db, (int) $FleetID, $universe);
			if (!is_array($lockedTrade) || MarketPlaceService::isOwnOffer($lockedTrade, (int) $USER['id'])) {
				$db->rollback();
				return $fail($LNG['market_p_msg_not_found']);
			}

			$lockedPlanet = $db->selectSingle(
				'SELECT metal, crystal, deuterium FROM %%PLANETS%% WHERE id = :id FOR UPDATE',
				array(':id' => $PLANET['id'])
			);
			if (!is_array($lockedPlanet) ||
				$lockedPlanet[$resource[901]] < $fleetResource[901] ||
				$lockedPlanet[$resource[902]] < $fleetResource[902] ||
				$lockedPlanet[$resource[903]] < $deutNeeded) {
				$db->rollback();
				return $fail($LNG['market_p_msg_resources_error']);
			}

			$db->update(
				'UPDATE %%PLANETS%% SET
				metal     = metal     - :metal,
				crystal   = crystal   - :crystal,
				deuterium = deuterium - :deuterium
				WHERE id = :planetId',
				array(
					':metal'     => $fleetResource[901],
					':crystal'   => $fleetResource[902],
					':deuterium' => $deutNeeded,
					':planetId'  => $PLANET['id'],
				)
			);

			$buyerfleet = FleetFunctions::sendFleet($fleetArray, 3/*Transport*/, $USER['id'], $PLANET['id'], $PLANET['galaxy'],
				$PLANET['system'], $PLANET['planet'], $PLANET['planet_type'], $fleetResult['fleet_owner'], $fleetResult['fleet_start_id'],
				$fleetResult['fleet_start_galaxy'], $fleetResult['fleet_start_system'], $fleetResult['fleet_start_planet'], $fleetResult['fleet_start_type'],
				$fleetResource, $fleetStartTime, $fleetStayTime, $fleetEndTime,0,0,1);

			/////////////////////////////////////////////////////////////////////////////
			/// SEND/
			$sellerFleetArray	= FleetFunctions::unserialize($fleetResult['fleet_array']);
			$SpeedFactor    	= FleetFunctions::GetGameSpeedFactor();
			$Distance    		= FleetFunctions::GetTargetDistance(array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']), array($fleetResult['fleet_end_galaxy'], $fleetResult['fleet_end_system'], $fleetResult['fleet_end_planet']));
			$SpeedAllMin		= FleetFunctions::GetFleetMaxSpeed($sellerFleetArray, $USER_2);
			$Duration			= FleetFunctions::GetMissionDuration(10, $SpeedAllMin, $Distance, $SpeedFactor, $USER_2);

			$fleetStartTime		= $Duration + TIMESTAMP;
			$fleetStayTime		= $fleetStartTime;
			$fleetEndTime		= $fleetStayTime + $Duration;

			$params = array(
				':fleetID' => $FleetID,
				':fleet_target_owner' => $USER['id'],
				':fleet_end_id' => $PLANET['id'],
				':fleet_end_planet' => $PLANET['planet'],
				':fleet_end_system' => $PLANET['system'],
				':fleet_end_galaxy' => $PLANET['galaxy'],
				':fleet_start_time' => $fleetStartTime,
				':fleet_end_stay' => $fleetStayTime,
				':fleet_end_time' => $fleetEndTime,
				':fleet_mission' => $fleetResult['transaction_type'] == 0 ? 3 : 17,
				':fleet_no_m_return' => 1,
				':fleet_mess'=> 0,
			);
			$sql = "UPDATE %%FLEETS%% SET `fleet_no_m_return` = :fleet_no_m_return, `fleet_end_id` = :fleet_end_id,`fleet_target_owner` = :fleet_target_owner, `fleet_mess` = :fleet_mess, `fleet_mission` = :fleet_mission, `fleet_end_stay` = :fleet_end_stay ,`fleet_end_time` = :fleet_end_time ,`fleet_start_time` = :fleet_start_time , `fleet_end_planet` = :fleet_end_planet, `fleet_end_system` = :fleet_end_system, `fleet_end_galaxy` = :fleet_end_galaxy WHERE fleet_id = :fleetID;";
			$db->update($sql, $params);
			$sql = "UPDATE %%LOG_FLEETS%% SET `fleet_no_m_return` = :fleet_no_m_return, `fleet_end_id` = :fleet_end_id,`fleet_target_owner` = :fleet_target_owner, `fleet_mess` = :fleet_mess, `fleet_mission` = :fleet_mission, `fleet_end_stay` = :fleet_end_stay ,`fleet_end_time` = :fleet_end_time ,`fleet_start_time` = :fleet_start_time , `fleet_end_planet` = :fleet_end_planet, `fleet_end_system` = :fleet_end_system, `fleet_end_galaxy` = :fleet_end_galaxy WHERE fleet_id = :fleetID;";
			$db->update($sql, $params);
			$sql	= 'UPDATE %%FLEETS_EVENT%% SET  `time` = :endTime WHERE fleetID	= :fleetId;';
			$db->update($sql, array(
				':fleetId'	=> $FleetID,
				':endTime'	=> $fleetStartTime
			));

			$sql	= 'UPDATE %%TRADES%% SET `buyer_fleet_id` = :buyerFleetId, `buy_time` = NOW() WHERE seller_fleet_id = :fleetId AND buyer_fleet_id IS NULL;';
			$db->update($sql, array(
				':fleetId'	=> $FleetID,
				':buyerFleetId' =>$buyerfleet
			));
			if ($db->rowCount() === 0) {
				$db->rollback();
				return $fail($LNG['market_p_msg_not_found']);
			}

			$db->commit();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		}

		$PLANET[$resource[901]]	-= $fleetResource[901];
		$PLANET[$resource[902]]	-= $fleetResource[902];
		$PLANET[$resource[903]]	-= $deutNeeded;

		// To customer
		$Message	= sprintf($LNG['market_msg_trade_bought'], $fleetResult['fleet_start_galaxy'].":".$fleetResult['fleet_start_system'].":".$fleetResult['fleet_start_planet'],
			$fleetResource[901],$LNG['tech'][901],
			$fleetResource[902],$LNG['tech'][902],
			$fleetResource[903],$LNG['tech'][903],
			$consumption, $LNG['tech'][903]);
		PlayerUtil::sendMessage($USER['id'], 0, $LNG['market_msg_trade_from'], 4, $LNG['market_msg_trade_topic'],
			$Message, TIMESTAMP, NULL, 1, $fleetResult['fleet_universe']);

		// To salesmen
		$Message	= sprintf($LNG['market_msg_trade_sold'], $PLANET['galaxy'].":".$PLANET['system'].":".$PLANET['planet'],
			$fleetResult['fleet_resource_metal'],$LNG['tech'][901],
			$fleetResult['fleet_resource_crystal'],$LNG['tech'][902],
			$fleetResult['fleet_resource_deuterium'],$LNG['tech'][903]);
		PlayerUtil::sendMessage($fleetResult['fleet_owner'], 0, $LNG['market_msg_trade_from'], 4, $LNG['market_msg_trade_topic'],
			$Message, TIMESTAMP, NULL, 1, $fleetResult['fleet_universe']);

		return array(
			'ok' => true,
			'message' => sprintf($LNG['market_p_msg_bought'], pretty_fly_time($Duration))
				. ' ' . sprintf($LNG['market_p_msg_paid_with'], MarketPlaceService::fleetSummary($fleetArray, $LNG['shortNames'])),
		);
	}

	/**
	 * @return array{ok: bool, message: string}
	 */
	private function doCancel(): array
	{
		global $USER, $LNG;
		$key = MarketPlaceService::cancelOffer(Database::get(), (int) $USER['id'], (int) HTTP::_GP('fleetID', 0));
		if ($key !== null) {
			return array('ok' => false, 'message' => $LNG[$key]);
		}
		return array('ok' => true, 'message' => $LNG['market_p_msg_cancelled']);
	}

	/**
	 * Offer rows for the template. Every buy blocker is decided here so the page
	 * can disable Buy with a reason instead of failing after submit.
	 */
	private function buildOffers(array $USER, array $PLANET): array
	{
		global $LNG;
		$db = Database::get();

		$rows = MarketPlaceService::listActiveOffers($db, (int) $USER['universe'], (int) $USER['ally_id']);

		$hasFreeSlot = $this->hasFreeSlot($USER);
		$pricelist = $this->pricelist();
		$flagged = $this->listFlagged($USER);
		$soldToMe = MarketTradeLedger::soldToBuyer($db, (int) $USER['id'], $pricelist, self::DEFAULT_RATIO);
		$capacity = $this->cargoCapacity($USER);
		$onPlanet = $this->cargoOnPlanet($PLANET);
		$planetRes = $this->planetResources($PLANET);
		$buyerTechs = $this->combatTechs($USER);
		$speedFactor = FleetFunctions::GetGameSpeedFactor();
		$offers = array();
		$seen = array();

		foreach ($rows as $row) {
			$fleetId = (int) $row['fleet_id'];
			if (isset($seen[$fleetId])) {
				continue;
			}
			$seen[$fleetId] = true;

			$isOwn = MarketPlaceService::isOwnOffer($row, (int) $USER['id']);
			$level = $row['diplo_level'] === null ? null : (int) $row['diplo_level'];
			$sellerAlly = (int) $row['ally_id'];
			$type = (int) $row['transaction_type'];
			$wantedType = (int) $row['ex_resource_type'];
			$wantedAmount = (float) $row['ex_resource_amount'];
			$price = MarketPlaceResource::amounts($wantedType, $wantedAmount);

			$diploKey = $isOwn ? null : MarketPlaceService::diploBlockKey((int) $row['filter_visibility'], $level, $sellerAlly, (int) $USER['ally_id']);
			$techKey = null;
			if (!$isOwn && $type === 1) {
				$techKey = MarketPlaceService::techBlockKey($buyerTechs, $this->combatTechs($row));
			}

			// Seller fleet: trading zone -> this planet (same as the buy path).
			$sellerFleet = FleetFunctions::unserialize($row['fleet_array']);
			$deliveryDistance = FleetFunctions::GetTargetDistance(
				array($PLANET['galaxy'], $PLANET['system'], $PLANET['planet']),
				array($row['fleet_end_galaxy'], $row['fleet_end_system'], $row['fleet_end_planet'])
			);
			$sellerSpeed = FleetFunctions::GetFleetMaxSpeed($sellerFleet, $row);
			$delivery = $sellerSpeed > 0
				? (int) FleetFunctions::GetMissionDuration(10, $sellerSpeed, $deliveryDistance, $speedFactor, $row)
				: 0;

			$goodsValue = MarketTradeLedger::goodsValue($row, $pricelist, self::DEFAULT_RATIO);
			$pairTraded = $soldToMe[(int) $row['fleet_owner']] ?? 0.0;
			$ruleKey = $isOwn ? null : MarketAbuseRules::buyRuleKey(
				$flagged,
				MarketAbuseRules::dealRatio($goodsValue, MarketAbuseRules::priceValue($wantedType, $wantedAmount, self::DEFAULT_RATIO)),
				$pairTraded,
				$goodsValue
			);

			$pay = array();
			foreach (array(MarketPlaceService::PAY_LIGHT_FIRST => 'lc', MarketPlaceService::PAY_HEAVY_FIRST => 'hc') as $payWith => $code) {
				$plan = MarketPlaceService::planPickupWithFuel($wantedAmount, $payWith, $capacity, $onPlanet,
					fn(array $fleet): float => $this->paymentFlight($fleet, $row, $USER, $PLANET)['fuel']);
				$blockKey = MarketPlaceService::buyBlockKey($isOwn, $diploKey, $techKey, $hasFreeSlot, $plan['missing'], $planetRes, $price, $plan['fuel'], $ruleKey);
				$pay[$code] = array(
					'ships' => MarketPlaceService::fleetSummary($plan['fleet'], $LNG['shortNames']),
					'fuel' => $plan['fuel'],
					'block' => $blockKey === null ? '' : ($blockKey === $ruleKey ? $this->ruleMessage($blockKey, $pairTraded) : ($LNG[$blockKey] ?? $blockKey)),
				);
			}

			$get = array(
				901 => (float) $row['fleet_resource_metal'],
				902 => (float) $row['fleet_resource_crystal'],
				903 => (float) $row['fleet_resource_deuterium'],
			);
			$ratio = $type === 0 ? MarketPlaceService::dealRatio($get, $wantedType, $wantedAmount, self::DEFAULT_RATIO) : null;

			$diploClass = MarketPlaceService::diploClass($level, $sellerAlly, (int) $USER['ally_id']);

			$getParts = array();
			if ($type === 0) {
				foreach ($get as $resId => $value) {
					if ($value > 0) {
						$getParts[] = pretty_number_plain($value) . ' ' . $LNG['tech'][$resId];
					}
				}
			} else {
				foreach ($sellerFleet as $shipId => $count) {
					$getParts[] = pretty_number_plain($count) . ' ' . ($LNG['tech'][$shipId] ?? (string) $shipId);
				}
			}

			$offers[] = array(
				'id' => $fleetId,
				'getLabel' => implode(' + ', $getParts),
				'payLabel' => pretty_number_plain($wantedAmount) . ' ' . MarketPlaceResource::label($wantedType, $LNG['tech']),
				'type' => $type,
				'own' => $isOwn,
				'username' => $row['username'],
				'diploClass' => $diploClass,
				'get' => $get,
				'ships' => $this->shipList($sellerFleet, $LNG),
				'wantedType' => $wantedType,
				'wantedTech' => MarketPlaceResource::techId($wantedType),
				'wantedName' => MarketPlaceResource::label($wantedType, $LNG['tech']),
				'wantedAmount' => $wantedAmount,
				'ratio' => $ratio,
				'rating' => MarketPlaceService::dealRating($ratio),
				'end' => max(0, (int) $row['fleet_end_stay'] - TIMESTAMP),
				'delivery' => $delivery,
				'pay' => $pay,
			);
		}
		return $offers;
	}

	public function show()
	{
		global $USER, $PLANET, $LNG, $resource;

		$action = HTTP::_GP('action', '');
		$tab = MarketPlaceService::normalizeTab(HTTP::_GP('tab', 'resources'));
		$payWith = MarketPlaceService::normalizePayWith(HTTP::_GP('shipType', MarketPlaceService::PAY_HEAVY_FIRST));

		if (($action === 'buy' || $action === 'cancel') && $_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!MarketPlaceService::validCsrf($_SESSION, HTTP::_GP('csrf', ''))) {
				$result = array('ok' => false, 'message' => $LNG['market_p_msg_bad_token']);
			} elseif (IsVacationMode($USER)) {
				$result = array('ok' => false, 'message' => $LNG['fl_vacation_mode_active']);
			} else {
				$result = $action === 'buy' ? $this->doBuy() : $this->doCancel();
			}
			// Post/redirect/get: a refresh must never repeat a buy.
			MarketPlaceService::pushFlash($_SESSION, $result['message'], $result['ok']);
			$this->redirectTo('game.php?page=marketPlace&tab=' . $tab . '&shipType=' . $payWith);
		}

		$offers = $this->buildOffers($USER, $PLANET);
		$resourceOffers = array_values(array_filter($offers, static fn(array $o): bool => $o['type'] === 0));
		$fleetOffers = array_values(array_filter($offers, static fn(array $o): bool => $o['type'] === 1));
		$myOffers = count(array_filter($offers, static fn(array $o): bool => $o['own']));

		$config = Config::get();
		$tradeZone = (int) $config->max_planets + 2;

		$this->assign(array(
			'resImages'       => array(901 => $resource[901], 902 => $resource[902], 903 => $resource[903]),
			'payKey'          => $payWith === MarketPlaceService::PAY_LIGHT_FIRST ? 'lc' : 'hc',
			'flash'           => MarketPlaceService::pullFlash($_SESSION),
			'tab'             => $tab,
			'payWith'         => $payWith,
			'csrf'            => MarketPlaceService::csrfToken($_SESSION),
			'resourceOffers'  => $resourceOffers,
			'fleetOffers'     => $fleetOffers,
			'myOfferCount'    => $myOffers,
			'resourceHistory' => $this->getResourceTradeHistory((int) $USER['universe']),
			'fleetHistory'    => $this->getFleetTradeHistory((int) $USER['universe']),
			'refRatio'        => self::DEFAULT_RATIO,
			'rulesText'       => sprintf(
				$LNG['market_how_rules'],
				MarketAbuseRules::BAND_MIN,
				MarketAbuseRules::BAND_MAX,
				(string) (MarketAbuseRules::LISTING_FEE_RATE * 100),
				MarketAbuseRules::maxHoldHours(),
				pretty_number_plain(MarketAbuseRules::DAILY_CAP_VALUE)
			),
			'tradeZone'       => array(
				'galaxy' => (int) $PLANET['galaxy'],
				'system' => (int) $PLANET['system'],
				'planet' => $tradeZone,
			),
			'sellUrl'         => 'game.php?page=fleetTable&galaxy=' . (int) $PLANET['galaxy']
				. '&system=' . (int) $PLANET['system'] . '&planet=' . $tradeZone . '&planettype=1&target_mission=' . FLEET_MISSION_TRADE,
		));
		$this->tplObj->loadscript('marketplace.js');
		$this->display('page.marketPlace.default.tpl');
	}
}
