-- Shift existing staff ranks so AUTH_PROMO can occupy 1 (Player=0, Promoter=1,
-- Moderator=2, Operator=3, Admin=4). Apply before deploying PHP that treats
-- authlevel 1 as Promoter, or current Moderators become Promoters.
UPDATE `%PREFIX%users` SET `authlevel` = `authlevel` + 1 WHERE `authlevel` >= 1;
