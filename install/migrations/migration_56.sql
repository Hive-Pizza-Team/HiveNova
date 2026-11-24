-- Signup channel-source tracking (?src= / utm_source).
ALTER TABLE `%PREFIX%users`
	ADD `signup_src` varchar(32) DEFAULT NULL AFTER `ref_bonus`;

ALTER TABLE `%PREFIX%users_valid`
	ADD `signup_src` varchar(32) DEFAULT NULL AFTER `referralID`;
