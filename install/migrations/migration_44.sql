-- Season cron was left pointing at the removed DiscordCronjob class on some hosts.
-- Normalize to HiveNova\Cronjob\SeasonCronjob (same shape as install.sql / migration_33).

-- Drop DiscordCronjob leftovers when a real SeasonCronjob row already exists.
DELETE FROM `%PREFIX%cronjobs`
WHERE `class` IN ('DiscordCronjob', 'HiveNova\\Cronjob\\DiscordCronjob')
  AND EXISTS (
	SELECT 1 FROM (
		SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\SeasonCronjob'
	) AS `_season_exists`
  );

-- Rewrite any remaining DiscordCronjob row into SeasonCronjob.
UPDATE `%PREFIX%cronjobs`
SET
	`name` = 'season',
	`min` = '*/15',
	`hours` = '*',
	`dom` = '*',
	`month` = '*',
	`dow` = '*',
	`class` = 'HiveNova\\Cronjob\\SeasonCronjob',
	`nextTime` = 0
WHERE `class` IN ('DiscordCronjob', 'HiveNova\\Cronjob\\DiscordCronjob');

-- Fix season-named rows that still have the wrong class.
UPDATE `%PREFIX%cronjobs`
SET
	`min` = '*/15',
	`hours` = '*',
	`dom` = '*',
	`month` = '*',
	`dow` = '*',
	`class` = 'HiveNova\\Cronjob\\SeasonCronjob',
	`nextTime` = 0
WHERE `name` = 'season'
  AND `class` <> 'HiveNova\\Cronjob\\SeasonCronjob';

-- Ensure the season cron exists (idempotent).
INSERT INTO `%PREFIX%cronjobs` (`name`, `isActive`, `min`, `hours`, `dom`, `month`, `dow`, `class`, `nextTime`, `lock`)
SELECT 'season', 1, '*/15', '*', '*', '*', '*', 'HiveNova\\Cronjob\\SeasonCronjob', 0, NULL
FROM DUAL
WHERE NOT EXISTS (
	SELECT 1 FROM `%PREFIX%cronjobs` WHERE `class` = 'HiveNova\\Cronjob\\SeasonCronjob'
);
