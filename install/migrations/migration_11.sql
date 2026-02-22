CREATE TABLE `%PREFIX%log_buildings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) unsigned NOT NULL,
  `planet_id` int(11) unsigned NOT NULL,
  `universe` tinyint(3) unsigned NOT NULL,
  `element_id` smallint(5) unsigned NOT NULL,
  `queued_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner_time` (`owner_id`, `queued_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `%PREFIX%log_research` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` int(11) unsigned NOT NULL,
  `planet_id` int(11) unsigned NOT NULL,
  `universe` tinyint(3) unsigned NOT NULL,
  `element_id` smallint(5) unsigned NOT NULL,
  `queued_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `owner_time` (`owner_id`, `queued_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 11;
