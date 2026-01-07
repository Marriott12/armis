-- Migration: add camelCase alias columns to match code expectations
-- Non-destructive: adds columns and populates them from existing snake_case columns
-- Rollback: DROP the added columns
-- IMPORTANT: Backup your database before running this migration.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;

START TRANSACTION;

-- The database already has camelCase columns (id, rankId, unitId, corpsId)
-- This migration just ensures the id column is populated from svcNo where needed

-- Populate id from svcNo if id is NULL or empty
UPDATE `staff` SET `id` = `svcNo` WHERE `id` IS NULL OR `id` = '';

COMMIT;

-- Rollback snippet (run only if you want to remove the added aliases)
-- START TRANSACTION;
-- ALTER TABLE `staff` DROP COLUMN IF EXISTS `id`, DROP COLUMN IF EXISTS `rankId`, DROP COLUMN IF EXISTS `unitId`, DROP COLUMN IF EXISTS `corpsId`;
-- ALTER TABLE `corps` DROP COLUMN IF EXISTS `corpsId`;
-- COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
