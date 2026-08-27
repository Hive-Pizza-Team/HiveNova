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

// VARS DB -> SCRIPT WRAPPER

$cache	= \HiveNova\Core\Cache::get();
$cache->add('vars', 'HiveNova\\Core\\Cache\\VarsBuildCache');
extract($cache->getData('vars'));

$resource[RESOURCE_METAL]      = 'metal';
$resource[RESOURCE_CRYSTAL]    = 'crystal';
$resource[RESOURCE_DEUTERIUM]  = 'deuterium';
$resource[RESOURCE_ENERGY]     = 'energy';
$resource[RESOURCE_DARKMATTER] = 'darkmatter';

$reslist['ressources']  = array(RESOURCE_METAL, RESOURCE_CRYSTAL, RESOURCE_DEUTERIUM, RESOURCE_ENERGY, RESOURCE_DARKMATTER);
$reslist['resstype'][1] = array(RESOURCE_METAL, RESOURCE_CRYSTAL, RESOURCE_DEUTERIUM);
$reslist['resstype'][2] = array(RESOURCE_ENERGY);
$reslist['resstype'][3] = array(RESOURCE_DARKMATTER);