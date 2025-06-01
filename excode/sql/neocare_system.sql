-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 31, 2025 at 07:13 PM
-- Server version: 8.0.31
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `neocare_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

DROP TABLE IF EXISTS `children`;
CREATE TABLE IF NOT EXISTS `children` (
  `child_id` int NOT NULL AUTO_INCREMENT,
  `hospital_id` int NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `child_name` varchar(255) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `parent_name` varchar(255) NOT NULL,
  `parent_phone` varchar(20) NOT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text,
  `registered_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`child_id`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `registered_by` (`registered_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `child_medical_records`
--

DROP TABLE IF EXISTS `child_medical_records`;
CREATE TABLE IF NOT EXISTS `child_medical_records` (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `recorded_by` int NOT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `age_months` int DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `notes` text,
  `visit_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  KEY `child_id` (`child_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

DROP TABLE IF EXISTS `hospitals`;
CREATE TABLE IF NOT EXISTS `hospitals` (
  `hospital_id` int NOT NULL AUTO_INCREMENT,
  `hospital_name` varchar(255) NOT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`hospital_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`hospital_id`, `hospital_name`, `address`, `phone`, `email`, `created_at`, `updated_at`) VALUES
(1, 'Central Hospital', '123 Main Street, City Center', '+255123456789', 'info@centralhospital.com', '2025-05-30 19:02:36', '2025-05-30 19:02:36'),
(2, 'Community Health Center', '456 Health Avenue, Suburb', '+255987654321', 'contact@communityhc.com', '2025-05-30 19:02:36', '2025-05-30 19:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE IF NOT EXISTS `sms_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `child_id` (`child_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `hospital_id` int DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('super_admin','hospital_admin','doctor','nurse') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `hospital_id` (`hospital_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `hospital_id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'superadmin', 'admin123', 'System Administrator', 'admin@neocare.com', NULL, 'super_admin', 1, '2025-05-30 19:02:36', '2025-05-30 19:02:36'),
(2, 1, 'central_admin', 'admin123', 'Central Hospital Admin', 'admin@centralhospital.com', NULL, 'hospital_admin', 1, '2025-05-30 19:02:36', '2025-05-30 19:02:36'),
(3, 2, 'community_admin', 'admin123', 'Community Health Admin', 'admin@communityhc.com', NULL, 'hospital_admin', 1, '2025-05-30 19:02:36', '2025-05-30 19:02:36');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_schedule`
--

DROP TABLE IF EXISTS `vaccination_schedule`;
CREATE TABLE IF NOT EXISTS `vaccination_schedule` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `vaccine_id` int NOT NULL,
  `scheduled_date` date NOT NULL,
  `status` enum('pending','completed','missed') DEFAULT 'pending',
  `administered_date` date DEFAULT NULL,
  `administered_by` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `child_id` (`child_id`),
  KEY `vaccine_id` (`vaccine_id`),
  KEY `administered_by` (`administered_by`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

DROP TABLE IF EXISTS `vaccines`;
CREATE TABLE IF NOT EXISTS `vaccines` (
  `vaccine_id` int NOT NULL AUTO_INCREMENT,
  `vaccine_name` varchar(255) NOT NULL,
  `description` text,
  `child_age_weeks` int NOT NULL,
  `dose_number` int DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vaccine_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`vaccine_id`, `vaccine_name`, `description`, `child_age_weeks`, `dose_number`, `is_active`, `created_at`) VALUES
(1, 'BCG', 'Bacillus Calmette-Guérin vaccine against tuberculosis', 0, 1, 1, '2025-05-30 19:02:40'),
(2, 'OPV 1', 'Oral Polio Vaccine - First dose', 6, 1, 1, '2025-05-30 19:02:40'),
(3, 'DPT 1', 'Diphtheria, Pertussis, Tetanus - First dose', 6, 1, 1, '2025-05-30 19:02:40'),
(4, 'OPV 2', 'Oral Polio Vaccine - Second dose', 10, 2, 1, '2025-05-30 19:02:40'),
(5, 'DPT 2', 'Diphtheria, Pertussis, Tetanus - Second dose', 10, 2, 1, '2025-05-30 19:02:40'),
(6, 'OPV 3', 'Oral Polio Vaccine - Third dose', 14, 3, 1, '2025-05-30 19:02:40'),
(7, 'DPT 3', 'Diphtheria, Pertussis, Tetanus - Third dose', 14, 3, 1, '2025-05-30 19:02:40'),
(8, 'Measles', 'Measles vaccine', 36, 1, 1, '2025-05-30 19:02:40'),
(9, 'MMR', 'Measles, Mumps, Rubella vaccine', 52, 1, 1, '2025-05-30 19:02:40');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
