ALTER TABLE `%PREFIX%config`
  ADD `hive_social_memo_active` tinyint(1) unsigned NOT NULL DEFAULT '0',
  ADD `hive_social_memo_memo_key` varchar(80) NOT NULL DEFAULT '';

CREATE TABLE `%PREFIX%hive_social_memo_queue` (
  `queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `kind` varchar(8) NOT NULL,
  `sender_name` varchar(32) NOT NULL,
  `lang` varchar(2) NOT NULL DEFAULT 'en',
  `created` int(11) unsigned NOT NULL,
  `claimed` int(11) unsigned DEFAULT NULL,
  `sent_at` int(11) unsigned DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`queue_id`),
  KEY `pending` (`sent_at`, `attempts`, `claimed`),
  KEY `user_kind` (`user_id`, `kind`, `sent_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'hive_social_memo', 1, '*/5', '*', '*', '*', '*', 'HiveNova\\Cronjob\\SocialHiveMemoCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\SocialHiveMemoCronjob'
);
