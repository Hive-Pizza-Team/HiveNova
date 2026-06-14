ALTER TABLE `%PREFIX%push_subscriptions` MODIFY `endpoint` varchar(2048) NOT NULL;
ALTER TABLE `%PREFIX%push_subscriptions` DROP INDEX `endpoint`;
ALTER TABLE `%PREFIX%push_subscriptions` ADD UNIQUE KEY `endpoint` (`endpoint`(768));
