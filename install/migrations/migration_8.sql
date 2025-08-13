ALTER TABLE %PREFIX%config
ADD COLUMN `rpg_geologue_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_geologue_power` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
ADD COLUMN `rpg_amiral_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_amiral_power` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
ADD COLUMN `rpg_ingenieur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_ingenieur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
ADD COLUMN `rpg_technocrate_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_technocrate_power` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
ADD COLUMN `rpg_espion_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_espion_power` SMALLINT UNSIGNED NOT NULL DEFAULT 35,
ADD COLUMN `rpg_constructeur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_constructeur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `rpg_scientifique_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_scientifique_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `rpg_commandant_cost` BIGINT UNSIGNED NOT NULL DEFAULT 15000,
ADD COLUMN `rpg_commandant_power` SMALLINT UNSIGNED NOT NULL DEFAULT 300,
ADD COLUMN `rpg_stockeur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_stockeur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
ADD COLUMN `rpg_defenseur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_defenseur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 25,
ADD COLUMN `rpg_destructeur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_destructeur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
ADD COLUMN `rpg_general_cost` BIGINT UNSIGNED NOT NULL DEFAULT 5000,
ADD COLUMN `rpg_general_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `rpg_bunker_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_bunker_power` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
ADD COLUMN `rpg_raideur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 3000,
ADD COLUMN `rpg_raideur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
ADD COLUMN `rpg_empereur_cost` BIGINT UNSIGNED NOT NULL DEFAULT 15000,
ADD COLUMN `rpg_empereur_power` SMALLINT UNSIGNED NOT NULL DEFAULT 200,
ADD COLUMN `dm_attack_cost` BIGINT UNSIGNED NOT NULL DEFAULT 50,
ADD COLUMN `dm_attack_power` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
ADD COLUMN `dm_defensive_cost` BIGINT UNSIGNED NOT NULL DEFAULT 50,
ADD COLUMN `dm_defensive_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `dm_buildtime_cost` BIGINT UNSIGNED NOT NULL DEFAULT 30,
ADD COLUMN `dm_buildtime_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `dm_researchtime_cost` BIGINT UNSIGNED NOT NULL DEFAULT 30,
ADD COLUMN `dm_researchtime_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `dm_resource_cost` BIGINT UNSIGNED NOT NULL DEFAULT 65,
ADD COLUMN `dm_resource_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `dm_energie_cost` BIGINT UNSIGNED NOT NULL DEFAULT 65,
ADD COLUMN `dm_energie_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
ADD COLUMN `dm_fleettime_cost` BIGINT UNSIGNED NOT NULL DEFAULT 75,
ADD COLUMN `dm_fleettime_power` SMALLINT UNSIGNED NOT NULL DEFAULT 10
;

UPDATE %PREFIX%vars
SET cost921
= 1
WHERE (name like 'rpg_%'
OR name like 'dm_%')
AND elementID >= 600;

UPDATE %PREFIX%vars
SET `bonusAttack` = `bonusAttack` / ABS(`bonusAttack`)
WHERE `bonusAttack` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusDefensive` = `bonusDefensive` / ABS(`bonusDefensive`)
WHERE `bonusDefensive` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusShield` = `bonusShield` / ABS(`bonusShield`)
WHERE `bonusShield` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusBuildTime` = `bonusBuildTime` / ABS(`bonusBuildTime`)
WHERE `bonusBuildTime` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusResearchTime` = `bonusResearchTime` / ABS(`bonusResearchTime`)
WHERE `bonusResearchTime` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusShipTime` = `bonusShipTime` / ABS(`bonusShipTime`)
WHERE `bonusShipTime` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusDefensiveTime` = `bonusDefensiveTime` / ABS(`bonusDefensiveTime`)
WHERE `bonusDefensiveTime` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusResource` = `bonusResource` / ABS(`bonusResource`)
WHERE `bonusResource` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusEnergy` = `bonusEnergy` / ABS(`bonusEnergy`)
WHERE `bonusEnergy` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusResourceStorage` = `bonusResourceStorage` / ABS(`bonusResourceStorage`)
WHERE `bonusResourceStorage` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusShipStorage` = `bonusShipStorage` / ABS(`bonusShipStorage`)
WHERE `bonusShipStorage` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusFlyTime` = `bonusFlyTime` / ABS(`bonusFlyTime`)
WHERE `bonusFlyTime` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');


UPDATE %PREFIX%vars
SET `bonusFleetSlots` = `bonusFleetSlots` / ABS(`bonusFleetSlots`)
WHERE `bonusFleetSlots` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusPlanets` = `bonusPlanets` / ABS(`bonusPlanets`)
WHERE `bonusPlanets` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

UPDATE %PREFIX%vars
SET `bonusSpyPower` = `bonusSpyPower` / ABS(`bonusSpyPower`)
WHERE `bonusSpyPower` != 0
AND
(`name` like 'rpg_%'
OR `name` like 'dm_%');

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 8;