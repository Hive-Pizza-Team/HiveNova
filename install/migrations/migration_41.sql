-- Cronjob lock column updates need InnoDB row locks (#329).
ALTER TABLE `%PREFIX%cronjobs` ENGINE=InnoDB;
