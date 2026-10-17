CREATE DATABASE  IF NOT EXISTS `bloodlink` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `bloodlink`;
-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: localhost    Database: bloodlink
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alert_recipients`
--

DROP TABLE IF EXISTS `alert_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alert_recipients` (
  `alert_recipient_id` int unsigned NOT NULL AUTO_INCREMENT,
  `alert_id` int unsigned NOT NULL,
  `donor_id` int unsigned NOT NULL,
  `notified_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`alert_recipient_id`),
  UNIQUE KEY `uq_alert_recipient` (`alert_id`,`donor_id`),
  KEY `idx_alert_recipients_pending` (`donor_id`,`notified_at`),
  CONSTRAINT `fk_alert_recipients_alert` FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`alert_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_alert_recipients_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alert_recipients`
--

LOCK TABLES `alert_recipients` WRITE;
/*!40000 ALTER TABLE `alert_recipients` DISABLE KEYS */;
INSERT INTO `alert_recipients` VALUES (1,1,4,'2026-10-14 08:01:00','2026-10-14 08:20:00'),(2,2,1,'2026-10-14 09:00:00',NULL),(3,2,3,NULL,NULL),(4,2,5,'2026-10-14 09:00:00','2026-10-14 12:45:00'),(5,2,7,NULL,NULL);
/*!40000 ALTER TABLE `alert_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `alert_id` int unsigned NOT NULL AUTO_INCREMENT,
  `drive_id` int unsigned DEFAULT NULL,
  `request_id` int unsigned DEFAULT NULL,
  `blood_type_id` tinyint unsigned DEFAULT NULL,
  `radius_km` decimal(5,1) NOT NULL DEFAULT '10.0',
  `title` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`alert_id`),
  KEY `fk_alerts_drive` (`drive_id`),
  KEY `fk_alerts_request` (`request_id`),
  KEY `fk_alerts_blood_type` (`blood_type_id`),
  CONSTRAINT `fk_alerts_blood_type` FOREIGN KEY (`blood_type_id`) REFERENCES `blood_types` (`blood_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_alerts_drive` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_alerts_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_alerts_radius` CHECK ((`radius_km` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alerts`
--

LOCK TABLES `alerts` WRITE;
/*!40000 ALTER TABLE `alerts` DISABLE KEYS */;
INSERT INTO `alerts` VALUES (1,NULL,1,8,30.0,'Emergency: O- blood needed','Pines City Blood Center urgently needs 4 units of O- blood by Oct 17.','2026-10-16 15:24:19'),(2,2,NULL,NULL,10.0,'Blood drive in Irisan this Saturday','Irisan Covered Court, Oct 18, 8 AM to 12 NN. Book a slot in BloodLink.','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_banks`
--

DROP TABLE IF EXISTS `blood_banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blood_banks` (
  `bank_id` int unsigned NOT NULL AUTO_INCREMENT,
  `city_id` smallint unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `latitude` decimal(8,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  PRIMARY KEY (`bank_id`),
  UNIQUE KEY `uq_blood_banks_name` (`name`),
  KEY `fk_blood_banks_city` (`city_id`),
  CONSTRAINT `fk_blood_banks_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`city_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_banks`
--

LOCK TABLES `blood_banks` WRITE;
/*!40000 ALTER TABLE `blood_banks` DISABLE KEYS */;
INSERT INTO `blood_banks` VALUES (1,1,'Pines City Blood Center','Upper General Luna Road','074-555-0101','2026-10-16 15:24:19',NULL,NULL),(2,2,'Benguet Provincial Blood Station','Km. 5 Halsema Highway','074-555-0202','2026-10-16 15:24:19',NULL,NULL),(3,1,'Cordillera Regional Blood Hub','Magsaysay Avenue','074-555-0303','2026-10-16 15:24:19',NULL,NULL);
/*!40000 ALTER TABLE `blood_banks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_requests`
--

DROP TABLE IF EXISTS `blood_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blood_requests` (
  `request_id` int unsigned NOT NULL AUTO_INCREMENT,
  `bank_id` int unsigned NOT NULL,
  `blood_type_id` tinyint unsigned NOT NULL,
  `units_needed` smallint unsigned NOT NULL,
  `urgency` enum('routine','urgent','emergency') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'routine',
  `status` enum('open','fulfilled','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `needed_by` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_requests_status` (`status`,`urgency`),
  KEY `fk_requests_bank` (`bank_id`),
  KEY `fk_requests_blood_type` (`blood_type_id`),
  CONSTRAINT `fk_requests_bank` FOREIGN KEY (`bank_id`) REFERENCES `blood_banks` (`bank_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_requests_blood_type` FOREIGN KEY (`blood_type_id`) REFERENCES `blood_types` (`blood_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_requests_units` CHECK ((`units_needed` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_requests`
--

LOCK TABLES `blood_requests` WRITE;
/*!40000 ALTER TABLE `blood_requests` DISABLE KEYS */;
INSERT INTO `blood_requests` VALUES (1,1,8,4,'emergency','open','2026-10-17','2026-10-16 15:24:19'),(2,2,1,1,'urgent','fulfilled','2026-10-12','2026-10-16 15:24:19'),(3,3,7,10,'routine','open','2026-10-31','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `blood_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_types`
--

DROP TABLE IF EXISTS `blood_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blood_types` (
  `blood_type_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`blood_type_id`),
  UNIQUE KEY `uq_blood_types_label` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_types`
--

LOCK TABLES `blood_types` WRITE;
/*!40000 ALTER TABLE `blood_types` DISABLE KEYS */;
INSERT INTO `blood_types` VALUES (2,'A-'),(1,'A+'),(6,'AB-'),(5,'AB+'),(4,'B-'),(3,'B+'),(8,'O-'),(7,'O+');
/*!40000 ALTER TABLE `blood_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cities`
--

DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `city_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`city_id`),
  UNIQUE KEY `uq_cities_name_province` (`name`,`province`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cities`
--

LOCK TABLES `cities` WRITE;
/*!40000 ALTER TABLE `cities` DISABLE KEYS */;
INSERT INTO `cities` VALUES (1,'Baguio City','Benguet'),(3,'Itogon','Benguet'),(2,'La Trinidad','Benguet'),(5,'Sablan','Benguet'),(4,'Tuba','Benguet');
/*!40000 ALTER TABLE `cities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donations`
--

DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donations` (
  `donation_id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `screening_id` int unsigned NOT NULL,
  `request_id` int unsigned DEFAULT NULL,
  `volume_ml` smallint unsigned NOT NULL DEFAULT '450',
  `status` enum('pending_test','cleared','discarded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_test',
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(8,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `gps_accuracy_m` decimal(7,2) DEFAULT NULL,
  `collected_at` datetime NOT NULL,
  `synced_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`donation_id`),
  UNIQUE KEY `uq_donations_client_uuid` (`client_uuid`),
  UNIQUE KEY `uq_donations_screening` (`screening_id`),
  KEY `idx_donations_collected` (`collected_at`),
  KEY `idx_donations_status` (`status`),
  KEY `fk_donations_request` (`request_id`),
  CONSTRAINT `fk_donations_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_donations_screening` FOREIGN KEY (`screening_id`) REFERENCES `screenings` (`screening_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_donations_lat` CHECK (((`latitude` is null) or (`latitude` between -(90) and 90))),
  CONSTRAINT `chk_donations_lng` CHECK (((`longitude` is null) or (`longitude` between -(180) and 180))),
  CONSTRAINT `chk_donations_volume` CHECK ((`volume_ml` between 100 and 600))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donations`
--

LOCK TABLES `donations` WRITE;
/*!40000 ALTER TABLE `donations` DISABLE KEYS */;
INSERT INTO `donations` VALUES (1,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e01',1,NULL,450,'cleared',NULL,16.415720,120.597410,12.50,'2026-10-05 09:12:00','2026-10-16 15:24:19','2026-10-16 15:24:19',NULL),(2,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e02',2,NULL,450,'pending_test',NULL,16.415690,120.597380,15.00,'2026-10-05 10:40:00','2026-10-16 15:24:19','2026-10-16 15:24:19',NULL),(3,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e03',3,2,450,'cleared','Replacement donation for request #2',16.455030,120.588020,8.25,'2026-10-12 08:55:00','2026-10-16 15:24:19','2026-10-16 15:24:19',NULL),(4,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e04',4,NULL,450,'discarded','Failed post-collection screening test',16.455010,120.587990,20.00,'2026-10-12 09:30:00','2026-10-16 15:24:19','2026-10-16 15:24:19',NULL),(5,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e05',5,1,450,'pending_test','Walk-in at blood center',16.409800,120.599100,5.00,'2026-10-13 14:05:00','2026-10-16 15:24:19','2026-10-16 15:24:19',NULL),(6,'3f1c2a9e-7b4d-4c1a-9e2f-1a2b3c4d5e06',6,NULL,450,'pending_test','Entered by mistake',NULL,NULL,NULL,'2026-10-13 15:20:00','2026-10-16 15:24:19','2026-10-16 15:24:19','2026-10-13 15:25:00');
/*!40000 ALTER TABLE `donations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donors`
--

DROP TABLE IF EXISTS `donors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `donors` (
  `donor_id` int unsigned NOT NULL AUTO_INCREMENT,
  `blood_type_id` tinyint unsigned DEFAULT NULL,
  `city_id` smallint unsigned DEFAULT NULL,
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_date` date NOT NULL,
  `contact_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(8,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`donor_id`),
  KEY `idx_donors_name` (`last_name`,`first_name`),
  KEY `fk_donors_blood_type` (`blood_type_id`),
  KEY `fk_donors_city` (`city_id`),
  CONSTRAINT `fk_donors_blood_type` FOREIGN KEY (`blood_type_id`) REFERENCES `blood_types` (`blood_type_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_donors_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`city_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donors`
--

LOCK TABLES `donors` WRITE;
/*!40000 ALTER TABLE `donors` DISABLE KEYS */;
INSERT INTO `donors` VALUES (1,7,1,'Maria','Dela Cruz','2001-03-14','0917-555-2001',16.412000,120.596000,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(2,1,2,'Joel','Bangit','1998-07-02','0917-555-2002',16.461000,120.587000,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(3,3,1,'Aileen','Santos','2003-11-21','0917-555-2003',NULL,NULL,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(4,8,3,'Ramon','Pacio','1990-01-30','0917-555-2004',16.365000,120.677000,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(5,5,1,'Grace','Ngaosi','1995-05-09','0917-555-2005',16.418000,120.556000,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(6,NULL,4,'Kevin','Toyhacao','2006-09-18','0917-555-2006',NULL,NULL,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(7,2,1,'Liza','Mendoza','1987-12-05','0917-555-2007',16.402000,120.601000,'2026-10-16 15:24:19','2026-10-16 15:24:19'),(8,7,5,'Paolo','Gawidan','2000-04-27','0917-555-2008',NULL,NULL,'2026-10-16 15:24:19','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `donors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drive_registrations`
--

DROP TABLE IF EXISTS `drive_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drive_registrations` (
  `registration_id` int unsigned NOT NULL AUTO_INCREMENT,
  `drive_id` int unsigned NOT NULL,
  `donor_id` int unsigned NOT NULL,
  `slot_time` time DEFAULT NULL,
  `status` enum('registered','attended','no_show','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'registered',
  `registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `uq_registration_drive_donor` (`drive_id`,`donor_id`),
  KEY `fk_registrations_donor` (`donor_id`),
  CONSTRAINT `fk_registrations_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_registrations_drive` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drive_registrations`
--

LOCK TABLES `drive_registrations` WRITE;
/*!40000 ALTER TABLE `drive_registrations` DISABLE KEYS */;
INSERT INTO `drive_registrations` VALUES (1,1,1,'09:00:00','attended','2026-10-16 15:24:19'),(2,1,3,'10:30:00','attended','2026-10-16 15:24:19'),(3,1,6,'11:00:00','attended','2026-10-16 15:24:19'),(4,3,2,'08:30:00','attended','2026-10-16 15:24:19'),(5,3,7,'09:30:00','attended','2026-10-16 15:24:19'),(6,2,4,'08:30:00','registered','2026-10-16 15:24:19'),(7,2,5,'09:00:00','registered','2026-10-16 15:24:19'),(8,4,8,'07:00:00','registered','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `drive_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drives`
--

DROP TABLE IF EXISTS `drives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `drives` (
  `drive_id` int unsigned NOT NULL AUTO_INCREMENT,
  `organizer_id` int unsigned NOT NULL,
  `bank_id` int unsigned NOT NULL,
  `city_id` smallint unsigned NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `venue` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drive_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `latitude` decimal(8,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `status` enum('scheduled','ongoing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`drive_id`),
  KEY `idx_drives_date` (`drive_date`),
  KEY `fk_drives_organizer` (`organizer_id`),
  KEY `fk_drives_bank` (`bank_id`),
  KEY `fk_drives_city` (`city_id`),
  CONSTRAINT `fk_drives_bank` FOREIGN KEY (`bank_id`) REFERENCES `blood_banks` (`bank_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_drives_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`city_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_drives_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `organizers` (`organizer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_drives_times` CHECK ((`end_time` > `start_time`))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drives`
--

LOCK TABLES `drives` WRITE;
/*!40000 ALTER TABLE `drives` DISABLE KEYS */;
INSERT INTO `drives` VALUES (1,1,1,1,'SLU Bloodletting Day','SLU Main Campus Gym','2026-10-05','08:00:00','15:00:00',16.415700,120.597400,'completed','2026-10-16 15:24:19'),(2,2,1,1,'Irisan Barangay Blood Drive','Irisan Covered Court','2026-10-18','08:00:00','12:00:00',16.420900,120.553600,'scheduled','2026-10-16 15:24:19'),(3,3,2,2,'Give Life Sunday','Grace Fellowship Hall','2026-10-12','07:30:00','11:30:00',16.455000,120.588000,'completed','2026-10-16 15:24:19'),(4,4,3,1,'Session Road Blood Run','Session Road (closed to cars)','2026-11-08','06:00:00','12:00:00',16.411500,120.598300,'scheduled','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `drives` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizers`
--

DROP TABLE IF EXISTS `organizers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizers` (
  `organizer_id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `org_type` enum('school','lgu','church','company','ngo','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `contact_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`organizer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizers`
--

LOCK TABLES `organizers` WRITE;
/*!40000 ALTER TABLE `organizers` DISABLE KEYS */;
INSERT INTO `organizers` VALUES (1,'SLU Red Cross Youth Council','school','0917-555-1001','rcy@example.edu.ph','2026-10-16 15:24:19'),(2,'Barangay Irisan Council','lgu','0918-555-1002',NULL,'2026-10-16 15:24:19'),(3,'Grace Fellowship Baguio','church','0919-555-1003','outreach@example.org','2026-10-16 15:24:19'),(4,'Session Road Merchants Assn','company','0920-555-1004',NULL,'2026-10-16 15:24:19');
/*!40000 ALTER TABLE `organizers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `screenings`
--

DROP TABLE IF EXISTS `screenings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `screenings` (
  `screening_id` int unsigned NOT NULL AUTO_INCREMENT,
  `client_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `donor_id` int unsigned NOT NULL,
  `bank_id` int unsigned NOT NULL,
  `drive_id` int unsigned DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `hemoglobin_g_l` smallint unsigned DEFAULT NULL,
  `bp_systolic` smallint unsigned DEFAULT NULL,
  `bp_diastolic` smallint unsigned DEFAULT NULL,
  `temperature_c` decimal(4,1) DEFAULT NULL,
  `result` enum('eligible','temporarily_deferred','permanently_deferred') COLLATE utf8mb4_unicode_ci NOT NULL,
  `deferral_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deferred_until` date DEFAULT NULL,
  `screened_at` datetime NOT NULL,
  `synced_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`screening_id`),
  UNIQUE KEY `uq_screenings_client_uuid` (`client_uuid`),
  KEY `idx_screenings_donor_date` (`donor_id`,`screened_at`),
  KEY `fk_screenings_bank` (`bank_id`),
  KEY `fk_screenings_drive` (`drive_id`),
  CONSTRAINT `fk_screenings_bank` FOREIGN KEY (`bank_id`) REFERENCES `blood_banks` (`bank_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_screenings_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_screenings_drive` FOREIGN KEY (`drive_id`) REFERENCES `drives` (`drive_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_screenings_deferral` CHECK (((`result` <> _utf8mb4'temporarily_deferred') or (`deferred_until` is not null)))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `screenings`
--

LOCK TABLES `screenings` WRITE;
/*!40000 ALTER TABLE `screenings` DISABLE KEYS */;
INSERT INTO `screenings` VALUES (1,'a1b2c3d4-0000-4000-8000-000000000001',1,1,1,54.00,132,112,74,36.6,'eligible',NULL,NULL,'2026-10-05 08:58:00','2026-10-16 15:24:19'),(2,'a1b2c3d4-0000-4000-8000-000000000002',3,1,1,51.50,128,108,70,36.7,'eligible',NULL,NULL,'2026-10-05 10:25:00','2026-10-16 15:24:19'),(3,'a1b2c3d4-0000-4000-8000-000000000003',2,2,3,68.00,151,124,80,36.5,'eligible',NULL,NULL,'2026-10-12 08:40:00','2026-10-16 15:24:19'),(4,'a1b2c3d4-0000-4000-8000-000000000004',7,2,3,60.20,138,118,76,36.8,'eligible',NULL,NULL,'2026-10-12 09:20:00','2026-10-16 15:24:19'),(5,'a1b2c3d4-0000-4000-8000-000000000005',4,1,NULL,72.40,149,130,84,36.6,'eligible',NULL,NULL,'2026-10-13 13:55:00','2026-10-16 15:24:19'),(6,'a1b2c3d4-0000-4000-8000-000000000006',8,3,NULL,65.00,144,120,78,36.7,'eligible',NULL,NULL,'2026-10-13 15:10:00','2026-10-16 15:24:19'),(7,'a1b2c3d4-0000-4000-8000-000000000007',6,1,1,52.00,118,110,72,36.6,'temporarily_deferred','Hemoglobin below 125 g/L','2026-11-05','2026-10-05 11:05:00','2026-10-16 15:24:19');
/*!40000 ALTER TABLE `screenings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 22:26:43
