<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Constants required by some classes
if (!defined('UTF8_SUPPORT'))  define('UTF8_SUPPORT',  true);
if (!defined('TIMESTAMP'))     define('TIMESTAMP',      time());
if (!defined('MODE'))          define('MODE',           'INSTALL');
if (!defined('DEFAULT_LANG'))  define('DEFAULT_LANG',   'en');
if (!defined('MIN_FLEET_TIME')) define('MIN_FLEET_TIME', 10);
if (!defined('INACTIVE'))      define('INACTIVE',       7 * 86400);
if (!defined('INACTIVE_LONG')) define('INACTIVE_LONG',  28 * 86400);

// Pure utility classes — no DB or global state required
require_once __DIR__ . '/../includes/classes/ArrayUtil.class.php';
require_once __DIR__ . '/../includes/classes/PlayerUtil.class.php';
require_once __DIR__ . '/../includes/classes/class.FleetFunctions.php';
require_once __DIR__ . '/../includes/classes/HTTP.class.php';
require_once __DIR__ . '/../includes/classes/Language.class.php';
require_once __DIR__ . '/../includes/GeneralFunctions.php';

// Element-type bitmask constants (defined in includes/constants.php)
if (!defined('ELEMENT_BUILD'))      define('ELEMENT_BUILD',      1);
if (!defined('ELEMENT_TECH'))       define('ELEMENT_TECH',       2);
if (!defined('ELEMENT_FLEET'))      define('ELEMENT_FLEET',      4);
if (!defined('ELEMENT_DEFENSIVE'))  define('ELEMENT_DEFENSIVE',  8);
if (!defined('ELEMENT_OFFICIER'))   define('ELEMENT_OFFICIER',   16);
if (!defined('ELEMENT_BONUS'))      define('ELEMENT_BONUS',      32);
if (!defined('FLEET_OUTWARD'))      define('FLEET_OUTWARD',      0);
if (!defined('FLEET_RETURN'))       define('FLEET_RETURN',       1);
if (!defined('FLEET_HOLD'))         define('FLEET_HOLD',         2);

// Shared game-data fixture (populates $pricelist, $resource, $reslist, $requirements, $CombatCaps)
require_once __DIR__ . '/fixtures/game_data.php';

// Testable infrastructure classes
require_once __DIR__ . '/../includes/classes/DatabaseInterface.php';
require_once __DIR__ . '/../includes/classes/Database.class.php';
require_once __DIR__ . '/../includes/classes/Config.class.php';
require_once __DIR__ . '/../includes/classes/class.PlanetRessUpdate.php';

// Additional testable source files
require_once __DIR__ . '/../includes/classes/class.BuildFunctions.php';
require_once __DIR__ . '/../includes/classes/missions/functions/calculateSteal.php';
require_once __DIR__ . '/../includes/classes/missions/functions/calculateMIPAttack.php';

// ---------------------------------------------------------------------------
// OPBE battle engine
// ---------------------------------------------------------------------------
$_opbePath = __DIR__ . '/../includes/libs/opbe/';
if (!defined('OPBEPATH')) define('OPBEPATH', $_opbePath);
unset($_opbePath);

// OPBE uses log_var/log_comment for debug output — stub them for tests
if (!function_exists('log_var'))     { function log_var($name, $value) {} }
if (!function_exists('log_comment')) { function log_comment($comment)  {} }

// Game constants that OPBE.php normally defines
if (!defined('ID_MIN_SHIPS'))        define('ID_MIN_SHIPS',        100);
if (!defined('ID_MAX_SHIPS'))        define('ID_MAX_SHIPS',        300);
if (!defined('HOME_FLEET'))          define('HOME_FLEET',          0);
if (!defined('METAL_ID'))            define('METAL_ID',            901);
if (!defined('CRYSTAL_ID'))          define('CRYSTAL_ID',          902);
if (!defined('MAX_MOON_PROB'))       define('MAX_MOON_PROB',       20);
if (!defined('SHIP_DEBRIS_FACTOR'))  define('SHIP_DEBRIS_FACTOR',  0.3);
if (!defined('DEFENSE_DEBRIS_FACTOR')) define('DEFENSE_DEBRIS_FACTOR', 0.0);

require_once OPBEPATH . 'constants/battle_constants.php';
require_once OPBEPATH . 'utils/GeometricDistribution.php';
require_once OPBEPATH . 'utils/Gauss.php';
require_once OPBEPATH . 'utils/IterableUtil.php';
require_once OPBEPATH . 'utils/Math.php';
require_once OPBEPATH . 'utils/Number.php';
require_once OPBEPATH . 'utils/Events.php';
require_once OPBEPATH . 'utils/Lang.php';
require_once OPBEPATH . 'utils/LangManager.php';
require_once OPBEPATH . 'models/Type.php';
require_once OPBEPATH . 'models/ShipType.php';
require_once OPBEPATH . 'models/Fleet.php';
require_once OPBEPATH . 'models/HomeFleet.php';
require_once OPBEPATH . 'models/Defense.php';
require_once OPBEPATH . 'models/Ship.php';
require_once OPBEPATH . 'models/Player.php';
require_once OPBEPATH . 'models/PlayerGroup.php';
require_once OPBEPATH . 'combatObject/Fire.php';
require_once OPBEPATH . 'combatObject/PhysicShot.php';
require_once OPBEPATH . 'combatObject/ShipsCleaner.php';
require_once OPBEPATH . 'combatObject/FireManager.php';
require_once OPBEPATH . 'core/Round.php';
require_once OPBEPATH . 'core/BattleReport.php';
require_once OPBEPATH . 'core/Battle.php';
