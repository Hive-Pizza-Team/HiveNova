INSERT INTO `%PREFIX%cronjobs` (`cronjobID`, `name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
VALUES (NULL, 'pushing', 1, '0', '0', '*', '*', '0', 'PushingDetectionCronjob', 0, NULL);

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 12;
