-- Add a temporary auto-increment column to use as a stable row tiebreaker
ALTER TABLE `%PREFIX%statpoints` ADD COLUMN `_tmp_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY;

-- Remove duplicate rows, keeping the one with the lowest _tmp_id per (id_owner, stat_type, universe)
DELETE a FROM `%PREFIX%statpoints` a
JOIN `%PREFIX%statpoints` b
    ON a.id_owner = b.id_owner
    AND a.stat_type = b.stat_type
    AND a.universe = b.universe
WHERE a._tmp_id > b._tmp_id;

-- Drop the temporary column
ALTER TABLE `%PREFIX%statpoints` DROP PRIMARY KEY, DROP COLUMN `_tmp_id`;

-- Add unique constraint to prevent future duplicates
ALTER TABLE `%PREFIX%statpoints` ADD UNIQUE KEY `unique_owner_type_universe` (`id_owner`, `stat_type`, `universe`);

-- Upgrade success, set new dbVersion
-- this is done automatically by PHP code
