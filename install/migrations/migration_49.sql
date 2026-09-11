ALTER TABLE `%PREFIX%push_research_notified`
	ADD COLUMN `notified_at` int(10) unsigned NOT NULL DEFAULT 0,
	ADD KEY `notified_at` (`notified_at`);

ALTER TABLE `%PREFIX%push_building_notified`
	ADD COLUMN `notified_at` int(10) unsigned NOT NULL DEFAULT 0,
	ADD KEY `notified_at` (`notified_at`);

UPDATE `%PREFIX%push_research_notified` SET `notified_at` = UNIX_TIMESTAMP() WHERE `notified_at` = 0;
UPDATE `%PREFIX%push_building_notified` SET `notified_at` = UNIX_TIMESTAMP() WHERE `notified_at` = 0;
