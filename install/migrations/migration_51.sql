-- Track which release notes a player has already viewed (What's New page).
ALTER TABLE `%PREFIX%users`
	ADD COLUMN `last_seen_release` VARCHAR(16) NULL DEFAULT NULL;
