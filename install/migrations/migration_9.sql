ALTER TABLE %PREFIX%records
ADD COLUMN `universe` tinyint(3) unsigned NOT NULL;
-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
-- INSERT INTO %PREFIX%system SET dbVersion = 9;