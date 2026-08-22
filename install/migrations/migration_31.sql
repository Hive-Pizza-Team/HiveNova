CREATE TABLE IF NOT EXISTS `%PREFIX%directive_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` tinyint(3) unsigned NOT NULL,
  `period_start` int(11) unsigned NOT NULL,
  `period_end` int(11) unsigned NOT NULL,
  `created_at` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_period_start` (`universe`, `period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `%PREFIX%user_directives` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `period_id` int(10) unsigned NOT NULL,
  `directive_key` varchar(32) NOT NULL,
  `progress_json` text,
  `completed_at` int(11) unsigned DEFAULT NULL,
  `reward_claimed_at` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_period` (`user_id`, `period_id`),
  KEY `period_id` (`period_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `%PREFIX%fleets`
  ADD `fleet_meta` text NULL;

ALTER TABLE `%PREFIX%log_fleets`
  ADD `fleet_meta` text NULL;

CREATE TABLE IF NOT EXISTS `%PREFIX%expedition_pending_choices` (
  `fleet_id` bigint(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `fleet_start_id` int(11) unsigned NOT NULL DEFAULT '0',
  `encounter_key` varchar(32) NOT NULL,
  `options_json` text NOT NULL,
  `stance` varchar(16) NOT NULL DEFAULT 'balanced',
  `resolved_at` int(11) unsigned DEFAULT NULL,
  `created_at` int(11) unsigned NOT NULL,
  PRIMARY KEY (`fleet_id`),
  KEY `user_unresolved` (`user_id`, `resolved_at`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'directive_period', 1, '0', '*', '*', '*', '*', 'HiveNova\\Cronjob\\DirectivePeriodCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\DirectivePeriodCronjob'
);
