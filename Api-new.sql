-- --------------------------------------------------------
-- Host:                         bagipanen.my.id
-- Server version:               10.6.23-MariaDB-cll-lve-log - MariaDB Server
-- Server OS:                    Linux
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for kmiprodm_aioa_tread
CREATE DATABASE IF NOT EXISTS `kmiprodm_aioa_tread` /*!40100 DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci */;
USE `kmiprodm_aioa_tread`;

-- Dumping structure for table kmiprodm_aioa_tread.machine_control
CREATE TABLE IF NOT EXISTS `machine_control` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_bit` tinyint(1) NOT NULL,
  `mode_bit` tinyint(1) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table kmiprodm_aioa_tread.machine_control: ~32 rows (approximately)
DELETE FROM `machine_control`;
INSERT INTO `machine_control` (`id`, `status_bit`, `mode_bit`, `recorded_at`) VALUES
	(1, 1, 1, '2025-12-22 10:59:06'),
	(2, 1, 1, '2025-12-23 07:44:37'),
	(3, 1, 1, '2025-12-24 09:01:40'),
	(4, 0, 1, '2025-12-24 09:02:03'),
	(5, 1, 1, '2025-12-24 14:51:30'),
	(6, 0, 1, '2025-12-24 14:51:51'),
	(7, 1, 1, '2025-12-24 14:52:54'),
	(8, 1, 1, '2025-12-24 18:50:37'),
	(9, 1, 1, '2025-12-29 09:58:25'),
	(10, 0, 1, '2025-12-29 09:58:32'),
	(11, 1, 1, '2025-12-29 11:48:11'),
	(12, 1, 1, '2025-12-29 11:48:19'),
	(13, 1, 1, '2025-12-29 11:48:48'),
	(14, 0, 1, '2025-12-29 14:08:04'),
	(15, 1, 1, '2025-12-29 14:08:14'),
	(16, 0, 1, '2025-12-29 14:43:09'),
	(17, 1, 1, '2025-12-29 14:43:26'),
	(18, 0, 1, '2025-12-29 14:43:42'),
	(19, 1, 1, '2025-12-29 14:51:22'),
	(20, 0, 1, '2025-12-29 14:55:49'),
	(21, 1, 1, '2025-12-29 14:57:30'),
	(22, 0, 1, '2025-12-29 15:05:24'),
	(23, 1, 1, '2025-12-29 15:28:08'),
	(24, 0, 1, '2025-12-29 15:28:31'),
	(25, 1, 1, '2025-12-29 15:28:39'),
	(26, 0, 1, '2025-12-29 15:34:40'),
	(27, 1, 1, '2025-12-29 15:52:59'),
	(28, 0, 1, '2025-12-29 15:57:07'),
	(29, 1, 1, '2025-12-29 15:58:23'),
	(30, 0, 1, '2025-12-29 16:18:57'),
	(31, 1, 1, '2025-12-29 16:19:41'),
	(32, 0, 1, '2025-12-29 16:21:08'),
	(33, 1, 1, '2025-12-29 16:23:24'),
	(34, 0, 1, '2025-12-29 16:23:33'),
	(35, 1, 1, '2025-12-29 16:28:28'),
	(36, 0, 1, '2025-12-29 16:30:39'),
	(37, 1, 1, '2026-01-02 15:16:44'),
	(38, 0, 1, '2026-01-02 15:16:50');

-- Dumping structure for table kmiprodm_aioa_tread.machine_events
CREATE TABLE IF NOT EXISTS `machine_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_code` varchar(20) NOT NULL,
  `status_bit` tinyint(1) DEFAULT NULL,
  `value_int` int(11) DEFAULT NULL,
  `value_float` float DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  `machine_id` varchar(20) DEFAULT 'EXTRUDER_01',
  `operator` varchar(50) DEFAULT NULL,
  `shift` enum('A','B','C') DEFAULT 'A',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table kmiprodm_aioa_tread.machine_events: ~43 rows (approximately)
DELETE FROM `machine_events`;
INSERT INTO `machine_events` (`id`, `metric_code`, `status_bit`, `value_int`, `value_float`, `description`, `recorded_at`, `machine_id`, `operator`, `shift`, `notes`) VALUES
	(1, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 11:48:11', 'EXTRUDER_01', NULL, 'A', NULL),
	(2, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 11:48:11', 'EXTRUDER_01', NULL, 'A', NULL),
	(3, 'RUNTIME', NULL, 5, NULL, NULL, '2025-12-29 11:48:11', 'EXTRUDER_01', NULL, 'A', NULL),
	(4, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 14:08:04', 'EXTRUDER_01', NULL, 'A', NULL),
	(5, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 14:08:14', 'EXTRUDER_01', NULL, 'A', NULL),
	(6, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 14:43:09', 'EXTRUDER_01', NULL, 'A', NULL),
	(7, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 14:43:26', 'EXTRUDER_01', NULL, 'A', NULL),
	(8, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 14:43:42', 'EXTRUDER_01', NULL, 'A', NULL),
	(9, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 14:51:22', 'EXTRUDER_01', NULL, 'A', NULL),
	(10, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 14:55:49', 'EXTRUDER_01', NULL, 'A', NULL),
	(11, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 14:57:30', 'EXTRUDER_01', NULL, 'A', NULL),
	(12, 'PRODUCTION', NULL, 1, NULL, 'Tread production from X-Ray check', '2025-12-29 14:59:28', 'EXTRUDER_01', NULL, 'A', NULL),
	(13, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 15:05:24', 'EXTRUDER_01', NULL, 'A', NULL),
	(14, 'PRODUCTION', NULL, 1, NULL, 'Tread production from X-Ray check', '2025-12-29 15:10:27', 'EXTRUDER_01', NULL, 'A', NULL),
	(15, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 15:28:08', 'EXTRUDER_01', NULL, 'A', NULL),
	(16, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 15:28:31', 'EXTRUDER_01', NULL, 'A', NULL),
	(17, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 15:28:39', 'EXTRUDER_01', NULL, 'A', NULL),
	(18, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 15:34:40', 'EXTRUDER_01', NULL, 'A', NULL),
	(19, 'PRODUCTION', NULL, 10, NULL, 'Tread production from X-Ray check', '2025-12-29 15:40:58', 'EXTRUDER_01', NULL, 'A', NULL),
	(20, 'PRODUCTION', NULL, 8, NULL, 'Tread production from X-Ray check', '2025-12-29 15:41:49', 'EXTRUDER_01', NULL, 'A', NULL),
	(21, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 15:52:59', 'EXTRUDER_01', NULL, 'A', NULL),
	(22, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 15:52:59', 'EXTRUDER_01', NULL, 'A', NULL),
	(23, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 15:57:07', 'EXTRUDER_01', NULL, 'A', NULL),
	(24, 'STATUS_HIST', 0, NULL, NULL, NULL, '2025-12-29 15:57:07', 'EXTRUDER_01', NULL, 'A', NULL),
	(25, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 15:58:23', 'EXTRUDER_01', NULL, 'A', NULL),
	(26, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 15:58:23', 'EXTRUDER_01', NULL, 'A', NULL),
	(27, 'PRODUCTION', NULL, 7, NULL, 'Tread production from X-Ray check', '2025-12-29 16:02:52', 'EXTRUDER_01', NULL, 'A', NULL),
	(28, 'PRODUCTION', NULL, 6, NULL, 'Tread production from X-Ray check', '2025-12-29 16:04:12', 'EXTRUDER_01', NULL, 'A', NULL),
	(29, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 16:18:57', 'EXTRUDER_01', NULL, 'A', NULL),
	(30, 'STATUS_HIST', 0, NULL, NULL, NULL, '2025-12-29 16:18:57', 'EXTRUDER_01', NULL, 'A', NULL),
	(31, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 16:19:41', 'EXTRUDER_01', NULL, 'A', NULL),
	(32, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 16:19:41', 'EXTRUDER_01', NULL, 'A', NULL),
	(33, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 16:21:08', 'EXTRUDER_01', NULL, 'A', NULL),
	(34, 'STATUS_HIST', 0, NULL, NULL, NULL, '2025-12-29 16:21:08', 'EXTRUDER_01', NULL, 'A', NULL),
	(35, 'PRODUCTION', NULL, 5, NULL, 'Tread production from X-Ray check', '2025-12-29 16:22:33', 'EXTRUDER_01', NULL, 'A', NULL),
	(36, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 16:23:24', 'EXTRUDER_01', NULL, 'A', NULL),
	(37, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 16:23:24', 'EXTRUDER_01', NULL, 'A', NULL),
	(38, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 16:23:33', 'EXTRUDER_01', NULL, 'A', NULL),
	(39, 'STATUS_HIST', 0, NULL, NULL, NULL, '2025-12-29 16:23:33', 'EXTRUDER_01', NULL, 'A', NULL),
	(40, 'STATUS_MON', 1, NULL, NULL, NULL, '2025-12-29 16:28:28', 'EXTRUDER_01', NULL, 'A', NULL),
	(41, 'STATUS_HIST', 1, NULL, NULL, NULL, '2025-12-29 16:28:28', 'EXTRUDER_01', NULL, 'A', NULL),
	(42, 'STATUS_MON', 0, NULL, NULL, NULL, '2025-12-29 16:30:39', 'EXTRUDER_01', NULL, 'A', NULL),
	(43, 'STATUS_HIST', 0, NULL, NULL, NULL, '2025-12-29 16:30:39', 'EXTRUDER_01', NULL, 'A', NULL),
	(44, 'STATUS_MON', 1, NULL, NULL, NULL, '2026-01-02 15:16:44', 'EXTRUDER_01', NULL, 'A', NULL),
	(45, 'STATUS_HIST', 1, NULL, NULL, NULL, '2026-01-02 15:16:44', 'EXTRUDER_01', NULL, 'A', NULL),
	(46, 'STATUS_MON', 0, NULL, NULL, NULL, '2026-01-02 15:16:50', 'EXTRUDER_01', NULL, 'A', NULL),
	(47, 'STATUS_HIST', 0, NULL, NULL, NULL, '2026-01-02 15:16:50', 'EXTRUDER_01', NULL, 'A', NULL);

-- Dumping structure for table kmiprodm_aioa_tread.production_quality_log
CREATE TABLE IF NOT EXISTS `production_quality_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qty` int(11) NOT NULL DEFAULT 1,
  `ok_bit` tinyint(1) NOT NULL,
  `tread_type` enum('TREAD','SIDEWALL') DEFAULT 'TREAD',
  `recorded_at` datetime DEFAULT current_timestamp(),
  `batch_no` varchar(20) DEFAULT NULL,
  `shift` enum('A','B','C') DEFAULT 'A',
  `operator` varchar(50) DEFAULT NULL,
  `dimension_ok` tinyint(1) DEFAULT 1,
  `temperature_ok` tinyint(1) DEFAULT 1,
  `defect_type` enum('NONE','CRACK','BUBBLE','DIMENSION','TEMP') DEFAULT 'NONE',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Dumping data for table kmiprodm_aioa_tread.production_quality_log: ~9 rows (approximately)
DELETE FROM `production_quality_log`;
INSERT INTO `production_quality_log` (`id`, `qty`, `ok_bit`, `tread_type`, `recorded_at`, `batch_no`, `shift`, `operator`, `dimension_ok`, `temperature_ok`, `defect_type`, `notes`) VALUES
	(1, 1, 1, 'TREAD', '2025-12-29 11:48:11', NULL, 'A', NULL, 1, 1, 'NONE', NULL),
	(2, 1, 1, 'TREAD', '2025-12-29 14:59:28', NULL, 'A', NULL, 1, 1, 'NONE', NULL),
	(3, 1, 0, 'TREAD', '2025-12-29 14:59:51', NULL, 'A', NULL, 1, 1, 'TEMP', NULL),
	(4, 1, 1, 'TREAD', '2025-12-29 15:10:27', NULL, 'A', NULL, 1, 1, 'NONE', NULL),
	(5, 10, 1, 'TREAD', '2025-12-29 15:40:58', NULL, 'A', NULL, 1, 1, 'NONE', NULL),
	(6, 8, 1, 'TREAD', '2025-12-29 15:41:49', NULL, 'A', NULL, 1, 1, 'NONE', NULL),
	(7, 7, 1, 'SIDEWALL', '2025-12-29 16:02:52', 'BATCH-0007', 'C', 'OP-02', 0, 0, 'NONE', 'Cekk'),
	(8, 6, 1, 'TREAD', '2025-12-29 16:04:12', 'BATCH-0008', 'B', 'OP-01', 0, 0, 'NONE', 'CEK1'),
	(9, 3, 0, 'TREAD', '2025-12-29 16:07:37', 'BATCH-0009', 'B', 'OP-01', 0, 1, 'BUBBLE', 'Y'),
	(10, 5, 1, 'SIDEWALL', '2025-12-29 16:22:33', 'BATCH-00010', 'C', 'OP-04', 1, 1, 'NONE', 'YY');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

ALTER TABLE machine_control
  ADD COLUMN machine_id VARCHAR(20) DEFAULT 'EXTRUDER_01' AFTER mode_bit;

ALTER TABLE production_quality_log
  ADD COLUMN machine_id VARCHAR(20) DEFAULT 'EXTRUDER_01' AFTER tread_type,
  ADD COLUMN defect_severity ENUM('LOW','MEDIUM','HIGH') DEFAULT NULL AFTER defect_type;

ALTER TABLE production_quality_log
  MODIFY defect_type ENUM('NONE','CRACK','BUBBLE','DIMENSION','TEMP','UNKNOWN','COLOR','SURFACE') DEFAULT 'NONE';


CREATE INDEX idx_machine_events_metric_time ON machine_events(metric_code, recorded_at, machine_id);
CREATE INDEX idx_pql_recorded ON production_quality_log(recorded_at);
CREATE INDEX idx_pql_shift ON production_quality_log(shift, recorded_at);

