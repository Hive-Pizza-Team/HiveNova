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

use HiveNova\Core\HTTP;
use HiveNova\Core\Session;


// =============================================================================
// HTTP / URL / PATHS
// =============================================================================

//SET TIMEZONE (if Server Timezone are not correct)
//date_default_timezone_set('America/Chicago');

define('DEFAULT_THEME'	 		    , 'hive');
define('HTTPS'						, isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]  == 'on');
define('PROTOCOL'					, HTTPS ? 'https://' : 'http://');
if(PHP_SAPI === 'cli')
{
	$requestUrl	= str_replace(array(dirname(dirname(__FILE__)), '\\'), array('', '/'), $_SERVER["PHP_SELF"]);

	//debug mode
	define('HTTP_BASE'					, str_replace(array('\\', '//'), '/', dirname((string) $_SERVER['SCRIPT_NAME']).'/'));
	define('HTTP_ROOT'					, str_replace(basename((string) $_SERVER['SCRIPT_FILENAME']), '', parse_url($requestUrl, PHP_URL_PATH)));

	define('HTTP_FILE'					, basename((string) $_SERVER['SCRIPT_NAME']));
	define('HTTP_HOST'					, '127.0.0.1');
	define('HTTP_PATH'					, PROTOCOL.HTTP_HOST.HTTP_ROOT);
}
else
{
	define('HTTP_BASE'					, str_replace(array('\\', '//'), '/', dirname((string) $_SERVER['SCRIPT_NAME']).'/'));
	define('HTTP_ROOT'					, str_replace(basename((string) $_SERVER['SCRIPT_FILENAME']), '', parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH)));

	define('HTTP_FILE'					, basename((string) $_SERVER['SCRIPT_NAME']));
	define('HTTP_HOST'					, $_SERVER['HTTP_HOST']);
	define('HTTP_PATH'					, PROTOCOL.HTTP_HOST.HTTP_ROOT);
}

if(!defined('AJAX_CHAT_PATH')) {
	define('AJAX_CHAT_PATH', ROOT_PATH.'chat/');
}

if(!defined('CACHE_PATH')) {
	define('CACHE_PATH', ROOT_PATH.'cache/');
}

// =============================================================================
// EXTERNAL LINKS
// =============================================================================

define('DISCORD_URL'				, 'https://discord.gg/BWqmGbtuDn');

// =============================================================================
// GAME ENGINE
// =============================================================================

define('COMBAT_ENGINE'				, 'xnova');

// =============================================================================
// LOCALIZATION
// =============================================================================

// Fallback language for fatal error pages (before user session is loaded)
define('DEFAULT_LANG'				, 'de');

// UTF-8 support for names (required for non-english chars!)
define('UTF8_SUPPORT'				, true);

// =============================================================================
// UNIVERSE / MULTIVERSE
// =============================================================================

// Root universe and user IDs (used by admin/cron processes)
define('ROOT_UNI'					, 1);
define('ROOT_USER'					, 1);

// Enable wildcard subdomain routing (e.g. uni1.example.com, uni2.example.com)
define('UNIS_WILDCAST'				, false);

// =============================================================================
// AUTH LEVELS
// =============================================================================

// Used in $USER['authlevel'] — check with: $USER['authlevel'] >= AUTH_MOD
define('AUTH_ADM'					, 3);
define('AUTH_OPS'					, 2);
define('AUTH_MOD'					, 1);
define('AUTH_USR'					, 0);

// =============================================================================
// SESSION / SECURITY
// =============================================================================

// Max. User Session in Seconds
define('SESSION_LIFETIME'			, 604800);

// Prevent the use of one account on multiple devices simultaneously
define('PREVENT_MULTISESSIONS'		, false);

// How many IP octets to compare for multi-account detection
// 1 = (AAA); 2 = (AAA.BBB); 3 = (AAA.BBB.CCC)
define('COMPARE_IP_BLOCKS'			, 2);

// =============================================================================
// PLANETS / BUILDING
// =============================================================================

// Fields added per level of the Lunar Base building
define('FIELDS_BY_MOONBASIS_LEVEL'	, 3);

// Fields added per level of the Terraformer building
define('FIELDS_BY_TERRAFORMER'		, 5);

// =============================================================================
// FLEET
// =============================================================================

// Minimum fleet travel time in seconds
define('MIN_FLEET_TIME'				, 5);

// Fleet direction states (stored in fleet DB row)
define('FLEET_OUTWARD'				, 0);
define('FLEET_RETURN'				, 1);
define('FLEET_HOLD'					, 2);

// Age of fleet logs kept (seconds); must be >= BASH_TIME
define('FLEETLOG_AGE'				, 86400);

// Show fleet notification popup when under attack (enable_multialert)
define('ENABLE_MULTIALERT'			, false);

// Web Push (mobile notifications). Generate keys: vendor/bin/web-push generate-keys
define('PUSH_VAPID_PUBLIC'			, '');
define('PUSH_VAPID_PRIVATE'			, '');
define('PUSH_VAPID_SUBJECT'			, 'mailto:support@hive.pizza');

// =============================================================================
// COMBAT
// =============================================================================

// Maximum battle rounds per combat simulation
define('MAX_ATTACK_ROUNDS'			, 6);

// =============================================================================
// BASH PROTECTION
// =============================================================================

// Enable bash protection (limits attacks on same target within BASH_TIME)
define('BASH_ON'					, true);

// Max attacks on same target within BASH_TIME before protection kicks in
define('BASH_COUNT'					, 6);

// Window in seconds for bash protection evaluation
define('BASH_TIME'					, 86400);

// 0 = bash protection always active; 1 = bash protection disabled during wars
define('BASH_WAR'					, 1);

// =============================================================================
// SPY MECHANICS
// =============================================================================

/*
	Difficulty of spying scales with tech level difference:
	  if sender tech > target tech: easier
	  else: harder
	  formula: min_spies = (abs(sender_tech - target_tech) * SPY_DIFFENCE_FACTOR) ^ 2
*/
define('SPY_DIFFENCE_FACTOR'		, 1);

/*
	Additional spies needed to reveal each layer (see MissionCaseSpy.php#78):
	  Fleet     = min_spies
	  Defense   = min_spies + 1 * SPY_VIEW_FACTOR
	  Buildings = min_spies + 3 * SPY_VIEW_FACTOR
	  Technology= min_spies + 5 * SPY_VIEW_FACTOR
*/
define('SPY_VIEW_FACTOR'			, 1);

// =============================================================================
// PHALANX
// =============================================================================

// Deuterium cost per phalanx scan
define('PHALANX_DEUTERIUM'			, 5000);

// =============================================================================
// SHIPYARD
// =============================================================================

// Fraction of resources refunded when cancelling a shipyard queue item
define('FACTOR_CANCEL_SHIPYARD'		, 0.6);

// =============================================================================
// PLAYER INACTIVITY
// =============================================================================

// Seconds until (i) marker appears on galaxy map
define('INACTIVE'					, 604800);

// Seconds until (i I) long-inactive marker appears on galaxy map
define('INACTIVE_LONG'				, 2419200);

// Cooldown in seconds before a player can change their username again
define('USERNAME_CHANGETIME'		, 604800);

// =============================================================================
// UI / SEARCH / MESSAGES
// =============================================================================

// Max search results (-1 = unlimited)
define('SEARCH_LIMIT'				, 25);

// Messages shown per page in the message list
define('MESSAGES_PER_PAGE'			, 10);

// Banned users shown per page in the ban list
define('BANNED_USERS_PER_PAGE'		, 25);

// Show one-click simulation link on spy reports
define('ENABLE_SIMULATOR_LINK'		, true);

// =============================================================================
// MODULES
// Each game page declares $requireModule = MODULE_XYZ.
// Modules can be disabled per-universe in the DB (uni1_config).
// MODULE_AMOUNT must equal the highest module ID + 1.
// =============================================================================

define('MODULE_AMOUNT'				, 43);
define('MODULE_ALLIANCE'			, 0);
define('MODULE_MISSION_ATTACK'		, 1);
define('MODULE_BUILDING'			, 2);
define('MODULE_RESEARCH'			, 3);
define('MODULE_SHIPYARD_FLEET'		, 4);
define('MODULE_SHIPYARD_DEFENSIVE'	, 5);
define('MODULE_BUDDYLIST'			, 6);
define('MODULE_CHAT'				, 7);
define('MODULE_DMEXTRAS'			, 8);
define('MODULE_FLEET_TABLE'			, 9);
define('MODULE_FLEET_EVENTS'		, 10);
define('MODULE_GALAXY'				, 11);
define('MODULE_BATTLEHALL'			, 12);
define('MODULE_TRADER'				, 13);
define('MODULE_INFORMATION'			, 14);
define('MODULE_IMPERIUM'			, 15);
define('MODULE_MESSAGES'			, 16);
define('MODULE_NOTICE'				, 17);
define('MODULE_OFFICIER'			, 18);
define('MODULE_PHALANX'				, 19);
define('MODULE_PLAYERCARD'			, 20);
define('MODULE_BANLIST'				, 21);
define('MODULE_RECORDS'				, 22);
define('MODULE_RESSOURCE_LIST'		, 23);
define('MODULE_MISSION_SPY'			, 24);
define('MODULE_STATISTICS'			, 25);
define('MODULE_SEARCH'				, 26);
define('MODULE_SUPPORT'				, 27);
define('MODULE_TECHTREE'			, 28);
define('MODULE_MISSION_DESTROY'		, 29);
define('MODULE_MISSION_EXPEDITION'	, 30);
define('MODULE_MISSION_DARKMATTER'	, 31);
define('MODULE_MISSION_RECYCLE'		, 32);
define('MODULE_MISSION_HOLD'		, 33);
define('MODULE_MISSION_TRANSPORT'	, 34);
define('MODULE_MISSION_COLONY'		, 35);
define('MODULE_MISSION_STATION'		, 36);
define('MODULE_BANNER'				, 37);
define('MODULE_FLEET_TRADER'		, 38);
define('MODULE_SIMULATOR'			, 39);
define('MODULE_MISSILEATTACK'		, 40);
define('MODULE_SHORTCUTS'			, 41);
define('MODULE_MISSION_ACS'			, 42);
define('MODULE_MISSION_TRADE'		, 44);
define('MODULE_MISSION_TRANSFER'	, 45);

// =============================================================================
// ELEMENT / RESOURCE TYPE FLAGS (bitmask)
// Used in $pricelist and vars tables to describe element capabilities.
// Element IDs are partitioned by type (see ranges below).
// =============================================================================

// Element type flags — identify what kind of game element an ID represents
define('ELEMENT_BUILD'				, 1);   // Buildings       — ID   1 –  99
define('ELEMENT_TECH'				, 2);   // Technologies    — ID 101 – 199
define('ELEMENT_FLEET'				, 4);   // Ships           — ID 201 – 399
define('ELEMENT_DEFENSIVE'			, 8);   // Defense         — ID 401 – 599
define('ELEMENT_OFFICIER'			, 16);  // Officers        — ID 601 – 699
define('ELEMENT_BONUS'				, 32);  // Bonuses         — ID 701 – 799
define('ELEMENT_RACE'				, 64);  // Race abilities  — ID 801 – 899
define('ELEMENT_PLANET_RESOURCE'	, 128); // Planet resources— ID 901 – 949 (metal/crystal/deut)
define('ELEMENT_USER_RESOURCE'		, 256); // User resources  — ID 951 – 999 (dark matter)

// Reserved bitmask slots: 512, 1024, 2048, 4096, 8192, 16384, 32768

// Element property flags — describe element behaviour (OR-combined with type flags)
define('ELEMENT_PRODUCTION'			, 65536);    // Produces resources
define('ELEMENT_STORAGE'			, 131072);   // Stores resources
define('ELEMENT_ONEPERPLANET'		, 262144);   // Only one allowed per planet
define('ELEMENT_BOUNS'				, 524288);   // Grants a bonus (note: legacy typo kept for DB compat)
define('ELEMENT_BUILD_ON_PLANET'	, 1048576);  // Can be built on planets
define('ELEMENT_BUILD_ON_MOONS'		, 2097152);  // Can be built on moons
define('ELEMENT_RESOURCE_ON_TF'		, 4194304);  // Resource available on terraform
define('ELEMENT_RESOURCE_ON_FLEET'	, 8388608);  // Resource carried by fleets
define('ELEMENT_RESOURCE_ON_STEAL'	, 16777216); // Resource can be stolen in combat
