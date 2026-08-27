-- Remaining high-traffic tables to InnoDB for row-level locking.
ALTER TABLE `%PREFIX%messages` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%buddy` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%banned` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%aks` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%diplo` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%alliance` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%alliance_ranks` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%achievements` ENGINE=InnoDB;
ALTER TABLE `%PREFIX%user_achievements` ENGINE=InnoDB;
