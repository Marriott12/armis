-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 22, 2025 at 09:51 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `armis1`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `AddColumnIfNotExists`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddColumnIfNotExists` (IN `table_name` VARCHAR(64), IN `column_name` VARCHAR(64), IN `column_definition` TEXT)   BEGIN
    DECLARE column_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO column_exists 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND COLUMN_NAME = column_name;
    
    IF column_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD COLUMN ', column_name, ' ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SafeAddColumn`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddColumn` (IN `table_name` VARCHAR(64), IN `column_name` VARCHAR(64), IN `column_definition` TEXT)   BEGIN
    DECLARE column_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO column_exists 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND COLUMN_NAME = column_name;
    
    IF column_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD COLUMN ', column_name, ' ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ Added column ', column_name, ' to ', table_name) as result;
    ELSE
        SELECT CONCAT('• Column ', column_name, ' already exists in ', table_name) as result;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SafeAddConstraint`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddConstraint` (IN `table_name` VARCHAR(64), IN `constraint_name` VARCHAR(64), IN `constraint_definition` TEXT)   BEGIN
    DECLARE constraint_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO constraint_exists 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND CONSTRAINT_NAME = constraint_name;
    
    IF constraint_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', table_name, ' ADD CONSTRAINT ', constraint_name, ' ', constraint_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ Added constraint ', constraint_name, ' to ', table_name) as result;
    ELSE
        SELECT CONCAT('• Constraint ', constraint_name, ' already exists in ', table_name) as result;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `SafeAddIndex`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SafeAddIndex` (IN `table_name` VARCHAR(64), IN `index_name` VARCHAR(64), IN `index_definition` TEXT)   BEGIN
    DECLARE index_exists INT DEFAULT 0;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
    
    SELECT COUNT(*) INTO index_exists 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = table_name 
    AND INDEX_NAME = index_name;
    
    IF index_exists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX ', index_name, ' ON ', table_name, ' ', index_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ Added index ', index_name, ' to ', table_name) as result;
    ELSE
        SELECT CONCAT('• Index ', index_name, ' already exists in ', table_name) as result;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=566 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 07:20:00'),
(2, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 07:22:04'),
(3, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 07:41:05'),
(4, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 08:02:33'),
(5, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:07:30'),
(6, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:07:39'),
(7, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 08:16:59'),
(8, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 08:26:24'),
(9, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:27:11'),
(10, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:28:59'),
(11, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:29:47'),
(12, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:48:11'),
(13, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:57:19'),
(14, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 08:58:39'),
(15, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 09:24:16'),
(16, 1, 'admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.102.1 Chrome/134.0.6998.205 Electron/35.6.0 Safari/537.36', '2025-07-22 09:25:11'),
(17, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 10:23:02'),
(18, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 11:50:25'),
(19, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 11:52:24'),
(20, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 11:54:56'),
(21, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 11:55:46'),
(22, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 12:36:57'),
(23, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 12:37:29'),
(24, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:09:35'),
(25, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:09:49'),
(26, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:10:04'),
(27, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:10:54'),
(28, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:25:36'),
(29, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:25:44'),
(30, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 13:26:34'),
(31, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 14:07:02'),
(32, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:30:17'),
(33, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:34:51'),
(34, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:45:42'),
(35, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:49:46'),
(36, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:54:01'),
(37, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:57:04'),
(38, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 14:59:49'),
(39, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 17:40:37'),
(40, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 17:40:37'),
(41, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-22 17:40:39'),
(42, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 17:45:44'),
(43, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 17:46:59'),
(44, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 17:50:15'),
(45, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 17:56:12'),
(46, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-22 18:01:14'),
(47, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:11:15'),
(48, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 05:11:27'),
(49, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:27:36'),
(50, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:36:11'),
(51, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:38:22'),
(52, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 05:38:35'),
(53, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:40:21'),
(54, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:46:41'),
(55, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 05:46:59'),
(56, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:47:30'),
(57, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:53:55'),
(58, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 05:57:40'),
(59, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:00:56'),
(60, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:03:32'),
(61, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:08:02'),
(62, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:08:07'),
(63, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:15:59'),
(64, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:22:22'),
(65, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:27:52'),
(66, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:28:47'),
(67, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:28:49'),
(68, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 06:31:15'),
(69, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:47:11'),
(70, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:52:31'),
(71, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 06:54:16'),
(72, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 07:04:26'),
(73, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 07:08:01'),
(74, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 07:21:02'),
(75, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 07:27:31'),
(76, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 07:32:05'),
(77, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 08:00:33'),
(78, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 08:01:10'),
(79, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 08:01:18'),
(80, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 08:04:59'),
(81, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:13:32'),
(82, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:18:06'),
(83, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:19:25'),
(84, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:20:24'),
(85, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:24:24'),
(86, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 09:28:17'),
(87, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 09:29:00'),
(88, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:49:28'),
(89, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:53:21'),
(90, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:55:24'),
(91, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:56:40'),
(92, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 09:56:47'),
(93, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 10:13:36'),
(94, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 11:26:28'),
(95, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 11:29:00'),
(96, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-23 11:54:44'),
(97, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 11:56:56'),
(98, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 11:58:13'),
(99, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:01:54'),
(100, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:08:36'),
(101, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:09:53'),
(102, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:11:32'),
(103, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:11:50'),
(104, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:17:48'),
(105, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:37:21'),
(106, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:38:35'),
(107, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:39:34'),
(108, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:43:32'),
(109, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 12:49:47'),
(110, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 13:05:20'),
(111, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 13:15:58'),
(112, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-23 14:27:01'),
(113, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 06:38:39'),
(114, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 06:40:35'),
(115, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 06:43:17'),
(116, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 06:44:30'),
(117, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 07:54:50'),
(118, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 07:55:09'),
(119, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 08:15:23'),
(120, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:04:45'),
(121, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:10:36'),
(122, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:11:12'),
(123, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:33:50'),
(124, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:34:49'),
(125, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:35:28'),
(126, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 09:53:08'),
(127, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:41:01'),
(128, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:42:11'),
(129, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:43:33'),
(130, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:45:04'),
(131, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:45:32'),
(132, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:46:38'),
(133, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:48:34'),
(134, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:48:51'),
(135, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:49:07'),
(136, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:51:30'),
(137, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:51:35'),
(138, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:53:36'),
(139, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:54:41'),
(140, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:55:24'),
(141, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:55:48'),
(142, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 11:58:11'),
(143, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:03:31'),
(144, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:14:09'),
(145, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:16:27'),
(146, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:16:39'),
(147, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:18:43'),
(148, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:18:47'),
(149, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:28:33'),
(150, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:30:33'),
(151, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:39:19'),
(152, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:40:05'),
(153, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 12:40:16'),
(154, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 12:51:00'),
(155, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:16:50'),
(156, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:35:16'),
(157, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:35:53'),
(158, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:43:32'),
(159, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:45:22'),
(160, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:47:04'),
(161, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:50:11'),
(162, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:50:40'),
(163, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:50:59'),
(164, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:51:35'),
(165, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:52:57'),
(166, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 13:53:08'),
(167, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:06:06'),
(168, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:06:33'),
(169, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:06:53'),
(170, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:07:00'),
(171, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:09:24'),
(172, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:16:37'),
(173, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:21:56'),
(174, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:30:54'),
(175, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:31:58'),
(176, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:38:06'),
(177, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:42:02'),
(178, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:51:27'),
(179, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 14:51:42'),
(180, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:53:46'),
(181, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 14:53:58'),
(182, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:54:55'),
(183, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 14:55:03'),
(184, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-24 14:56:52'),
(185, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-24 14:57:59'),
(186, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:24:37'),
(187, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 05:28:27'),
(188, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 05:31:48'),
(189, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:31:58'),
(190, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:35:57'),
(191, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 05:36:07'),
(192, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:40:15'),
(193, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:41:31'),
(194, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:43:41'),
(195, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 05:43:54'),
(196, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:46:40'),
(197, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:47:06'),
(198, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:47:36'),
(199, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:49:33'),
(200, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:50:11'),
(201, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:50:32'),
(202, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:50:39'),
(203, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:50:45'),
(204, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:51:20'),
(205, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:51:32'),
(206, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:51:57'),
(207, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 05:55:28'),
(208, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:02:01'),
(209, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:03:07'),
(210, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 06:03:40'),
(211, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 06:03:48'),
(212, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:03:54'),
(213, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:03:55'),
(214, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:03:55'),
(215, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:03:55'),
(216, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:10:06'),
(217, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:11:23'),
(218, 6, 'staff1', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 06:11:36'),
(219, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:11:58'),
(220, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 06:57:53'),
(221, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:08:28'),
(222, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:17:34');
INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(223, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:21:40'),
(224, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:24:42'),
(225, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:30:03'),
(226, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:32:13'),
(227, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:32:19'),
(228, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:32:28'),
(229, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:35:31'),
(230, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 07:35:59'),
(231, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:37:25'),
(232, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:39:37'),
(233, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 07:50:58'),
(234, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 08:28:29'),
(235, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 08:28:41'),
(236, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 08:29:22'),
(237, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 08:29:48'),
(238, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 08:35:25'),
(239, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 08:57:38'),
(240, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:00:20'),
(241, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 09:00:41'),
(242, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:00:47'),
(243, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:10:23'),
(244, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:10:27'),
(245, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 09:10:27'),
(246, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:12:31'),
(247, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:14:04'),
(248, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:15:32'),
(249, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:17:27'),
(250, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:27:29'),
(251, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:29:37'),
(252, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:30:46'),
(253, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:32:57'),
(254, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:38:34'),
(255, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:40:59'),
(256, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:41:32'),
(257, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:51:21'),
(258, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:52:50'),
(259, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 09:53:11'),
(260, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:54:34'),
(261, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 09:55:27'),
(262, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-25 10:03:00'),
(263, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 10:07:28'),
(264, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 11:07:55'),
(265, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 11:27:24'),
(266, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-25 14:47:47'),
(267, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-28 07:41:12'),
(268, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-28 10:30:59'),
(269, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-28 11:10:38'),
(270, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-28 13:30:09'),
(271, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-28 14:37:21'),
(272, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 08:48:16'),
(273, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 08:50:40'),
(274, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 13:58:01'),
(275, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:17:36'),
(276, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:18:00'),
(277, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:18:10'),
(278, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:18:37'),
(279, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:18:47'),
(280, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:18:56'),
(281, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:19:04'),
(282, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:19:12'),
(283, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:23:58'),
(284, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:24:28'),
(285, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:35:16'),
(286, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:36:41'),
(287, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:36:51'),
(288, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 14:37:31'),
(289, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:38:58'),
(290, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:46:44'),
(291, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:48:05'),
(292, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:48:23'),
(293, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:48:30'),
(294, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:48:40'),
(295, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:49:45'),
(296, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:51:17'),
(297, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:56:54'),
(298, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:58:48'),
(299, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:59:51'),
(300, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 17:59:57'),
(301, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 18:01:57'),
(302, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 18:01:58'),
(303, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-29 18:02:06'),
(304, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:09:10'),
(305, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:10:59'),
(306, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:11:16'),
(307, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:11:28'),
(308, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:11:33'),
(309, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:49:14'),
(310, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:50:05'),
(311, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:50:17'),
(312, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:50:25'),
(313, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 05:51:36'),
(314, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 07:24:20'),
(315, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 09:05:51'),
(316, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 09:06:11'),
(317, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 09:07:06'),
(318, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-30 09:36:46'),
(319, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 07:42:04'),
(320, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 07:43:14'),
(321, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 07:43:40'),
(322, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 07:43:48'),
(323, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 07:43:57'),
(324, 6, 'staff1', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 07:45:54'),
(325, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 07:50:39'),
(326, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 07:54:36'),
(327, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 07:54:45'),
(328, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 07:54:57'),
(329, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:03:36'),
(330, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:04:12'),
(331, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:11:10'),
(332, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:11:17'),
(333, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:11:45'),
(334, 6, 'staff1', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-07-31 08:12:25'),
(335, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 08:28:53'),
(336, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 08:41:15'),
(337, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 08:58:27'),
(338, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 09:01:22'),
(339, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 09:06:00'),
(340, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 09:06:08'),
(341, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 09:28:07'),
(342, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 13:32:06'),
(343, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 13:58:24'),
(344, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 14:52:06'),
(345, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 14:55:15'),
(346, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-31 15:12:35'),
(347, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 06:25:53'),
(348, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 06:26:39'),
(349, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:00:26'),
(350, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:23:34'),
(351, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:24:09'),
(352, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:47:02'),
(353, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:47:18'),
(354, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 07:47:53'),
(355, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 08:10:31'),
(356, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 08:43:32'),
(357, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 08:43:49'),
(358, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 08:53:32'),
(359, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 08:58:58'),
(360, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:00:48'),
(361, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:09:57'),
(362, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:10:10'),
(363, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:32:28'),
(364, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:32:47'),
(365, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 09:33:08'),
(366, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 10:36:33'),
(367, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 10:51:44'),
(368, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 10:53:05'),
(369, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 12:13:18'),
(370, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 12:34:15'),
(371, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 12:41:49'),
(372, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 12:45:14'),
(373, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:17:37'),
(374, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:23:47'),
(375, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:24:23'),
(376, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:42:24'),
(377, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:45:11'),
(378, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:49:52'),
(379, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 13:52:18'),
(380, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:13:28'),
(381, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:20:40'),
(382, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:41:02'),
(383, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:43:02'),
(384, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:44:46'),
(385, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:46:09'),
(386, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:47:00'),
(387, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:47:18'),
(388, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 14:49:19'),
(389, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:12:42'),
(390, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:17:53'),
(391, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:18:09'),
(392, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:22:49'),
(393, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:23:06'),
(394, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-01 17:25:08'),
(395, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:00:46'),
(396, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:04:10'),
(397, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:06:24'),
(398, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:06:55'),
(399, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:27:01'),
(400, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 07:39:40'),
(401, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 08:24:01'),
(402, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 09:52:35'),
(403, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 09:53:42'),
(404, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:05:05'),
(405, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:06:57'),
(406, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:20:13'),
(407, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:29:14'),
(408, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:32:40'),
(409, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:32:45'),
(410, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:37:03'),
(411, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:38:04'),
(412, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:38:06'),
(413, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:40'),
(414, 1, '', 'staff_search', '{\"search\":\"ma\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:43'),
(415, 1, '', 'staff_search', '{\"search\":\"marriott\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:44'),
(416, 1, '', 'staff_search', '{\"search\":\"marriot\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:46'),
(417, 1, '', 'staff_search', '{\"search\":\"marr\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:57'),
(418, 1, '', 'staff_search', '{\"search\":\"mar\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:40:58'),
(419, 1, '', 'staff_search', '{\"search\":\"ma\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:41:00'),
(420, 1, '', 'staff_search', '{\"search\":\"m\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:41:00'),
(421, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:41:02'),
(422, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:41:29'),
(423, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:55:25'),
(424, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:56:05'),
(425, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 10:56:47'),
(426, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 08:56:49'),
(427, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"Retired\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:03:03'),
(428, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"Inactive\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:03:07'),
(429, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"Transferred\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:03:12'),
(430, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"Active\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:03:15'),
(431, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:03:36'),
(432, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:04:27'),
(433, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:04:28'),
(434, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:13:58'),
(435, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:24:06'),
(436, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:25:09'),
(437, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:25:11'),
(438, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:25:21'),
(439, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 09:25:28'),
(440, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 11:25:34'),
(441, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:01:43'),
(442, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:02:17'),
(443, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:02:20'),
(444, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:03:21'),
(445, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 10:03:25'),
(446, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:04:30'),
(447, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-02 12:05:16'),
(448, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 10:05:18'),
(449, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-02 12:06:16'),
(450, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-02 12:06:36'),
(451, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-02 10:06:43'),
(452, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 05:23:14'),
(453, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-06 03:24:02');
INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(454, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 05:28:52'),
(455, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 05:57:28'),
(456, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:02:43'),
(457, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 06:12:13'),
(458, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-06 04:12:39'),
(459, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 06:13:32'),
(460, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 06:14:21'),
(461, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-06 04:14:23'),
(462, 1, '', 'staff_search', '{\"search\":\"mar\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-06 04:14:28'),
(463, 1, '', 'staff_search', '{\"search\":\"marriott\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-06 04:14:30'),
(464, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 06:14:34'),
(465, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:15:12'),
(466, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:18:37'),
(467, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:20:56'),
(468, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:25:14'),
(469, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:25:35'),
(470, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:29:18'),
(471, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 06:29:40'),
(472, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:30:09'),
(473, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:31:13'),
(474, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:31:41'),
(475, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 06:32:24'),
(476, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 07:25:25'),
(477, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 08:13:25'),
(478, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 09:05:15'),
(479, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 09:33:54'),
(480, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 10:06:30'),
(481, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 10:51:29'),
(482, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-06 10:52:30'),
(483, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-06 10:56:33'),
(484, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 05:50:09'),
(485, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 05:57:12'),
(486, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:05:12'),
(487, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:16:17'),
(488, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 04:16:29'),
(489, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:29:07'),
(490, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-07 06:33:58'),
(491, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:37:10'),
(492, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:37:35'),
(493, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 04:38:40'),
(494, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 06:39:00'),
(495, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:03:18'),
(496, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:03:20'),
(497, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:03:44'),
(498, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:03:47'),
(499, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 07:03:52'),
(500, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:04:55'),
(501, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 07:04:57'),
(502, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 09:24:32'),
(503, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 07:25:22'),
(504, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 10:46:23'),
(505, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 10:46:42'),
(506, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 08:46:52'),
(507, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 12:39:41'),
(508, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 10:40:32'),
(509, 1, '', 'staff_search', '{\"search\":\"cxo\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:19'),
(510, 1, '', 'staff_search', '{\"search\":\"c\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:20'),
(511, 1, '', 'staff_search', '{\"search\":\"ccom\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:21'),
(512, 1, '', 'staff_search', '{\"search\":\"cc\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:24'),
(513, 1, '', 'staff_search', '{\"search\":\"c\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:24'),
(514, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:25'),
(515, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"5\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:28'),
(516, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"5\",\"unit\":\"1\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:33'),
(517, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"1\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:41'),
(518, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"1\",\"status\":\"Active\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:45'),
(519, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"1\",\"status\":\"Transferred\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:48'),
(520, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 11:06:50'),
(521, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-07 14:33:38'),
(522, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-07 12:33:45'),
(523, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 05:14:34'),
(524, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-08 03:15:46'),
(525, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 07:41:39'),
(526, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-08 05:41:45'),
(527, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 08:42:52'),
(528, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-08 06:43:11'),
(529, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 10:59:04'),
(530, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', '2025-08-11 05:48:37'),
(531, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:23:03'),
(532, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 08:23:26'),
(533, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-11 06:23:29'),
(534, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:41:56'),
(535, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:42:20'),
(536, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-11 10:42:25'),
(537, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:42:33'),
(538, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:46:34'),
(539, 1, 'test_admin', 'edit_staff_access', 'Accessed Edit Staff page', '127.0.0.1', 'Test Agent', '2025-08-11 12:55:32'),
(540, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:57:34'),
(541, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 12:58:30'),
(542, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:19:22'),
(543, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:19:25'),
(544, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:21:13'),
(545, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:24:56'),
(546, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:33:56'),
(547, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 13:47:36'),
(548, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 14:06:21'),
(549, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 14:06:54'),
(550, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-11 12:07:03'),
(551, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 14:11:52'),
(552, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-11 12:12:06'),
(553, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-11 14:14:24'),
(554, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-11 12:14:38'),
(555, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 06:36:32'),
(556, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-12 04:36:54'),
(557, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 07:57:03'),
(558, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-12 05:57:11'),
(559, 1, 'Admin', 'edit_staff_access', 'Accessed Edit Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 07:57:31'),
(560, 1, '', 'staff_search', '{\"search\":\"\",\"rank\":\"\",\"unit\":\"\",\"status\":\"\",\"action\":\"staff_search\"}', '::1', NULL, '2025-08-12 05:57:37'),
(561, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 07:58:21'),
(562, 1, 'Admin', 'admin_branch_dashboard_access', 'Accessed Admin Branch Dashboard', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 08:34:44'),
(563, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-12 09:19:35'),
(564, 1, 'admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-13 13:14:53'),
(565, 1, 'Admin', 'create_staff_access', 'Accessed Create Staff page', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-14 07:11:30');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(10) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `unit_id` varchar(25) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appointments_staff` (`staff_id`),
  KEY `idx_appointments_unit` (`unit_id`),
  KEY `idx_appointments_current` (`is_current`),
  KEY `idx_appointments_dates` (`start_date`,`end_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit`
--

DROP TABLE IF EXISTS `audit`;
CREATE TABLE IF NOT EXISTS `audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(10) NOT NULL,
  `page` varchar(255) NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(255) NOT NULL,
  `viewed` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `corps`
--

DROP TABLE IF EXISTS `corps`;
CREATE TABLE IF NOT EXISTS `corps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `abbreviation` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `corps`
--

INSERT INTO `corps` (`id`, `name`, `abbreviation`) VALUES
(1, 'Infantry', 'ZINF'),
(2, 'Armour', 'ZAC'),
(3, 'Artillery', 'ZA'),
(4, 'Commando', 'CDO'),
(5, 'Marine', 'MAR'),
(6, 'Education', 'ZCE'),
(7, 'Ordnance', 'ZOC'),
(8, 'Chaplaincy ', 'CHAP'),
(9, 'Finance', 'ZAPC'),
(10, 'Signals', 'ZSIGS'),
(11, 'Engineer', 'ZE'),
(12, 'Transport', 'ZCT'),
(13, 'Medical', 'ZAMC'),
(14, 'Military Police', 'ZMP'),
(15, 'Electrical & Mech Engineer', 'ZEME');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `duration_weeks` int DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `dateCreated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `code` varchar(20) DEFAULT NULL,
  `type` enum('Military','Professional','Academic','Technical') DEFAULT 'Military',
  `institution_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_courses_code` (`code`),
  KEY `idx_courses_type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `name`, `description`, `duration_weeks`, `category`, `dateCreated`, `code`, `type`, `institution_id`) VALUES
(1, 'Basic Military Training', 'Fundamental military skills and discipline', 12, 'Basic', '2025-07-17 11:17:49', 'CSE-001', 'Professional', NULL),
(2, 'Leadership Development', 'Advanced leadership and management skills', 8, 'Leadership', '2025-07-17 11:17:49', 'CSE-002', 'Professional', NULL),
(3, 'Technical Training', 'Specialized technical skills training', 16, 'Technical', '2025-07-17 11:17:49', 'CSE-003', 'Technical', NULL),
(4, 'Combat Training', 'Advanced combat and tactical training', 10, 'Combat', '2025-07-17 11:17:49', 'CSE-004', 'Professional', NULL),
(5, 'Communication Systems', 'Military communication equipment and procedures', 6, 'Technical', '2025-07-17 11:17:49', 'CSE-005', 'Technical', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `current_appointments`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `current_appointments`;
CREATE TABLE IF NOT EXISTS `current_appointments` (
);

-- --------------------------------------------------------

--
-- Table structure for table `data_classifications`
--

DROP TABLE IF EXISTS `data_classifications`;
CREATE TABLE IF NOT EXISTS `data_classifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `level` int NOT NULL,
  `description` text,
  `color_code` varchar(7) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_level` (`level`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `data_classifications`
--

INSERT INTO `data_classifications` (`id`, `name`, `level`, `description`, `color_code`, `created_at`) VALUES
(1, 'PUBLIC', 1, 'Information that can be freely shared', '#28a745', '2025-08-19 15:53:48'),
(2, 'INTERNAL', 2, 'Information for internal use only', '#ffc107', '2025-08-19 15:53:48'),
(3, 'CONFIDENTIAL', 3, 'Sensitive information requiring protection', '#fd7e14', '2025-08-19 15:53:48'),
(4, 'SECRET', 4, 'Highly sensitive information', '#dc3545', '2025-08-19 15:53:48'),
(5, 'TOP_SECRET', 5, 'Most sensitive information requiring highest protection', '#6f42c1', '2025-08-19 15:53:48');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
CREATE TABLE IF NOT EXISTS `document_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `allowed_extensions` text NOT NULL,
  `max_file_size` int DEFAULT '5242880',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `name`, `allowed_extensions`, `max_file_size`, `description`, `created_at`) VALUES
(1, 'Photo/Image', 'jpg,jpeg,png,gif,bmp,webp', 10485760, 'Staff photos and image documents', '2025-07-22 07:57:05'),
(2, 'PDF Document', 'pdf', 20971520, 'PDF documents and forms', '2025-07-22 07:57:05'),
(3, 'Word Document', 'doc,docx', 10485760, 'Microsoft Word documents', '2025-07-22 07:57:05'),
(4, 'Excel Spreadsheet', 'xls,xlsx,csv', 10485760, 'Excel files and spreadsheets', '2025-07-22 07:57:05'),
(5, 'Text Document', 'txt,rtf', 5242880, 'Plain text and rich text documents', '2025-07-22 07:57:05'),
(6, 'Medical Certificate', 'pdf,jpg,jpeg,png', 15728640, 'Medical certificates and health documents', '2025-07-22 07:57:05'),
(7, 'Training Certificate', 'pdf,jpg,jpeg,png', 15728640, 'Training and certification documents', '2025-07-22 07:57:05'),
(8, 'ID Documents', 'pdf,jpg,jpeg,png', 10485760, 'Identity documents and credentials', '2025-07-22 07:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text,
  `variables` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_name` (`template_name`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_name`, `subject`, `body_html`, `body_text`, `variables`, `created_at`, `updated_at`) VALUES
(1, 'welcome_new_staff', 'Welcome to ARMIS - Account Created', '<h2>Welcome to ARMIS</h2>\r\n        <p>Dear {{rank}} {{last_name}},</p>\r\n        <p>Your ARMIS account has been successfully created.</p>\r\n        <p><strong>Login Details:</strong></p>\r\n        <ul>\r\n        <li>Username: {{username}}</li>\r\n        <li>Temporary Password: {{temp_password}}</li>\r\n        <li>Service Number: {{service_number}}</li>\r\n        </ul>\r\n        <p><strong>Important:</strong> You must change your password on first login for security.</p>\r\n        <p>Please login at: {{login_url}}</p>\r\n        <p>Best regards,<br>ARMIS Admin Team</p>', 'Welcome to ARMIS\n\nDear {{rank}} {{last_name}},\n\nYour ARMIS account has been created.\n\nLogin Details:\nUsername: {{username}}\nTemporary Password: {{temp_password}}\nService Number: {{service_number}}\n\nIMPORTANT: Change your password on first login.\n\nLogin at: {{login_url}}\n\nBest regards,\nARMIS Admin Team', '[\"rank\", \"last_name\", \"username\", \"temp_password\", \"service_number\", \"login_url\"]', '2025-07-22 06:42:10', '2025-07-22 06:42:10'),
(2, 'password_reset', 'ARMIS Password Reset Request', '<h2>Password Reset Request</h2>\r\n        <p>Dear {{rank}} {{last_name}},</p>\r\n        <p>A password reset was requested for your ARMIS account.</p>\r\n        <p>Click here to reset: <a href=\"{{reset_url}}\">Reset Password</a></p>\r\n        <p>This link expires in 24 hours.</p>\r\n        <p>If you did not request this, please ignore this email.</p>', 'Password Reset Request\n\nDear {{rank}} {{last_name}},\n\nA password reset was requested.\n\nReset URL: {{reset_url}}\n\nExpires in 24 hours.\n\nIgnore if not requested.', '[\"rank\", \"last_name\", \"reset_url\"]', '2025-07-22 06:42:11', '2025-07-22 06:42:11');

-- --------------------------------------------------------

--
-- Table structure for table `file_access_log`
--

DROP TABLE IF EXISTS `file_access_log`;
CREATE TABLE IF NOT EXISTS `file_access_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `document_id` int NOT NULL,
  `accessed_by` int NOT NULL,
  `access_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `action` enum('view','download','delete','modify') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `idx_file_access_log_document` (`document_id`),
  KEY `idx_file_access_log_user` (`accessed_by`),
  KEY `idx_file_access_log_date` (`access_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `file_encryption`
--

DROP TABLE IF EXISTS `file_encryption`;
CREATE TABLE IF NOT EXISTS `file_encryption` (
  `id` int NOT NULL AUTO_INCREMENT,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `encryption_key_id` varchar(100) NOT NULL,
  `classification_id` int NOT NULL,
  `encrypted_at` datetime NOT NULL,
  `encrypted_by` int NOT NULL,
  `file_size` bigint NOT NULL,
  `checksum` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_file_path` (`file_path`(250)),
  KEY `idx_classification` (`classification_id`),
  KEY `idx_encrypted_by` (`encrypted_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

DROP TABLE IF EXISTS `institutions`;
CREATE TABLE IF NOT EXISTS `institutions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `type` enum('Military Academy','Training School','University','College','Institute') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Training School',
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `institutions`
--

INSERT INTO `institutions` (`id`, `name`, `type`, `location`, `created_at`) VALUES
(1, 'Institution INST001', 'Training School', 'Kabwe', '2025-07-17 12:36:53'),
(2, 'Institution INST002', 'Training School', 'Lusaka', '2025-07-17 12:36:53'),
(3, 'Institution INST003', 'Training School', 'Lusaka', '2025-07-17 12:36:53'),
(4, 'Institution INST004', 'Training School', 'Mumbwa', '2025-07-17 12:36:53'),
(5, 'Institution INST005', 'Training School', 'Kaduna', '2025-07-17 12:36:53');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `success` tinyint(1) DEFAULT '0',
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_attempted` (`attempted_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(10) NOT NULL,
  `logdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logtype` varchar(25) NOT NULL,
  `lognote` mediumtext NOT NULL,
  `ip` varchar(75) DEFAULT NULL,
  `metadata` blob,
  PRIMARY KEY (`id`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medals`
--

DROP TABLE IF EXISTS `medals`;
CREATE TABLE IF NOT EXISTS `medals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `medals`
--

INSERT INTO `medals` (`id`, `name`, `description`, `image_path`, `created_at`) VALUES
(1, 'Campaign Medal', 'Awarded for exceptional gallantry in action', NULL, '2025-07-17 11:22:30'),
(2, 'Distinguished Service Cross', 'Awarded for exceptional gallantry in action', NULL, '2025-07-17 11:23:54'),
(3, 'Meritorious Service Medal', 'Awarded for outstanding meritorious achievement or', NULL, '2025-07-17 11:23:54'),
(4, 'Good Conduct Medal', 'Awarded for exemplary behavior, efficiency, and fi', NULL, '2025-07-17 11:23:54'),
(5, 'Long Service Medal', 'Awarded for long and faithful service', NULL, '2025-07-17 11:23:54'),
(6, 'Campaign Medal', 'Awarded for participation in specific military cam', NULL, '2025-07-17 11:23:54');

-- --------------------------------------------------------

--
-- Table structure for table `mfa_backup_codes`
--

DROP TABLE IF EXISTS `mfa_backup_codes`;
CREATE TABLE IF NOT EXISTS `mfa_backup_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_used` (`used`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(10) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `is_archived` tinyint(1) DEFAULT '0',
  `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_read` datetime DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `class` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operations`
--

DROP TABLE IF EXISTS `operations`;
CREATE TABLE IF NOT EXISTS `operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `type` enum('Peacekeeping','Combat','Training','Humanitarian','Border Security') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Training',
  `status` enum('Planning','Active','Completed','Suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Active',
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_operations_code` (`code`),
  KEY `idx_operations_status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `operations`
--

INSERT INTO `operations` (`id`, `name`, `code`, `type`, `status`, `created_by`, `created_at`) VALUES
(1, 'Operation OP001', 'OPS-OP001', 'Training', 'Active', 'SYSTEM', '2025-07-17 12:37:05'),
(2, 'Operation OP002', 'OPS-OP002', 'Training', 'Active', 'SYSTEM', '2025-07-17 12:37:05'),
(3, 'Operation OP003', 'OPS-OP003', 'Training', 'Active', 'SYSTEM', '2025-07-17 12:37:05'),
(4, 'Operation OP004', 'OPS-OP004', 'Training', 'Active', 'SYSTEM', '2025-07-17 12:37:05'),
(5, 'Operation OP005', 'OPS-OP005', 'Training', 'Active', 'SYSTEM', '2025-07-17 12:37:05');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_reset` (`user_id`),
  KEY `idx_token` (`token`(250)),
  KEY `idx_expires` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

DROP TABLE IF EXISTS `ranks`;
CREATE TABLE IF NOT EXISTS `ranks` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `abbreviation` varchar(10) DEFAULT NULL,
  `level` int DEFAULT NULL,
  `category` enum('Officer','NCO','Private','CE') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Private',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ranks_rankID` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`id`, `name`, `abbreviation`, `level`, `category`, `created_at`) VALUES
(1, 'General', 'Gen', 1, 'Officer', '2025-07-17 12:00:00'),
(2, 'Lieutenant General', 'Lt Gen', 2, 'Officer', '2025-07-17 12:00:00'),
(3, 'Major General', 'Maj Gen', 3, 'Officer', '2025-07-17 12:00:00'),
(4, 'Brigadier General', 'Brig Gen', 4, 'Officer', '2025-07-17 12:00:00'),
(5, 'Colonel', 'Col', 5, 'Officer', '2025-07-17 12:00:00'),
(6, 'Lieutenant Colonel', 'Lt Col', 6, 'Officer', '2025-07-17 12:00:00'),
(7, 'Temporal Lieutenant Colonel', 'T/Lt Col', 7, 'Officer', '2025-07-17 12:00:00'),
(8, 'Major', 'Maj', 8, 'Officer', '2025-07-17 12:00:00'),
(9, 'Temporal Major', 'T/Maj', 9, 'Officer', '2025-07-17 12:00:00'),
(10, 'Captain', 'Capt', 10, 'Officer', '2025-07-17 12:00:00'),
(11, 'Temporal Captain', 'T/Capt', 11, 'Officer', '2025-07-17 12:00:00'),
(12, 'Lieutenant', 'Lt', 12, 'Officer', '2025-07-17 12:00:00'),
(13, 'Second Lieutenant', '2Lt', 13, 'Officer', '2025-07-17 12:00:00'),
(14, 'Officer Cadet', 'O/Cdt', 14, 'Officer', '2025-07-17 12:00:00'),
(15, 'Warrant Officer Class 1', 'WoI', 15, 'NCO', '2025-07-17 12:00:00'),
(16, 'Warrant Officer Class 2', 'WoII', 16, 'NCO', '2025-07-17 12:00:00'),
(17, 'Temporal Warrant Officer Class 2', 'T/WoII', 17, 'NCO', '2025-07-17 12:00:00'),
(18, 'Staff Sergeant ', 'S Sgt', 18, 'NCO', '2025-07-17 12:00:00'),
(19, 'Temporal Staff Sergeant', 'T/S Sgt', 19, 'NCO', '2025-07-17 12:00:00'),
(20, 'Sergeant', 'Sgt', 20, 'NCO', '2025-07-17 12:00:00'),
(21, 'Temporal Sergeant', 'T/Sgt', 21, 'NCO', '2025-07-17 12:00:00'),
(22, 'Corporal', 'Cpl', 22, 'NCO', '2025-07-17 12:00:00'),
(23, 'Temporal Corporal', 'T/Cpl', 23, 'NCO', '2025-07-17 12:00:00'),
(24, 'Lance Corporal', 'L Cpl', 24, 'NCO', '2025-07-17 12:00:00'),
(25, 'Temporal Lance Corporal', 'T/L Cpl', 25, 'NCO', '2025-07-17 12:00:00'),
(26, 'Private', 'Pte', 26, 'NCO', '2025-07-17 12:00:00'),
(27, 'Recruit', 'Rct', 27, 'Private', '2025-07-17 12:00:00'),
(28, 'Mister', 'Mr', 28, 'CE', '2025-07-17 12:00:00'),
(29, 'Miss', 'Ms', 28, 'CE', '2025-07-17 12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_token` (`token`(250)),
  KEY `idx_expires` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

DROP TABLE IF EXISTS `search_history`;
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `search_query` text NOT NULL,
  `search_filters` json DEFAULT NULL,
  `results_count` int DEFAULT '0',
  `search_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `execution_time` decimal(8,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_search_history_user` (`user_id`),
  KEY `idx_search_history_date` (`search_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

DROP TABLE IF EXISTS `security_audit_log`;
CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(100) DEFAULT NULL,
  `resource_id` varchar(100) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `session_id` varchar(255) DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_severity` (`severity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `value` text,
  `description` text,
  `type` enum('string','number','boolean','json') DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`key_name`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `description`, `type`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'ARMIS', 'Site name displayed in header', 'string', '2025-07-17 12:05:28', '2025-07-17 12:05:28'),
(2, 'site_description', 'Army Resource Management Information System', 'Site description', 'string', '2025-07-17 12:05:28', '2025-07-17 12:05:28'),
(3, 'timezone', 'UTC', 'Default timezone', 'string', '2025-07-17 12:05:28', '2025-07-17 12:05:28'),
(4, 'max_login_attempts', '5', 'Maximum login attempts before lockout', 'number', '2025-07-17 12:05:28', '2025-07-17 12:05:28'),
(5, 'maintenance_mode', 'false', 'Enable maintenance mode', 'boolean', '2025-07-17 12:05:28', '2025-07-17 12:05:28'),
(6, 'version', '2.0', 'ARMIS version', 'string', '2025-07-17 12:05:28', '2025-07-17 12:05:28');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `rank_id` int DEFAULT NULL,
  `last_name` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `NRC` varchar(11) DEFAULT NULL,
  `passport` varchar(15) NOT NULL,
  `passExp` date NOT NULL,
  `combatSize` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `bsize` varchar(5) NOT NULL,
  `ssize` varchar(5) NOT NULL,
  `hdress` varchar(5) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `unit_id` int DEFAULT NULL,
  `corps_id` int DEFAULT NULL,
  `category` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `svcStatus` enum('Active','Retired','Deceased','Discharged') DEFAULT 'Active',
  `appt` varchar(30) DEFAULT NULL,
  `subRank` varchar(10) DEFAULT NULL,
  `subWef` date DEFAULT NULL,
  `tempRank` varchar(10) DEFAULT NULL,
  `tempWef` date DEFAULT NULL,
  `localRank` varchar(150) NOT NULL,
  `localWef` date NOT NULL,
  `attestDate` date DEFAULT NULL,
  `intake` varchar(10) DEFAULT NULL,
  `DOB` date DEFAULT NULL,
  `height` varchar(5) NOT NULL,
  `province` varchar(12) DEFAULT NULL,
  `district` varchar(100) NOT NULL,
  `corps` varchar(20) DEFAULT NULL,
  `bloodGp` varchar(4) DEFAULT NULL,
  `profession` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `religion` varchar(100) NOT NULL,
  `village` varchar(100) NOT NULL,
  `trade` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `digitalID` varchar(10) DEFAULT NULL,
  `prefix` varchar(3) DEFAULT NULL,
  `marital` enum('Single','Married','Divorced','Widowed') DEFAULT 'Single',
  `initials` varchar(4) DEFAULT NULL,
  `titles` varchar(80) DEFAULT NULL,
  `nok` varchar(30) DEFAULT NULL,
  `nokNrc` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `nokRelat` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `nokTel` varchar(20) DEFAULT NULL,
  `altNok` varchar(50) NOT NULL,
  `altNokTel` varchar(20) NOT NULL,
  `altNokNrc` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `altNokRelat` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(25) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `tel` varchar(10) DEFAULT NULL,
  `unitAtt` varchar(25) DEFAULT NULL,
  `username` varchar(25) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','admin_branch','command','training','operations') DEFAULT 'command',
  `renewDate` date DEFAULT NULL,
  `accStatus` enum('Active','Inactive','Suspended','Pending') DEFAULT 'Active',
  `createdBy` varchar(15) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `status` enum('Active','Retired','Deceased','Discharged') DEFAULT 'Active',
  `date_retired` date NOT NULL,
  `last_profile_update` timestamp NULL DEFAULT NULL,
  `address` text,
  `specialization` varchar(100) DEFAULT NULL,
  `lastPromotion` date DEFAULT NULL,
  `postingHistory` text,
  `awards` text,
  `disciplinaryRecord` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_svcNo` (`service_number`),
  UNIQUE KEY `service_number` (`service_number`),
  KEY `idx_svcNo` (`service_number`),
  KEY `idx_fName` (`first_name`),
  KEY `idx_sName` (`last_name`),
  KEY `idx_staff_rankID` (`rank_id`),
  KEY `idx_staff_svcNo` (`service_number`),
  KEY `idx_staff_fname` (`first_name`),
  KEY `idx_staff_lname` (`last_name`),
  KEY `idx_staff_username` (`username`),
  KEY `idx_staff_unitID` (`unit_id`),
  KEY `idx_staff_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `service_number`, `rank_id`, `last_name`, `first_name`, `NRC`, `passport`, `passExp`, `combatSize`, `bsize`, `ssize`, `hdress`, `gender`, `unit_id`, `corps_id`, `category`, `svcStatus`, `appt`, `subRank`, `subWef`, `tempRank`, `tempWef`, `localRank`, `localWef`, `attestDate`, `intake`, `DOB`, `height`, `province`, `district`, `corps`, `bloodGp`, `profession`, `religion`, `village`, `trade`, `digitalID`, `prefix`, `marital`, `initials`, `titles`, `nok`, `nokNrc`, `nokRelat`, `nokTel`, `altNok`, `altNokTel`, `altNokNrc`, `altNokRelat`, `email`, `profile_photo`, `tel`, `unitAtt`, `username`, `password`, `role`, `renewDate`, `accStatus`, `createdBy`, `dateCreated`, `updated_at`, `last_login`, `password_changed_at`, `date_of_birth`, `status`, `date_retired`, `last_profile_update`, `address`, `specialization`, `lastPromotion`, `postingHistory`, `awards`, `disciplinaryRecord`) VALUES
(1, '007414', 28, 'Mumba', 'Marriott Gift', '375460/10/1', '', '0000-00-00', '', '8', '7', '54', 'Male', 1, 10, 'Officer', 'Active', NULL, NULL, '2025-06-02', NULL, NULL, '', '0000-00-00', '2024-06-04', '33', '1996-01-12', '179', 'Lusaka', 'Lusaka', 'ZSIGS', 'O+', '', 'Christianity', '', '', NULL, NULL, 'Single', 'M G', NULL, '', '', '', '', '', '', NULL, NULL, '', 'users/uploads/profile_photo/007414.jpg', '0974297313', NULL, 'Admin', '$2y$10$.wk0BFiJUS4J3pRQ0YGfTueRdlKwnkvcJQpEUHOrPeUlcmbd7S.Ke', 'admin', NULL, 'Active', NULL, '2025-06-19 12:35:30', '2025-08-19 07:44:03', '2025-08-19 07:44:03', NULL, '1996-01-12', 'Active', '0000-00-00', NULL, '', '', '0000-00-00', '', '', ''),
(2, '007417', 28, 'Chanda', 'David', '123456/78/1', '', '2033-01-01', 'M', '8', '7', '54', 'Male', 0, NULL, 'Civilian Employee', 'Active', NULL, 'mr', '2025-06-06', NULL, NULL, '', '0000-00-00', '2025-06-06', NULL, '2001-09-27', '150', 'Lusaka', '', '', 'O-', NULL, '', '', '', NULL, 'S', 'Single', NULL, 'Mr', 'John', '123456/78/1', 'Brother', '0987654321', '', '', '', '', '', NULL, '', NULL, 'David', '$2y$10$.wk0BFiJUS4J3pRQ0YGfTueRdlKwnkvcJQpEUHOrPeUlcmbd7S.Ke', 'command', NULL, 'Active', NULL, '2025-07-01 10:44:28', '2025-07-29 13:28:13', NULL, NULL, '2001-09-27', 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'DEMO001', 2, 'User', 'Admin', NULL, '', '0000-00-00', NULL, '', '', '', 'Male', 1, NULL, 'Officer', 'Active', NULL, NULL, NULL, NULL, NULL, '', '0000-00-00', NULL, NULL, '1990-01-01', '175', NULL, '', NULL, 'O+', NULL, '', '', NULL, NULL, NULL, 'Single', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, NULL, 'admin@armis.demo', NULL, '+260-97-00', NULL, 'admin', '$2y$10$25MmhX1NSF2wwPqTTGhPruQOT8A5YwEI.PfpuX1KELU.WHqLd7TTC', 'admin', NULL, 'Active', 'system', '2025-07-22 09:21:13', '2025-07-22 09:21:13', NULL, NULL, NULL, 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'DEMO002', 1, 'Officer', 'Command', NULL, '', '0000-00-00', NULL, '', '', '', 'Male', 1, NULL, 'Officer', 'Active', NULL, NULL, NULL, NULL, NULL, '', '0000-00-00', NULL, NULL, '1990-01-01', '175', NULL, '', NULL, 'O+', NULL, '', '', NULL, NULL, NULL, 'Single', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, NULL, 'commander@armis.demo', NULL, '+260-97-00', NULL, 'commander', '$2y$10$YaFoQM/DDIakqs7Wyuj6Ee3.xy9I9JfA2qNZH3muOcWtLwkxvtcv.', 'command', NULL, 'Active', 'system', '2025-07-22 09:21:13', '2025-07-29 12:01:20', '2025-07-29 12:01:20', NULL, NULL, 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'DEMO003', 7, 'Officer', 'Training', NULL, '', '0000-00-00', NULL, '', '', '', 'Male', 4, NULL, 'Officer', 'Active', NULL, NULL, NULL, NULL, NULL, '', '0000-00-00', NULL, NULL, '1990-01-01', '175', NULL, '', NULL, 'O+', NULL, '', '', NULL, NULL, NULL, 'Single', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, NULL, 'trainer@armis.demo', NULL, '+260-97-00', NULL, 'trainer', '$2y$10$d.tIFQWmrxgPE2CV7KA1x.nw4/ndDFJUikJ7PTpaRPO6Aybbn1aFO', 'training', NULL, 'Active', 'system', '2025-07-22 09:21:13', '2025-07-31 08:57:00', '2025-07-31 08:57:00', NULL, NULL, 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'DEMO004', 6, 'Smith', 'John', NULL, '', '0000-00-00', NULL, '', '', '', 'Male', 6, NULL, 'Officer', 'Active', NULL, NULL, NULL, NULL, NULL, '', '0000-00-00', NULL, NULL, '1990-01-01', '175', NULL, '', NULL, 'O+', NULL, '', '', NULL, NULL, NULL, 'Single', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, NULL, 'john.smith@armis.demo', NULL, '+260-97-00', NULL, 'staff1', '$2y$10$PL93uo/LN2lpDANy6Iq3Nuia.iRGnYyjZ6v1mnZ.ofyKjxvPFzn6e', 'admin_branch', NULL, 'Active', 'system', '2025-07-22 09:21:13', '2025-07-31 07:45:49', '2025-07-31 07:45:49', NULL, NULL, 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'DEMO005', 5, 'Johnson', 'Sarah', NULL, '', '0000-00-00', NULL, '', '', '', 'Male', 7, NULL, 'Officer', 'Active', NULL, NULL, NULL, NULL, NULL, '', '0000-00-00', NULL, NULL, '1990-01-01', '175', NULL, '', NULL, 'O+', NULL, '', '', NULL, NULL, NULL, 'Single', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, NULL, 'sarah.johnson@armis.demo', NULL, '+260-97-00', NULL, 'staff2', '$2y$10$.4NuIoKKQUJYKxWBQYSgyeiGHlAFkMfQ3.K2TrQY43Q4mpmmEgFMK', 'operations', NULL, 'Active', 'system', '2025-07-22 09:21:13', '2025-07-25 05:52:16', '2025-07-25 05:52:16', NULL, NULL, 'Active', '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_addresses`
--

DROP TABLE IF EXISTS `staff_addresses`;
CREATE TABLE IF NOT EXISTS `staff_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `address_type` enum('current','permanent','next_of_kin','emergency','postal','work') NOT NULL,
  `address_line_1` varchar(255) NOT NULL,
  `address_line_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Zambia',
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_addresses` (`staff_id`),
  KEY `idx_address_type` (`staff_id`,`address_type`),
  KEY `idx_primary_address` (`staff_id`,`is_primary`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_advancements`
--

DROP TABLE IF EXISTS `staff_advancements`;
CREATE TABLE IF NOT EXISTS `staff_advancements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `advancement_type` enum('promotion','reversion','demotion','commendation','award','transfer','appointment','completion','decoration','special_assignment','other') NOT NULL,
  `advancement_date` date NOT NULL,
  `previous_rank_id` int DEFAULT NULL,
  `new_rank_id` int DEFAULT NULL,
  `previous_unit_id` int DEFAULT NULL,
  `new_unit_id` int DEFAULT NULL,
  `authority` varchar(255) DEFAULT NULL,
  `notes` text,
  `certificate_number` varchar(100) DEFAULT NULL,
  `attachment_document_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_advancement_type` (`advancement_type`),
  KEY `idx_advancement_date` (`advancement_date`),
  KEY `idx_new_rank_id` (`new_rank_id`),
  KEY `idx_new_unit_id` (`new_unit_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_appointment`
--

DROP TABLE IF EXISTS `staff_appointment`;
CREATE TABLE IF NOT EXISTS `staff_appointment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `appointment_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `unit_id` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `service_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `appointment_date` date DEFAULT NULL,
  `comment` varchar(30) DEFAULT NULL,
  `created_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_current_appt` (`service_number`),
  KEY `apptID` (`appointment_id`),
  KEY `unitID` (`unit_id`),
  KEY `idx_staff_appointment_staff_id` (`staff_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_awards`
--

DROP TABLE IF EXISTS `staff_awards`;
CREATE TABLE IF NOT EXISTS `staff_awards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `award_name` varchar(255) NOT NULL,
  `award_type` enum('medal','decoration','commendation','certificate','badge','other') NOT NULL,
  `awarded_date` date NOT NULL,
  `awarded_by` varchar(255) DEFAULT NULL,
  `citation` text,
  `award_level` enum('international','national','military','unit','departmental') DEFAULT NULL,
  `ceremony_location` varchar(255) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_awards` (`staff_id`),
  KEY `idx_award_type` (`award_type`),
  KEY `idx_award_date` (`awarded_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_contact_info`
--

DROP TABLE IF EXISTS `staff_contact_info`;
CREATE TABLE IF NOT EXISTS `staff_contact_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `contact_type` enum('mobile','home','work','email','emergency','fax') NOT NULL,
  `contact_value` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_verified` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_primary_type` (`staff_id`,`contact_type`,`is_primary`),
  KEY `idx_staff_contact` (`staff_id`),
  KEY `idx_contact_type` (`staff_id`,`contact_type`),
  KEY `idx_primary_contacts` (`staff_id`,`is_primary`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_courses`
--

DROP TABLE IF EXISTS `staff_courses`;
CREATE TABLE IF NOT EXISTS `staff_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `service_number` int NOT NULL,
  `course_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `position_in_class` int DEFAULT NULL,
  `total_participants` int DEFAULT NULL,
  `certificate_number` varchar(50) DEFAULT NULL,
  `status` enum('Completed','In Progress','Withdrawn','Failed') DEFAULT 'Completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_course_date` (`staff_id`,`course_id`,`start_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_cvs`
--

DROP TABLE IF EXISTS `staff_cvs`;
CREATE TABLE IF NOT EXISTS `staff_cvs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int NOT NULL,
  `extracted_data` text,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_verified` tinyint(1) DEFAULT '0',
  `applied_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staff_cvs_staff_id` (`staff_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff_cvs`
--

INSERT INTO `staff_cvs` (`id`, `staff_id`, `filename`, `original_name`, `file_type`, `file_size`, `extracted_data`, `upload_date`, `is_verified`, `applied_date`) VALUES
(1, 1, 'cv_1_1753194141.pdf', 'CV - Marriott G. Mumba.pdf', 'application/pdf', 238370, '{\"education\":[],\"experience\":[],\"skills\":[],\"contact\":[],\"certifications\":[]}', '2025-07-22 14:22:26', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_dependants`
--

DROP TABLE IF EXISTS `staff_dependants`;
CREATE TABLE IF NOT EXISTS `staff_dependants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `nrc` varchar(30) DEFAULT NULL,
  `relationship` varchar(30) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `svcNo` (`service_number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_deployments`
--

DROP TABLE IF EXISTS `staff_deployments`;
CREATE TABLE IF NOT EXISTS `staff_deployments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `deployment_name` varchar(255) NOT NULL,
  `mission_type` enum('peacekeeping','training','operational','humanitarian','border_security','internal_security','other') NOT NULL,
  `location` varchar(255) NOT NULL,
  `country` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `duration_months` int DEFAULT NULL,
  `deployment_status` enum('planned','active','completed','cancelled') DEFAULT 'planned',
  `rank_during_deployment` varchar(100) DEFAULT NULL,
  `role_during_deployment` varchar(255) DEFAULT NULL,
  `commanding_officer` varchar(255) DEFAULT NULL,
  `deployment_allowance` decimal(10,2) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_deployments` (`staff_id`),
  KEY `idx_deployment_status` (`deployment_status`),
  KEY `idx_deployment_dates` (`start_date`,`end_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

DROP TABLE IF EXISTS `staff_documents`;
CREATE TABLE IF NOT EXISTS `staff_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `document_type_id` int NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int NOT NULL,
  `access_level` enum('public','restricted','confidential') DEFAULT 'restricted',
  `description` text,
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_staff_documents_staff_id` (`staff_id`),
  KEY `idx_staff_documents_type` (`document_type_id`),
  KEY `idx_staff_documents_date` (`upload_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_edit_log`
--

DROP TABLE IF EXISTS `staff_edit_log`;
CREATE TABLE IF NOT EXISTS `staff_edit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_number` varchar(50) NOT NULL,
  `edited_by` int NOT NULL,
  `edited_at` datetime NOT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `changes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff_edit_log`
--

INSERT INTO `staff_edit_log` (`id`, `service_number`, `edited_by`, `edited_at`, `user_ip`, `changes`, `created_at`) VALUES
(1, '007414', 1, '2025-08-11 13:33:48', '::1', '[{\"field\": \"rank_id\", \"new_value\": \"28\", \"old_value\": 12}]', '2025-08-11 13:33:48'),
(2, '007414', 1, '2025-08-11 13:47:36', '::1', '[{\"field\": \"rank_id\", \"new_value\": \"28\", \"old_value\": 12}, {\"field\": \"corps_id\", \"new_value\": \"10\", \"old_value\": null}]', '2025-08-11 13:47:36'),
(3, '007414', 1, '2025-08-11 14:06:19', '::1', '[{\"field\": \"rank_id\", \"new_value\": \"28\", \"old_value\": 12}, {\"field\": \"corps_id\", \"new_value\": \"10\", \"old_value\": null}]', '2025-08-11 14:06:19');

-- --------------------------------------------------------

--
-- Table structure for table `staff_education`
--

DROP TABLE IF EXISTS `staff_education`;
CREATE TABLE IF NOT EXISTS `staff_education` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `institution` varchar(255) NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `level` enum('primary','secondary','certificate','diploma','degree','masters','doctorate','professional') NOT NULL,
  `field_of_study` varchar(255) DEFAULT NULL,
  `year_started` year DEFAULT NULL,
  `year_completed` year DEFAULT NULL,
  `grade_obtained` varchar(50) DEFAULT NULL,
  `is_highest_qualification` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_education` (`staff_id`),
  KEY `idx_education_level` (`staff_id`,`level`),
  KEY `idx_highest_qualification` (`staff_id`,`is_highest_qualification`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_equipment`
--

DROP TABLE IF EXISTS `staff_equipment`;
CREATE TABLE IF NOT EXISTS `staff_equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `equipment_type` enum('weapon','vehicle','communication','protective','office','medical','other') NOT NULL,
  `equipment_serial` varchar(100) DEFAULT NULL,
  `equipment_model` varchar(255) DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `condition_when_assigned` enum('new','good','fair','poor') DEFAULT 'good',
  `condition_when_returned` enum('new','good','fair','poor','damaged','lost') DEFAULT NULL,
  `assigned_by` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_equipment` (`staff_id`),
  KEY `idx_equipment_type` (`equipment_type`),
  KEY `idx_assignment_dates` (`assigned_date`,`return_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_family_members`
--

DROP TABLE IF EXISTS `staff_family_members`;
CREATE TABLE IF NOT EXISTS `staff_family_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `relationship` enum('spouse','father','mother','son','daughter','brother','sister','uncle','aunt','cousin','nephew','niece','grandfather','grandmother','other','emergency') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `is_next_of_kin` tinyint(1) DEFAULT '0',
  `is_emergency_contact` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_family` (`staff_id`),
  KEY `idx_next_of_kin` (`staff_id`,`is_next_of_kin`),
  KEY `idx_emergency_contact` (`staff_id`,`is_emergency_contact`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_full`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `staff_full`;
CREATE TABLE IF NOT EXISTS `staff_full` (
);

-- --------------------------------------------------------

--
-- Table structure for table `staff_languages`
--

DROP TABLE IF EXISTS `staff_languages`;
CREATE TABLE IF NOT EXISTS `staff_languages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `language_name` varchar(100) NOT NULL,
  `proficiency_level` enum('native','fluent','intermediate','basic','beginner') NOT NULL,
  `can_read` tinyint(1) DEFAULT '1',
  `can_write` tinyint(1) DEFAULT '1',
  `can_speak` tinyint(1) DEFAULT '1',
  `can_understand` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_languages` (`staff_id`),
  KEY `idx_language_proficiency` (`staff_id`,`proficiency_level`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_leave_records`
--

DROP TABLE IF EXISTS `staff_leave_records`;
CREATE TABLE IF NOT EXISTS `staff_leave_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `leave_type` enum('annual','sick','maternity','paternity','compassionate','study','unpaid','special','other') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` int NOT NULL,
  `days_approved` int DEFAULT NULL,
  `leave_status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `reason` text,
  `approved_by` varchar(255) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `approval_comments` text,
  `contact_during_leave` varchar(255) DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_leave` (`staff_id`),
  KEY `idx_leave_type` (`leave_type`),
  KEY `idx_leave_dates` (`start_date`,`end_date`),
  KEY `idx_leave_status` (`leave_status`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_medals`
--

DROP TABLE IF EXISTS `staff_medals`;
CREATE TABLE IF NOT EXISTS `staff_medals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `service_number` varchar(32) NOT NULL,
  `medal_id` int NOT NULL,
  `award_date` date NOT NULL,
  `citation` text,
  `gazette_reference` varchar(50) DEFAULT NULL,
  `bar_number` int DEFAULT '0',
  `created_by` varchar(15) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_medal` (`staff_id`,`medal_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_medical_records`
--

DROP TABLE IF EXISTS `staff_medical_records`;
CREATE TABLE IF NOT EXISTS `staff_medical_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `medical_conditions` text,
  `allergies` text,
  `current_medications` text,
  `medical_fitness_status` enum('fit','unfit','limited_duty','under_review') DEFAULT 'fit',
  `last_medical_exam` date DEFAULT NULL,
  `next_medical_exam` date DEFAULT NULL,
  `medical_officer` varchar(255) DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_medical` (`staff_id`),
  KEY `idx_fitness_status` (`medical_fitness_status`),
  KEY `idx_medical_exams` (`last_medical_exam`,`next_medical_exam`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_operations`
--

DROP TABLE IF EXISTS `staff_operations`;
CREATE TABLE IF NOT EXISTS `staff_operations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `operation_id` int NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `performance_rating` enum('Excellent','Good','Satisfactory','Needs Improvement') DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_operation` (`staff_id`,`operation_id`,`start_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_password_history`
--

DROP TABLE IF EXISTS `staff_password_history`;
CREATE TABLE IF NOT EXISTS `staff_password_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_performance_reviews`
--

DROP TABLE IF EXISTS `staff_performance_reviews`;
CREATE TABLE IF NOT EXISTS `staff_performance_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `review_period_start` date NOT NULL,
  `review_period_end` date NOT NULL,
  `overall_rating` enum('outstanding','exceeds_expectations','meets_expectations','below_expectations','unsatisfactory') NOT NULL,
  `technical_competence_rating` int DEFAULT '0',
  `leadership_rating` int DEFAULT '0',
  `teamwork_rating` int DEFAULT '0',
  `discipline_rating` int DEFAULT '0',
  `initiative_rating` int DEFAULT '0',
  `strengths` text,
  `areas_for_improvement` text,
  `goals_for_next_period` text,
  `reviewer_name` varchar(255) NOT NULL,
  `reviewer_rank` varchar(100) DEFAULT NULL,
  `review_date` date NOT NULL,
  `staff_comments` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_reviews` (`staff_id`),
  KEY `idx_review_period` (`review_period_start`,`review_period_end`),
  KEY `idx_overall_rating` (`overall_rating`)
) ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_promotions`
--

DROP TABLE IF EXISTS `staff_promotions`;
CREATE TABLE IF NOT EXISTS `staff_promotions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int UNSIGNED NOT NULL,
  `current_rank` int UNSIGNED NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `type` enum('promotion','reversion','demotion') NOT NULL,
  `new_rank` int UNSIGNED NOT NULL,
  `authority` varchar(255) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `before_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_new_rank` (`new_rank`),
  KEY `idx_current_rank` (`current_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_skills`
--

DROP TABLE IF EXISTS `staff_skills`;
CREATE TABLE IF NOT EXISTS `staff_skills` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `skill_level` enum('beginner','intermediate','advanced','expert') NOT NULL,
  `skill_category` enum('technical','language','leadership','combat','driving','computer','communication','other') NOT NULL,
  `years_of_experience` int DEFAULT NULL,
  `certified` tinyint(1) DEFAULT '0',
  `certification_body` varchar(255) DEFAULT NULL,
  `certification_date` date DEFAULT NULL,
  `certification_expiry` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_skills` (`staff_id`),
  KEY `idx_skill_category` (`skill_category`),
  KEY `idx_skill_level` (`staff_id`,`skill_level`),
  KEY `idx_staff_skills_staff_id` (`staff_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_spouse`
--

DROP TABLE IF EXISTS `staff_spouse`;
CREATE TABLE IF NOT EXISTS `staff_spouse` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `spouseName` varchar(150) NOT NULL,
  `spouseDOB` date DEFAULT NULL,
  `spouseNRC` varchar(15) DEFAULT NULL,
  `spouseOccup` varchar(150) DEFAULT NULL,
  `spouseContact` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_number` (`service_number`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_training`
--

DROP TABLE IF EXISTS `staff_training`;
CREATE TABLE IF NOT EXISTS `staff_training` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `course_type` enum('military','technical','leadership','specialist','international','academic','certification') NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `duration_days` int DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `grade_obtained` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `sponsored_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_training` (`staff_id`),
  KEY `idx_training_type` (`staff_id`,`course_type`),
  KEY `idx_training_dates` (`start_date`,`end_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_with_ranks`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `staff_with_ranks`;
CREATE TABLE IF NOT EXISTS `staff_with_ranks` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `staff_with_units`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `staff_with_units`;
CREATE TABLE IF NOT EXISTS `staff_with_units` (
);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `code` varchar(20) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `parent_unit_id` int DEFAULT NULL,
  `commander_id` int DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `name`, `code`, `type`, `parent_unit_id`, `commander_id`, `location`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Zambian Army Headquarters', 'ZA-HQ', 'HQ', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(2, '1st Armoured Regiment', '1-AR', 'Battalion', 16, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(3, '1st Battalion Zambian Regiment', '1-ZR', 'Battalion', 16, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(4, '2nd Battalion Zambian Regiment', '2-ZR', 'Battalion', 17, NULL, 'Ndola', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(5, '3rd Battalion Zambian Regiment', '3-ZR', 'Battalion', 20, NULL, 'Solwezi', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(6, '1st Commando Battalion', '1-COMM', 'Battalion', 16, NULL, 'Kabwe', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(7, 'Zambian Intelligence Corps', 'ZIC', 'Battalion', 16, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(8, 'Army Medical Corps', 'AMC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(9, 'Army Engineers Corps', 'AEC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(10, 'Army Signals Corps', 'ASC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(11, 'Army Logistics Corps', 'ALC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(12, 'Military Police Corps', 'MPC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(13, 'Zambian Army Training School', 'ZATS', 'Company', 16, NULL, 'Kabwe', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(14, 'Combat Training Centre', 'CTC', 'Company', 16, NULL, 'Mumbwa', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(15, 'Officer Cadet School', 'OCS', 'Company', 16, NULL, 'Kabwe', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(16, 'Central Command', 'CENT-CMD', 'HQ', 1, NULL, 'Kabwe', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(17, 'Northern Command', 'NORTH-CMD', 'HQ', 1, NULL, 'Ndola', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(18, 'Southern Command', 'SOUTH-CMD', 'HQ', 1, NULL, 'Livingstone', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(19, 'Eastern Command', 'EAST-CMD', 'HQ', 1, NULL, 'Chipata', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(20, 'Western Command', 'WEST-CMD', 'HQ', 1, NULL, 'Mongu', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(21, 'Presidential Guard Battalion', 'PGB', 'Battalion', 16, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(22, 'Special Forces Unit', 'SFU', 'Company', 16, NULL, 'Kabwe', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(23, 'Army Air Wing', 'AAW', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(24, 'Military Intelligence', 'MI', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(25, 'Peacekeeping Training Centre', 'PKTC', 'Company', 1, NULL, 'Lusaka', 1, '2025-07-17 12:32:53', '2025-07-17 12:32:53'),
(26, 'Zambian Army Headquarters', 'ZA-HQ', 'HQ', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(27, '1st Armoured Regiment', '1-AR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(28, '1st Battalion Zambian Regiment', '1-ZR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(29, '2nd Battalion Zambian Regiment', '2-ZR', 'Battalion', NULL, NULL, 'Ndola', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(30, '3rd Battalion Zambian Regiment', '3-ZR', 'Battalion', NULL, NULL, 'Solwezi', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(31, '1st Commando Battalion', '1-COMM', 'Battalion', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(32, 'Zambian Intelligence Corps', 'ZIC', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(33, 'Army Medical Corps', 'AMC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(34, 'Army Engineers Corps', 'AEC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(35, 'Army Signals Corps', 'ASC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(36, 'Army Logistics Corps', 'ALC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(37, 'Military Police Corps', 'MPC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(38, 'Zambian Army Training School', 'ZATS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(39, 'Combat Training Centre', 'CTC', 'Company', NULL, NULL, 'Mumbwa', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(40, 'Officer Cadet School', 'OCS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(41, 'Central Command', 'CENT-CMD', 'HQ', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(42, 'Northern Command', 'NORTH-CMD', 'HQ', NULL, NULL, 'Ndola', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(43, 'Southern Command', 'SOUTH-CMD', 'HQ', NULL, NULL, 'Livingstone', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(44, 'Eastern Command', 'EAST-CMD', 'HQ', NULL, NULL, 'Chipata', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(45, 'Western Command', 'WEST-CMD', 'HQ', NULL, NULL, 'Mongu', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(46, 'Presidential Guard Battalion', 'PGB', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(47, 'Special Forces Unit', 'SFU', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(48, 'Army Air Wing', 'AAW', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(49, 'Military Intelligence', 'MI', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(50, 'Peacekeeping Training Centre', 'PKTC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:00', '2025-07-17 12:35:00'),
(51, 'Zambian Army Headquarters', 'ZA-HQ', 'HQ', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(52, '1st Armoured Regiment', '1-AR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(53, '1st Battalion Zambian Regiment', '1-ZR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(54, '2nd Battalion Zambian Regiment', '2-ZR', 'Battalion', NULL, NULL, 'Ndola', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(55, '3rd Battalion Zambian Regiment', '3-ZR', 'Battalion', NULL, NULL, 'Solwezi', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(56, '1st Commando Battalion', '1-COMM', 'Battalion', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(57, 'Zambian Intelligence Corps', 'ZIC', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(58, 'Army Medical Corps', 'AMC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(59, 'Army Engineers Corps', 'AEC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(60, 'Army Signals Corps', 'ASC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(61, 'Army Logistics Corps', 'ALC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(62, 'Military Police Corps', 'MPC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(63, 'Zambian Army Training School', 'ZATS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(64, 'Combat Training Centre', 'CTC', 'Company', NULL, NULL, 'Mumbwa', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(65, 'Officer Cadet School', 'OCS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(66, 'Central Command', 'CENT-CMD', 'HQ', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(67, 'Northern Command', 'NORTH-CMD', 'HQ', NULL, NULL, 'Ndola', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(68, 'Southern Command', 'SOUTH-CMD', 'HQ', NULL, NULL, 'Livingstone', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(69, 'Eastern Command', 'EAST-CMD', 'HQ', NULL, NULL, 'Chipata', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(70, 'Western Command', 'WEST-CMD', 'HQ', NULL, NULL, 'Mongu', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(71, 'Presidential Guard Battalion', 'PGB', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(72, 'Special Forces Unit', 'SFU', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(73, 'Army Air Wing', 'AAW', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(74, 'Military Intelligence', 'MI', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(75, 'Peacekeeping Training Centre', 'PKTC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:35:59', '2025-07-17 12:35:59'),
(76, 'Zambian Army Headquarters', 'ZA-HQ', 'HQ', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(77, '1st Armoured Regiment', '1-AR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(78, '1st Battalion Zambian Regiment', '1-ZR', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(79, '2nd Battalion Zambian Regiment', '2-ZR', 'Battalion', NULL, NULL, 'Ndola', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(80, '3rd Battalion Zambian Regiment', '3-ZR', 'Battalion', NULL, NULL, 'Solwezi', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(81, '1st Commando Battalion', '1-COMM', 'Battalion', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(82, 'Zambian Intelligence Corps', 'ZIC', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(83, 'Army Medical Corps', 'AMC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(84, 'Army Engineers Corps', 'AEC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(85, 'Army Signals Corps', 'ASC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(86, 'Army Logistics Corps', 'ALC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(87, 'Military Police Corps', 'MPC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(88, 'Zambian Army Training School', 'ZATS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(89, 'Combat Training Centre', 'CTC', 'Company', NULL, NULL, 'Mumbwa', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(90, 'Officer Cadet School', 'OCS', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(91, 'Central Command', 'CENT-CMD', 'HQ', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(92, 'Northern Command', 'NORTH-CMD', 'HQ', NULL, NULL, 'Ndola', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(93, 'Southern Command', 'SOUTH-CMD', 'HQ', NULL, NULL, 'Livingstone', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(94, 'Eastern Command', 'EAST-CMD', 'HQ', NULL, NULL, 'Chipata', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(95, 'Western Command', 'WEST-CMD', 'HQ', NULL, NULL, 'Mongu', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(96, 'Presidential Guard Battalion', 'PGB', 'Battalion', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(97, 'Special Forces Unit', 'SFU', 'Company', NULL, NULL, 'Kabwe', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(98, 'Army Air Wing', 'AAW', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(99, 'Military Intelligence', 'MI', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27'),
(100, 'Peacekeeping Training Centre', 'PKTC', 'Company', NULL, NULL, 'Lusaka', 1, '2025-07-17 12:36:27', '2025-07-17 12:36:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','admin_branch','command','training','operations') DEFAULT 'command',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `staff_id` (`staff_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_mfa`
--

DROP TABLE IF EXISTS `user_mfa`;
CREATE TABLE IF NOT EXISTS `user_mfa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `secret` varchar(255) NOT NULL,
  `enabled` tinyint(1) DEFAULT '0',
  `backup_codes_used` int DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure for view `current_appointments`
--
DROP TABLE IF EXISTS `current_appointments`;

DROP VIEW IF EXISTS `current_appointments`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `current_appointments`  AS SELECT `a`.`apptID` AS `apptID`, `a`.`staff_id` AS `staff_id`, concat(`s`.`fname`,' ',`s`.`lname`) AS `staff_name`, `s`.`svcNo` AS `svcNo`, `a`.`position` AS `position`, `a`.`unit_id` AS `unit_id`, `u`.`unitName` AS `unit_name`, `u`.`unitLoc` AS `unit_location`, `a`.`start_date` AS `start_date`, `a`.`end_date` AS `end_date`, `a`.`is_current` AS `is_current`, `a`.`created_at` AS `created_at` FROM ((`appointments` `a` left join `staff` `s` on((`a`.`staff_id` = `s`.`svcNo`))) left join `units` `u` on((`a`.`unit_id` = `u`.`unitID`))) WHERE (`a`.`is_current` = true) ORDER BY `u`.`unitName` ASC, `a`.`position` ASC, `s`.`lname` ASC, `s`.`fname` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `staff_full`
--
DROP TABLE IF EXISTS `staff_full`;

DROP VIEW IF EXISTS `staff_full`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_full`  AS SELECT `s`.`svcNo` AS `svcNo`, `s`.`rankID` AS `rankID`, `s`.`lname` AS `lname`, `s`.`fname` AS `fname`, `s`.`NRC` AS `NRC`, `s`.`passport` AS `passport`, `s`.`passExp` AS `passExp`, `s`.`combatSize` AS `combatSize`, `s`.`bsize` AS `bsize`, `s`.`ssize` AS `ssize`, `s`.`hdress` AS `hdress`, `s`.`gender` AS `gender`, `s`.`unitID` AS `unitID`, `s`.`category` AS `category`, `s`.`svcStatus` AS `svcStatus`, `s`.`appt` AS `appt`, `s`.`subRank` AS `subRank`, `s`.`subWef` AS `subWef`, `s`.`tempRank` AS `tempRank`, `s`.`tempWef` AS `tempWef`, `s`.`localRank` AS `localRank`, `s`.`localWef` AS `localWef`, `s`.`attestDate` AS `attestDate`, `s`.`intake` AS `intake`, `s`.`DOB` AS `DOB`, `s`.`height` AS `height`, `s`.`province` AS `province`, `s`.`corps` AS `corps`, `s`.`bloodGp` AS `bloodGp`, `s`.`profession` AS `profession`, `s`.`trade` AS `trade`, `s`.`digitalID` AS `digitalID`, `s`.`prefix` AS `prefix`, `s`.`marital` AS `marital`, `s`.`initials` AS `initials`, `s`.`titles` AS `titles`, `s`.`nok` AS `nok`, `s`.`nokNrc` AS `nokNrc`, `s`.`nokRelat` AS `nokRelat`, `s`.`nokTel` AS `nokTel`, `s`.`altNok` AS `altNok`, `s`.`altNokTel` AS `altNokTel`, `s`.`altNokNrc` AS `altNokNrc`, `s`.`altNokRelat` AS `altNokRelat`, `s`.`email` AS `email`, `s`.`tel` AS `tel`, `s`.`unitAtt` AS `unitAtt`, `s`.`username` AS `username`, `s`.`password` AS `password`, `s`.`role` AS `role`, `s`.`renewDate` AS `renewDate`, `s`.`accStatus` AS `accStatus`, `s`.`createdBy` AS `createdBy`, `s`.`dateCreated` AS `dateCreated`, `s`.`updated_at` AS `updated_at`, `s`.`last_login` AS `last_login`, `s`.`password_changed_at` AS `password_changed_at`, `r`.`rankName` AS `rank_name`, `r`.`rankAbb` AS `rank_abbreviation`, `r`.`rankIndex` AS `rank_level`, `u`.`unitName` AS `unit_name`, `u`.`unitLoc` AS `unit_location` FROM ((`staff` `s` left join `ranks` `r` on((`s`.`rankID` = `r`.`rankID`))) left join `units` `u` on((`s`.`unitID` = `u`.`unitID`))) ;

-- --------------------------------------------------------

--
-- Structure for view `staff_with_ranks`
--
DROP TABLE IF EXISTS `staff_with_ranks`;

DROP VIEW IF EXISTS `staff_with_ranks`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_with_ranks`  AS SELECT `s`.`svcNo` AS `svcNo`, `s`.`rankID` AS `rankID`, `s`.`lname` AS `lname`, `s`.`fname` AS `fname`, `s`.`NRC` AS `NRC`, `s`.`passport` AS `passport`, `s`.`passExp` AS `passExp`, `s`.`combatSize` AS `combatSize`, `s`.`bsize` AS `bsize`, `s`.`ssize` AS `ssize`, `s`.`hdress` AS `hdress`, `s`.`gender` AS `gender`, `s`.`unitID` AS `unitID`, `s`.`category` AS `category`, `s`.`svcStatus` AS `svcStatus`, `s`.`appt` AS `appt`, `s`.`subRank` AS `subRank`, `s`.`subWef` AS `subWef`, `s`.`tempRank` AS `tempRank`, `s`.`tempWef` AS `tempWef`, `s`.`localRank` AS `localRank`, `s`.`localWef` AS `localWef`, `s`.`attestDate` AS `attestDate`, `s`.`intake` AS `intake`, `s`.`DOB` AS `DOB`, `s`.`height` AS `height`, `s`.`province` AS `province`, `s`.`corps` AS `corps`, `s`.`bloodGp` AS `bloodGp`, `s`.`profession` AS `profession`, `s`.`trade` AS `trade`, `s`.`digitalID` AS `digitalID`, `s`.`prefix` AS `prefix`, `s`.`marital` AS `marital`, `s`.`initials` AS `initials`, `s`.`titles` AS `titles`, `s`.`nok` AS `nok`, `s`.`nokNrc` AS `nokNrc`, `s`.`nokRelat` AS `nokRelat`, `s`.`nokTel` AS `nokTel`, `s`.`altNok` AS `altNok`, `s`.`altNokTel` AS `altNokTel`, `s`.`altNokNrc` AS `altNokNrc`, `s`.`altNokRelat` AS `altNokRelat`, `s`.`email` AS `email`, `s`.`tel` AS `tel`, `s`.`unitAtt` AS `unitAtt`, `s`.`username` AS `username`, `s`.`password` AS `password`, `s`.`role` AS `role`, `s`.`renewDate` AS `renewDate`, `s`.`accStatus` AS `accStatus`, `s`.`createdBy` AS `createdBy`, `s`.`dateCreated` AS `dateCreated`, `s`.`updated_at` AS `updated_at`, `s`.`last_login` AS `last_login`, `s`.`password_changed_at` AS `password_changed_at`, `r`.`rankName` AS `rank_name`, `r`.`rankAbb` AS `rank_abbreviation`, `r`.`rankIndex` AS `rank_level` FROM (`staff` `s` left join `ranks` `r` on((`s`.`rankID` = `r`.`rankID`))) ;

-- --------------------------------------------------------

--
-- Structure for view `staff_with_units`
--
DROP TABLE IF EXISTS `staff_with_units`;

DROP VIEW IF EXISTS `staff_with_units`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `staff_with_units`  AS SELECT `s`.`svcNo` AS `svcNo`, `s`.`rankID` AS `rankID`, `s`.`lname` AS `lname`, `s`.`fname` AS `fname`, `s`.`NRC` AS `NRC`, `s`.`passport` AS `passport`, `s`.`passExp` AS `passExp`, `s`.`combatSize` AS `combatSize`, `s`.`bsize` AS `bsize`, `s`.`ssize` AS `ssize`, `s`.`hdress` AS `hdress`, `s`.`gender` AS `gender`, `s`.`unitID` AS `unitID`, `s`.`category` AS `category`, `s`.`svcStatus` AS `svcStatus`, `s`.`appt` AS `appt`, `s`.`subRank` AS `subRank`, `s`.`subWef` AS `subWef`, `s`.`tempRank` AS `tempRank`, `s`.`tempWef` AS `tempWef`, `s`.`localRank` AS `localRank`, `s`.`localWef` AS `localWef`, `s`.`attestDate` AS `attestDate`, `s`.`intake` AS `intake`, `s`.`DOB` AS `DOB`, `s`.`height` AS `height`, `s`.`province` AS `province`, `s`.`corps` AS `corps`, `s`.`bloodGp` AS `bloodGp`, `s`.`profession` AS `profession`, `s`.`trade` AS `trade`, `s`.`digitalID` AS `digitalID`, `s`.`prefix` AS `prefix`, `s`.`marital` AS `marital`, `s`.`initials` AS `initials`, `s`.`titles` AS `titles`, `s`.`nok` AS `nok`, `s`.`nokNrc` AS `nokNrc`, `s`.`nokRelat` AS `nokRelat`, `s`.`nokTel` AS `nokTel`, `s`.`altNok` AS `altNok`, `s`.`altNokTel` AS `altNokTel`, `s`.`altNokNrc` AS `altNokNrc`, `s`.`altNokRelat` AS `altNokRelat`, `s`.`email` AS `email`, `s`.`tel` AS `tel`, `s`.`unitAtt` AS `unitAtt`, `s`.`username` AS `username`, `s`.`password` AS `password`, `s`.`role` AS `role`, `s`.`renewDate` AS `renewDate`, `s`.`accStatus` AS `accStatus`, `s`.`createdBy` AS `createdBy`, `s`.`dateCreated` AS `dateCreated`, `s`.`updated_at` AS `updated_at`, `s`.`last_login` AS `last_login`, `s`.`password_changed_at` AS `password_changed_at`, `u`.`unitName` AS `unit_name`, `u`.`unitLoc` AS `unit_location` FROM (`staff` `s` left join `units` `u` on((`s`.`unitID` = `u`.`unitID`))) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `staff_promotions`
--
ALTER TABLE `staff_promotions`
  ADD CONSTRAINT `staff_promotions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `staff_promotions_ibfk_2` FOREIGN KEY (`new_rank`) REFERENCES `ranks` (`id`),
  ADD CONSTRAINT `staff_promotions_ibfk_3` FOREIGN KEY (`current_rank`) REFERENCES `ranks` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
