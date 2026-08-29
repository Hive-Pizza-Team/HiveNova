-- Lobby / firehose: optional public actor names for richer activity rows.
ALTER TABLE `%PREFIX%universe_events`
	ADD `actor_name` varchar(32) NOT NULL DEFAULT '',
	ADD `target_name` varchar(32) NOT NULL DEFAULT '';
