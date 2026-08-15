CREATE TABLE IF NOT EXISTS `%PREFIX%achievements` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `universe` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `key` varchar(64) NOT NULL,
  `category` varchar(32) NOT NULL,
  `name_key` varchar(64) NOT NULL,
  `desc_key` varchar(64) NOT NULL,
  `trigger_type` varchar(32) NOT NULL,
  `trigger_params` text NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT '0',
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `reward_type` varchar(16) NOT NULL DEFAULT 'none',
  `reward_amount` double(50,0) NOT NULL DEFAULT '0',
  `points` smallint(5) unsigned NOT NULL DEFAULT '10',
  `celebration_tier` varchar(16) NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `universe_key` (`universe`, `key`),
  KEY `trigger_type` (`trigger_type`, `active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%PREFIX%user_achievement_progress` (
  `user_id` int(11) unsigned NOT NULL,
  `achievement_id` int(11) unsigned NOT NULL,
  `progress` bigint(20) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `achievement_id`),
  KEY `achievement_id` (`achievement_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%PREFIX%user_achievements` (
  `user_id` int(11) unsigned NOT NULL,
  `achievement_id` int(11) unsigned NOT NULL,
  `unlocked_at` int(11) NOT NULL DEFAULT '0',
  `celebrated` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `achievement_id`),
  KEY `achievement_id` (`achievement_id`),
  KEY `celebrated` (`user_id`, `celebrated`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%PREFIX%achievement_grants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `achievement_id` int(11) unsigned NOT NULL,
  `reward_type` varchar(16) NOT NULL,
  `reward_amount` double(50,0) NOT NULL DEFAULT '0',
  `granted_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `%PREFIX%achievements` (`universe`, `key`, `category`, `name_key`, `desc_key`, `trigger_type`, `trigger_params`, `sort_order`, `hidden`, `active`, `reward_type`, `reward_amount`, `points`, `celebration_tier`) VALUES
(1, 'combat_first_win', 'combat', 'ach_combat_first_win_name', 'ach_combat_first_win_desc', 'combat_wins', '{"threshold":1}', 10, 0, 1, 'darkmatter', 500, 25, 'legendary'),
(1, 'combat_wins_10', 'combat', 'ach_combat_wins_10_name', 'ach_combat_wins_10_desc', 'combat_wins', '{"threshold":10}', 20, 0, 1, 'darkmatter', 250, 15, 'normal'),
(1, 'combat_wins_25', 'combat', 'ach_combat_wins_25_name', 'ach_combat_wins_25_desc', 'combat_wins', '{"threshold":25}', 30, 0, 1, 'darkmatter', 500, 20, 'epic'),
(1, 'combat_wins_100', 'combat', 'ach_combat_wins_100_name', 'ach_combat_wins_100_desc', 'combat_wins', '{"threshold":100}', 40, 0, 1, 'darkmatter', 1500, 35, 'legendary'),
(1, 'combat_destroy_1m', 'combat', 'ach_combat_destroy_1m_name', 'ach_combat_destroy_1m_desc', 'units_destroyed', '{"threshold":1000000}', 50, 0, 1, 'darkmatter', 750, 25, 'epic'),
(1, 'combat_win_rate_50', 'combat', 'ach_combat_win_rate_50_name', 'ach_combat_win_rate_50_desc', 'combat_win_rate', '{"threshold":50,"min_fights":20}', 60, 1, 1, 'darkmatter', 1000, 30, 'epic'),
(1, 'economy_metal_mine_5', 'economy', 'ach_economy_metal_mine_5_name', 'ach_economy_metal_mine_5_desc', 'element_level', '{"element_id":1,"level":5}', 110, 0, 1, 'darkmatter', 100, 10, 'normal'),
(1, 'economy_metal_mine_10', 'economy', 'ach_economy_metal_mine_10_name', 'ach_economy_metal_mine_10_desc', 'element_level', '{"element_id":1,"level":10}', 120, 0, 1, 'darkmatter', 300, 20, 'epic'),
(1, 'research_astro_1', 'research', 'ach_research_astro_1_name', 'ach_research_astro_1_desc', 'element_level', '{"element_id":124,"level":1}', 210, 0, 1, 'darkmatter', 150, 15, 'normal'),
(1, 'research_astro_3', 'research', 'ach_research_astro_3_name', 'ach_research_astro_3_desc', 'element_level', '{"element_id":124,"level":3}', 220, 0, 1, 'darkmatter', 400, 25, 'epic'),
(1, 'fleet_points_50k', 'fleet', 'ach_fleet_points_50k_name', 'ach_fleet_points_50k_desc', 'stat_points', '{"stat":"fleet","threshold":50000}', 310, 0, 1, 'darkmatter', 200, 15, 'normal'),
(1, 'fleet_points_100k', 'fleet', 'ach_fleet_points_100k_name', 'ach_fleet_points_100k_desc', 'stat_points', '{"stat":"fleet","threshold":100000}', 320, 0, 1, 'darkmatter', 500, 25, 'epic'),
(1, 'empire_colony_2', 'empire', 'ach_empire_colony_2_name', 'ach_empire_colony_2_desc', 'planet_count', '{"threshold":2}', 410, 0, 1, 'darkmatter', 200, 15, 'normal'),
(1, 'empire_colony_5', 'empire', 'ach_empire_colony_5_name', 'ach_empire_colony_5_desc', 'planet_count', '{"threshold":5}', 420, 0, 1, 'darkmatter', 600, 30, 'legendary'),
(1, 'exploration_expedition_10', 'exploration', 'ach_exploration_expedition_10_name', 'ach_exploration_expedition_10_desc', 'expedition_count', '{"threshold":10}', 510, 0, 1, 'darkmatter', 150, 15, 'normal'),
(1, 'exploration_expedition_50', 'exploration', 'ach_exploration_expedition_50_name', 'ach_exploration_expedition_50_desc', 'expedition_count', '{"threshold":50}', 520, 0, 1, 'darkmatter', 500, 25, 'epic'),
(1, 'social_alliance', 'social', 'ach_social_alliance_name', 'ach_social_alliance_desc', 'ally_joined', '{"threshold":1}', 610, 0, 1, 'darkmatter', 100, 10, 'normal'),
(1, 'hive_linked', 'hive', 'ach_hive_linked_name', 'ach_hive_linked_desc', 'hive_account_valid', '{"threshold":1}', 710, 0, 1, 'darkmatter', 250, 20, 'legendary'),
(1, 'meta_points_10k', 'empire', 'ach_meta_points_10k_name', 'ach_meta_points_10k_desc', 'stat_points', '{"stat":"total","threshold":10000}', 430, 0, 1, 'darkmatter', 100, 10, 'normal'),
(1, 'meta_points_100k', 'empire', 'ach_meta_points_100k_name', 'ach_meta_points_100k_desc', 'stat_points', '{"stat":"total","threshold":100000}', 440, 0, 1, 'darkmatter', 750, 30, 'epic'),
(1, 'account_7_days', 'empire', 'ach_account_7_days_name', 'ach_account_7_days_desc', 'account_age_days', '{"threshold":7}', 450, 0, 1, 'darkmatter', 50, 10, 'normal');

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`) VALUES
('achievement_backfill', 1, '15', '3', '*', '*', '*', 'HiveNova\\Cronjob\\AchievementBackfillCronjob', 0, NULL);
