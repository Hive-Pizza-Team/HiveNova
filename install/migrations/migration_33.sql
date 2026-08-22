ALTER TABLE `%PREFIX%config`
  ADD `season_mode` tinyint(1) unsigned NOT NULL DEFAULT '0',
  ADD `season_length_seconds` int(10) unsigned NOT NULL DEFAULT '604800',
  ADD `season_preclose_seconds` int(10) unsigned NOT NULL DEFAULT '14400',
  ADD `season_house_cut_percent` decimal(5,2) unsigned NOT NULL DEFAULT '10.00',
  ADD `season_min_points` bigint(20) unsigned NOT NULL DEFAULT '0',
  ADD `season_entry_pizza` decimal(10,3) unsigned NOT NULL DEFAULT '0.100',
  ADD `season_wallet_account` varchar(16) NOT NULL DEFAULT '',
  ADD `season_wallet_active_key` varchar(80) NOT NULL DEFAULT '',
  ADD `season_id` int(10) unsigned NOT NULL DEFAULT '0',
  ADD `season_starts_at` int(11) unsigned NOT NULL DEFAULT '0',
  ADD `season_closes_at` int(11) unsigned NOT NULL DEFAULT '0',
  ADD `season_status` varchar(16) NOT NULL DEFAULT 'idle',
  ADD `season_last_reminder` varchar(128) NOT NULL DEFAULT '';

CREATE TABLE `%PREFIX%season_weeks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `starts_at` int(11) unsigned NOT NULL DEFAULT '0',
  `closes_at` int(11) unsigned NOT NULL DEFAULT '0',
  `status` varchar(16) NOT NULL DEFAULT 'running',
  `pool_pizza` decimal(20,3) unsigned NOT NULL DEFAULT '0.000',
  `house_cut_pizza` decimal(20,3) unsigned NOT NULL DEFAULT '0.000',
  `payout_budget` decimal(20,3) unsigned NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_season` (`universe`,`season_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `%PREFIX%season_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `hive_account` varchar(16) NOT NULL DEFAULT '',
  `pizza_amount` decimal(20,3) unsigned NOT NULL DEFAULT '0.000',
  `trx_id` varchar(80) NOT NULL DEFAULT '',
  `created_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_season_user` (`universe`,`season_id`,`user_id`),
  KEY `trx` (`universe`,`trx_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `%PREFIX%season_snapshots` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `hive_account` varchar(16) NOT NULL DEFAULT '',
  `rank` int(10) unsigned NOT NULL DEFAULT '0',
  `points` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_season_user` (`universe`,`season_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `%PREFIX%season_payouts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `hive_account` varchar(16) NOT NULL DEFAULT '',
  `rank` int(10) unsigned NOT NULL DEFAULT '0',
  `points` bigint(20) NOT NULL DEFAULT '0',
  `pizza_amount` decimal(20,3) unsigned NOT NULL DEFAULT '0.000',
  `trx_id` varchar(80) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `universe_season_status` (`universe`,`season_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'season', 1, '*/15', '*', '*', '*', '*', 'HiveNova\\Cronjob\\SeasonCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\SeasonCronjob'
);
