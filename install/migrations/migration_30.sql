ALTER TABLE `%PREFIX%config`
  ADD `feat_tracking_from_start` tinyint(1) unsigned NOT NULL DEFAULT '0',
  ADD `discord_feat_webhook` varchar(512) NOT NULL DEFAULT '',
  ADD `feat_banner_key` varchar(64) NOT NULL DEFAULT '',
  ADD `feat_banner_user_id` int(11) unsigned NOT NULL DEFAULT '0',
  ADD `feat_banner_at` int(11) NOT NULL DEFAULT '0';

ALTER TABLE `%PREFIX%achievements`
  ADD `hof_only` tinyint(1) unsigned NOT NULL DEFAULT '0';

CREATE TABLE IF NOT EXISTS `%PREFIX%feat_states` (
  `universe` tinyint(3) unsigned NOT NULL,
  `feat_key` varchar(64) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'unknown',
  `winner_id` int(11) unsigned NOT NULL DEFAULT '0',
  `claimed_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`universe`, `feat_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%PREFIX%feat_claims` (
  `universe` tinyint(3) unsigned NOT NULL,
  `feat_key` varchar(64) NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `claimed_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`universe`, `feat_key`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
