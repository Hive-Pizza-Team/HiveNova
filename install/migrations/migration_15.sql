UPDATE `%PREFIX%cronjobs` SET `class` = CONCAT('HiveNova\\Cronjob\\', `class`) WHERE `class` NOT LIKE 'HiveNova\\%';
