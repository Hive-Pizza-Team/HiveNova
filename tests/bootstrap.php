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
