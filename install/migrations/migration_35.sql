ALTER TABLE `%PREFIX%user_achievements`
  ADD COLUMN `showcase_order` tinyint(1) unsigned DEFAULT NULL,
  ADD KEY `showcase` (`user_id`, `showcase_order`);
