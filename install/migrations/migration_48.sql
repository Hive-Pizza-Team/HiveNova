CREATE TABLE IF NOT EXISTS `%PREFIX%push_research_notified` (
  `user_id` int(10) unsigned NOT NULL,
  `element_id` smallint(5) unsigned NOT NULL,
  `level` smallint(5) unsigned NOT NULL,
  `tech_end` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`, `element_id`, `level`, `tech_end`),
  KEY `tech_end` (`tech_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'research_complete_push', 1, '*', '*', '*', '*', '*', 'HiveNova\\Cronjob\\ResearchCompletePushCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\ResearchCompletePushCronjob'
);
