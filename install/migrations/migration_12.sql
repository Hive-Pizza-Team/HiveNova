ALTER TABLE `%PREFIX%log_buildings`
  ADD COLUMN `metal`     bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `element_id`,
  ADD COLUMN `crystal`   bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `metal`,
  ADD COLUMN `deuterium` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `crystal`;

ALTER TABLE `%PREFIX%log_research`
  ADD COLUMN `metal`     bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `element_id`,
  ADD COLUMN `crystal`   bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `metal`,
  ADD COLUMN `deuterium` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `crystal`;

ALTER TABLE `%PREFIX%log_shipyard`
  ADD COLUMN `count`     int(10) unsigned NOT NULL DEFAULT 1 AFTER `element_id`,
  ADD COLUMN `metal`     bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `count`,
  ADD COLUMN `crystal`   bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `metal`,
  ADD COLUMN `deuterium` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `crystal`;

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 12;
