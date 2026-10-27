-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smart_matrimony
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_global_message`
--

DROP TABLE IF EXISTS `admin_global_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_global_message` (
  `message_id` tinyint(3) unsigned NOT NULL,
  `message_text` text NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_messages`
--

DROP TABLE IF EXISTS `admin_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_admin_messages_user` (`user_id`,`status`,`created_at`),
  KEY `idx_admin_messages_admin` (`admin_id`,`created_at`),
  CONSTRAINT `fk_admin_messages_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `authenticator_messages`
--

DROP TABLE IF EXISTS `authenticator_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `authenticator_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `authenticator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_authenticator_messages_user` (`user_id`,`status`,`created_at`),
  KEY `idx_authenticator_messages_authenticator` (`authenticator_id`,`created_at`),
  CONSTRAINT `fk_authenticator_messages_authenticator` FOREIGN KEY (`authenticator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_authenticator_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `authenticator_profile_claims`
--

DROP TABLE IF EXISTS `authenticator_profile_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `authenticator_profile_claims` (
  `claim_id` int(11) NOT NULL AUTO_INCREMENT,
  `profile_id` int(11) NOT NULL,
  `authenticator_id` int(11) NOT NULL,
  `status` enum('Active','Completed','Released') NOT NULL DEFAULT 'Active',
  `claimed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`claim_id`),
  UNIQUE KEY `uq_claim_profile` (`profile_id`),
  KEY `idx_claim_authenticator_status` (`authenticator_id`,`status`),
  CONSTRAINT `fk_claim_authenticator` FOREIGN KEY (`authenticator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_claim_profile` FOREIGN KEY (`profile_id`) REFERENCES `user_profiles` (`profile_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `booking_details`
--

DROP TABLE IF EXISTS `booking_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `event_date` date NOT NULL,
  `special_instruction` text DEFAULT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_booking_detail` (`booking_id`),
  KEY `fk_booking_provider` (`provider_id`),
  CONSTRAINT `fk_booking_detail` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `booking_status` enum('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` varchar(20) DEFAULT NULL,
  `confirmation_email_status` enum('Not Sent','Sent','Failed') NOT NULL DEFAULT 'Not Sent',
  `confirmation_email_sent_at` datetime DEFAULT NULL,
  `cancellation_email_status` enum('Not Sent','Sent','Failed') NOT NULL DEFAULT 'Not Sent',
  `cancellation_email_sent_at` datetime DEFAULT NULL,
  `confirmed_by_manager_id` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `completed_by_manager_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_by_manager_id` int(11) DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_removed` tinyint(1) NOT NULL DEFAULT 0,
  `admin_removed_at` datetime DEFAULT NULL,
  `admin_removed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`booking_id`),
  KEY `fk_booking_user` (`user_id`),
  KEY `fk_booking_manager` (`manager_id`),
  KEY `idx_bookings_admin_removed` (`manager_id`,`admin_removed`,`booking_status`),
  KEY `idx_bookings_admin_removed_by` (`admin_removed_by`,`admin_removed_at`),
  CONSTRAINT `fk_booking_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks` (
  `bookmark_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bookmarked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bookmark_id`),
  UNIQUE KEY `uq_bookmark` (`user_id`,`bookmarked_user_id`),
  KEY `fk_bookmarked_user` (`bookmarked_user_id`),
  CONSTRAINT `fk_bookmark_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bookmarked_user` FOREIGN KEY (`bookmarked_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chat_requests`
--

DROP TABLE IF EXISTS `chat_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_requests` (
  `chat_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled') DEFAULT 'Pending',
  `chat_active` tinyint(1) DEFAULT 0,
  `responded_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`chat_request_id`),
  KEY `fk_chat_match` (`match_id`),
  KEY `fk_chat_sender` (`sender_user_id`),
  KEY `fk_chat_receiver` (`receiver_user_id`),
  CONSTRAINT `fk_chat_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`match_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_messages`
--

DROP TABLE IF EXISTS `conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversation_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `fk_message_conversation` (`conversation_id`),
  KEY `fk_message_sender` (`sender_user_id`),
  CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_request_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `status` enum('Active','Closed') DEFAULT 'Active',
  `message_limit` tinyint(4) DEFAULT 10,
  `user1_message_count` tinyint(4) DEFAULT 0,
  `user2_message_count` tinyint(4) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`conversation_id`),
  KEY `fk_conversation_chat_request` (`chat_request_id`),
  KEY `fk_conversation_user1` (`user1_id`),
  KEY `fk_conversation_user2` (`user2_id`),
  CONSTRAINT `fk_conversation_chat_request` FOREIGN KEY (`chat_request_id`) REFERENCES `chat_requests` (`chat_request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conversation_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conversation_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `districts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `division_id` int(11) NOT NULL,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `division_id` (`division_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `divisions`
--

DROP TABLE IF EXISTS `divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `divisions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verifications` (
  `verification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_sent_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verification_id`),
  UNIQUE KEY `uq_email_verification_user` (`user_id`),
  KEY `idx_email_verification_email` (`email`),
  CONSTRAINT `fk_email_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `health_conditions`
--

DROP TABLE IF EXISTS `health_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_conditions` (
  `condition_id` int(11) NOT NULL AUTO_INCREMENT,
  `condition_name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`condition_id`),
  UNIQUE KEY `condition_name` (`condition_name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `health_profiles`
--

DROP TABLE IF EXISTS `health_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_profiles` (
  `health_profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Don''t Know') DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `disability_status` varchar(100) DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`health_profile_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_health_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `manager_activity_log`
--

DROP TABLE IF EXISTS `manager_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manager_activity_log` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) DEFAULT NULL,
  `manager_name` varchar(150) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `idx_manager_activity_manager` (`manager_id`,`created_at`),
  KEY `idx_manager_activity_booking` (`booking_id`),
  KEY `idx_manager_activity_provider` (`provider_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `manager_package_transfer_history`
--

DROP TABLE IF EXISTS `manager_package_transfer_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manager_package_transfer_history` (
  `transfer_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `provider_id` int(11) DEFAULT NULL,
  `from_manager_id` int(11) DEFAULT NULL,
  `to_manager_id` int(11) DEFAULT NULL,
  `transferred_by_admin_id` int(11) DEFAULT NULL,
  `transfer_reason` varchar(255) NOT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transfer_id`),
  KEY `idx_mpth_provider` (`provider_id`,`transferred_at`),
  KEY `idx_mpth_from_manager` (`from_manager_id`,`transferred_at`),
  KEY `idx_mpth_to_manager` (`to_manager_id`,`transferred_at`),
  KEY `fk_mpth_admin` (`transferred_by_admin_id`),
  CONSTRAINT `fk_mpth_admin` FOREIGN KEY (`transferred_by_admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mpth_from_manager` FOREIGN KEY (`from_manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mpth_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`provider_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mpth_to_manager` FOREIGN KEY (`to_manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `manager_role_history`
--

DROP TABLE IF EXISTS `manager_role_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manager_role_history` (
  `history_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `previous_role` enum('User','Manager','Authenticator') NOT NULL,
  `new_role` enum('User','Manager','Authenticator') NOT NULL,
  `changed_by_admin_id` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `details` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `idx_mrh_user` (`user_id`,`changed_at`),
  KEY `idx_mrh_admin` (`changed_by_admin_id`,`changed_at`),
  CONSTRAINT `fk_mrh_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mrh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matches`
--

DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `matches` (
  `match_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `interest_message` varchar(300) DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled','Unmatched') DEFAULT 'Pending',
  `relationship_active` tinyint(1) DEFAULT 1,
  `sender_history_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `receiver_history_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `responded_at` datetime DEFAULT NULL,
  `matched_at` datetime DEFAULT NULL,
  `unmatched_at` datetime DEFAULT NULL,
  `unmatched_by` int(11) DEFAULT NULL,
  `closed_reason` enum('Marriage','Rejected','Cancelled','Unmatched','Other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`match_id`),
  KEY `fk_match_sender` (`sender_user_id`),
  KEY `fk_match_receiver` (`receiver_user_id`),
  KEY `fk_match_unmatched_by` (`unmatched_by`),
  CONSTRAINT `fk_match_receiver` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_unmatched_by` FOREIGN KEY (`unmatched_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_settings` (
  `setting_id` tinyint(3) unsigned NOT NULL,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partner_trait_preferences`
--

DROP TABLE IF EXISTS `partner_trait_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_trait_preferences` (
  `preference_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `preferred_answer` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`preference_id`),
  UNIQUE KEY `uq_partner_user_question` (`user_id`,`question_id`),
  KEY `idx_partner_question_id` (`question_id`),
  CONSTRAINT `fk_partner_trait_question` FOREIGN KEY (`question_id`) REFERENCES `trait_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_partner_trait_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_sent_at` datetime NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  UNIQUE KEY `uq_password_resets_user` (`user_id`),
  KEY `idx_password_resets_email` (`email`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `profile_media`
--

DROP TABLE IF EXISTS `profile_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profile_media` (
  `media_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `media_type` enum('Profile Photo','Voice Introduction','Video Introduction') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `duration_seconds` tinyint(3) unsigned DEFAULT NULL,
  `visibility` enum('Everyone','Verified Users','Matched Users','Private') NOT NULL DEFAULT 'Verified Users',
  `status` enum('Active','Pending','Rejected','Deleted') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`media_id`),
  KEY `idx_profile_media_user` (`user_id`),
  KEY `idx_profile_media_type` (`media_type`),
  KEY `idx_profile_media_visibility` (`visibility`),
  KEY `idx_profile_media_status` (`status`),
  CONSTRAINT `fk_profile_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `search_preferences`
--

DROP TABLE IF EXISTS `search_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `search_preferences` (
  `preference_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preferred_gender` enum('Male','Female') NOT NULL,
  `min_age` tinyint(4) DEFAULT NULL,
  `max_age` tinyint(4) DEFAULT NULL,
  `min_height_cm` decimal(5,2) DEFAULT NULL,
  `max_height_cm` decimal(5,2) DEFAULT NULL,
  `complexion` varchar(50) DEFAULT NULL,
  `min_weight_kg` decimal(5,2) DEFAULT NULL,
  `max_weight_kg` decimal(5,2) DEFAULT NULL,
  `religion` enum('Islam','Hinduism','Christianity','Buddhism','Other') DEFAULT NULL,
  `madhhab` varchar(50) DEFAULT NULL,
  `prayer_status` varchar(50) DEFAULT NULL,
  `halal_lifestyle` varchar(50) DEFAULT NULL,
  `mahram_maintained` tinyint(1) DEFAULT NULL,
  `islamic_knowledge` varchar(50) DEFAULT NULL,
  `hijab_status` varchar(100) DEFAULT NULL,
  `beard_status` varchar(50) DEFAULT NULL,
  `personality_type` varchar(50) DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
  `family_status` varchar(50) DEFAULT NULL,
  `education` varchar(100) DEFAULT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `min_monthly_income` decimal(10,2) DEFAULT NULL,
  `division_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `upazila_id` int(11) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `accept_smoker` tinyint(1) DEFAULT 1,
  `accept_alcohol` tinyint(1) DEFAULT 1,
  `accept_disability` tinyint(1) DEFAULT 1,
  `accept_chronic_disease` tinyint(1) DEFAULT 1,
  `additional_preferences` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `partner_description` text DEFAULT NULL,
  PRIMARY KEY (`preference_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_cart_items`
--

DROP TABLE IF EXISTS `service_cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_cart_items` (
  `cart_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cart_item_id`),
  UNIQUE KEY `uq_service_cart_item` (`user_id`,`provider_id`),
  KEY `idx_cart_user` (`user_id`),
  KEY `idx_cart_provider` (`provider_id`),
  CONSTRAINT `fk_cart_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_package_sequences`
--

DROP TABLE IF EXISTS `service_package_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_package_sequences` (
  `service_id` int(11) NOT NULL,
  `last_number` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `service_providers`
--

DROP TABLE IF EXISTS `service_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_providers` (
  `provider_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_code` varchar(20) NOT NULL,
  `service_id` int(11) NOT NULL,
  `provider_name` varchar(150) NOT NULL,
  `package_name` varchar(150) DEFAULT NULL,
  `package_details` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `contact_number` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `review_count` int(10) unsigned NOT NULL DEFAULT 0,
  `manager_id` int(11) DEFAULT NULL,
  `original_manager_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`provider_id`),
  UNIQUE KEY `uq_service_providers_package_code` (`package_code`),
  KEY `fk_provider_service` (`service_id`),
  KEY `idx_provider_manager` (`manager_id`),
  KEY `idx_provider_status` (`status`),
  KEY `idx_service_providers_original_manager` (`original_manager_id`),
  CONSTRAINT `fk_provider_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_provider_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sm_service_providers_package_code_bi`
BEFORE INSERT ON `service_providers` FOR EACH ROW
BEGIN
  DECLARE next_number INT UNSIGNED;
  DECLARE prefix_code VARCHAR(5);
  IF NEW.`package_code` IS NULL OR TRIM(NEW.`package_code`) = '' THEN
    SELECT `service_prefix` INTO prefix_code
      FROM `services` WHERE `service_id` = NEW.`service_id` LIMIT 1;
    IF prefix_code IS NULL OR TRIM(prefix_code) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A valid service package prefix is required.';
    END IF;
    INSERT INTO `service_package_sequences` (`service_id`, `last_number`)
    VALUES (NEW.`service_id`, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE `last_number` = LAST_INSERT_ID(`last_number` + 1);
    SET next_number = LAST_INSERT_ID();
    SET NEW.`package_code` = CONCAT(prefix_code, '-', LPAD(next_number, 3, '0'));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `sm_service_providers_package_code_bu`
BEFORE UPDATE ON `service_providers` FOR EACH ROW
BEGIN
  DECLARE next_number INT UNSIGNED;
  DECLARE prefix_code VARCHAR(5);
  IF NOT (NEW.`service_id` <=> OLD.`service_id`) THEN
    SELECT `service_prefix` INTO prefix_code
      FROM `services` WHERE `service_id` = NEW.`service_id` LIMIT 1;
    IF prefix_code IS NULL OR TRIM(prefix_code) = '' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A valid service package prefix is required.';
    END IF;
    INSERT INTO `service_package_sequences` (`service_id`, `last_number`)
    VALUES (NEW.`service_id`, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE `last_number` = LAST_INSERT_ID(`last_number` + 1);
    SET next_number = LAST_INSERT_ID();
    SET NEW.`package_code` = CONCAT(prefix_code, '-', LPAD(next_number, 3, '0'));
  ELSE
    -- Preserve historical package IDs when only package details are edited.
    SET NEW.`package_code` = OLD.`package_code`;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `service_reviews`
--

DROP TABLE IF EXISTS `service_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `booking_detail_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `review_text` varchar(2000) DEFAULT NULL,
  `status` enum('Published','Hidden') NOT NULL DEFAULT 'Published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `uq_service_review_booking_detail` (`booking_detail_id`),
  KEY `idx_service_reviews_provider_status_created` (`provider_id`,`status`,`created_at`),
  KEY `idx_service_reviews_user_created` (`user_id`,`created_at`),
  KEY `idx_service_reviews_booking` (`booking_id`),
  CONSTRAINT `fk_service_review_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_review_booking_detail` FOREIGN KEY (`booking_detail_id`) REFERENCES `booking_details` (`detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_review_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `service_name_bn` varchar(100) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `description_bn` text DEFAULT NULL,
  `icon_class` varchar(60) NOT NULL DEFAULT 'fa-heart',
  `service_prefix` varchar(5) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `service_name` (`service_name`),
  UNIQUE KEY `uq_services_service_prefix` (`service_prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_messages`
--

DROP TABLE IF EXISTS `site_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_type` enum('Contact','Feedback') NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('New','Read','Resolved') NOT NULL DEFAULT 'New',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`message_id`),
  KEY `idx_site_messages_type` (`message_type`),
  KEY `idx_site_messages_status` (`status`),
  KEY `idx_site_messages_created_at` (`created_at`),
  KEY `idx_site_messages_user_id` (`user_id`),
  CONSTRAINT `fk_site_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_admin_messages`
--

DROP TABLE IF EXISTS `staff_admin_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_admin_messages` (
  `message_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `thread_id` char(32) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('Admin','Manager','Authenticator') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_role` enum('Admin','Manager','Authenticator') NOT NULL,
  `message` text NOT NULL,
  `status` enum('Unread','Read') NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_staff_admin_thread` (`thread_id`,`created_at`,`message_id`),
  KEY `idx_staff_admin_recipient` (`recipient_id`,`recipient_role`,`status`,`created_at`),
  KEY `idx_staff_admin_sender` (`sender_id`,`sender_role`,`created_at`),
  CONSTRAINT `fk_staff_admin_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_admin_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_profiles`
--

DROP TABLE IF EXISTS `staff_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_profiles` (
  `user_id` int(11) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_staff_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trait_questions`
--

DROP TABLE IF EXISTS `trait_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trait_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_text` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Both') DEFAULT 'Both',
  `answer_type` enum('Yes/No','Text','Dropdown') DEFAULT 'Text',
  `options` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `upazilas`
--

DROP TABLE IF EXISTS `upazilas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upazilas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `district_id` int(11) NOT NULL,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `district_id` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=494 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_blocks`
--

DROP TABLE IF EXISTS `user_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_blocks` (
  `block_id` int(11) NOT NULL AUTO_INCREMENT,
  `blocker_user_id` int(11) NOT NULL,
  `blocked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`block_id`),
  UNIQUE KEY `uq_user_block` (`blocker_user_id`,`blocked_user_id`),
  KEY `idx_user_blocks_blocked` (`blocked_user_id`),
  CONSTRAINT `fk_user_blocks_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_health_conditions`
--

DROP TABLE IF EXISTS `user_health_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_health_conditions` (
  `user_health_condition_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `condition_id` int(11) NOT NULL,
  `status` enum('Controlled','Uncontrolled','Recovered','Carrier','Positive','Negative','Unknown') DEFAULT 'Unknown',
  `severity` enum('Mild','Moderate','Severe') DEFAULT NULL,
  `diagnosed_date` date DEFAULT NULL,
  `treatment_status` enum('None','Ongoing','Completed') DEFAULT 'None',
  `disclosure_required` tinyint(1) DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_health_condition_id`),
  UNIQUE KEY `uq_user_condition` (`user_id`,`condition_id`),
  KEY `fk_uhc_condition` (`condition_id`),
  CONSTRAINT `fk_uhc_condition` FOREIGN KEY (`condition_id`) REFERENCES `health_conditions` (`condition_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uhc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_match_weights`
--

DROP TABLE IF EXISTS `user_match_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_match_weights` (
  `weight_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `age_weight` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `height_weight` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `religion_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `islamic_practice_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `marital_status_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `education_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `profession_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `location_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `lifestyle_weight` tinyint(3) unsigned NOT NULL DEFAULT 8,
  `qna_weight` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `skin_colour_weight` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `weight_weight` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `family_status_weight` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`weight_id`),
  UNIQUE KEY `uq_user_match_weights_user` (`user_id`),
  KEY `idx_user_match_weights_user` (`user_id`),
  CONSTRAINT `fk_user_match_weights_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_media_visibility_preferences`
--

DROP TABLE IF EXISTS `user_media_visibility_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_media_visibility_preferences` (
  `user_id` int(11) NOT NULL,
  `voice_visibility` enum('Everyone','Verified Users','Matched Users','Private') NOT NULL DEFAULT 'Verified Users',
  `video_visibility` enum('Everyone','Verified Users','Matched Users','Private') NOT NULL DEFAULT 'Verified Users',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_media_visibility_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_notification_preferences`
--

DROP TABLE IF EXISTS `user_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notification_preferences` (
  `user_id` int(11) NOT NULL,
  `notifications_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_notification_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_profiles`
--

DROP TABLE IF EXISTS `user_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `religion` enum('Islam','Hinduism','Christianity','Buddhism','Other') NOT NULL,
  `madhhab` enum('Ahle Hadith','Hanafi','Shafi','Maliki','Hanbali','Salafi','Not Applicable','Other') DEFAULT 'Not Applicable',
  `marital_status` enum('Single','Divorced','Widowed','Unmarried','Separated') NOT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `height_cm` smallint(6) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `complexion` enum('Fair','Medium','Wheatish','Dark','Very Fair','Medium-Deep') DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Bangladesh',
  `division_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `upazila_id` int(11) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `area_type` enum('Urban','Rural','Semi-Urban') DEFAULT NULL,
  `residence_type` enum('Own House','Family House','Rented','Other') DEFAULT NULL,
  `post_office` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `permanent_hometown` varchar(255) DEFAULT NULL,
  `permanent_country` varchar(100) DEFAULT 'Bangladesh',
  `permanent_division_id` int(11) DEFAULT NULL,
  `permanent_district_id` int(11) DEFAULT NULL,
  `permanent_upazila_id` int(11) DEFAULT NULL,
  `permanent_area` varchar(255) DEFAULT NULL,
  `permanent_area_type` enum('Urban','Rural','Semi-Urban') DEFAULT NULL,
  `permanent_residence_type` enum('Own House','Family House','Rented','Other') DEFAULT NULL,
  `permanent_post_office` varchar(255) DEFAULT NULL,
  `permanent_postal_code` varchar(20) DEFAULT NULL,
  `highest_education` enum('Primary','JDC/JSC','Dakhil (SSC)','SSC','Alim (HSC)','HSC','Fazil (Bachelor)','Bachelor','Kamil (Masters)','Masters','MBBS','Engineering','MPhil','PhD','Other','JDC / JSC','SSC (General)','Vocational SSC','HSC (General)','Diploma','Bachelor''s','BSc Engineering','BDS','DVM','LLB','BBA','Kamil (Master)','Master''s','MSc Engineering','MBA','LLM','Hifz','Qari','Mufti','No Formal Education','Ebtedayi') DEFAULT NULL,
  `profession` enum('Student','Teacher','Doctor','Nurse','Pharmacist','Engineer','Lawyer','Government Job','Private Job','Business','Banker','Police','Army','Navy','Air Force','Imam','Muazzin','Madrasa Teacher','Islamic Scholar','Freelancer','Agriculture','Expatriate','Unemployed','Other','Agriculture (Farmer)','Government Service','Private Service','Lecturer','Professor','Software Engineer','IT Professional','Dentist','Accountant','Architect','BGB','Ansar','Fire Service','Journalist','Driver','Shopkeeper','Electrician','Plumber','Mechanic','Tailor','Entrepreneur','Overseas Worker','Retired') DEFAULT NULL,
  `occupation_details` varchar(150) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_profession` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_profession` varchar(100) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_relation` enum('Father','Mother','Brother','Uncle','Other') DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `family_type` enum('Nuclear','Joint','Small') DEFAULT NULL,
  `family_status` enum('Lower Middle Class','Middle Class','Upper Middle Class','Upper Class','Affluent') DEFAULT NULL,
  `siblings` tinyint(4) DEFAULT NULL,
  `brothers_count` tinyint(3) unsigned DEFAULT NULL,
  `sisters_count` tinyint(3) unsigned DEFAULT NULL,
  `paternal_uncles_count` tinyint(3) unsigned DEFAULT NULL,
  `paternal_aunts_count` tinyint(3) unsigned DEFAULT NULL,
  `paternal_siblings_married` varchar(255) DEFAULT NULL,
  `family_purdah_environment` text DEFAULT NULL,
  `family_religious_lifestyle` varchar(50) DEFAULT NULL,
  `living_with_family` enum('Yes','No','Immigrant (প্রবাসী)') DEFAULT NULL,
  `division` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `upazila` varchar(100) DEFAULT NULL,
  `address_details` text DEFAULT NULL,
  `smoking_status` enum('Never','Occasionally','Regular','Quit') DEFAULT 'Never',
  `prayer_status` enum('Regular','Sometimes','Rarely','Five Times Daily','Usually Five Times') DEFAULT NULL,
  `beard_status` enum('Yes','No','Not Applicable','Full Beard','Short Beard','No Beard','Not Yet Grown') DEFAULT 'Not Applicable',
  `hijab_status` varchar(100) DEFAULT 'Not Applicable',
  `hijab_details` text DEFAULT NULL,
  `mahram_maintained` tinyint(1) DEFAULT NULL,
  `quran_reading` varchar(50) DEFAULT NULL,
  `fasting_status` varchar(50) DEFAULT NULL,
  `religious_practice` varchar(80) DEFAULT NULL,
  `islamic_knowledge` varchar(50) DEFAULT NULL,
  `halal_lifestyle` varchar(50) DEFAULT NULL,
  `islamic_activities` text DEFAULT NULL,
  `tea_coffee` varchar(50) DEFAULT NULL,
  `diet_preference` varchar(80) DEFAULT NULL,
  `sleep_pattern` varchar(50) DEFAULT NULL,
  `personality_type` varchar(50) DEFAULT NULL,
  `social_nature` varchar(80) DEFAULT NULL,
  `free_time_interests` text DEFAULT NULL,
  `travel_interest` varchar(60) DEFAULT NULL,
  `spending_style` varchar(50) DEFAULT NULL,
  `pets` varchar(80) DEFAULT NULL,
  `nid_number` varchar(30) DEFAULT NULL,
  `hearing_check_passed` tinyint(1) NOT NULL DEFAULT 0,
  `vision_check_passed` tinyint(1) NOT NULL DEFAULT 0,
  `verification_status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `profile_visibility` enum('Public','Hidden') DEFAULT 'Public',
  `photo_visibility` enum('Everyone','Verified Users','Matched Users','Hidden') DEFAULT 'Verified Users',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `father_alive` enum('Yes','No') DEFAULT NULL,
  `mother_alive` enum('Yes','No') DEFAULT NULL,
  `parents_details` text DEFAULT NULL,
  `brothers_details` text DEFAULT NULL,
  `sisters_details` text DEFAULT NULL,
  `paternal_uncles_details` text DEFAULT NULL,
  `paternal_aunts_details` text DEFAULT NULL,
  `biodata_created_by` varchar(20) DEFAULT NULL,
  `guardian_consent` tinyint(1) DEFAULT NULL,
  `favorite_scholars` text DEFAULT NULL,
  `favorite_books` text DEFAULT NULL,
  `religious_knowledge_sources` text DEFAULT NULL,
  `bidah_shrine_stance` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  KEY `fk_profile_user` (`user_id`),
  CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_reports`
--

DROP TABLE IF EXISTS `user_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_reports` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_user_id` int(11) NOT NULL,
  `reported_user_id` int(11) NOT NULL,
  `reason` varchar(80) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('Pending','Reviewed','Resolved','Dismissed') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `idx_user_reports_reporter` (`reporter_user_id`,`created_at`),
  KEY `idx_user_reports_reported` (`reported_user_id`,`status`,`created_at`),
  CONSTRAINT `fk_user_reports_reported` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_trait_answers`
--

DROP TABLE IF EXISTS `user_trait_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_trait_answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`answer_id`),
  UNIQUE KEY `uq_user_question` (`user_id`,`question_id`),
  KEY `fk_trait_question` (`question_id`),
  CONSTRAINT `fk_trait_question` FOREIGN KEY (`question_id`) REFERENCES `trait_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trait_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=386 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `facebook_id` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','User','Authenticator','Manager') DEFAULT 'User',
  `account_status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `name_change_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `mobile_change_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `mobile` (`mobile`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_users_google_id` (`google_id`),
  UNIQUE KEY `uq_users_facebook_id` (`facebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `verification_logs`
--

DROP TABLE IF EXISTS `verification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `authenticator_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `action` enum('Verified','Rejected') NOT NULL,
  `remarks` text DEFAULT NULL,
  `action_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_verification_profile` (`profile_id`),
  CONSTRAINT `fk_verification_profile` FOREIGN KEY (`profile_id`) REFERENCES `user_profiles` (`profile_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 23:20:53
