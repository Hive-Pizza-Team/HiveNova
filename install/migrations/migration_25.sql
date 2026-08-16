CREATE TABLE IF NOT EXISTS `%PREFIX%universe_events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `universe` tinyint(3) unsigned NOT NULL,
  `time` int(11) NOT NULL,
  `event_type` varchar(16) NOT NULL,
  `size_bucket` varchar(16) NOT NULL,
  `outcome` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `universe_id` (`universe`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
