ALTER TABLE `%PREFIX%season_payouts`
  ADD UNIQUE KEY `universe_season_user` (`universe`,`season_id`,`user_id`);
