-- Remember FCM/Web Push endpoints that returned 410 so a page load cannot save them again.
CREATE TABLE IF NOT EXISTS `%PREFIX%push_expired_endpoints` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint` varchar(2048) NOT NULL,
  `expired_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint` (`endpoint`(768))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
