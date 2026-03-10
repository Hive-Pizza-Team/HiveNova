ALTER TABLE `%PREFIX%users` ADD COLUMN `number_format` varchar(4) NOT NULL DEFAULT 'auto';

-- Upgrade sucess, set new dbVersion
-- this is done automatically by PHP code
