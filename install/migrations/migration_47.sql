CREATE TABLE IF NOT EXISTS `%PREFIX%push_building_notified` (
  `planet_id` int(10) unsigned NOT NULL,
  `element_id` smallint(5) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL,
  `build_end` int(10) unsigned NOT NULL,
  PRIMARY KEY (`planet_id`, `element_id`, `level`, `build_end`),
  KEY `build_end` (`build_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'building_complete_push', 1, '*', '*', '*', '*', '*', 'HiveNova\\Cronjob\\BuildingCompletePushCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\BuildingCompletePushCronjob'
);
