CREATE TABLE IF NOT EXISTS `%PREFIX%push_fleet_return_notified` (
  `fleet_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `mission` tinyint(3) unsigned NOT NULL,
  `galaxy` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `system` smallint(5) unsigned NOT NULL DEFAULT 0,
  `planet` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `planet_name` varchar(50) NOT NULL DEFAULT '',
  `return_end` int(10) unsigned NOT NULL,
  `notified_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`fleet_id`),
  KEY `user_id` (`user_id`),
  KEY `return_end` (`return_end`),
  KEY `notified_at` (`notified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'fleet_return_push', 1, '*', '*', '*', '*', '*', 'HiveNova\\Cronjob\\FleetReturnPushCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\FleetReturnPushCronjob'
);
