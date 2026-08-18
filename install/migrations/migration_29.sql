ALTER TABLE `%PREFIX%config`
  ADD `hive_inactive_memo_active` tinyint(1) unsigned NOT NULL DEFAULT '0',
  ADD `hive_inactive_memo_armed` tinyint(1) unsigned NOT NULL DEFAULT '0',
  ADD `hive_inactive_memo_account` varchar(16) NOT NULL DEFAULT '',
  ADD `hive_inactive_memo_active_key` varchar(80) NOT NULL DEFAULT '',
  ADD `hive_inactive_memo_asset` varchar(4) NOT NULL DEFAULT 'HIVE',
  ADD `hive_inactive_memo_amount` decimal(10,3) unsigned NOT NULL DEFAULT '0.003';

ALTER TABLE `%PREFIX%users`
  ADD `inactive_hive_memo_onlinetime` int(11) DEFAULT NULL AFTER `inactive_mail`;

INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'hive_inactive_memo', 1, '0', '4', '*', '*', '*', 'HiveNova\\Cronjob\\InactiveHiveMemoCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\InactiveHiveMemoCronjob'
);
