-- Uni3 email play + Hive claim gate: one Hive seat per season, in-game season medal, pilot events.
CREATE TABLE `%PREFIX%season_hive_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `hive_account` varchar(16) NOT NULL,
  `origin` varchar(16) NOT NULL DEFAULT 'email',
  `linked_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_season_user` (`universe`,`season_id`,`user_id`),
  UNIQUE KEY `universe_season_hive` (`universe`,`season_id`,`hive_account`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `%PREFIX%season_medals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `hive_account` varchar(16) NOT NULL DEFAULT '',
  `tier` varchar(16) NOT NULL DEFAULT 'participant',
  `status` varchar(16) NOT NULL DEFAULT 'pending_claim',
  `points` bigint(20) NOT NULL DEFAULT '0',
  `claimed_at` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_season_user` (`universe`,`season_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `%PREFIX%uni3_pilot_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(32) NOT NULL,
  `universe` int(11) NOT NULL,
  `season_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `detail` varchar(255) NOT NULL DEFAULT '',
  `created_at` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_created` (`event`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
