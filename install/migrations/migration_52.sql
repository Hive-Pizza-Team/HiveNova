-- Admin-gated Hive account create requests (#638 P1; migration 52 (51 is push_expired_endpoints)).
CREATE TABLE IF NOT EXISTS `%PREFIX%hive_account_create_requests` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`user_id` int(10) unsigned NOT NULL,
	`universe` tinyint(3) unsigned NOT NULL,
	`desired_name` varchar(16) NOT NULL,
	`status` varchar(16) NOT NULL DEFAULT 'pending',
	`reject_reason` varchar(255) NOT NULL DEFAULT '',
	`created_at` int(10) unsigned NOT NULL,
	`reviewed_at` int(10) unsigned NOT NULL DEFAULT 0,
	`reviewed_by` int(10) unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `status_created` (`status`, `created_at`),
	KEY `user_universe_status` (`user_id`, `universe`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
