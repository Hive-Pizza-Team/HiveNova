CREATE TABLE IF NOT EXISTS `%PREFIX%bot_detection_state` (
  `universe` tinyint(3) unsigned NOT NULL,
  `last_digest_hash` char(64) NOT NULL DEFAULT '',
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`universe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `%PREFIX%log_fleets`
  ADD KEY `universe_start` (`fleet_universe`, `fleet_start_time`, `fleet_owner`);

ALTER TABLE `%PREFIX%log_buildings`
  ADD KEY `universe_time` (`universe`, `queued_at`, `owner_id`);

ALTER TABLE `%PREFIX%log_research`
  ADD KEY `universe_time` (`universe`, `queued_at`, `owner_id`);

ALTER TABLE `%PREFIX%log_shipyard`
  ADD KEY `universe_time` (`universe`, `queued_at`, `owner_id`);

UPDATE `%PREFIX%cronjobs`
SET
  `min` = '0',
  `hours` = '0',
  `dom` = '*',
  `month` = '*',
  `dow` = '*',
  `class` = 'HiveNova\\Cronjob\\BotDetectionCronjob',
  `nextTime` = 0
WHERE `name` = 'botdetect';

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'botdetect', 1, '0', '0', '*', '*', '*', 'HiveNova\\Cronjob\\BotDetectionCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `%PREFIX%cronjobs` WHERE `name` = 'botdetect'
);
