-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 04, 2025 at 10:15 AM
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
-- Database: `armis`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

DROP TABLE IF EXISTS `appointment`;
CREATE TABLE IF NOT EXISTS `appointment` (
  `apptID` varchar(30) NOT NULL,
  `apptType` varchar(50) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`apptID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit`
--

DROP TABLE IF EXISTS `audit`;
CREATE TABLE IF NOT EXISTS `audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user` int NOT NULL,
  `page` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `viewed` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit`
--

INSERT INTO `audit` (`id`, `user`, `page`, `timestamp`, `ip`, `viewed`) VALUES
(1, 5, '99', '2025-06-19 07:10:55', '::1', 0),
(2, 0, '98', '2025-06-20 09:23:20', '::1', 0),
(3, 5, '108', '2025-06-20 10:06:36', '::1', 0),
(4, 5, '114', '2025-06-20 14:13:04', '::1', 0),
(5, 0, '4', '2025-06-25 07:43:16', '::1', 0),
(6, 0, '24', '2025-07-01 13:07:15', '::1', 0),
(7, 0, '107', '2025-07-02 09:33:24', '::1', 0),
(8, 0, '3', '2025-07-02 09:33:24', '::1', 0);

-- --------------------------------------------------------

--
-- Table structure for table `corps`
--

DROP TABLE IF EXISTS `corps`;
CREATE TABLE IF NOT EXISTS `corps` (
  `corpsID` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `corpsName` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `corpsAbb` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`corpsID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `corps`
--

INSERT INTO `corps` (`corpsID`, `corpsName`, `corpsAbb`) VALUES
('1', 'Infantry', 'ZINF'),
('2', 'Armour', 'ZAC'),
('3', 'Artillery', 'ZA'),
('4', 'Commando', 'CDO'),
('5', 'Marine', 'MAR'),
('6', 'Education', 'ZCE'),
('7', 'Ordnance', 'ZOC'),
('8', 'Chaplaincy ', 'CHAP'),
('9', 'Finance', 'ZAPC'),
('10', 'Signals', 'ZSIGS'),
('11', 'Engineer', 'ZE'),
('12', 'Transport', 'ZCT'),
('13', 'Medical', 'ZAMC'),
('14', 'Military Police', 'ZMP'),
('15', 'Electrical & Mech Engineer', 'ZEME');

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

DROP TABLE IF EXISTS `course`;
CREATE TABLE IF NOT EXISTS `course` (
  `cseID` varchar(30) NOT NULL,
  `cseName` varchar(100) NOT NULL,
  `cseType` varchar(50) NOT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cseID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crons`
--

DROP TABLE IF EXISTS `crons`;
CREATE TABLE IF NOT EXISTS `crons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `active` int NOT NULL DEFAULT '1',
  `sort` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `createdby` int NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crons`
--

INSERT INTO `crons` (`id`, `active`, `sort`, `name`, `file`, `createdby`, `created`, `modified`) VALUES
(1, 0, 100, 'Auto-Backup', 'backup.php', 1, '2017-09-16 07:49:22', '2017-11-11 20:15:36');

-- --------------------------------------------------------

--
-- Table structure for table `crons_logs`
--

DROP TABLE IF EXISTS `crons_logs`;
CREATE TABLE IF NOT EXISTS `crons_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cron_id` int NOT NULL,
  `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deletion_log`
--

DROP TABLE IF EXISTS `deletion_log`;
CREATE TABLE IF NOT EXISTS `deletion_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(32) NOT NULL,
  `deleted_by` int NOT NULL,
  `deleted_at` datetime NOT NULL,
  `user_ip` varchar(45) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email`
--

DROP TABLE IF EXISTS `email`;
CREATE TABLE IF NOT EXISTS `email` (
  `id` int NOT NULL AUTO_INCREMENT,
  `website_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `smtp_server` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `smtp_port` int NOT NULL,
  `email_login` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_pass` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `from_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `from_email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `transport` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `verify_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_act` int NOT NULL,
  `debug_level` int NOT NULL DEFAULT '0',
  `isSMTP` int NOT NULL DEFAULT '0',
  `isHTML` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'true',
  `useSMTPauth` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'true',
  `authtype` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'CRAM-MD5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email`
--

INSERT INTO `email` (`id`, `website_name`, `smtp_server`, `smtp_port`, `email_login`, `email_pass`, `from_name`, `from_email`, `transport`, `verify_url`, `email_act`, `debug_level`, `isSMTP`, `isHTML`, `useSMTPauth`, `authtype`) VALUES
(1, 'Army Resource Management Information System', 'smtp.gmail.com', 587, 'yourEmail@gmail.com', '1234', 'Army Resource Management Information System', 'yourEmail@gmail.com', 'tls', 'http://localhost/armis/', 0, 0, 0, 'true', 'true', 'CRAM-MD5');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `device` text NOT NULL,
  `model` varchar(200) NOT NULL,
  `snumber` text NOT NULL,
  `itlabel` text NOT NULL,
  `issuedto` text NOT NULL,
  `fpoint` text NOT NULL,
  `dte` datetime(6) NOT NULL,
  `unit` text NOT NULL,
  `serviceability` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groups_menus`
--

DROP TABLE IF EXISTS `groups_menus`;
CREATE TABLE IF NOT EXISTS `groups_menus` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int UNSIGNED NOT NULL,
  `menu_id` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `menu_id` (`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups_menus`
--

INSERT INTO `groups_menus` (`id`, `group_id`, `menu_id`) VALUES
(5, 0, 3),
(6, 0, 1),
(7, 0, 2),
(8, 0, 51),
(9, 0, 52),
(10, 0, 37),
(11, 0, 38),
(12, 2, 39),
(13, 2, 40),
(14, 2, 41),
(15, 2, 42),
(16, 2, 43),
(17, 2, 44),
(18, 2, 45),
(19, 0, 46),
(20, 0, 47),
(21, 0, 49),
(25, 0, 18),
(26, 0, 20),
(27, 0, 21),
(28, 0, 7),
(29, 0, 8),
(30, 2, 9),
(31, 2, 10),
(32, 2, 11),
(33, 2, 12),
(34, 2, 13),
(35, 2, 14),
(36, 2, 15),
(37, 0, 16),
(38, 1, 15),
(45, 2, 23),
(46, 3, 23);

-- --------------------------------------------------------

--
-- Table structure for table `institution`
--

DROP TABLE IF EXISTS `institution`;
CREATE TABLE IF NOT EXISTS `institution` (
  `instID` varchar(30) NOT NULL,
  `instLoc` varchar(30) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`instID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keys`
--

DROP TABLE IF EXISTS `keys`;
CREATE TABLE IF NOT EXISTS `keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stripe_ts` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stripe_tp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stripe_ls` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stripe_lp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recap_pub` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recap_pri` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT '0',
  `logdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logtype` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lognote` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `metadata` blob,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `logdate`, `logtype`, `lognote`, `ip`, `metadata`) VALUES
(1, 1, '2022-12-23 12:05:38', 'System Updates', 'Update 2022-05-04a successfully deployed.', '::1', NULL),
(2, 1, '2022-12-23 12:05:43', 'login', 'User logged in.', '::1', NULL),
(3, 1, '2022-12-23 12:06:38', 'System Updates', 'Update 2022-11-06a successfully deployed.', '::1', NULL),
(4, 1, '2022-12-23 12:06:38', 'System Updates', 'Update 2022-11-20a successfully deployed.', '::1', NULL),
(5, 1, '2022-12-23 12:06:38', 'System Updates', 'Update 2022-12-04a successfully deployed.', '::1', NULL),
(6, 1, '2022-12-23 12:06:38', 'System Updates', 'Update 2022-12-22a successfully deployed.', '::1', NULL),
(7, 1, '2022-12-23 12:06:38', 'System Updates', 'Update 2022-12-23a successfully deployed.', '::1', NULL),
(8, 1, '2022-12-23 12:16:27', 'login', 'User logged in.', '::1', NULL),
(9, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-01-02a successfully deployed.', '::1', NULL),
(10, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-01-03a successfully deployed.', '::1', NULL),
(11, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-01-03b successfully deployed.', '::1', NULL),
(12, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-01-05a successfully deployed.', '::1', NULL),
(13, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-01-07a successfully deployed.', '::1', NULL),
(14, 1, '2024-09-25 09:30:55', 'System Updates', 'Update 2023-02-10a successfully deployed.', '::1', NULL),
(15, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2023-05-19a successfully deployed.', '::1', NULL),
(16, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2023-06-29a successfully deployed.', '::1', NULL),
(17, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2023-06-29b successfully deployed.', '::1', NULL),
(18, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2023-11-15a successfully deployed.', '::1', NULL),
(19, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2023-11-17a successfully deployed.', '::1', NULL),
(20, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-12a successfully deployed.', '::1', NULL),
(21, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-13a successfully deployed.', '::1', NULL),
(22, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-14a successfully deployed.', '::1', NULL),
(23, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-15a successfully deployed.', '::1', NULL),
(24, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-17a successfully deployed.', '::1', NULL),
(25, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-17b successfully deployed.', '::1', NULL),
(26, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-18a successfully deployed.', '::1', NULL),
(27, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-20a successfully deployed.', '::1', NULL),
(28, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-03-22a successfully deployed.', '::1', NULL),
(29, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-04-01a successfully deployed.', '::1', NULL),
(30, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-04-13a successfully deployed.', '::1', NULL),
(31, 1, '2024-09-25 09:30:56', 'System Updates', 'Update 2024-06-24a successfully deployed.', '::1', NULL),
(32, 1, '2024-09-25 09:31:58', 'login', 'User logged in.', '::1', NULL),
(33, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2024-09-25a successfully deployed.', '::1', NULL),
(34, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2024-11-22a successfully deployed.', '::1', NULL),
(35, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2024-12-16a successfully deployed.', '::1', NULL),
(36, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2024-12-21a successfully deployed.', '::1', NULL),
(37, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2025-02-23a successfully deployed.', '::1', NULL),
(38, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2025-03-02a successfully deployed.', '::1', NULL),
(39, 1, '2025-04-12 10:51:28', 'System Updates', 'Update 2025-03-03a successfully deployed.', '::1', NULL),
(40, 1, '2025-04-12 10:52:00', 'login', 'User logged in.', '::1', NULL),
(41, 1, '2025-04-12 10:52:55', 'User', 'Updated password.', '::1', NULL),
(42, 0, '2025-06-09 09:58:18', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(43, 1, '2025-06-09 09:58:53', 'login', 'User logged in.', '::1', NULL),
(44, 0, '2025-06-11 10:26:22', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(45, 1, '2025-06-11 10:26:39', 'login', 'User logged in.', '::1', NULL),
(46, 1, '2025-06-11 10:33:33', 'User Manager', 'Added user User.', '::1', NULL),
(47, 3, '2025-06-11 11:02:43', 'login', 'User logged in.', '::1', NULL),
(48, 3, '2025-06-12 08:23:06', 'login', 'User logged in.', '::1', NULL),
(49, 1, '2025-06-12 08:31:28', 'Email Settings', 'Updated website_name from User Spice to Army Resource Management Information System.', '::1', NULL),
(50, 1, '2025-06-12 08:31:28', 'Email Settings', 'Updated from_name from User Spice to Army Resource Management Information System.', '::1', NULL),
(51, 1, '2025-06-12 08:31:28', 'Email Settings', 'Updated verify_url from http://localhost/userspice to http://localhost/armis/.', '::1', NULL),
(52, 0, '2025-06-16 08:04:04', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(53, 3, '2025-06-16 08:04:17', 'login', 'User logged in.', '::1', NULL),
(54, 3, '2025-06-16 12:40:32', 'login', 'User logged in.', '::1', NULL),
(55, 1, '2025-06-16 13:48:28', 'login', 'User logged in.', '::1', NULL),
(56, 1, '2025-06-16 13:51:17', 'User Manager', 'Added user 007414.', '::1', NULL),
(57, 0, '2025-06-17 07:39:09', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(58, 3, '2025-06-17 07:39:30', 'login', 'User logged in.', '::1', NULL),
(59, 1, '2025-06-17 08:30:39', 'User Manager', 'User # 4 Updated. Updated username for Gift from 007414 to User 2.', '::1', NULL),
(60, 5, '2025-06-17 10:11:28', 'User', 'Registration completed.', '::1', NULL),
(61, 5, '2025-06-17 10:15:29', 'login', 'User logged in.', '::1', NULL),
(62, 5, '2025-06-17 12:28:40', 'User', 'Changed phone from  to 0974297313.', '::1', NULL),
(63, 5, '2025-06-17 12:28:41', 'User', 'Changed province from  to Lusaka.', '::1', NULL),
(64, 5, '2025-06-17 12:30:25', 'User', 'Changed intake from  to 33.', '::1', NULL),
(65, 0, '2025-06-17 13:05:23', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(66, 0, '2025-06-17 13:05:46', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(67, 5, '2025-06-17 13:06:02', 'login', 'User logged in.', '::1', NULL),
(68, 1, '2025-06-18 06:06:58', 'Pages Manager', 'Added 2 permission(s) to users/cv_view.php.', '::1', NULL),
(69, 1, '2025-06-18 06:06:58', 'Pages Manager', 'Retitled \'users/cv_view.php\' to \'My CV\'.', '::1', NULL),
(70, 0, '2025-06-18 07:32:27', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(71, 0, '2025-06-18 07:32:57', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(72, 5, '2025-06-18 07:33:14', 'login', 'User logged in.', '::1', NULL),
(73, 5, '2025-06-18 07:36:13', 'login', 'User logged in.', '::1', NULL),
(74, 5, '2025-06-18 07:45:12', 'login', 'User logged in.', '::1', NULL),
(75, 1, '2025-06-18 07:53:49', 'Permissions Manager', 'Added Permission Level named Admin Branch User.', '::1', NULL),
(76, 1, '2025-06-18 07:54:35', 'User Manager', 'User # 5 Updated. Added 1 permission(s) to John Banda.', '::1', NULL),
(77, 5, '2025-06-18 07:55:16', 'login', 'User logged in.', '::1', NULL),
(78, 5, '2025-06-18 12:08:05', 'login', 'User logged in.', '::1', NULL),
(79, 1, '2025-06-18 14:50:05', 'Pages Manager', 'Added 1 permission(s) to users/command_reports.php.', '::1', NULL),
(80, 1, '2025-06-18 14:50:06', 'Pages Manager', 'Retitled \'users/command_reports.php\' to \'Command Reports\'.', '::1', NULL),
(81, 5, '2025-06-19 05:51:51', 'login', 'User logged in.', '::1', NULL),
(82, 1, '2025-06-19 07:10:49', 'Pages Manager', 'Retitled \'users/command/command_profiles.php\' to \'Profiles\'.', '::1', NULL),
(83, 1, '2025-06-19 07:11:15', 'Pages Manager', 'Changed private from private to public for Page #99.', '::1', NULL),
(84, 1, '2025-06-19 07:15:36', 'Pages Manager', 'Changed private from private to public for Page #102.', '::1', NULL),
(85, 1, '2025-06-19 07:15:36', 'Pages Manager', 'Retitled \'users/command/profiles.php\' to \'Profiles\'.', '::1', NULL),
(86, 1, '2025-06-19 08:49:14', 'Pages Manager', 'Changed private from private to public for Page #104.', '::1', NULL),
(87, 1, '2025-06-19 08:49:14', 'Pages Manager', 'Retitled \'users/command/reports.php\' to \'Reports\'.', '::1', NULL),
(88, 1, '2025-06-19 13:40:21', 'Pages Manager', 'Changed private from private to public for Page #105.', '::1', NULL),
(89, 5, '2025-06-20 09:23:55', 'login', 'User logged in.', '::1', NULL),
(90, 1, '2025-06-20 09:31:58', 'Pages Manager', 'Changed private from private to public for Page #107.', '::1', NULL),
(91, 1, '2025-06-20 09:31:59', 'Pages Manager', 'Retitled \'users/admin_branch.php\' to \'Admin Branch\'.', '::1', NULL),
(92, 1, '2025-06-20 10:06:30', 'Pages Manager', 'Retitled \'users/admin_branch/create_staff.php\' to \'Add Staff\'.', '::1', NULL),
(93, 1, '2025-06-20 10:06:46', 'Pages Manager', 'Changed private from private to public for Page #108.', '::1', NULL),
(94, 1, '2025-06-20 11:52:33', 'Pages Manager', 'Changed private from private to public for Page #109.', '::1', NULL),
(95, 1, '2025-06-20 11:52:34', 'Pages Manager', 'Retitled \'users/admin_branch/edit_staff.php\' to \'Edit\'.', '::1', NULL),
(96, 1, '2025-06-20 11:53:36', 'Pages Manager', 'Changed private from public to private for Page #109.', '::1', NULL),
(97, 1, '2025-06-20 11:53:46', 'Pages Manager', 'Changed private from private to public for Page #109.', '::1', NULL),
(98, 1, '2025-06-20 12:17:58', 'Pages Manager', 'Changed private from private to public for Page #111.', '::1', NULL),
(99, 1, '2025-06-20 12:17:58', 'Pages Manager', 'Retitled \'users/admin_branch/promote_staff.php\' to \'Promote\'.', '::1', NULL),
(100, 1, '2025-06-20 12:27:56', 'Pages Manager', 'Retitled \'users/admin_branch/delete_staff.php\' to \'Delete\'.', '::1', NULL),
(101, 1, '2025-06-20 12:28:04', 'Pages Manager', 'Changed private from private to public for Page #110.', '::1', NULL),
(102, 1, '2025-06-20 14:11:02', 'Pages Manager', 'Changed private from private to public for Page #112.', '::1', NULL),
(103, 1, '2025-06-20 14:11:02', 'Pages Manager', 'Retitled \'users/admin_branch/appoint_staff.php\' to \'Appoint\'.', '::1', NULL),
(104, 1, '2025-06-20 14:11:59', 'Pages Manager', 'Changed private from private to public for Page #113.', '::1', NULL),
(105, 1, '2025-06-20 14:11:59', 'Pages Manager', 'Retitled \'users/admin_branch/appointment.php\' to \'Appointment\'.', '::1', NULL),
(106, 1, '2025-06-20 14:12:56', 'Pages Manager', 'Retitled \'users/admin_branch/appointments.php\' to \'Appointments\'.', '::1', NULL),
(107, 1, '2025-06-20 14:13:14', 'Pages Manager', 'Changed private from private to public for Page #114.', '::1', NULL),
(108, 1, '2025-06-23 06:13:27', 'Pages Manager', 'Changed private from private to public for Page #115.', '::1', NULL),
(109, 1, '2025-06-23 06:13:29', 'Pages Manager', 'Retitled \'users/admin_branch/medals.php\' to \'Medals\'.', '::1', NULL),
(110, 0, '2025-06-23 07:19:24', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(111, 0, '2025-06-23 07:19:32', 'Login Fail', 'A failed login on login.php', '::1', NULL),
(112, 5, '2025-06-23 07:19:54', 'login', 'User logged in.', '::1', NULL),
(113, 5, '2025-06-23 07:25:45', 'login', 'User logged in.', '::1', NULL),
(114, 1, '2025-06-23 09:34:02', 'Pages Manager', 'Changed private from private to public for Page #116.', '::1', NULL),
(115, 1, '2025-06-23 09:34:02', 'Pages Manager', 'Retitled \'users/admin_branch/create_medal.php\' to \'Add Medal\'.', '::1', NULL),
(116, 1, '2025-06-23 10:47:38', 'Pages Manager', 'Changed private from private to public for Page #117.', '::1', NULL),
(117, 1, '2025-06-23 10:47:39', 'Pages Manager', 'Retitled \'users/admin_branch/assign_medal.php\' to \'Assign Medal\'.', '::1', NULL),
(118, 1, '2025-06-23 11:30:46', 'Pages Manager', 'Changed private from private to public for Page #119.', '::1', NULL),
(119, 1, '2025-06-23 11:30:46', 'Pages Manager', 'Retitled \'users/admin_branch/reports_seniority.php\' to \'Seniority Roll\'.', '::1', NULL),
(120, 1, '2025-06-23 11:46:39', 'Pages Manager', 'Changed private from private to public for Page #120.', '::1', NULL),
(121, 1, '2025-06-23 11:46:39', 'Pages Manager', 'Retitled \'users/admin_branch/reports_units.php\' to \'Units Report\'.', '::1', NULL),
(122, 1, '2025-06-23 12:05:12', 'Pages Manager', 'Changed private from private to public for Page #121.', '::1', NULL),
(123, 1, '2025-06-23 12:05:13', 'Pages Manager', 'Retitled \'users/admin_branch/reports_rank.php\' to \'Ranks\'.', '::1', NULL),
(124, 1, '2025-06-23 12:18:48', 'Pages Manager', 'Retitled \'users/admin_branch/reports_corps.php\' to \'Corps\'.', '::1', NULL),
(125, 1, '2025-06-23 12:19:00', 'Pages Manager', 'Changed private from private to public for Page #122.', '::1', NULL),
(126, 1, '2025-06-23 12:37:04', 'Pages Manager', 'Retitled \'users/admin_branch/reports_trade.php\' to \'Trades\'.', '::1', NULL),
(127, 1, '2025-06-23 12:37:17', 'Pages Manager', 'Changed private from private to public for Page #123.', '::1', NULL),
(128, 1, '2025-06-23 12:56:45', 'Pages Manager', 'Changed private from private to public for Page #124.', '::1', NULL),
(129, 1, '2025-06-23 12:56:45', 'Pages Manager', 'Retitled \'users/admin_branch/reports_gender.php\' to \'Gender Report\'.', '::1', NULL),
(130, 1, '2025-06-23 13:14:14', 'Pages Manager', 'Changed private from private to public for Page #125.', '::1', NULL),
(131, 1, '2025-06-23 13:14:15', 'Pages Manager', 'Retitled \'users/admin_branch/reports_appointment.php\' to \'Appointment Reports\'.', '::1', NULL),
(132, 1, '2025-06-23 13:28:42', 'Pages Manager', 'Changed private from private to public for Page #126.', '::1', NULL),
(133, 1, '2025-06-23 13:28:42', 'Pages Manager', 'Retitled \'users/admin_branch/reports_retired.php\' to \'Retired List\'.', '::1', NULL),
(134, 1, '2025-06-23 13:33:57', 'Pages Manager', 'Changed private from private to public for Page #127.', '::1', NULL),
(135, 1, '2025-06-23 13:33:57', 'Pages Manager', 'Retitled \'users/admin_branch/reports_contract.php\' to \'Contracts\'.', '::1', NULL),
(136, 1, '2025-06-23 13:37:45', 'Pages Manager', 'Retitled \'users/admin_branch/reports_deceased.php\' to \'Deceased\'.', '::1', NULL),
(137, 1, '2025-06-23 13:37:54', 'Pages Manager', 'Changed private from private to public for Page #128.', '::1', NULL),
(138, 1, '2025-06-23 13:45:27', 'Pages Manager', 'Changed private from private to public for Page #129.', '::1', NULL),
(139, 1, '2025-06-23 13:45:27', 'Pages Manager', 'Retitled \'users/admin_branch/reports_marital.php\' to \'Marital List\'.', '::1', NULL),
(140, 1, '2025-06-25 08:03:32', 'login', 'User logged in.', '::1', NULL),
(141, 1, '2025-07-01 10:12:44', 'login', 'User logged in.', '::1', NULL),
(142, 5, '2025-07-01 13:07:41', 'login', 'User logged in.', '::1', NULL),
(143, 1, '2025-07-02 06:59:04', 'Menu Manager', 'Added new item', '::1', NULL),
(144, 1, '2025-07-02 07:04:11', 'Menu Manager', 'Updated 23', '::1', NULL),
(145, 1, '2025-07-02 07:06:01', 'Pages Manager', 'Changed private from public to private for Page #107.', '::1', NULL),
(146, 1, '2025-07-02 07:06:02', 'Pages Manager', 'Added 1 permission(s) to users/admin_branch.php.', '::1', NULL),
(147, 1, '2025-07-02 07:06:57', 'Menu Manager', 'Updated 23', '::1', NULL),
(148, 5, '2025-07-02 07:10:54', 'login', 'User logged in.', '::1', NULL),
(149, 1, '2025-07-02 07:12:00', 'Menu Manager', 'Updated 23', '::1', NULL),
(150, 1, '2025-07-02 07:14:59', 'Menu Manager', 'Updated 23', '::1', NULL),
(151, 1, '2025-07-02 07:44:49', 'Menu Manager', 'Updated 23', '::1', NULL),
(152, 1, '2025-07-02 07:46:04', 'Menu Manager', 'Updated 23', '::1', NULL),
(153, 5, '2025-07-02 08:38:50', 'login', 'User logged in.', '::1', NULL),
(154, 5, '2025-07-02 09:33:49', 'login', 'User logged in.', '::1', NULL),
(155, 1, '2025-07-02 10:49:49', 'Pages Manager', 'Changed private from public to private for Page #129.', '::1', NULL),
(156, 1, '2025-07-02 10:49:50', 'Pages Manager', 'Added 1 permission(s) to users/admin_branch/reports_marital.php.', '::1', NULL),
(157, 1, '2025-07-02 10:50:33', 'Pages Manager', 'Changed private from public to private for Page #108.', '::1', NULL),
(158, 1, '2025-07-02 10:50:33', 'Pages Manager', 'Added 1 permission(s) to users/admin_branch/create_staff.php.', '::1', NULL),
(159, 1, '2025-07-03 10:16:30', 'Pages Manager', 'Added 2 permission(s) to users/admin_branch/reports_courses.php.', '::1', NULL),
(160, 1, '2025-07-03 10:16:30', 'Pages Manager', 'Retitled \'users/admin_branch/reports_courses.php\' to \'Courses Done\'.', '::1', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `logtracking`
--

DROP TABLE IF EXISTS `logtracking`;
CREATE TABLE IF NOT EXISTS `logtracking` (
  `logID` int NOT NULL AUTO_INCREMENT,
  `logType` varchar(10) DEFAULT NULL,
  `username` varchar(15) DEFAULT NULL,
  `logTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`logID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medals`
--

DROP TABLE IF EXISTS `medals`;
CREATE TABLE IF NOT EXISTS `medals` (
  `medID` varchar(30) NOT NULL,
  `medName` varchar(255) NOT NULL,
  `medDesc` varchar(50) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`medID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE IF NOT EXISTS `menus` (
  `id` int NOT NULL AUTO_INCREMENT,
  `menu_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `parent` int NOT NULL,
  `dropdown` int NOT NULL,
  `logged_in` int NOT NULL,
  `display_order` int NOT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `icon_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `visible_from` datetime DEFAULT NULL,
  `visible_to` datetime DEFAULT NULL,
  `placement` varchar(16) COLLATE utf8mb4_general_ci DEFAULT 'top',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `menu_title`, `parent`, `dropdown`, `logged_in`, `display_order`, `label`, `link`, `icon_class`, `deleted`, `visible_from`, `visible_to`, `placement`) VALUES
(1, 'main', 2, 0, 1, 1, '{{home}}', '', 'fa fa-fw fa-home', 0, NULL, NULL, 'top'),
(2, 'main', -1, 1, 1, 14, '', '', 'fa fa-fw fa-cogs', 0, NULL, NULL, 'top'),
(3, 'main', -1, 0, 1, 11, '{{username}}', 'users/account.php', 'fa fa-fw fa-user', 0, NULL, NULL, 'top'),
(4, 'main', -1, 1, 0, 3, '{{help}}', '', 'fa fa-fw fa-life-ring', 0, NULL, NULL, 'top'),
(5, 'main', -1, 0, 0, 2, '{{register}}', 'users/join.php', 'fa fa-fw fa-plus-square', 0, NULL, NULL, 'top'),
(6, 'main', -1, 0, 0, 1, '{{login}}', 'users/login.php', 'fa fa-fw fa-sign-in', 0, NULL, NULL, 'top'),
(7, 'main', 2, 0, 1, 2, '{{account}}', 'users/account.php', 'fa fa-fw fa-user', 0, NULL, NULL, 'top'),
(8, 'main', 2, 0, 1, 3, '{{hr}}', '', '', 0, NULL, NULL, 'top'),
(9, 'main', 2, 0, 1, 4, '{{dashboard}}', 'users/admin.php', 'fa fa-fw fa-cogs', 0, NULL, NULL, 'top'),
(10, 'main', 2, 0, 1, 5, '{{users}}', 'users/admin.php?view=users', 'fa fa-fw fa-user', 0, NULL, NULL, 'top'),
(11, 'main', 2, 0, 1, 6, '{{perms}}', 'users/admin.php?view=permissions', 'fa fa-fw fa-lock', 0, NULL, NULL, 'top'),
(12, 'main', 2, 0, 1, 7, '{{pages}}', 'users/admin.php?view=pages', 'fa fa-fw fa-wrench', 0, NULL, NULL, 'top'),
(13, 'main', 2, 0, 1, 9, '{{logs}}', 'users/admin.php?view=logs', 'fa fa-fw fa-search', 0, NULL, NULL, 'top'),
(14, 'main', 2, 0, 1, 10, '{{hr}}', '', '', 0, NULL, NULL, 'top'),
(15, 'main', 2, 0, 1, 11, '{{logout}}', 'users/logout.php', 'fa fa-fw fa-sign-out', 0, NULL, NULL, 'top'),
(16, 'main', -1, 0, 0, 0, '{{home}}', '', 'fa fa-fw fa-home', 0, NULL, NULL, 'top'),
(17, 'main', -1, 0, 1, 10, '{{home}}', '', 'fa fa-fw fa-home', 0, NULL, NULL, 'top'),
(18, 'main', 4, 0, 0, 1, '{{forgot}}', 'users/forgot_password.php', 'fa fa-fw fa-wrench', 0, NULL, NULL, 'top'),
(20, 'main', 4, 0, 0, 99999, '{{resend}}', 'users/verify_resend.php', 'fa fa-exclamation-triangle', 0, NULL, NULL, 'top'),
(23, 'main', -1, 0, 1, 3, 'Admin Branch', 'users/admin_branch.php', 'fa fa-fw-book', 0, NULL, NULL, 'top');

-- --------------------------------------------------------

--
-- Table structure for table `menu_bookmarks`
--

DROP TABLE IF EXISTS `menu_bookmarks`;
CREATE TABLE IF NOT EXISTS `menu_bookmarks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `menu_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_customfields`
--

DROP TABLE IF EXISTS `menu_customfields`;
CREATE TABLE IF NOT EXISTS `menu_customfields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `menu_id` int DEFAULT NULL,
  `field_name` varchar(64) DEFAULT NULL,
  `field_value` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_versions`
--

DROP TABLE IF EXISTS `menu_versions`;
CREATE TABLE IF NOT EXISTS `menu_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `snapshot` longtext,
  `timestamp` datetime DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `msg_from` int NOT NULL,
  `msg_to` int NOT NULL,
  `msg_body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `msg_read` int NOT NULL,
  `msg_thread` int NOT NULL,
  `deleted` int NOT NULL,
  `sent_on` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_threads`
--

DROP TABLE IF EXISTS `message_threads`;
CREATE TABLE IF NOT EXISTS `message_threads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `msg_to` int NOT NULL,
  `msg_from` int NOT NULL,
  `msg_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_update` datetime NOT NULL,
  `last_update_by` int NOT NULL,
  `archive_from` int NOT NULL DEFAULT '0',
  `archive_to` int NOT NULL DEFAULT '0',
  `hidden_from` int NOT NULL DEFAULT '0',
  `hidden_to` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint NOT NULL,
  `is_archived` tinyint(1) DEFAULT '0',
  `date_created` datetime DEFAULT NULL,
  `date_read` datetime DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `class` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operation`
--

DROP TABLE IF EXISTS `operation`;
CREATE TABLE IF NOT EXISTS `operation` (
  `opID` varchar(30) NOT NULL,
  `opType` varchar(30) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`opID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
CREATE TABLE IF NOT EXISTS `pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `private` int NOT NULL DEFAULT '0',
  `re_auth` int NOT NULL DEFAULT '0',
  `core` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `page`, `title`, `private`, `re_auth`, `core`) VALUES
(1, 'index.php', 'Home', 0, 0, 1),
(2, 'z_us_root.php', '', 0, 0, 1),
(3, 'users/account.php', 'Account Dashboard', 1, 0, 1),
(4, 'users/admin.php', 'Admin Dashboard', 1, 0, 1),
(14, 'users/forgot_password.php', 'Forgotten Password', 0, 0, 1),
(15, 'users/forgot_password_reset.php', 'Reset Forgotten Password', 0, 0, 1),
(16, 'users/index.php', 'Home', 0, 0, 1),
(17, 'users/init.php', '', 0, 0, 1),
(18, 'users/join.php', 'Join', 0, 0, 1),
(20, 'users/login.php', 'Login', 0, 0, 1),
(21, 'users/logout.php', 'Logout', 0, 0, 1),
(24, 'users/user_settings.php', 'User Settings', 1, 0, 1),
(25, 'users/verify.php', 'Account Verification', 0, 0, 1),
(26, 'users/verify_resend.php', 'Account Verification', 0, 0, 1),
(45, 'users/maintenance.php', 'Maintenance', 0, 0, 1),
(68, 'users/update.php', 'Update Manager', 1, 0, 1),
(81, 'users/admin_pin.php', 'Verification PIN Set', 1, 0, 1),
(90, 'users/complete.php', NULL, 1, 0, 0),
(91, 'users/init.example.php', NULL, 1, 0, 0),
(92, 'users/passwordless.php', NULL, 1, 0, 0),
(93, 'users/release_blacklist.php', NULL, 1, 0, 0),
(94, 'users/db.php', NULL, 1, 0, 0),
(95, 'users/employees.php', NULL, 1, 0, 0),
(96, 'users/cv_view.php', 'My CV', 1, 0, 0),
(97, 'users/cv_download.php', NULL, 1, 0, 0),
(98, 'users/command_reports.php', 'Command Reports', 1, 0, 0),
(102, 'users/command/profiles.php', 'Profiles', 0, 0, 0),
(103, 'users/command/courses.php', NULL, 1, 0, 0),
(104, 'users/command/reports.php', 'Reports', 0, 0, 0),
(105, 'users/user_settings2.php', NULL, 0, 0, 0),
(106, 'users/command/op_reports.php', NULL, 1, 0, 0),
(107, 'users/admin_branch.php', 'Admin Branch', 1, 0, 0),
(108, 'users/admin_branch/create_staff.php', 'Add Staff', 1, 0, 0),
(109, 'users/admin_branch/edit_staff.php', 'Edit', 0, 0, 0),
(110, 'users/admin_branch/delete_staff.php', 'Delete', 0, 0, 0),
(111, 'users/admin_branch/promote_staff.php', 'Promote', 0, 0, 0),
(114, 'users/admin_branch/appointments.php', 'Appointments', 0, 0, 0),
(115, 'users/admin_branch/medals.php', 'Medals', 0, 0, 0),
(116, 'users/admin_branch/create_medal.php', 'Add Medal', 0, 0, 0),
(117, 'users/admin_branch/assign_medal.php', 'Assign Medal', 0, 0, 0),
(118, 'users/admin_branch/search_staff.php', NULL, 1, 0, 0),
(119, 'users/admin_branch/reports_seniority.php', 'Seniority Roll', 0, 0, 0),
(120, 'users/admin_branch/reports_units.php', 'Units Report', 0, 0, 0),
(121, 'users/admin_branch/reports_rank.php', 'Ranks', 0, 0, 0),
(122, 'users/admin_branch/reports_corps.php', 'Corps', 0, 0, 0),
(123, 'users/admin_branch/reports_trade.php', 'Trades', 0, 0, 0),
(124, 'users/admin_branch/reports_gender.php', 'Gender Report', 0, 0, 0),
(125, 'users/admin_branch/reports_appointment.php', 'Appointment Reports', 0, 0, 0),
(126, 'users/admin_branch/reports_retired.php', 'Retired List', 0, 0, 0),
(127, 'users/admin_branch/reports_contract.php', 'Contracts', 0, 0, 0),
(128, 'users/admin_branch/reports_deceased.php', 'Deceased', 0, 0, 0),
(129, 'users/admin_branch/reports_marital.php', 'Marital List', 1, 0, 0),
(130, 'users/command_reports - Copy.php', NULL, 1, 0, 0),
(131, 'users/command/ajax_staff_search.php', NULL, 1, 0, 0),
(132, 'users/admin_branch/ajax_edit_staff_form.php', NULL, 1, 0, 0),
(133, 'users/admin_branch/reports_courses.php', 'Courses Done', 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descrip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `descrip`) VALUES
(1, 'User', 'Standard User'),
(2, 'Administrator', 'System Administrator'),
(3, 'Admin Branch User', 'Administration Branch User'),
(4, 'Command', 'Command Staff'),
(5, 'Operations', 'Operations Staff'),
(6, 'Training', 'Training Staff'),
(7, 'HR', 'Human Resources');

-- --------------------------------------------------------

--
-- Table structure for table `permission_page_matches`
--

DROP TABLE IF EXISTS `permission_page_matches`;
CREATE TABLE IF NOT EXISTS `permission_page_matches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `permission_id` int DEFAULT NULL,
  `page_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission_page_matches`
--

INSERT INTO `permission_page_matches` (`id`, `permission_id`, `page_id`) VALUES
(3, 1, 24),
(14, 2, 4),
(15, 1, 3),
(38, 2, 68),
(54, 1, 81),
(58, 1, 96),
(59, 2, 96),
(60, 3, 98),
(61, 3, 107),
(62, 3, 129),
(63, 3, 108),
(64, 2, 133),
(65, 3, 133);

-- --------------------------------------------------------

--
-- Table structure for table `plg_social_logins`
--

DROP TABLE IF EXISTS `plg_social_logins`;
CREATE TABLE IF NOT EXISTS `plg_social_logins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `provider` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enabledsetting` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `built_in` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plg_tags`
--

DROP TABLE IF EXISTS `plg_tags`;
CREATE TABLE IF NOT EXISTS `plg_tags` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descrip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plg_tags_matches`
--

DROP TABLE IF EXISTS `plg_tags_matches`;
CREATE TABLE IF NOT EXISTS `plg_tags_matches` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id` int UNSIGNED NOT NULL,
  `tag_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_id` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profiles`
--

DROP TABLE IF EXISTS `profiles`;
CREATE TABLE IF NOT EXISTS `profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bio` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profiles`
--

INSERT INTO `profiles` (`id`, `user_id`, `bio`) VALUES
(1, 1, '&lt;h1&gt;This is the Admin&#039;s bio.&lt;/h1&gt;'),
(2, 2, 'This is your bio');

-- --------------------------------------------------------

--
-- Table structure for table `promex`
--

DROP TABLE IF EXISTS `promex`;
CREATE TABLE IF NOT EXISTS `promex` (
  `promexId` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
CREATE TABLE IF NOT EXISTS `promotions` (
  `apptID` varchar(30) NOT NULL,
  `apptType` varchar(50) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`apptID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

DROP TABLE IF EXISTS `ranks`;
CREATE TABLE IF NOT EXISTS `ranks` (
  `rankID` varchar(10) NOT NULL,
  `rankIndex` varchar(2) NOT NULL,
  `rankName` varchar(50) NOT NULL,
  `rankAbb` varchar(10) NOT NULL,
  PRIMARY KEY (`rankID`),
  KEY `idx_ranks_rankIndex` (`rankIndex`),
  KEY `idx_ranks_rankID` (`rankID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ranks`
--

INSERT INTO `ranks` (`rankID`, `rankIndex`, `rankName`, `rankAbb`) VALUES
('1', '1', 'General', 'Gen'),
('2', '2', 'Lieutenant General', 'Lt Gen'),
('3', '3', 'Major General', 'Maj Gen'),
('4', '4', 'Brigadier General', 'Brig Gen'),
('5', '5', 'Colonel', 'Col'),
('6', '6', 'Lieutenant Colonel', 'Lt Col'),
('7', '7', 'Temporal Lieutenant Colonel', 'T/Lt Col'),
('8', '8', 'Major', 'Maj'),
('9', '9', 'Temporal Major', 'T/Maj'),
('10', '10', 'Captain', 'Capt'),
('11', '11', 'Temporal Captain', 'T/Capt'),
('12', '12', 'Lieutenant', 'Lt'),
('13', '13', 'Second Lieutenant', '2Lt'),
('14', '14', 'Officer Cadet', 'O/Cdt'),
('15', '15', 'Warrant Officer Class 1', 'WoI'),
('16', '16', 'Warrant Officer Class 2', 'WoII'),
('17', '17', 'Temporal Warrant Officer Class 2', 'T/WoII'),
('18', '18', 'Staff Sergeant ', 'S Sgt'),
('19', '19', 'Temporal Staff Sergeant', 'T/S Sgt'),
('20', '20', 'Sergeant', 'Sgt'),
('21', '21', 'Temporal Sergeant', 'T/Sgt'),
('22', '22', 'Corporal', 'Cpl'),
('23', '23', 'Temporal Corporal', 'T/Cpl'),
('24', '24', 'Lance Corporal', 'L Cpl'),
('25', '25', 'Temporal Lance Corporal', 'T/L Cpl'),
('26', '26', 'Private', 'Pte'),
('27', '27', 'Recruit', 'Rct'),
('28', '28', 'Mister', 'Mr'),
('29', '28', 'Miss', 'Ms');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recaptcha` int NOT NULL DEFAULT '0',
  `force_ssl` int NOT NULL,
  `css_sample` int NOT NULL,
  `site_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `language` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `site_offline` int NOT NULL,
  `force_pr` int NOT NULL,
  `glogin` int NOT NULL DEFAULT '0',
  `fblogin` int NOT NULL,
  `gid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gsecret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gredirect` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ghome` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fbid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fbsecret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `fbcallback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `graph_ver` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `finalredir` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `req_cap` int NOT NULL,
  `req_num` int NOT NULL,
  `min_pw` int NOT NULL,
  `max_pw` int NOT NULL,
  `min_un` int NOT NULL,
  `max_un` int NOT NULL,
  `messaging` int NOT NULL,
  `snooping` int NOT NULL,
  `echouser` int NOT NULL,
  `wys` int NOT NULL,
  `change_un` int NOT NULL,
  `backup_dest` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `backup_source` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `backup_table` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `msg_notification` int NOT NULL,
  `permission_restriction` int NOT NULL,
  `auto_assign_un` int NOT NULL,
  `page_permission_restriction` int NOT NULL,
  `msg_blocked_users` int NOT NULL,
  `msg_default_to` int NOT NULL,
  `notifications` int NOT NULL,
  `notif_daylimit` int NOT NULL,
  `recap_public` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `recap_private` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `page_default_private` int NOT NULL,
  `navigation_type` tinyint(1) NOT NULL,
  `copyright` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `custom_settings` int NOT NULL,
  `system_announcement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `twofa` int DEFAULT '0',
  `force_notif` tinyint(1) DEFAULT NULL,
  `cron_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `registration` tinyint(1) DEFAULT NULL,
  `join_vericode_expiry` int UNSIGNED NOT NULL,
  `reset_vericode_expiry` int UNSIGNED NOT NULL,
  `admin_verify` tinyint(1) NOT NULL,
  `admin_verify_timeout` int NOT NULL,
  `session_manager` tinyint(1) NOT NULL,
  `template` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'standard',
  `saas` tinyint(1) DEFAULT NULL,
  `redirect_uri_after_login` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `show_tos` tinyint(1) DEFAULT '1',
  `default_language` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allow_language` tinyint(1) DEFAULT NULL,
  `spice_api` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `announce` datetime DEFAULT NULL,
  `bleeding_edge` tinyint(1) DEFAULT '0',
  `err_time` int DEFAULT '15',
  `container_open_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'container-fluid',
  `debug` tinyint(1) DEFAULT '0',
  `widgets` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uman_search` tinyint(1) DEFAULT '0',
  `no_passwords` tinyint(1) DEFAULT '0',
  `email_login` tinyint(1) DEFAULT '0',
  `pwl_length` int DEFAULT '5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `recaptcha`, `force_ssl`, `css_sample`, `site_name`, `language`, `site_offline`, `force_pr`, `glogin`, `fblogin`, `gid`, `gsecret`, `gredirect`, `ghome`, `fbid`, `fbsecret`, `fbcallback`, `graph_ver`, `finalredir`, `req_cap`, `req_num`, `min_pw`, `max_pw`, `min_un`, `max_un`, `messaging`, `snooping`, `echouser`, `wys`, `change_un`, `backup_dest`, `backup_source`, `backup_table`, `msg_notification`, `permission_restriction`, `auto_assign_un`, `page_permission_restriction`, `msg_blocked_users`, `msg_default_to`, `notifications`, `notif_daylimit`, `recap_public`, `recap_private`, `page_default_private`, `navigation_type`, `copyright`, `custom_settings`, `system_announcement`, `twofa`, `force_notif`, `cron_ip`, `registration`, `join_vericode_expiry`, `reset_vericode_expiry`, `admin_verify`, `admin_verify_timeout`, `session_manager`, `template`, `saas`, `redirect_uri_after_login`, `show_tos`, `default_language`, `allow_language`, `spice_api`, `announce`, `bleeding_edge`, `err_time`, `container_open_class`, `debug`, `widgets`, `uman_search`, `no_passwords`, `email_login`, `pwl_length`) VALUES
(1, 0, 0, 0, 'Army Resource Management Information System', 'en', 0, 0, 0, 0, '', '', '', '', '', '', '', '', '', 0, 0, 6, 150, 4, 30, 0, 1, 0, 1, 0, '/', 'everything', '', 0, 0, 0, 0, 0, 1, 0, 7, '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI', '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe', 1, 1, 'Zambia Army', 1, '', 0, 0, 'off', 1, 24, 15, 1, 120, 0, 'customizer', NULL, NULL, 1, 'en-US', 0, NULL, '2025-07-02 15:39:11', 0, 15, 'container-fluid', 0, 'settings,misc,tools,plugins,snapshot,active_users,active-users', 0, 0, 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `svcNo` varchar(10) NOT NULL,
  `rankID` varchar(10) DEFAULT NULL,
  `lname` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `fname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `NRC` varchar(11) DEFAULT NULL,
  `passport` varchar(15) NOT NULL,
  `passExp` date NOT NULL,
  `combatSize` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `bsize` varchar(5) NOT NULL,
  `ssize` varchar(5) NOT NULL,
  `hdress` varchar(5) NOT NULL,
  `gender` varchar(6) DEFAULT NULL,
  `unitID` varchar(25) DEFAULT NULL,
  `category` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `svcStatus` varchar(30) DEFAULT NULL,
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
  `corps` varchar(20) DEFAULT NULL,
  `bloodGp` varchar(4) DEFAULT NULL,
  `profession` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `trade` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `digitalID` varchar(10) DEFAULT NULL,
  `prefix` varchar(3) DEFAULT NULL,
  `marital` varchar(10) DEFAULT NULL,
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
  `tel` varchar(10) DEFAULT NULL,
  `unitAtt` varchar(25) DEFAULT NULL,
  `username` varchar(25) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(25) NOT NULL,
  `renewDate` date DEFAULT NULL,
  `accStatus` enum('Active','Inactive') DEFAULT 'Inactive',
  `createdBy` varchar(15) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`svcNo`),
  KEY `idx_svcNo` (`svcNo`),
  KEY `idx_fName` (`fname`),
  KEY `idx_sName` (`lname`),
  KEY `idx_staff_rankID` (`rankID`),
  KEY `idx_staff_svcNo` (`svcNo`),
  KEY `idx_staff_fname` (`fname`),
  KEY `idx_staff_lname` (`lname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`svcNo`, `rankID`, `lname`, `fname`, `NRC`, `passport`, `passExp`, `combatSize`, `bsize`, `ssize`, `hdress`, `gender`, `unitID`, `category`, `svcStatus`, `appt`, `subRank`, `subWef`, `tempRank`, `tempWef`, `localRank`, `localWef`, `attestDate`, `intake`, `DOB`, `height`, `province`, `corps`, `bloodGp`, `profession`, `trade`, `digitalID`, `prefix`, `marital`, `initials`, `titles`, `nok`, `nokNrc`, `nokRelat`, `nokTel`, `altNok`, `altNokTel`, `altNokNrc`, `altNokRelat`, `email`, `tel`, `unitAtt`, `username`, `password`, `role`, `renewDate`, `accStatus`, `createdBy`, `dateCreated`) VALUES
('007414', '12', 'Mumba', 'Marriott Gift', '375460/10/1', '', '0000-00-00', NULL, '', '', '', 'Male', NULL, 'Officer', 'Serving', NULL, NULL, '2025-06-02', NULL, NULL, '', '0000-00-00', '2024-06-24', '33', '1996-01-12', '179', 'Lusaka', 'ZSIGS', 'O+', NULL, 'Software Engine', NULL, NULL, 'Single', 'M G', NULL, 'Constancy Mutena', NULL, NULL, NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', NULL, 'Active', NULL, '2025-06-19 12:35:30'),
('007417', '28', 'Chanda', 'David', '123456/78/1', '', '2033-01-01', 'M', '8', '7', '54', 'Male', '', 'Civilian Employee', 'Serving', NULL, 'mr', '2025-06-06', NULL, NULL, '', '0000-00-00', '2025-06-06', NULL, '2001-09-27', '150', 'Lusaka', '', 'O-', NULL, '', NULL, 'S', 'Single', NULL, 'Mr', 'John', '123456/78/1', 'Brother', '0987654321', '', '', '', '', '', '', NULL, NULL, '', '', NULL, NULL, NULL, '2025-07-01 10:44:28');

-- --------------------------------------------------------

--
-- Table structure for table `staff_appointment`
--

DROP TABLE IF EXISTS `staff_appointment`;
CREATE TABLE IF NOT EXISTS `staff_appointment` (
  `ID` varchar(10) NOT NULL,
  `apptID` varchar(30) DEFAULT NULL,
  `svcNo` varchar(10) DEFAULT NULL,
  `unitID` varchar(25) DEFAULT NULL,
  `apptDate` date DEFAULT NULL,
  `comment` varchar(30) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `apptID` (`apptID`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_course`
--

DROP TABLE IF EXISTS `staff_course`;
CREATE TABLE IF NOT EXISTS `staff_course` (
  `ID` varchar(10) NOT NULL,
  `cseID` varchar(30) DEFAULT NULL,
  `svcNo` varchar(10) DEFAULT NULL,
  `instID` varchar(30) DEFAULT NULL,
  `cseStart` date DEFAULT NULL,
  `cseEnd` date DEFAULT NULL,
  `result` varchar(25) DEFAULT NULL,
  `comment` varchar(30) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `cseID` (`cseID`),
  KEY `svcNo` (`svcNo`),
  KEY `instID` (`instID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_dependants`
--

DROP TABLE IF EXISTS `staff_dependants`;
CREATE TABLE IF NOT EXISTS `staff_dependants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `nrc` varchar(30) DEFAULT NULL,
  `relationship` varchar(30) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_medal`
--

DROP TABLE IF EXISTS `staff_medal`;
CREATE TABLE IF NOT EXISTS `staff_medal` (
  `medID` varchar(30) NOT NULL,
  `svcNo` varchar(10) NOT NULL,
  `issueDate` date DEFAULT NULL,
  `auth` varchar(25) DEFAULT NULL,
  `comment` varchar(30) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`medID`,`svcNo`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_operation`
--

DROP TABLE IF EXISTS `staff_operation`;
CREATE TABLE IF NOT EXISTS `staff_operation` (
  `ID` varchar(10) NOT NULL,
  `opID` varchar(30) DEFAULT NULL,
  `svcNo` varchar(10) DEFAULT NULL,
  `opStart` date DEFAULT NULL,
  `opEnd` date DEFAULT NULL,
  `opLoc` varchar(25) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `opID` (`opID`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_promotions`
--

DROP TABLE IF EXISTS `staff_promotions`;
CREATE TABLE IF NOT EXISTS `staff_promotions` (
  `promID` int NOT NULL AUTO_INCREMENT,
  `svcNo` varchar(10) DEFAULT NULL,
  `currentRank` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dateFrom` date NOT NULL,
  `dateTo` date DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `newRank` varchar(50) DEFAULT NULL,
  `authority` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `remark` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`promID`),
  KEY `svcNo` (`svcNo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_spouse`
--

DROP TABLE IF EXISTS `staff_spouse`;
CREATE TABLE IF NOT EXISTS `staff_spouse` (
  `id` int NOT NULL AUTO_INCREMENT,
  `svcNo` int NOT NULL,
  `spouseName` varchar(150) NOT NULL,
  `spouseDOB` date NOT NULL,
  `spouseNRC` varchar(15) NOT NULL,
  `spouseOccup` varchar(150) NOT NULL,
  `spouseContact` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sysadmin`
--

DROP TABLE IF EXISTS `sysadmin`;
CREATE TABLE IF NOT EXISTS `sysadmin` (
  `username` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(25) NOT NULL,
  `renewDate` date DEFAULT NULL,
  `accStatus` enum('Active','Inactive') DEFAULT 'Inactive',
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sysadmin`
--

INSERT INTO `sysadmin` (`username`, `password`, `role`, `renewDate`, `accStatus`, `createdBy`, `dateCreated`) VALUES
('admin', '12345', 'Super', '2025-04-02', 'Active', 'Warren', '2025-06-11 07:34:26');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
CREATE TABLE IF NOT EXISTS `transaction` (
  `transID` int NOT NULL AUTO_INCREMENT,
  `username` varchar(25) DEFAULT NULL,
  `transDesc` varchar(50) DEFAULT NULL,
  `transTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE IF NOT EXISTS `units` (
  `unitID` varchar(25) NOT NULL,
  `unitName` varchar(150) NOT NULL,
  `mainUnit` varchar(25) DEFAULT NULL,
  `unitLoc` varchar(25) DEFAULT NULL,
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unitID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `updates`
--

DROP TABLE IF EXISTS `updates`;
CREATE TABLE IF NOT EXISTS `updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `applied_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `update_skipped` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `updates`
--

INSERT INTO `updates` (`id`, `migration`, `applied_on`, `update_skipped`) VALUES
(15, '1XdrInkjV86F', '2018-02-18 22:33:24', NULL),
(16, '3GJYaKcqUtw7', '2018-04-25 16:51:08', NULL),
(17, '3GJYaKcqUtz8', '2018-04-25 16:51:08', NULL),
(18, '69qa8h6E1bzG', '2018-04-25 16:51:08', NULL),
(19, '2XQjsKYJAfn1', '2018-04-25 16:51:08', NULL),
(20, '549DLFeHMNw7', '2018-04-25 16:51:08', NULL),
(21, '4Dgt2XVjgz2x', '2018-04-25 16:51:08', NULL),
(22, 'VLBp32gTWvEo', '2018-04-25 16:51:08', NULL),
(23, 'Q3KlhjdtxE5X', '2018-04-25 16:51:08', NULL),
(24, 'ug5D3pVrNvfS', '2018-04-25 16:51:08', NULL),
(25, '69FbVbv4Jtrz', '2018-04-25 16:51:09', NULL),
(26, '4A6BdJHyvP4a', '2018-04-25 16:51:09', NULL),
(27, '37wvsb5BzymK', '2018-04-25 16:51:09', NULL),
(28, 'c7tZQf926zKq', '2018-04-25 16:51:09', NULL),
(29, 'ockrg4eU33GP', '2018-04-25 16:51:09', NULL),
(30, 'XX4zArPs4tor', '2018-04-25 16:51:09', NULL),
(31, 'pv7r2EHbVvhD', '2018-04-26 00:00:00', NULL),
(32, 'uNT7NpgcBDFD', '2018-04-26 00:00:00', NULL),
(33, 'mS5VtQCZjyJs', '2018-12-11 14:19:16', NULL),
(34, '23rqAv5elJ3G', '2018-12-11 14:19:51', NULL),
(35, 'qPEARSh49fob', '2019-01-01 12:01:01', NULL),
(36, 'FyMYJ2oeGCTX', '2019-01-01 12:01:01', NULL),
(37, 'iit5tHSLatiS', '2019-01-01 12:01:01', NULL),
(38, 'hcA5B3PLhq6E', '2020-07-16 11:27:53', NULL),
(39, 'VNEno3E4zaNz', '2020-07-16 11:27:53', NULL),
(40, '2ZB9mg1l0JXe', '2020-07-16 11:27:53', NULL),
(41, 'B9t6He7qmFXa', '2020-07-16 11:27:53', NULL),
(42, '86FkFVV4TGRg', '2020-07-16 11:27:53', NULL),
(43, 'y4A1Y0u9n2Rt', '2020-07-16 11:27:53', NULL),
(44, 'Tm5xY22MM8eC', '2020-07-16 11:27:53', NULL),
(45, '0YXdrInkjV86F', '2020-07-16 11:27:53', NULL),
(46, '99plgnkjV86', '2020-07-16 11:27:53', NULL),
(47, '0DaShInkjV86', '2020-07-16 11:27:53', NULL),
(48, '0DaShInkjVz1', '2020-07-16 11:27:53', NULL),
(49, 'y4A1Y0u9n2SS', '2020-07-16 11:27:53', NULL),
(50, '0DaShInkjV87', '2020-07-16 11:27:53', NULL),
(51, '0DaShInkjV88', '2020-07-16 11:27:53', NULL),
(52, '2019-09-04a', '2020-07-16 11:27:53', NULL),
(53, '2019-09-05a', '2020-07-16 11:27:53', NULL),
(54, '2019-09-26a', '2020-07-16 11:27:53', NULL),
(55, '2019-11-19a', '2020-07-16 11:27:53', NULL),
(56, '2019-12-28a', '2020-07-16 11:27:53', NULL),
(57, '2020-01-21a', '2020-07-16 11:27:54', NULL),
(58, '2020-03-26a', '2020-07-16 11:27:54', NULL),
(59, '2020-04-17a', '2020-07-16 11:27:54', NULL),
(60, '2020-06-06a', '2020-07-16 11:27:54', NULL),
(61, '2020-06-30a', '2020-07-16 11:27:54', NULL),
(62, '2020-07-01a', '2020-07-16 11:27:54', NULL),
(63, '2020-07-16a', '2020-10-08 01:26:22', NULL),
(64, '2020-07-30a', '2020-10-08 01:26:22', NULL),
(65, '2020-10-06a', '2022-04-15 17:37:11', NULL),
(66, '2020-11-03a', '2022-04-15 17:37:11', NULL),
(67, '2020-11-08a', '2022-04-15 17:37:11', NULL),
(68, '2020-11-10a', '2022-04-15 17:37:11', NULL),
(69, '2020-11-10b', '2022-04-15 17:37:11', NULL),
(70, '2020-12-17a', '2022-04-15 17:37:11', NULL),
(71, '2020-12-28a', '2022-04-15 17:37:11', NULL),
(72, '2021-01-20a', '2022-04-15 17:37:11', NULL),
(73, '2021-02-16a', '2022-04-15 17:37:11', NULL),
(74, '2021-04-14a', '2022-04-15 17:37:11', NULL),
(75, '2021-04-15a', '2022-04-15 17:37:11', NULL),
(76, '2021-05-20a', '2022-04-15 17:37:11', NULL),
(77, '2021-07-11a', '2022-04-15 17:37:11', NULL),
(78, '2021-08-22a', '2022-04-15 17:37:11', NULL),
(79, '2021-08-24a', '2022-04-15 17:37:11', NULL),
(80, '2021-09-25a', '2022-04-15 17:37:11', NULL),
(81, '2021-12-26a', '2022-04-15 17:37:11', NULL),
(82, '2022-05-04a', '2022-12-23 12:05:38', NULL),
(83, '2022-11-06a', '2022-12-23 12:06:38', NULL),
(84, '2022-11-20a', '2022-12-23 12:06:38', NULL),
(85, '2022-12-04a', '2022-12-23 12:06:38', NULL),
(86, '2022-12-22a', '2022-12-23 12:06:38', NULL),
(87, '2022-12-23a', '2022-12-23 12:06:38', NULL),
(88, '2023-01-02a', '2024-09-25 09:30:55', NULL),
(89, '2023-01-03a', '2024-09-25 09:30:55', NULL),
(90, '2023-01-03b', '2024-09-25 09:30:55', NULL),
(91, '2023-01-05a', '2024-09-25 09:30:55', NULL),
(92, '2023-01-07a', '2024-09-25 09:30:55', NULL),
(93, '2023-02-10a', '2024-09-25 09:30:55', NULL),
(94, '2023-05-19a', '2024-09-25 09:30:56', NULL),
(95, '2023-06-29a', '2024-09-25 09:30:56', NULL),
(96, '2023-06-29b', '2024-09-25 09:30:56', NULL),
(97, '2023-11-15a', '2024-09-25 09:30:56', NULL),
(98, '2023-11-17a', '2024-09-25 09:30:56', NULL),
(99, '2024-03-12a', '2024-09-25 09:30:56', NULL),
(100, '2024-03-13a', '2024-09-25 09:30:56', NULL),
(101, '2024-03-14a', '2024-09-25 09:30:56', NULL),
(102, '2024-03-15a', '2024-09-25 09:30:56', NULL),
(103, '2024-03-17a', '2024-09-25 09:30:56', NULL),
(104, '2024-03-17b', '2024-09-25 09:30:56', NULL),
(105, '2024-03-18a', '2024-09-25 09:30:56', NULL),
(106, '2024-03-20a', '2024-09-25 09:30:56', NULL),
(107, '2024-03-22a', '2024-09-25 09:30:56', NULL),
(108, '2024-04-01a', '2024-09-25 09:30:56', NULL),
(109, '2024-04-13a', '2024-09-25 09:30:56', NULL),
(110, '2024-06-24a', '2024-09-25 09:30:56', NULL),
(111, '2024-09-25a', '2025-04-12 10:51:28', NULL),
(112, '2024-11-22a', '2025-04-12 10:51:28', NULL),
(113, '2024-12-16a', '2025-04-12 10:51:28', NULL),
(114, '2024-12-21a', '2025-04-12 10:51:28', NULL),
(115, '2025-02-23a', '2025-04-12 10:51:28', NULL),
(116, '2025-03-02a', '2025-04-12 10:51:28', NULL),
(117, '2025-03-03a', '2025-04-12 10:51:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `username` varchar(25) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(25) NOT NULL,
  `fullname` varchar(30) NOT NULL,
  `renewDate` date DEFAULT NULL,
  `accStatus` enum('Active','Inactive') DEFAULT 'Inactive',
  `createdBy` varchar(25) DEFAULT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dob` date NOT NULL,
  `svcNo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `doe` date NOT NULL,
  `intake` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `prov` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `rank` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `unit` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `combat` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `boot` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `nrc` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `blood` varchar(155) COLLATE utf8mb4_general_ci NOT NULL,
  `permissions` tinyint(1) NOT NULL,
  `email` varchar(155) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email_new` varchar(155) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `language` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'en-US',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `vericode` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `vericode_expiry` datetime DEFAULT NULL,
  `oauth_provider` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `oauth_uid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gender` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `locale` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `gpluslink` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `account_owner` tinyint NOT NULL DEFAULT '1',
  `account_id` int NOT NULL DEFAULT '0',
  `account_mgr` int NOT NULL DEFAULT '0',
  `fb_uid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `picture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created` datetime NOT NULL,
  `protected` tinyint(1) NOT NULL DEFAULT '0',
  `msg_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `dev_user` tinyint(1) NOT NULL DEFAULT '0',
  `msg_notification` tinyint(1) NOT NULL DEFAULT '1',
  `cloak_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `oauth_tos_accepted` tinyint(1) DEFAULT NULL,
  `un_changed` tinyint(1) NOT NULL DEFAULT '0',
  `force_pr` tinyint(1) NOT NULL DEFAULT '0',
  `logins` int UNSIGNED NOT NULL DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `join_date` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `EMAIL` (`email`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `dob`, `svcNo`, `doe`, `intake`, `phone`, `prov`, `rank`, `category`, `unit`, `combat`, `boot`, `nrc`, `blood`, `permissions`, `email`, `email_new`, `username`, `password`, `pin`, `fname`, `lname`, `language`, `email_verified`, `vericode`, `vericode_expiry`, `oauth_provider`, `oauth_uid`, `gender`, `locale`, `gpluslink`, `account_owner`, `account_id`, `account_mgr`, `fb_uid`, `picture`, `created`, `protected`, `msg_exempt`, `dev_user`, `msg_notification`, `cloak_allowed`, `oauth_tos_accepted`, `un_changed`, `force_pr`, `logins`, `last_login`, `join_date`, `modified`, `active`) VALUES
(1, '1996-01-12', '007414', '0000-00-00', '', '', 'Lusaka', 'Lieutenant', 'Officer', '', '', '', '', 'O+', 1, 'marriottmumba@gmail.com', NULL, 'admin', '$2y$14$Nyc3u9hav7yLeVf.PvvgKOfetzV1bRlTAwDTARGmfu1pA9zfFHomW', NULL, 'Marriott Gift', 'Mumba', 'en-US', 1, 's2GiSN9THd7V0Lb', '2022-11-25 05:32:17', '', '', ' Male', '', '', 1, 0, 0, '', '', '0000-00-00 00:00:00', 1, 1, 0, 1, 1, NULL, 0, 0, 5, '2025-07-01 12:12:44', '2022-12-25 00:00:00', '2025-06-17 00:00:00', 1),
(3, '0000-00-00', '', '0000-00-00', '', '', '', '', '', '', '', '', '', '', 1, 'john@doe.com', NULL, 'User', '$2y$13$CsiEkdhYfQdoOHBhGfdCTuDBJ.vScz/QkG6de9DfJSKGOOkxr3hsO', NULL, 'John', 'Doe', 'en-US', 1, '68495c14f1c34xGF2wscdQ50KEV3', '2025-06-11 12:51:04', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, '0000-00-00 00:00:00', 0, 0, 0, 1, 0, 1, 0, 0, 5, '2025-06-17 09:39:30', '2025-06-11 12:33:32', '2025-06-11 00:00:00', 1),
(4, '0000-00-00', '', '0000-00-00', '', '', '', '', '', '', '', '', '', '', 1, 'marriottgiftmumba@yahoo.com', NULL, 'User 2', '$2y$13$MkatU08N3FxFGUbWVI8QnuJ0fIEnVyKMmMVmOnuPyTcsVBM23OF6W', NULL, 'Gift', 'Mumba', 'en-US', 1, '685127aff0a8241tarJQ9099Jjbr', '2025-06-17 10:45:39', NULL, NULL, NULL, NULL, NULL, 1, 0, 0, NULL, NULL, '0000-00-00 00:00:00', 0, 0, 0, 1, 0, 1, 0, 0, 0, NULL, '2025-06-16 15:51:16', '2025-06-17 00:00:00', 1),
(5, '1996-01-12', '08347', '0000-00-00', '33', '0974297313', 'Lusaka', 'Lieutenant', 'Officer', '1 INF BDE', 'Large', '7', '375460/10/1', 'O+', 1, 'john@banda.com', NULL, 'User 3', '$2y$13$jB0xg6l59FHOUZyMvFeqaOdQEPgG0jkRstpHzvDhGadviw9kz4E2S', NULL, 'John', 'Banda', 'en-US', 1, '685270bb5bd45DVjLSACJQgmYXOV', '2025-06-18 10:09:35', NULL, NULL, 'Male', NULL, NULL, 1, 0, 0, NULL, 'images/pics/007414.jpg', '0000-00-00 00:00:00', 0, 0, 0, 1, 0, 1, 0, 0, 15, '2025-07-02 11:33:49', '2025-06-17 12:11:27', '2025-06-18 00:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users_online`
--

DROP TABLE IF EXISTS `users_online`;
CREATE TABLE IF NOT EXISTS `users_online` (
  `id` int NOT NULL,
  `ip` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `timestamp` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `session` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users_session`
--

DROP TABLE IF EXISTS `users_session`;
CREATE TABLE IF NOT EXISTS `users_session` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `uagent` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_permission_matches`
--

DROP TABLE IF EXISTS `user_permission_matches`;
CREATE TABLE IF NOT EXISTS `user_permission_matches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permission_matches`
--

INSERT INTO `user_permission_matches` (`id`, `user_id`, `permission_id`) VALUES
(100, 1, 1),
(101, 1, 2),
(111, 3, 1),
(112, 4, 1),
(113, 5, 1),
(114, 5, 3);

-- --------------------------------------------------------

--
-- Table structure for table `us_announcements`
--

DROP TABLE IF EXISTS `us_announcements`;
CREATE TABLE IF NOT EXISTS `us_announcements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dismissed` int NOT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ignore` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `class` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dismissed_by` int DEFAULT '0',
  `update_announcement` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_email_logins`
--

DROP TABLE IF EXISTS `us_email_logins`;
CREATE TABLE IF NOT EXISTS `us_email_logins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vericode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `success` tinyint(1) DEFAULT '0',
  `login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `login_date` datetime NOT NULL,
  `expired` tinyint(1) DEFAULT '0',
  `expires` datetime DEFAULT NULL,
  `verification_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `invalid_attempts` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_fingerprints`
--

DROP TABLE IF EXISTS `us_fingerprints`;
CREATE TABLE IF NOT EXISTS `us_fingerprints` (
  `kFingerprintID` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `fkUserID` int NOT NULL,
  `Fingerprint` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Fingerprint_Expiry` datetime NOT NULL,
  `Fingerprint_Added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`kFingerprintID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_fingerprint_assets`
--

DROP TABLE IF EXISTS `us_fingerprint_assets`;
CREATE TABLE IF NOT EXISTS `us_fingerprint_assets` (
  `kFingerprintAssetID` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `fkFingerprintID` int NOT NULL,
  `IP_Address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `User_Browser` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `User_OS` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`kFingerprintAssetID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_forms`
--

DROP TABLE IF EXISTS `us_forms`;
CREATE TABLE IF NOT EXISTS `us_forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `form` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_form_validation`
--

DROP TABLE IF EXISTS `us_form_validation`;
CREATE TABLE IF NOT EXISTS `us_form_validation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `params` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `us_form_validation`
--

INSERT INTO `us_form_validation` (`id`, `value`, `description`, `params`) VALUES
(1, 'min', 'Minimum # of Characters', 'number'),
(2, 'max', 'Maximum # of Characters', 'number'),
(3, 'is_numeric', 'Must be a number', 'true'),
(4, 'valid_email', 'Must be a valid email address', 'true'),
(5, '<', 'Must be a number less than', 'number'),
(6, '>', 'Must be a number greater than', 'number'),
(7, '<=', 'Must be a number less than or equal to', 'number'),
(8, '>=', 'Must be a number greater than or equal to', 'number'),
(9, '!=', 'Must not be equal to', 'text'),
(10, '==', 'Must be equal to', 'text'),
(11, 'is_integer', 'Must be an integer', 'true'),
(12, 'is_timezone', 'Must be a valid timezone name', 'true'),
(13, 'is_datetime', 'Must be a valid DateTime', 'true');

-- --------------------------------------------------------

--
-- Table structure for table `us_form_views`
--

DROP TABLE IF EXISTS `us_form_views`;
CREATE TABLE IF NOT EXISTS `us_form_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `view_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fields` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_ip_blacklist`
--

DROP TABLE IF EXISTS `us_ip_blacklist`;
CREATE TABLE IF NOT EXISTS `us_ip_blacklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `last_user` int NOT NULL DEFAULT '0',
  `reason` int NOT NULL DEFAULT '0',
  `expires` datetime DEFAULT NULL,
  `descrip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_ip_list`
--

DROP TABLE IF EXISTS `us_ip_list`;
CREATE TABLE IF NOT EXISTS `us_ip_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `us_ip_list`
--

INSERT INTO `us_ip_list` (`id`, `ip`, `user_id`, `timestamp`) VALUES
(2, '::1', 5, '2025-07-02 09:33:49');

-- --------------------------------------------------------

--
-- Table structure for table `us_ip_whitelist`
--

DROP TABLE IF EXISTS `us_ip_whitelist`;
CREATE TABLE IF NOT EXISTS `us_ip_whitelist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descrip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_login_fails`
--

DROP TABLE IF EXISTS `us_login_fails`;
CREATE TABLE IF NOT EXISTS `us_login_fails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `login_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ts` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_management`
--

DROP TABLE IF EXISTS `us_management`;
CREATE TABLE IF NOT EXISTS `us_management` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `view` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `feature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `access` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `us_management`
--

INSERT INTO `us_management` (`id`, `page`, `view`, `feature`, `access`) VALUES
(1, '_admin_manage_ip.php', 'ip', 'IP Whitelist/Blacklist', ''),
(2, '_admin_nav.php', 'nav', 'Navigation [List/Add/Delete]', ''),
(3, '_admin_nav_item.php', 'nav_item', 'Navigation [View/Edit]', ''),
(4, '_admin_pages.php', 'pages', 'Page Management [List]', ''),
(5, '_admin_page.php', 'page', 'Page Management [View/Edit]', ''),
(6, '_admin_security_logs.php', 'security_logs', 'Security Logs', ''),
(7, '_admin_templates.php', 'templates', 'Templates', ''),
(8, '_admin_tools_check_updates.php', 'updates', 'Check Updates', ''),
(16, '_admin_menus.php', 'menus', 'Manage UltraMenu', ''),
(17, '_admin_logs.php', 'logs', 'System Logs', '');

-- --------------------------------------------------------

--
-- Table structure for table `us_menus`
--

DROP TABLE IF EXISTS `us_menus`;
CREATE TABLE IF NOT EXISTS `us_menus` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(255) DEFAULT NULL,
  `type` varchar(75) DEFAULT NULL,
  `nav_class` varchar(255) DEFAULT NULL,
  `theme` varchar(25) DEFAULT NULL,
  `z_index` int DEFAULT NULL,
  `brand_html` text,
  `disabled` tinyint(1) DEFAULT '0',
  `justify` varchar(10) DEFAULT 'right',
  `show_active` tinyint(1) DEFAULT '0',
  `screen_reader_mode` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `us_menus`
--

INSERT INTO `us_menus` (`id`, `menu_name`, `type`, `nav_class`, `theme`, `z_index`, `brand_html`, `disabled`, `justify`, `show_active`, `screen_reader_mode`) VALUES
(1, 'Main Menu', 'horizontal', '', 'dark', 50, '&lt;a href=&quot;{{root}}&quot; &gt;\r\n&lt;img src=&quot;{{root}}users/images/logo.png&quot; /&gt;', 0, 'right', 0, 0),
(2, 'Dashboard Menu', 'horizontal', NULL, 'dark', 55, '&lt;a href=&quot;{{root}}&quot; title=&quot;Home Page&quot;&gt;\r\n&lt;img src=&quot;{{root}}users/images/logo.png&quot; alt=&quot;Main logo&quot; /&gt;&lt;/a&gt;', 0, 'right', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `us_menu_items`
--

DROP TABLE IF EXISTS `us_menu_items`;
CREATE TABLE IF NOT EXISTS `us_menu_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu` int UNSIGNED NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `link` text,
  `icon_class` varchar(255) DEFAULT NULL,
  `li_class` varchar(255) DEFAULT NULL,
  `a_class` varchar(255) DEFAULT NULL,
  `link_target` varchar(50) DEFAULT NULL,
  `parent` int DEFAULT NULL,
  `display_order` int DEFAULT NULL,
  `disabled` tinyint(1) DEFAULT '0',
  `permissions` varchar(1000) DEFAULT NULL,
  `tags` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `us_menu_items`
--

INSERT INTO `us_menu_items` (`id`, `menu`, `type`, `label`, `link`, `icon_class`, `li_class`, `a_class`, `link_target`, `parent`, `display_order`, `disabled`, `permissions`, `tags`) VALUES
(1, 1, 'dropdown', '', '', 'fa fa-cogs', NULL, NULL, '_self', 0, 14, 0, '[1]', NULL),
(2, 1, 'link', '{{LOGGED_IN_USERNAME}}', 'users/account.php', 'fa fa-user', NULL, NULL, '_self', 0, 11, 0, '[1]', NULL),
(3, 1, 'dropdown', '{{MENU_HELP}}', '', 'fa fa-life-ring', NULL, NULL, '_self', 0, 3, 0, '[0]', NULL),
(4, 1, 'link', '{{SIGNUP_TEXT}}', 'users/join.php', 'fa fa-plus-square', NULL, NULL, '_self', 0, 2, 0, '[0]', NULL),
(5, 1, 'link', '{{SIGNIN_BUTTONTEXT}}', 'users/login.php', 'fa fa-sign-in', NULL, NULL, '_self', 0, 1, 0, '[0]', NULL),
(6, 1, 'link', '{{MENU_HOME}}', '', 'fa fa-home', NULL, NULL, '_self', 0, 0, 0, '[0]', NULL),
(7, 1, 'link', '{{MENU_HOME}}', '', 'fa fa-home', NULL, NULL, '_self', 0, 10, 0, '[]', NULL),
(8, 1, 'link', '{{MENU_HOME}}', '', 'fa fa-home', NULL, NULL, '_self', 1, 1, 0, '[1]', NULL),
(9, 1, 'link', '{{MENU_ACCOUNT}}', 'users/account.php', 'fa fa-user', NULL, NULL, '_self', 1, 2, 0, '[1]', NULL),
(10, 1, 'separator', '', '', '', NULL, NULL, '_self', 1, 3, 0, '[1]', NULL),
(11, 1, 'link', '{{MENU_DASH}}', 'users/admin.php', 'fa fa-cogs', NULL, NULL, '_self', 1, 4, 0, '[2]', NULL),
(12, 1, 'link', '{{MENU_USER_MGR}}', 'users/admin.php?view=users', 'fa fa-user', NULL, NULL, '_self', 1, 5, 0, '[2]', NULL),
(13, 1, 'link', '{{MENU_PERM_MGR}}', 'users/admin.php?view=permissions', 'fa fa-lock', NULL, NULL, '_self', 1, 6, 0, '[2]', NULL),
(14, 1, 'link', '{{MENU_PAGE_MGR}}', 'users/admin.php?view=pages', 'fa fa-wrench', NULL, NULL, '_self', 1, 7, 0, '[2]', NULL),
(15, 1, 'link', '{{MENU_LOGS_MGR}}', 'users/admin.php?view=logs', 'fa fa-search', NULL, NULL, '_self', 1, 9, 0, '[2]', NULL),
(16, 1, 'separator', '', '', '', NULL, NULL, '_self', 1, 10, 0, '[2]', NULL),
(17, 1, 'link', '{{MENU_LOGOUT}}', 'users/logout.php', 'fa fa-sign-out', NULL, NULL, '_self', 1, 11, 0, '[2,1]', NULL),
(18, 1, 'link', '{{SIGNIN_FORGOTPASS}}', 'users/forgot_password.php', 'fa fa-wrench', NULL, NULL, '_self', 3, 1, 0, '[0]', NULL),
(19, 1, 'link', '{{VER_RESEND}}', 'users/verify_resend.php', 'fa fa-exclamation-triangle', NULL, NULL, '_self', 3, 99999, 0, '[0]', NULL),
(45, 2, 'dropdown', 'Tools', '', 'fa fa-wrench', '', '', '_self', 0, 3, 0, '[2]', NULL),
(46, 2, 'link', 'User Manager', 'users/admin.php?view=users', 'fa fa-user', NULL, NULL, NULL, 45, 15, 0, '[2]', NULL),
(47, 2, 'link', 'Bug Report', 'users/admin.php?view=bugs', 'fa fa-bug', NULL, NULL, NULL, 45, 1, 0, '[2]', NULL),
(48, 2, 'link', 'IP Manager', 'users/admin.php?view=ip', 'fa fa-warning', NULL, NULL, NULL, 45, 3, 0, '[0]', NULL),
(49, 2, 'link', 'Cron Jobs', 'users/admin.php?view=cron', 'fa fa-terminal', NULL, NULL, NULL, 45, 2, 0, '[2]', NULL),
(50, 2, 'link', 'Security Logs', 'users/admin.php?view=security_logs', 'fa fa-lock', NULL, NULL, NULL, 45, 9, 0, '[2]', NULL),
(51, 2, 'link', 'System Logs', 'users/admin.php?view=logs', 'fa fa-list-ol', NULL, NULL, NULL, 45, 10, 0, '[2]', NULL),
(52, 2, 'link', 'Templates', 'users/admin.php?view=templates', 'fa fa-eye', NULL, NULL, NULL, 45, 11, 0, '[2]', NULL),
(53, 2, 'link', 'Updates', 'users/admin.php?view=updates', 'fa fa-arrow-circle-o-up', NULL, NULL, NULL, 45, 12, 0, '[2]', NULL),
(54, 2, 'link', 'Page Manager', 'users/admin.php?view=pages', 'fa fa-file', NULL, NULL, NULL, 45, 7, 0, '[2]', NULL),
(55, 2, 'link', 'Permissions', 'users/admin.php?view=permissions', 'fa fa-unlock-alt', NULL, NULL, NULL, 45, 8, 0, '[2]', NULL),
(56, 2, 'dropdown', 'Settings', '', 'fa fa-gear', '', '', '_self', 0, 4, 0, '[2]', NULL),
(57, 2, 'link', 'General', 'users/admin.php?view=general', 'fa fa-check', NULL, NULL, NULL, 56, 1, 0, '[2]', NULL),
(58, 2, 'link', 'Registration', 'users/admin.php?view=reg', 'fa fa-users', NULL, NULL, NULL, 56, 2, 0, '[2]', NULL),
(59, 2, 'link', 'Email', 'users/admin.php?view=email', 'fa fa-envelope', NULL, NULL, NULL, 56, 3, 0, '[0]', NULL),
(60, 2, 'link', 'Navigation (Classic)', 'users/admin.php?view=nav', 'fa fa-rocket', NULL, NULL, NULL, 56, 4, 0, '[2]', NULL),
(61, 2, 'link', 'UltraMenu', 'users/admin.php?view=menus', 'fa fa-lock', NULL, NULL, NULL, 56, 5, 0, '[2]', NULL),
(62, 2, 'link', 'Dashboard Access', 'users/admin.php?view=access', 'fa fa-file-code-o', NULL, NULL, NULL, 56, 5, 0, '[2]', NULL),
(63, 2, 'dropdown', 'Plugins', '#', 'fa fa-plug', '', '', '_self', 0, 5, 0, '[2]', NULL),
(64, 2, 'snippet', 'All Plugins', 'users/includes/menu_hooks/plugins.php', '', NULL, NULL, NULL, 63, 2, 0, '[2]', NULL),
(65, 2, 'link', 'Plugin Manager', 'users/admin.php?view=plugins', 'fa fa-puzzle-piece', NULL, NULL, NULL, 63, 1, 0, '[2]', NULL),
(66, 2, 'link', 'Spice Shaker', 'users/admin.php?view=spice', 'fa fa-user-secret', '', '', '_self', 0, 2, 0, '[2]', NULL),
(67, 2, 'link', 'Home', '#', 'fa fa-home', '', '', '_self', 0, 1, 0, '[2]', NULL),
(68, 2, 'link', 'Dashboard', 'users/admin.php', 'fa-solid fa-desktop', '', '', '_self', 0, 1, 0, '[2]', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `us_password_strength`
--

DROP TABLE IF EXISTS `us_password_strength`;
CREATE TABLE IF NOT EXISTS `us_password_strength` (
  `id` int NOT NULL AUTO_INCREMENT,
  `enforce_rules` tinyint(1) DEFAULT '0',
  `meter_active` tinyint(1) DEFAULT '0',
  `min_length` int DEFAULT '8',
  `max_length` int DEFAULT '24',
  `require_lowercase` tinyint(1) DEFAULT '1',
  `require_uppercase` tinyint(1) DEFAULT '1',
  `require_numbers` tinyint(1) DEFAULT '1',
  `require_symbols` tinyint(1) DEFAULT '1',
  `min_score` int DEFAULT '5',
  `uppercase_score` int NOT NULL DEFAULT '6',
  `lowercase_score` int NOT NULL DEFAULT '6',
  `number_score` int NOT NULL DEFAULT '6',
  `symbol_score` int NOT NULL DEFAULT '11',
  `greater_eight` int NOT NULL DEFAULT '15',
  `greater_twelve` int NOT NULL DEFAULT '28',
  `greater_sixteen` int NOT NULL DEFAULT '40',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `us_password_strength`
--

INSERT INTO `us_password_strength` (`id`, `enforce_rules`, `meter_active`, `min_length`, `max_length`, `require_lowercase`, `require_uppercase`, `require_numbers`, `require_symbols`, `min_score`, `uppercase_score`, `lowercase_score`, `number_score`, `symbol_score`, `greater_eight`, `greater_twelve`, `greater_sixteen`) VALUES
(1, 1, 0, 8, 150, 1, 1, 1, 1, 75, 6, 6, 6, 11, 15, 28, 40);

-- --------------------------------------------------------

--
-- Table structure for table `us_plugins`
--

DROP TABLE IF EXISTS `us_plugins`;
CREATE TABLE IF NOT EXISTS `us_plugins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updates` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `last_check` datetime DEFAULT '2020-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_plugin_hooks`
--

DROP TABLE IF EXISTS `us_plugin_hooks`;
CREATE TABLE IF NOT EXISTS `us_plugin_hooks` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `page` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `folder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hook` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `disabled` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `us_plugin_hooks`
--

INSERT INTO `us_plugin_hooks` (`id`, `page`, `folder`, `position`, `hook`, `disabled`) VALUES
(1, 'admin.php?view=user', 'userspice_core', 'form', 'hooks/tags_admin_user_form.php', 0),
(2, 'admin.php?view=user', 'userspice_core', 'post', 'hooks/tags_admin_user_post.php', 0);

-- --------------------------------------------------------

--
-- Table structure for table `us_saas_levels`
--

DROP TABLE IF EXISTS `us_saas_levels`;
CREATE TABLE IF NOT EXISTS `us_saas_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `users` int NOT NULL,
  `details` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_saas_orgs`
--

DROP TABLE IF EXISTS `us_saas_orgs`;
CREATE TABLE IF NOT EXISTS `us_saas_orgs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `org` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `owner` int NOT NULL,
  `level` int NOT NULL,
  `active` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `us_user_sessions`
--

DROP TABLE IF EXISTS `us_user_sessions`;
CREATE TABLE IF NOT EXISTS `us_user_sessions` (
  `kUserSessionID` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `fkUserID` int UNSIGNED NOT NULL,
  `UserFingerprint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `UserSessionIP` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `UserSessionOS` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `UserSessionBrowser` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `UserSessionStarted` datetime NOT NULL,
  `UserSessionLastUsed` datetime DEFAULT NULL,
  `UserSessionLastPage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `UserSessionEnded` tinyint(1) NOT NULL DEFAULT '0',
  `UserSessionEnded_Time` datetime DEFAULT NULL,
  PRIMARY KEY (`kUserSessionID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
