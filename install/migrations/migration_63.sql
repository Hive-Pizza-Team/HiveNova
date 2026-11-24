-- Product version follows the SemVer tag. varchar(8) cannot hold a longer tag.
ALTER TABLE `%PREFIX%config` MODIFY `VERSION` varchar(32) NOT NULL;
UPDATE `%PREFIX%config` SET `VERSION` = 'v1.6.0';
