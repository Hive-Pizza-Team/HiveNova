ALTER TABLE `%PREFIX%users` ADD COLUMN `settings_push` tinyint(1) NOT NULL DEFAULT '1' AFTER `settings_blockPM`;
