CREATE TABLE IF NOT EXISTS `%PREFIX%salvage_packages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `universe` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `galaxy` int(11) unsigned NOT NULL,
  `system` int(11) unsigned NOT NULL,
  `planet` int(11) unsigned NOT NULL,
  `planet_id` int(11) unsigned DEFAULT NULL,
  `metal` double(50,0) unsigned NOT NULL DEFAULT '0',
  `crystal` double(50,0) unsigned NOT NULL DEFAULT '0',
  `spawned_at` int(11) unsigned NOT NULL DEFAULT '0',
  `expires_at` int(11) unsigned NOT NULL DEFAULT '0',
  `tier` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `encounter_seed` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `coords` (`universe`, `galaxy`, `system`, `planet`),
  KEY `expires_at` (`expires_at`),
  KEY `planet_id` (`planet_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
VALUES ('pve_spawn', 1, '*/15', '*', '*', '*', '*', 'HiveNova\\Cronjob\\PveSpawnCronjob', 0, NULL);
