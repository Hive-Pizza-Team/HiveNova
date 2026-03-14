ALTER TABLE `%PREFIX%cronjobs` MODIFY `class` varchar(128) NOT NULL;
UPDATE `%PREFIX%cronjobs` SET `class` = CONCAT('HiveNova\\Cronjob\\', `class`) WHERE `class` NOT LIKE 'HiveNova\\%';
RENAME TABLE `%PREFIX%vars_requriements` TO `%PREFIX%vars_requirements`;

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
