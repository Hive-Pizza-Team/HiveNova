-- Core economy tables must be InnoDB so FOR UPDATE / transactions actually lock.
-- Without this, marketplace claims, combat steals, and fleet dispatch races are
-- ineffective on leftover MyISAM production tables (#327).
ALTER TABLE `%PREFIX%fleets` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%trades` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%fleet_event` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%log_fleets` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%planets` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%users` ENGINE=InnoDB;
