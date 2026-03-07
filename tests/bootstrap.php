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
