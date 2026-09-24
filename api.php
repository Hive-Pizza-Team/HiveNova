<?php

define('MODE', 'API');
define('ROOT_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');
set_include_path(ROOT_PATH);

require 'includes/common.php';

(new \HiveNova\Core\ApiFrontController())->dispatch();
