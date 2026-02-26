<?php

/**
 * Sparse game-data fixture for unit tests.
 *
 * Uses $GLOBALS explicitly so the values survive PHPUnit's bootstrap loading
 * (which runs require_once inside a static method scope, not true global scope).
 *
 * Only the keys actually accessed by the methods under test are defined here.
 * Add entries as needed when new test classes require them.
 */

// ---------------------------------------------------------------------------
// $resource  — element-ID → column-name map
// ---------------------------------------------------------------------------

$GLOBALS['resource'] = array_replace($GLOBALS['resource'] ?? [], [
	1   => 'metal_mine',
	2   => 'crystal_mine',
	6   => 'research_lab',
	14  => 'robotic_factory',
	15  => 'nanite_factory',
	21  => 'hangar',
	22  => 'solar_plant',
	31  => 'intergalactic_research',

	108 => 'computer_tech',
	109 => 'weapons_tech',
	110 => 'shielding_tech',
	111 => 'armour_tech',
	113 => 'energy_tech',
	115 => 'combustion_tech',
	117 => 'impulse_motor_tech',
	118 => 'hyperspace_motor_tech',
	124 => 'astrophysics_tech',

	202 => 'light_fighter',
	210 => 'bomber',

	901 => 'metal',
	902 => 'crystal',
	903 => 'deuterium',
	911 => 'energy',
]);

// ---------------------------------------------------------------------------
// $reslist  — category → array of element IDs
// ---------------------------------------------------------------------------

$GLOBALS['reslist'] = array_replace($GLOBALS['reslist'] ?? [], [
	'build'     => [1, 2, 3, 4, 6, 12, 14, 15, 21, 22, 23, 24, 31, 33, 34, 41, 42, 43, 44],
	'tech'      => [106, 108, 109, 110, 111, 113, 114, 115, 117, 118, 120, 121, 122, 123, 124],
	'fleet'     => [202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214, 215],
	'defense'   => [401, 402, 403, 404, 405, 406, 407, 408],
	'missile'   => [502, 503],
	'officier'  => [601, 602, 603, 604, 605, 606, 607, 608, 609, 610, 611, 612, 613, 614, 615],
	'dmfunc'    => [701, 702, 703, 704, 705, 706, 707],
	'one'       => [31],
	'ressources'=> [901, 902, 903],
]);

// ---------------------------------------------------------------------------
// $pricelist  — element-ID → cost/property map
// ---------------------------------------------------------------------------

$GLOBALS['pricelist'] = array_replace($GLOBALS['pricelist'] ?? [], [
	// Metal mine (1) — building
	1   => [
		'cost'   => [901 => 60, 902 => 15, 903 => 0],
		'factor' => 1.5,
		'time'   => 1,
	],
	// Light Fighter (202) — combustion-drive ship
	202 => [
		'cost'         => [901 => 3000, 902 => 1000, 903 => 0],
		'capacity'     => 50,
		'consumption'  => 20,
		'consumption2' => 10,   // when impulse_motor_tech >= 5
		'speed'        => 12500,
		'speed2'       => 17500, // when impulse_motor_tech >= 5
		'tech'         => 1,    // 1=combustion 2=impulse 3=hyperspace
		'factor'       => 0,
		'time'         => 2,
	],
	// Bomber (210) — impulse-drive ship
	210 => [
		'cost'        => [901 => 50000, 902 => 25000, 903 => 15000],
		'capacity'    => 500,
		'consumption' => 700,
		'speed'       => 4000,
		'tech'        => 2,
		'factor'      => 0,
		'time'        => 200,
	],
	// IPM (503) — missile
	503 => [
		'cost'   => [901 => 12500, 902 => 2500, 903 => 10000],
		'attack' => 12000,
	],
	// Rocket launcher (401) — defence
	401 => [
		'cost'   => [901 => 2000, 902 => 0, 903 => 0],
		'factor' => 0,
	],
	// Light laser (402) — defence
	402 => [
		'cost'   => [901 => 1500, 902 => 500, 903 => 0],
		'factor' => 0,
	],
]);

// ---------------------------------------------------------------------------
// $CombatCaps  — element-ID → combat stats
// ---------------------------------------------------------------------------

$GLOBALS['CombatCaps'] = array_replace($GLOBALS['CombatCaps'] ?? [], [
	503 => ['attack' => 12000],
	401 => ['attack' => 80,  'shield' => 20,  'plunder' => 40000],
	402 => ['attack' => 100, 'shield' => 25,  'plunder' => 20000],
]);

// ---------------------------------------------------------------------------
// $requirements  — element-ID → [required-element-ID => min-level]
// ---------------------------------------------------------------------------

$GLOBALS['requirements'] = array_replace($GLOBALS['requirements'] ?? [], [
	202 => [115 => 1],               // Light Fighter: Combustion Drive lv 1
	210 => [117 => 6, 118 => 3],     // Bomber: Impulse lv6 + Hyperspace lv3
]);
