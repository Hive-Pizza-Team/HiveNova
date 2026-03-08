INSERT INTO `%PREFIX%cronjobs` (`cronjobID`, `name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
VALUES (NULL, 'botdetect', 1, '0', '1', '*', '*', '0', 'BotDetectionCronjob', 0, NULL);

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
