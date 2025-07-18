<?php

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

// import hive-php and instantiate $hive
$hivePhp = __DIR__.'/../../vendor/mahdiyari/hive-php/lib/Hive.php';
if (file_exists($hivePhp)) {
    require_once $hivePhp;
}

class PlayerUtil
{
	static public function cryptPassword($password)
	{
		return password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);
	}

	static public function isPositionFree($universe, $galaxy, $system, $position, $type = 1)
	{
		$db = Database::get();
		$sql = "SELECT COUNT(*) as record
		FROM %%PLANETS%%
		WHERE universe = :universe
		AND galaxy = :galaxy
		AND `system` = :system
		AND planet = :position
		AND planet_type = :type;";

		$count = $db->selectSingle($sql, array(
			':universe' => $universe,
			':galaxy' 	=> $galaxy,
			':system' 	=> $system,
			':position'	=> $position,
			':type'		=> $type,
		), 'record');

		return $count == 0;
	}

	static public function isNameValid($name)
	{
		if(UTF8_SUPPORT) {
			return preg_match('/^[\p{L}\p{N}_\-. ]*$/u', $name);
		} else {
			return preg_match('/^[A-z0-9_\-. ]*$/', $name);
		}
	}

	static public function isHiveAccountValid($hiveaccount)
	{
		if (is_null($hiveaccount) || strlen($hiveaccount) == 0 || strlen($hiveaccount) > 16) {
			return false;
		}
		return preg_match('/^[a-z][-a-z0-9]+[a-z0-9](\.[a-z][-a-z0-9]+[a-z0-9])*$/', $hiveaccount);
	}

	static public function isHiveSignValid($hiveaccount, $signedblob)
	{
		// verify account
		if (!PlayerUtil::isHiveAccountValid($hiveaccount)) {
			return false;
		}

		// verify length
		if (is_null($signedblob) || strlen($signedblob) < 32 || strlen($signedblob) > 132) {
			return false;
		}
		// verify content
		if (!PlayerUtil::isNameValid($signedblob)) {
			return false;
		}

		$hive = new Hive\Hive();

		// verify signature using hive-php
		$result = $hive->call('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]');

		if(!is_array($result) || count($result) == 0 || !array_key_exists('posting',$result[0])) {
			return false;
		}

		$publicKeyString = $result[0]['posting']['key_auths'][0][0];
		$publicKey = $hive->publicKeyFrom($publicKeyString);

		if (is_null($publicKey)) {
			return false;
		}

		$message = hash('sha256', $hiveaccount.' is my account.');
		$verified = $publicKey->verify($message, $signedblob);
		if ($verified) {
			return true;
		}

		return false;
	}

	static public function isHiveAccountExists($hiveaccount) {
		$hiveaccount = strtolower($hiveaccount); // force lower case

		// verify account
		if (!PlayerUtil::isHiveAccountValid($hiveaccount)) {
			return false;
		}

		$hive = new Hive\Hive();

		// check existence using hive-php
		$result = $hive->call('condenser_api.get_accounts', '[["'.$hiveaccount.'"]]');

		if(is_array($result) && count($result) > 0) {
			return true;
		}

		return false;
	}

	static public function getPlayerAvatarURL($USER){
		$usernameLower = strtolower($USER['username']);
		if (PlayerUtil::isHiveAccountValid($usernameLower) && isset($USER['hive_account']) && $usernameLower === $USER['hive_account']) {
			return 'https://images.hive.blog/u/'.$usernameLower.'/avatar';
		}

		return 'styles/resource/images/user.png';
	}

	static public function getPlayerBadges($USER){
		$usernameLower = strtolower($USER['username']);
		if (PlayerUtil::isHiveAccountValid($usernameLower) && isset($USER['hive_account']) && $usernameLower === $USER['hive_account']) {
			return '<a href="https://peakd.com/@'.$USER['hive_account'].'" target="_blank">♦️</a>';
		}

		if ($USER['hive_account'] !== '' && $usernameLower !== $USER['hive_account']) {
			return '🔗';
		}

		return '⛓️‍💥';
	}

	static public function isMailValid($address) {
		
		if(function_exists('filter_var')) {
			return filter_var($address, FILTER_VALIDATE_EMAIL) !== FALSE;
		} else {
			return preg_match('^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$', $address);
		}
	}

	static public function checkPosition($universe, $galaxy, $system, $position)
	{
		$config	= Config::get($universe);

		return !(1 > $galaxy
			|| 1 > $system
			|| 1 > $position
			|| $config->max_galaxy < $galaxy
			|| $config->max_system < $system
			|| $config->max_planets < $position);
	}
	
	static public function createPlayer($universe, $userName, $userPassword, $userMail, $hiveAccount, $userLanguage = NULL, $galaxy = NULL, $system = NULL, $position = NULL, $name = NULL, $authlevel = 0, $userIpAddress = NULL)
	{
		$config	= Config::get($universe);
		
		if (isset($universe, $galaxy, $system, $position))
		{
			if (self::checkPosition($universe, $galaxy, $system, $position) === false)
			{
				throw new Exception(sprintf("Try to create a planet at position: %s:%s:%s!", $galaxy, $system, $position));
			}

			if (self::isPositionFree($universe, $galaxy, $system, $position) === false)
			{
				throw new Exception(sprintf("Position is not empty: %s:%s:%s!", $galaxy, $system, $position));
			}
		} else {
			$galaxy	= $config->LastSettedGalaxyPos;
			$system = $config->LastSettedSystemPos;
			$planet	= $config->LastSettedPlanetPos;

			if($galaxy > $config->max_galaxy) {
				$galaxy	= 1;
			}

			if($system > $config->max_system) {
				$system	= 1;
			}
			
			do {
				$position = mt_rand(round($config->max_planets * 0.2), round($config->max_planets * 0.8));
				if ($planet < 3) {
					$planet += 1;
				} else {
					if ($system >= $config->max_system) {
						$system = 1;
						if($galaxy >= $config->max_galaxy) {
							$galaxy	= 1;
						} else {
							$galaxy += 1;
						}
					} else {
						$system += 1;
					}
				}
			} while (self::isPositionFree($universe, $galaxy, $system, $position) === false);

			// Update last coordinates to config table
			$config->LastSettedGalaxyPos = $galaxy;
			$config->LastSettedSystemPos = $system;
			$config->LastSettedPlanetPos = $planet;
		}

		$params			= array(
			':username'				=> $userName,
			':email'				=> $userMail,
			':email2'				=> $userMail,
			':hiveAccount'			=> $hiveAccount,
			':authlevel'			=> $authlevel,
			':universe'				=> $universe,
			':language'				=> $userLanguage,
			':registerAddress'		=> !empty($userIpAddress) ? $userIpAddress : '127.0.0.1',
			':onlinetime'			=> TIMESTAMP,
			':registerTimestamp'	=> TIMESTAMP,
			':password'				=> $userPassword,
			':dpath'				=> DEFAULT_THEME,
			':timezone'				=> $config->timezone,
			':nameLastChanged'		=> 0,
			':darkmatter_start'		=> $config->darkmatter_start,
		);

		$sql = 'INSERT INTO %%USERS%% SET
		username		= :username,
		email			= :email,
		email_2			= :email2,
		hive_account	= :hiveAccount,
		authlevel		= :authlevel,
		universe		= :universe,
		lang			= :language,
		ip_at_reg		= :registerAddress,
		onlinetime		= :onlinetime,
		register_time	= :registerTimestamp,
		password		= :password,
		dpath			= :dpath,
		timezone		= :timezone,
		uctime			= :nameLastChanged,
		darkmatter		= :darkmatter_start;';

		$db = Database::get();

		$db->insert($sql, $params);
		
		$userId		= $db->lastInsertId();
		$planetId	= self::createPlanet($galaxy, $system, $position, $universe, $userId, $name, true, $authlevel);
				
		$currentUserAmount		= $config->users_amount + 1;
		$config->users_amount	= $currentUserAmount;

		$sql = "UPDATE %%USERS%% SET
		galaxy = :galaxy,
		`system` = :system,
		planet = :position,
		id_planet = :planetId
		WHERE id = :userId;";

		$db->update($sql, array(
			':galaxy'	=> $galaxy,
			':system'	=> $system,
			':position'	=> $position,
			':planetId'	=> $planetId,
			':userId'	=> $userId,
		));

		$sql 	= "SELECT MAX(total_rank) as `rank` FROM %%STATPOINTS%% WHERE universe = :universe AND stat_type = :type;";
		$rank	= $db->selectSingle($sql, array(
			':universe'	=> $universe,
			':type'		=> 1,
		), 'rank');
		
		$sql = "INSERT INTO %%STATPOINTS%% SET
				id_owner	= :userId,
				universe	= :universe,
				stat_type	= :type,
				tech_rank	= :rank,
				build_rank	= :rank,
				defs_rank	= :rank,
				fleet_rank	= :rank,
				total_rank	= :rank;";

		$db->insert($sql, array(
		   ':universe'	=> $universe,
		   ':userId'	=> $userId,
		   ':type'		=> 1,
		   ':rank'		=> $rank + 1,
		));
		
		$config->save();

		return array($userId, $planetId);
	}
	
	static public function createPlanet($galaxy, $system, $position, $universe, $userId, $name = NULL, $isHome = false, $authlevel = 0)
	{
		global $LNG;

		if (self::checkPosition($universe, $galaxy, $system, $position) === false)
		{
			throw new Exception(sprintf("Try to create a planet at position: %s:%s:%s!", $galaxy, $system, $position));
		}

		if (self::isPositionFree($universe, $galaxy, $system, $position) === false)
		{
			throw new Exception(sprintf("Position is not empty: %s:%s:%s!", $galaxy, $system, $position));
		}

		$planetData	= array();
		require 'includes/PlanetData.php';

		$config		= Config::get($universe);

		$dataIndex		= (int) ceil($position / ($config->max_planets / count($planetData)));
		$maxTemperature	= $planetData[$dataIndex]['temp'];
		$minTemperature	= $maxTemperature - 40;

		if($isHome) {
			$maxFields				= $config->initial_fields;
		} else {
			$maxFields				= (int) floor($planetData[$dataIndex]['fields'] * $config->planet_factor);
		}

		$diameter			= (int) floor(1000 * sqrt($maxFields));

		$imageNames			= array_keys($planetData[$dataIndex]['image']);
		$imageNameType		= $imageNames[array_rand($imageNames)];
		$imageName			= $imageNameType;
		$imageName			.= 'planet';
		$imageName			.= $planetData[$dataIndex]['image'][$imageNameType] < 10 ? '0' : '';
		$imageName			.= $planetData[$dataIndex]['image'][$imageNameType];

		if(empty($name))
		{
			$name	= $isHome ? $LNG['fcm_mainplanet'] : $LNG['fcp_colony'];
		}

		$params	= array(
			':name'				=> $name,
			':universe'			=> $universe,
			':userId'			=> $userId,
			':galaxy'			=> $galaxy,
			':system'			=> $system,
			':position'			=> $position,
			':updateTimestamp'	=> TIMESTAMP,
			':type'				=> 1,
			':imageName'		=> $imageName,
			':diameter'			=> $diameter,
			':maxFields'		=> $maxFields,
			':minTemperature'	=> $minTemperature,
			':maxTemperature'	=> $maxTemperature,
			':metal_start'		=> $config->metal_start,
			':crystal_start'	=> $config->crystal_start,
			':deuterium_start'	=> $config->deuterium_start
		);

		$sql = 'INSERT INTO %%PLANETS%% SET
		name		= :name,
		universe	= :universe,
		id_owner	= :userId,
		galaxy		= :galaxy,
		`system`		= :system,
		planet		= :position,
		last_update	= :updateTimestamp,
		planet_type	= :type,
		image		= :imageName,
		diameter	= :diameter,
		field_max	= :maxFields,
		temp_min 	= :minTemperature,
		temp_max 	= :maxTemperature,
		metal		= :metal_start,
		crystal		= :crystal_start,
		deuterium	= :deuterium_start;';

		$db = Database::get();
		$db->insert($sql, $params);

		return $db->lastInsertId();
	}
	
	static public function createMoon($universe, $galaxy, $system, $position, $userId, $chance, $diameter = NULL, $moonName = NULL)
	{
		global $LNG;

		$db	= Database::get();

		$sql = "SELECT id_luna, planet_type, id, name, temp_max, temp_min
				FROM %%PLANETS%%
				WHERE universe = :universe
				AND galaxy = :galaxy
				AND `system` = :system
				AND planet = :position
				AND planet_type = :type;";

		$parentPlanet	= $db->selectSingle($sql, array(
	 		':universe'	=> $universe,
	 		':galaxy'	=> $galaxy,
	 		':system'	=> $system,
	 		':position'	=> $position,
	 		':type'		=> 1,
		));

		if ($parentPlanet['id_luna'] != 0)
		{
			return false;
		}

		if(is_null($diameter))
		{
			$diameter	= floor(pow(mt_rand(10, 20) + 3 * $chance, 0.5) * 1000); # New Calculation - 23.04.2011
		}

		$maxTemperature = $parentPlanet['temp_max'] - mt_rand(10, 45);
		$minTemperature = $parentPlanet['temp_min'] - mt_rand(10, 45);

		if(empty($moonName))
		{
			$moonName		= $LNG['type_planet_3'];
		}

		$sql	= "INSERT INTO %%PLANETS%% SET
		name				= :name,
		id_owner			= :owner,
		universe			= :universe,
		galaxy				= :galaxy,
		`system`				= :system,
		planet				= :planet,
		last_update			= :updateTimestamp,
		planet_type			= :type,
		image				= :image,
		diameter			= :diameter,
		field_max			= :fields,
		temp_min			= :minTemperature,
		temp_max			= :maxTemperature,
		metal				= :metal,
		metal_perhour		= :metPerHour,
		crystal				= :crystal,
		crystal_perhour		= :cryPerHour,
		deuterium			= :deuterium,
		deuterium_perhour	= :deuPerHour;";

		$db->insert($sql, array(
			':name'				=> $moonName,
			':owner'			=> $userId,
			':universe'			=> $universe,
			':galaxy'			=> $galaxy,
			':system'			=> $system,
			':planet'			=> $position,
			':updateTimestamp'	=> TIMESTAMP,
			':type'				=> 3,
			':image'			=> 'mond',
			':diameter'			=> $diameter,
			':fields'			=> 1,
			':minTemperature'	=> $minTemperature,
			':maxTemperature'	=> $maxTemperature,
			':metal'			=> 0,
			':metPerHour'		=> 0,
			':crystal'			=> 0,
			':cryPerHour'		=> 0,
			':deuterium'		=> 0,
			':deuPerHour'		=> 0,
		));

		$moonId	= $db->lastInsertId();

		$sql	= "UPDATE %%PLANETS%% SET id_luna = :moonId WHERE id = :planetId;";

		$db->update($sql, array(
			':moonId'	=> $moonId,
			':planetId'	=> $parentPlanet['id'],
		));

		return $moonId;
	}
 
	static public function deletePlayer($userId)
	{
		if(ROOT_USER == $userId)
		{
			// superuser can not be deleted.
			throw new Exception("Superuser #".ROOT_USER." can't be deleted!");
		}

		$db			= Database::get();
		$sql		= 'SELECT universe, ally_id FROM %%USERS%% WHERE id = :userId;';
		$userData	= $db->selectSingle($sql, array(
			':userId'	=> $userId
		));

		if (empty($userData))
		{
			return false;
		}

		if (!empty($userData['ally_id']))
		{
			$sql			= 'SELECT ally_members FROM %%ALLIANCE%% WHERE id = :allianceId;';
			$memberCount	= $db->selectSingle($sql, array(
				':allianceId'	=> $userData['ally_id']
			), 'ally_members');
			
			if ($memberCount > 1)
			{
				$sql	= 'UPDATE %%ALLIANCE%% SET ally_members = ally_members - 1 WHERE id = :allianceId;';
				$db->update($sql, array(
					':allianceId'	=> $userData['ally_id']
				));
			}
			else
			{
				$sql	= 'DELETE FROM %%ALLIANCE%% WHERE id = :allianceId;';
				$db->delete($sql, array(
					':allianceId'	=> $userData['ally_id']
				));

				$sql	= 'DELETE FROM %%STATPOINTS%% WHERE stat_type = :type AND id_owner = :allianceId;';
				$db->delete($sql, array(
					':allianceId'	=> $userData['ally_id'],
					':type'			=> 2
				));

				$sql	= 'UPDATE %%STATPOINTS%% SET id_ally = :resetId WHERE id_ally = :allianceId;';
				$db->update($sql, array(
				  	':allianceId'	=> $userData['ally_id'],
				  	':resetId'		=> 0
			 	));
			}
		}

		$sql	= 'DELETE FROM %%ALLIANCE_REQUEST%% WHERE userID = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
	 	));

		$sql	= 'DELETE FROM %%BUDDY%% WHERE owner = :userId OR sender = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
		));

		$sql	= 'DELETE %%FLEETS%%, %%FLEETS_EVENT%%
		FROM %%FLEETS%% LEFT JOIN %%FLEETS_EVENT%% on fleet_id = fleetId
		WHERE fleet_owner = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
		));

		$sql	= 'DELETE FROM %%MESSAGES%% WHERE message_owner = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
		));

		$sql	= 'DELETE FROM %%NOTES%% WHERE owner = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
		));

		$sql	= 'DELETE FROM %%PLANETS%% WHERE id_owner = :userId;';
		$db->delete($sql, array(
		   	':userId'	=> $userId
	  	));

		$sql	= 'DELETE FROM %%USERS%% WHERE id = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId
		));

		$sql	= 'DELETE FROM %%STATPOINTS%% WHERE stat_type = :type AND id_owner = :userId;';
		$db->delete($sql, array(
			':userId'	=> $userId,
			':type'		=> 1
		));
		
		$fleetIds	= $db->select('SELECT fleet_id FROM %%FLEETS%% WHERE fleet_target_owner = :userId;', array(
			':userId'	=> $userId
		));

		foreach($fleetIds as $fleetId)
		{
			FleetFunctions::SendFleetBack(array('id' => $userId), $fleetId['fleet_id']);
		}

        /*
		$sql	= 'UPDATE %%UNIVERSE%% SET userAmount = userAmount - 1 WHERE universe = :universe;';
		$db->update($sql, array(
			':universe' => $userData['universe']
		));
		
		Cache::get()->flush('universe');
        */

		return true;
	}

	static public function deletePlanet($planetId)
	{
		$db			= Database::get();
		$sql		= 'SELECT id_owner, planet_type, id_luna FROM %%PLANETS%% WHERE id = :planetId AND id NOT IN (SELECT id_planet FROM %%USERS%%);';
		$planetData = $db->selectSingle($sql, array(
			':planetId'	=> $planetId
		));
		
		if(empty($planetData))
		{
			throw new Exception("Can not found planet #".$planetId."!");
		}

		$sql		= 'SELECT fleet_id FROM %%FLEETS%% WHERE fleet_end_id = :planetId OR (fleet_end_type = 3 AND fleet_end_id = :moondId);';
		$fleetIds	= $db->select($sql, array(
			':planetId'	=> $planetId,
			':moondId'	=> $planetData['id_luna']
		));

		foreach($fleetIds as $fleetId)
		{
			FleetFunctions::SendFleetBack(array('id' => $planetData['id_owner']), $fleetId['fleet_id']);
		}

		if ($planetData['planet_type'] == 3) {
			$sql	= 'DELETE FROM %%PLANETS%% WHERE id = :planetId;';
			$db->delete($sql, array(
				':planetId'	=> $planetId
			));

			$sql	= 'UPDATE %%PLANETS%% SET id_luna = :resetId WHERE id_luna = :planetId;';
			$db->update($sql, array(
				':resetId'	=> 0,
				':planetId'	=> $planetId
			));
		} else {
			$sql	= 'DELETE FROM %%PLANETS%% WHERE id = :planetId OR id_luna = :planetId;';
			$db->delete($sql, array(
			   ':planetId'	=> $planetId
			));
		}

		return true;
	}
	
	static public function maxPlanetCount($USER)
	{
		global $resource;
		$config	= Config::get($USER['universe']);

		$planetPerTech	= $config->planets_tech;
		$planetPerBonus	= $config->planets_officier;
		
		if($config->min_player_planets == 0)
		{
			$planetPerTech = 999;
		}

		if($config->min_player_planets == 0)
		{
			$planetPerBonus = 999;
		}
		
		// http://owiki.de/index.php/Astrophysik#.C3.9Cbersicht
		return (int) ceil($config->min_player_planets + min($planetPerTech, $USER[$resource[124]] * $config->planets_per_tech) + min($planetPerBonus, $USER['factor']['Planets']));
	}

	static public function allowPlanetPosition($position, $USER)
	{
		// http://owiki.de/index.php/Astrophysik#.C3.9Cbersicht

		global $resource;
		$config	= Config::get($USER['universe']);

		switch($position) {
			case 1:
			case ($config->max_planets):
				return $USER[$resource[124]] >= 8;
			break;
			case 2:
			case ($config->max_planets-1):
				return $USER[$resource[124]] >= 6;
			break;
			case 3:
			case ($config->max_planets-2):
				return $USER[$resource[124]] >= 4;
			break;
			default:
				return $USER[$resource[124]] >= 1;
			break;
		}
	}

	static public function sendMessage($userId, $senderId, $senderName, $messageType, $subject, $text, $time, $parentID = NULL, $unread = 1, $universe = NULL)
	{
		if(is_null($universe))
		{
			$universe = Universe::current();
		}

		$db = Database::get();

		$sql = "INSERT INTO %%MESSAGES%% SET
		message_owner		= :userId,
		message_sender		= :sender,
		message_time		= :time,
		message_type		= :type,
		message_from		= :from,
		message_subject 	= :subject,
		message_text		= :text,
		message_unread		= :unread,
		message_universe 	= :universe;";

		$db->insert($sql, array(
			':userId'	=> $userId,
			':sender'	=> $senderId,
			':time'		=> $time,
			':type'		=> $messageType,
			':from'		=> $senderName,
			':subject'	=> $subject,
			':text'		=> $text,
			':unread'	=> $unread,
			':universe'	=> $universe,
		));
	}
}
