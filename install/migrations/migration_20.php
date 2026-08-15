<?php

use HiveNova\Core\PlanetImageUtil;

/** @var PDO $pdo */

if (!defined('DB_PREFIX')) {
	require dirname(__DIR__, 2) . '/includes/dbtables.php';
}

$prefix = DB_PREFIX;

$stmt = $pdo->query(
	"SELECT id, image, temp_min, temp_max FROM `{$prefix}planets` WHERE planet_type = 1"
);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$newImage = PlanetImageUtil::remapLegacyImage(
		(string) $row['image'],
		(int) $row['temp_min'],
		(int) $row['temp_max']
	);

	if ($newImage === $row['image']) {
		continue;
	}

	$update = $pdo->prepare(
		"UPDATE `{$prefix}planets` SET image = :image WHERE id = :id"
	);
	$update->execute([
		':image' => $newImage,
		':id'     => (int) $row['id'],
	]);
}
