CREATE TABLE IF NOT EXISTS `%PREFIX%frequent_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ownerID` int(10) unsigned NOT NULL,
  `galaxy` tinyint(3) unsigned NOT NULL,
  `system` smallint(5) unsigned NOT NULL,
  `planet` tinyint(3) unsigned NOT NULL,
  `type` tinyint(1) unsigned NOT NULL,
  `lastUsed` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `owner_coords` (`ownerID`,`galaxy`,`system`,`planet`,`type`),
  KEY `owner_recent` (`ownerID`,`lastUsed`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
