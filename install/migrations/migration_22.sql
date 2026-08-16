ALTER TABLE `%PREFIX%users` ADD COLUMN `public_message` varchar(2000) NOT NULL DEFAULT '' AFTER `hive_account`;
