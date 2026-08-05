-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 05, 2026 at 03:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smart_matrimony`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT 0.00,
  `booking_status` enum('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_details`
--

CREATE TABLE `booking_details` (
  `detail_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `special_instruction` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `bookmark_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bookmarked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_requests`
--

CREATE TABLE `chat_requests` (
  `chat_request_id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled') DEFAULT 'Pending',
  `chat_active` tinyint(1) DEFAULT 0,
  `responded_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `chat_request_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `status` enum('Active','Closed') DEFAULT 'Active',
  `message_limit` tinyint(4) DEFAULT 10,
  `user1_message_count` tinyint(4) DEFAULT 0,
  `user2_message_count` tinyint(4) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_messages`
--

CREATE TABLE `conversation_messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `division_id`, `name_bn`, `name_en`) VALUES
(1, 1, 'ঢাকা', 'Dhaka'),
(2, 1, 'গাজীপুর', 'Gazipur'),
(3, 1, 'নারায়ণগঞ্জ', 'Narayanganj'),
(4, 1, 'নরসিংদী', 'Narsingdi'),
(5, 1, 'কিশোরগঞ্জ', 'Kishoreganj'),
(6, 1, 'মানিকগঞ্জ', 'Manikganj'),
(7, 1, 'মুন্সীগঞ্জ', 'Munshiganj'),
(8, 1, 'রাজবাড়ী', 'Rajbari'),
(9, 1, 'ফরিদপুর', 'Faridpur'),
(10, 1, 'গোপালগঞ্জ', 'Gopalganj'),
(11, 1, 'মাদারীপুর', 'Madaripur'),
(12, 1, 'শরীয়তপুর', 'Shariatpur'),
(13, 1, 'টাঙ্গাইল', 'Tangail'),
(14, 2, 'চট্টগ্রাম', 'Chattogram'),
(15, 2, 'কুমিল্লা', 'Cumilla'),
(16, 2, 'ব্রাহ্মণবাড়িয়া', 'Brahmanbaria'),
(17, 2, 'চাঁদপুর', 'Chandpur'),
(18, 2, 'ফেনী', 'Feni'),
(19, 2, 'নোয়াখালী', 'Noakhali'),
(20, 2, 'লক্ষ্মীপুর', 'Lakshmipur'),
(21, 2, 'কক্সবাজার', 'Cox\'s Bazar'),
(22, 2, 'খাগড়াছড়ি', 'Khagrachari'),
(23, 2, 'রাঙ্গামাটি', 'Rangamati'),
(24, 2, 'বান্দরবান', 'Bandarban'),
(25, 3, 'রাজশাহী', 'Rajshahi'),
(26, 3, 'নাটোর', 'Natore'),
(27, 3, 'নওগাঁ', 'Naogaon'),
(28, 3, 'চাঁপাইনবাবগঞ্জ', 'Chapainawabganj'),
(29, 3, 'পাবনা', 'Pabna'),
(30, 3, 'সিরাজগঞ্জ', 'Sirajganj'),
(31, 3, 'বগুড়া', 'Bogura'),
(32, 3, 'জয়পুরহাট', 'Joypurhat'),
(33, 4, 'খুলনা', 'Khulna'),
(34, 4, 'বাগেরহাট', 'Bagerhat'),
(35, 4, 'সাতক্ষীরা', 'Satkhira'),
(36, 4, 'যশোর', 'Jashore'),
(37, 4, 'ঝিনাইদহ', 'Jhenaidah'),
(38, 4, 'মাগুরা', 'Magura'),
(39, 4, 'নড়াইল', 'Narail'),
(40, 4, 'কুষ্টিয়া', 'Kushtia'),
(41, 4, 'চুয়াডাঙ্গা', 'Chuadanga'),
(42, 4, 'মেহেরপুর', 'Meherpur'),
(43, 5, 'বরিশাল', 'Barishal'),
(44, 5, 'ভোলা', 'Bhola'),
(45, 5, 'ঝালকাঠি', 'Jhalokathi'),
(46, 5, 'পটুয়াখালী', 'Patuakhali'),
(47, 5, 'পিরোজপুর', 'Pirojpur'),
(48, 5, 'বরগুনা', 'Barguna'),
(49, 6, 'সিলেট', 'Sylhet'),
(50, 6, 'মৌলভীবাজার', 'Moulvibazar'),
(51, 6, 'হবিগঞ্জ', 'Habiganj'),
(52, 6, 'সুনামগঞ্জ', 'Sunamganj'),
(53, 7, 'রংপুর', 'Rangpur'),
(54, 7, 'দিনাজপুর', 'Dinajpur'),
(55, 7, 'কুড়িগ্রাম', 'Kurigram'),
(56, 7, 'গাইবান্ধা', 'Gaibandha'),
(57, 7, 'লালমনিরহাট', 'Lalmonirhat'),
(58, 7, 'নীলফামারী', 'Nilphamari'),
(59, 7, 'পঞ্চগড়', 'Panchagarh'),
(60, 7, 'ঠাকুরগাঁও', 'Thakurgaon'),
(61, 8, 'ময়মনসিংহ', 'Mymensingh'),
(62, 8, 'জামালপুর', 'Jamalpur'),
(63, 8, 'নেত্রকোনা', 'Netrokona'),
(64, 8, 'শেরপুর', 'Sherpur');

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `name_bn`, `name_en`) VALUES
(1, 'ঢাকা', 'Dhaka'),
(2, 'চট্টগ্রাম', 'Chattogram'),
(3, 'রাজশাহী', 'Rajshahi'),
(4, 'খুলনা', 'Khulna'),
(5, 'বরিশাল', 'Barishal'),
(6, 'সিলেট', 'Sylhet'),
(7, 'রংপুর', 'Rangpur'),
(8, 'ময়মনসিংহ', 'Mymensingh');

-- --------------------------------------------------------

--
-- Table structure for table `health_conditions`
--

CREATE TABLE `health_conditions` (
  `condition_id` int(11) NOT NULL,
  `condition_name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_conditions`
--

INSERT INTO `health_conditions` (`condition_id`, `condition_name`, `category`, `description`, `created_at`) VALUES
(1, 'Diabetes', 'Chronic Disease', 'High blood sugar condition', '2026-08-01 10:03:50'),
(2, 'Hypertension', 'Chronic Disease', 'High blood pressure', '2026-08-01 10:03:50'),
(3, 'Heart Disease', 'Cardiovascular', 'Heart-related diseases', '2026-08-01 10:03:50'),
(4, 'Kidney Disease', 'Chronic Disease', 'Kidney-related diseases', '2026-08-01 10:03:50'),
(5, 'Liver Disease', 'Chronic Disease', 'Liver-related diseases', '2026-08-01 10:03:50'),
(6, 'Asthma', 'Respiratory', 'Respiratory disorder', '2026-08-01 10:03:50'),
(7, 'Thyroid Disorder', 'Hormonal', 'Thyroid gland disorder', '2026-08-01 10:03:50'),
(8, 'Epilepsy', 'Neurological', 'Neurological disorder', '2026-08-01 10:03:50'),
(9, 'Cancer', 'Oncology', 'Any type of cancer', '2026-08-01 10:03:50'),
(10, 'Thalassemia', 'Genetic', 'Inherited blood disorder', '2026-08-01 10:03:50'),
(11, 'Sickle Cell Disease', 'Genetic', 'Inherited blood disorder', '2026-08-01 10:03:50'),
(12, 'Hemophilia', 'Genetic', 'Blood clotting disorder', '2026-08-01 10:03:50'),
(13, 'Hepatitis B', 'Infectious', 'Viral liver infection', '2026-08-01 10:03:50'),
(14, 'Hepatitis C', 'Infectious', 'Viral liver infection', '2026-08-01 10:03:50'),
(15, 'Tuberculosis', 'Infectious', 'Bacterial lung infection', '2026-08-01 10:03:50'),
(16, 'HIV/AIDS', 'Infectious', 'Human Immunodeficiency Virus', '2026-08-01 10:03:50'),
(17, 'Autism Spectrum Disorder', 'Mental Health', 'Neurodevelopmental disorder', '2026-08-01 10:03:50'),
(18, 'Depression', 'Mental Health', 'Mental health disorder', '2026-08-01 10:03:50'),
(19, 'Anxiety Disorder', 'Mental Health', 'Anxiety-related disorder', '2026-08-01 10:03:50'),
(20, 'Other', 'Other', 'Other health condition', '2026-08-01 10:03:50');

-- --------------------------------------------------------

--
-- Table structure for table `health_profiles`
--

CREATE TABLE `health_profiles` (
  `health_profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `disability_status` varchar(100) DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `match_id` int(11) NOT NULL,
  `sender_user_id` int(11) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `interest_message` varchar(300) DEFAULT NULL,
  `status` enum('Pending','Accepted','Rejected','Cancelled','Unmatched') DEFAULT 'Pending',
  `relationship_active` tinyint(1) DEFAULT 1,
  `responded_at` datetime DEFAULT NULL,
  `matched_at` datetime DEFAULT NULL,
  `unmatched_at` datetime DEFAULT NULL,
  `unmatched_by` int(11) DEFAULT NULL,
  `closed_reason` enum('Marriage','Rejected','Cancelled','Unmatched','Other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `search_preferences`
--

CREATE TABLE `search_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `preferred_gender` enum('Male','Female') NOT NULL,
  `min_age` tinyint(4) DEFAULT NULL,
  `max_age` tinyint(4) DEFAULT NULL,
  `min_height_cm` decimal(5,2) DEFAULT NULL,
  `max_height_cm` decimal(5,2) DEFAULT NULL,
  `religion` enum('Islam','Hinduism','Christianity','Buddhism','Other') DEFAULT NULL,
  `marital_status` varchar(50) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `search_preferences`
--

INSERT INTO `search_preferences` (`preference_id`, `user_id`, `preferred_gender`, `min_age`, `max_age`, `min_height_cm`, `max_height_cm`, `religion`, `marital_status`, `education`, `profession`, `min_monthly_income`, `division_id`, `district_id`, `upazila_id`, `blood_group`, `accept_smoker`, `accept_alcohol`, `accept_disability`, `accept_chronic_disease`, `additional_preferences`, `created_at`) VALUES
(1, 1, 'Female', 16, 24, 150.00, 165.00, 'Islam', 'Never Married', 'Alim (HSC)', 'Student', NULL, 4, 40, 307, NULL, 1, 1, 1, 1, NULL, '2026-08-03 17:11:05'),
(2, 6, 'Female', 17, 25, 145.00, 165.00, 'Islam', 'Divorced', 'No Formal Education', NULL, 100000.00, 1, 13, 80, NULL, 1, 1, 1, 1, NULL, '2026-08-04 04:35:43'),
(3, 7, 'Female', 16, 25, 140.00, 160.00, 'Islam', 'Never Married', 'SSC (General)', 'Student', NULL, 3, 25, 193, NULL, 1, 1, 1, 1, NULL, '2026-08-04 06:16:58');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `created_at`) VALUES
(1, 'Photography', 'Wedding photography service', '2026-08-01 12:27:58'),
(2, 'Catering', 'Food and catering service', '2026-08-01 12:27:58'),
(3, 'Decoration', 'Wedding decoration service', '2026-08-01 12:27:58'),
(4, 'Car Rental', 'Wedding car rental service', '2026-08-01 12:27:58'),
(5, 'Makeup Artist', 'Bridal makeup service', '2026-08-01 12:27:58'),
(6, 'Wedding Planner', 'Complete wedding planning', '2026-08-01 12:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `service_providers`
--

CREATE TABLE `service_providers` (
  `provider_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `provider_name` varchar(150) NOT NULL,
  `package_details` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trait_questions`
--

CREATE TABLE `trait_questions` (
  `question_id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Both') DEFAULT 'Both',
  `answer_type` enum('Yes/No','Text','Dropdown') DEFAULT 'Text',
  `options` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trait_questions`
--

INSERT INTO `trait_questions` (`question_id`, `question_text`, `gender`, `answer_type`, `options`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'টাখনুর উপর কাপড় পরেন?', 'Male', 'Yes/No', NULL, 1, 1, '2026-08-01 19:58:15'),
(2, 'স্ত্রীর কাজে সাহায্য করতে চান?', 'Male', 'Yes/No', NULL, 2, 1, '2026-08-01 19:58:15'),
(3, 'কোরআন পড়তে পারেন?', 'Both', 'Yes/No', NULL, 3, 1, '2026-08-01 19:58:15'),
(4, 'রাগ হলে কী করেন?', 'Both', 'Text', NULL, 4, 1, '2026-08-01 19:58:15'),
(5, 'পছন্দের খাবার কী?', 'Both', 'Text', NULL, 5, 1, '2026-08-01 19:58:15'),
(6, 'ঘরের খাবার নাকি বাইরের খাবার বেশি পছন্দ?', 'Both', 'Dropdown', 'ঘরের খাবার,বাইরের খাবার,দুটোই', 6, 1, '2026-08-01 19:58:15'),
(7, 'সাজগোজ করতে পছন্দ করেন?', 'Female', 'Yes/No', NULL, 7, 1, '2026-08-01 19:58:15'),
(8, 'রান্না করতে পারেন?', 'Female', 'Yes/No', NULL, 8, 1, '2026-08-01 19:58:15'),
(9, 'বই পড়তে ভালোবাসেন?', 'Female', 'Yes/No', NULL, 9, 1, '2026-08-01 19:58:15'),
(10, 'বেশি সময় ঘরের বাইরে থাকেন?', 'Male', 'Yes/No', NULL, 10, 1, '2026-08-01 19:58:15');

-- --------------------------------------------------------

--
-- Table structure for table `upazilas`
--

CREATE TABLE `upazilas` (
  `id` int(11) NOT NULL,
  `district_id` int(11) NOT NULL,
  `name_bn` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `upazilas`
--

INSERT INTO `upazilas` (`id`, `district_id`, `name_bn`, `name_en`) VALUES
(1, 1, 'ধামরাই', 'Dhamrai'),
(2, 1, 'কেরানীগঞ্জ', 'Keraniganj'),
(3, 1, 'দোহার', 'Dohar'),
(4, 1, 'নবাবগঞ্জ', 'Nawabganj'),
(5, 1, 'সাভার', 'Savar'),
(6, 2, 'গাজীপুর সদর', 'Gazipur Sadar'),
(7, 2, 'কালিয়াকৈর', 'Kaliakair'),
(8, 2, 'কালীগঞ্জ', 'Kaliganj'),
(9, 2, 'কাপাসিয়া', 'Kapasia'),
(10, 2, 'শ্রীপুর', 'Sreepur'),
(11, 3, 'নারায়ণগঞ্জ সদর', 'Narayanganj Sadar'),
(12, 3, 'আড়াইহাজার', 'Araihazar'),
(13, 3, 'বন্দর', 'Bandar'),
(14, 3, 'রূপগঞ্জ', 'Rupganj'),
(15, 3, 'সোনারগাঁ', 'Sonargaon'),
(16, 4, 'নরসিংদী সদর', 'Narsingdi Sadar'),
(17, 4, 'বেলাবো', 'Belabo'),
(18, 4, 'মনোহরদী', 'Monohardi'),
(19, 4, 'পলাশ', 'Palash'),
(20, 4, 'রায়পুরা', 'Raipura'),
(21, 4, 'শিবপুর', 'Shibpur'),
(22, 5, 'কিশোরগঞ্জ সদর', 'Kishoreganj Sadar'),
(23, 5, 'অষ্টগ্রাম', 'Austagram'),
(24, 5, 'বাজিতপুর', 'Bajitpur'),
(25, 5, 'ভৈরব', 'Bhairab'),
(26, 5, 'হোসেনপুর', 'Hossainpur'),
(27, 5, 'ইটনা', 'Itna'),
(28, 5, 'করিমগঞ্জ', 'Karimganj'),
(29, 5, 'কটিয়াদী', 'Katiadi'),
(30, 5, 'কুলিয়ারচর', 'Kuliarchar'),
(31, 5, 'মিঠামইন', 'Mithamain'),
(32, 5, 'নিকলী', 'Nikli'),
(33, 5, 'পাকুন্দিয়া', 'Pakundia'),
(34, 5, 'তাড়াইল', 'Tarail'),
(35, 6, 'মানিকগঞ্জ সদর', 'Manikganj Sadar'),
(36, 6, 'দৌলতপুর', 'Daulatpur'),
(37, 6, 'ঘিওর', 'Ghior'),
(38, 6, 'হরিরামপুর', 'Harirampur'),
(39, 6, 'সাটুরিয়া', 'Saturia'),
(40, 6, 'শিবালয়', 'Shibalaya'),
(41, 6, 'সিংগাইর', 'Singair'),
(42, 7, 'মুন্সীগঞ্জ সদর', 'Munshiganj Sadar'),
(43, 7, 'গজারিয়া', 'Gazaria'),
(44, 7, 'লৌহজং', 'Louhajang'),
(45, 7, 'সিরাজদিখান', 'Sirajdikhan'),
(46, 7, 'শ্রীনগর', 'Sreenagar'),
(47, 7, 'টঙ্গীবাড়ী', 'Tongibari'),
(48, 8, 'রাজবাড়ী সদর', 'Rajbari Sadar'),
(49, 8, 'বালিয়াকান্দি', 'Baliakandi'),
(50, 8, 'গোয়ালন্দ', 'Goalanda'),
(51, 8, 'কালুখালী', 'Kalukhali'),
(52, 8, 'পাংশা', 'Pangsha'),
(53, 9, 'ফরিদপুর সদর', 'Faridpur Sadar'),
(54, 9, 'আলফাডাঙ্গা', 'Alfadanga'),
(55, 9, 'ভাঙ্গা', 'Bhanga'),
(56, 9, 'বোয়ালমারী', 'Boalmari'),
(57, 9, 'চরভদ্রাসন', 'Charbhadrasan'),
(58, 9, 'মধুখালী', 'Madhukhali'),
(59, 9, 'নগরকান্দা', 'Nagarkanda'),
(60, 9, 'সদরপুর', 'Sadarpur'),
(61, 9, 'সালথা', 'Saltha'),
(62, 10, 'গোপালগঞ্জ সদর', 'Gopalganj Sadar'),
(63, 10, 'কাশিয়ানী', 'Kashiani'),
(64, 10, 'কোটালীপাড়া', 'Kotalipara'),
(65, 10, 'মুকসুদপুর', 'Muksudpur'),
(66, 10, 'টুঙ্গিপাড়া', 'Tungipara'),
(67, 11, 'মাদারীপুর সদর', 'Madaripur Sadar'),
(68, 11, 'কালকিনি', 'Kalkini'),
(69, 11, 'রাজৈর', 'Rajoir'),
(70, 11, 'শিবচর', 'Shibchar'),
(71, 12, 'শরীয়তপুর সদর', 'Shariatpur Sadar'),
(72, 12, 'ডামুড্যা', 'Damudya'),
(73, 12, 'গোসাইরহাট', 'Gosairhat'),
(74, 12, 'নড়িয়া', 'Naria'),
(75, 12, 'ভেদরগঞ্জ', 'Bhedarganj'),
(76, 12, 'জাজিরা', 'Jajira'),
(77, 13, 'টাঙ্গাইল সদর', 'Tangail Sadar'),
(78, 13, 'বাসাইল', 'Basail'),
(79, 13, 'ভুঞাপুর', 'Bhuapur'),
(80, 13, 'দেলদুয়ার', 'Delduar'),
(81, 13, 'ধনবাড়ী', 'Dhanbari'),
(82, 13, 'ঘাটাইল', 'Ghatail'),
(83, 13, 'গোপালপুর', 'Gopalpur'),
(84, 13, 'কালিহাতী', 'Kalihati'),
(85, 13, 'মধুপুর', 'Madhupur'),
(86, 13, 'মির্জাপুর', 'Mirzapur'),
(87, 13, 'নাগরপুর', 'Nagarpur'),
(88, 13, 'সখিপুর', 'Sakhipur'),
(89, 14, 'চট্টগ্রাম সদর', 'Chattogram Sadar'),
(90, 14, 'আনোয়ারা', 'Anwara'),
(91, 14, 'বাঁশখালী', 'Banshkhali'),
(92, 14, 'বোয়ালখালী', 'Boalkhali'),
(93, 14, 'চন্দনাইশ', 'Chandanaish'),
(94, 14, 'ফটিকছড়ি', 'Fatikchhari'),
(95, 14, 'হাটহাজারী', 'Hathazari'),
(96, 14, 'লোহাগাড়া', 'Lohagara'),
(97, 14, 'মিরসরাই', 'Mirsharai'),
(98, 14, 'পটিয়া', 'Patiya'),
(99, 14, 'রাঙ্গুনিয়া', 'Rangunia'),
(100, 14, 'রাউজান', 'Raozan'),
(101, 14, 'সন্দ্বীপ', 'Sandwip'),
(102, 14, 'সাতকানিয়া', 'Satkania'),
(103, 14, 'সীতাকুণ্ড', 'Sitakunda'),
(104, 15, 'কুমিল্লা সদর', 'Cumilla Sadar'),
(105, 15, 'আদর্শ সদর', 'Adarsha Sadar'),
(106, 15, 'বরুড়া', 'Barura'),
(107, 15, 'ব্রাহ্মণপাড়া', 'Brahmanpara'),
(108, 15, 'বুড়িচং', 'Burichang'),
(109, 15, 'চান্দিনা', 'Chandina'),
(110, 15, 'চৌদ্দগ্রাম', 'Chauddagram'),
(111, 15, 'দাউদকান্দি', 'Daudkandi'),
(112, 15, 'দেবিদ্বার', 'Debidwar'),
(113, 15, 'হোমনা', 'Homna'),
(114, 15, 'লাকসাম', 'Laksam'),
(115, 15, 'লালমাই', 'Lalmai'),
(116, 15, 'মনোহরগঞ্জ', 'Monoharganj'),
(117, 15, 'মেঘনা', 'Meghna'),
(118, 15, 'মুরাদনগর', 'Muradnagar'),
(119, 15, 'নাঙ্গলকোট', 'Nangalkot'),
(120, 15, 'তিতাস', 'Titas'),
(121, 16, 'ব্রাহ্মণবাড়িয়া সদর', 'Brahmanbaria Sadar'),
(122, 16, 'আখাউড়া', 'Akhaura'),
(123, 16, 'আশুগঞ্জ', 'Ashuganj'),
(124, 16, 'বাঞ্ছারামপুর', 'Bancharampur'),
(125, 16, 'বিজয়নগর', 'Bijoynagar'),
(126, 16, 'কসবা', 'Kasba'),
(127, 16, 'নাসিরনগর', 'Nasirnagar'),
(128, 16, 'নবীনগর', 'Nabinagar'),
(129, 16, 'সরাইল', 'Sarail'),
(130, 17, 'চাঁদপুর সদর', 'Chandpur Sadar'),
(131, 17, 'ফরিদগঞ্জ', 'Faridganj'),
(132, 17, 'হাইমচর', 'Haimchar'),
(133, 17, 'হাজীগঞ্জ', 'Hajiganj'),
(134, 17, 'কচুয়া', 'Kachua'),
(135, 17, 'মতলব দক্ষিণ', 'Matlab Dakshin'),
(136, 17, 'মতলব উত্তর', 'Matlab Uttar'),
(137, 17, 'শাহরাস্তি', 'Shahrasti'),
(138, 18, 'ফেনী সদর', 'Feni Sadar'),
(139, 18, 'ছাগলনাইয়া', 'Chhagalnaiya'),
(140, 18, 'দাগনভূঞা', 'Daganbhuiyan'),
(141, 18, 'ফুলগাজী', 'Fulgazi'),
(142, 18, 'পরশুরাম', 'Parshuram'),
(143, 18, 'সোনাগাজী', 'Sonagazi'),
(144, 20, 'লক্ষ্মীপুর সদর', 'Lakshmipur Sadar'),
(145, 20, 'কমলনগর', 'Kamalnagar'),
(146, 20, 'রামগঞ্জ', 'Ramganj'),
(147, 20, 'রামগতি', 'Ramgati'),
(148, 20, 'রায়পুর', 'Raipur'),
(149, 19, 'নোয়াখালী সদর', 'Noakhali Sadar'),
(150, 19, 'বেগমগঞ্জ', 'Begumganj'),
(151, 19, 'চাটখিল', 'Chatkhil'),
(152, 19, 'কবিরহাট', 'Kabirhat'),
(153, 19, 'কোম্পানীগঞ্জ', 'Companiganj'),
(154, 19, 'হাতিয়া', 'Hatiya'),
(155, 19, 'সেনবাগ', 'Senbagh'),
(156, 19, 'সোনাইমুড়ী', 'Sonaimuri'),
(157, 19, 'সুবর্ণচর', 'Subarnachar'),
(158, 21, 'কক্সবাজার সদর', 'Cox\'s Bazar Sadar'),
(159, 21, 'চকরিয়া', 'Chakaria'),
(160, 21, 'কুতুবদিয়া', 'Kutubdia'),
(161, 21, 'মহেশখালী', 'Maheshkhali'),
(162, 21, 'পেকুয়া', 'Pekua'),
(163, 21, 'রামু', 'Ramu'),
(164, 21, 'টেকনাফ', 'Teknaf'),
(165, 21, 'উখিয়া', 'Ukhia'),
(166, 22, 'খাগড়াছড়ি সদর', 'Khagrachari Sadar'),
(167, 22, 'দিঘীনালা', 'Dighinala'),
(168, 22, 'গুইমারা', 'Guimara'),
(169, 22, 'লক্ষ্মীছড়ি', 'Lakshmichhari'),
(170, 22, 'মাটিরাঙ্গা', 'Matiranga'),
(171, 22, 'মানিকছড়ি', 'Manikchhari'),
(172, 22, 'মহালছড়ি', 'Mahalchhari'),
(173, 22, 'পানছড়ি', 'Panchhari'),
(174, 22, 'রামগড়', 'Ramgarh'),
(175, 23, 'রাঙ্গামাটি সদর', 'Rangamati Sadar'),
(176, 23, 'বাঘাইছড়ি', 'Baghaichhari'),
(177, 23, 'বরকল', 'Barkal'),
(178, 23, 'বিলাইছড়ি', 'Belaichhari'),
(179, 23, 'জুরাছড়ি', 'Juraichhari'),
(180, 23, 'কাউখালী', 'Kawkhali'),
(181, 23, 'কাপ্তাই', 'Kaptai'),
(182, 23, 'লংগদু', 'Langadu'),
(183, 23, 'নানিয়ারচর', 'Naniarchar'),
(184, 23, 'রাজস্থলী', 'Rajasthali'),
(185, 24, 'বান্দরবান সদর', 'Bandarban Sadar'),
(186, 24, 'আলীকদম', 'Alikadam'),
(187, 24, 'লামা', 'Lama'),
(188, 24, 'নাইক্ষ্যংছড়ি', 'Naikhongchhari'),
(189, 24, 'রুমা', 'Ruma'),
(190, 24, 'রোয়াংছড়ি', 'Rowangchhari'),
(191, 24, 'থানচি', 'Thanchi'),
(192, 25, 'রাজশাহী সদর', 'Rajshahi Sadar'),
(193, 25, 'বাগমারা', 'Bagmara'),
(194, 25, 'বাঘা', 'Bagha'),
(195, 25, 'চারঘাট', 'Charghat'),
(196, 25, 'দুর্গাপুর', 'Durgapur'),
(197, 25, 'গোদাগাড়ী', 'Godagari'),
(198, 25, 'মোহনপুর', 'Mohanpur'),
(199, 25, 'পবা', 'Paba'),
(200, 25, 'পুঠিয়া', 'Puthia'),
(201, 25, 'তানোর', 'Tanore'),
(202, 26, 'নাটোর সদর', 'Natore Sadar'),
(203, 26, 'বাগাতিপাড়া', 'Bagatipara'),
(204, 26, 'বড়াইগ্রাম', 'Baraigram'),
(205, 26, 'গুরুদাসপুর', 'Gurudaspur'),
(206, 26, 'লালপুর', 'Lalpur'),
(207, 26, 'নলডাঙ্গা', 'Naldanga'),
(208, 26, 'সিংড়া', 'Singra'),
(209, 27, 'নওগাঁ সদর', 'Naogaon Sadar'),
(210, 27, 'আত্রাই', 'Atrai'),
(211, 27, 'বদলগাছী', 'Badalgachhi'),
(212, 27, 'ধামইরহাট', 'Dhamoirhat'),
(213, 27, 'মান্দা', 'Manda'),
(214, 27, 'মহাদেবপুর', 'Mahadebpur'),
(215, 27, 'নিয়ামতপুর', 'Niamatpur'),
(216, 27, 'পত্নীতলা', 'Patnitala'),
(217, 27, 'পোরশা', 'Porsha'),
(218, 27, 'রাণীনগর', 'Raninagar'),
(219, 27, 'সাপাহার', 'Sapahar'),
(220, 28, 'চাঁপাইনবাবগঞ্জ সদর', 'Chapainawabganj Sadar'),
(221, 28, 'ভোলাহাট', 'Bholahat'),
(222, 28, 'গোমস্তাপুর', 'Gomastapur'),
(223, 28, 'নাচোল', 'Nachole'),
(224, 28, 'শিবগঞ্জ', 'Shibganj'),
(225, 29, 'পাবনা সদর', 'Pabna Sadar'),
(226, 29, 'আটঘরিয়া', 'Atgharia'),
(227, 29, 'বেড়া', 'Bera'),
(228, 29, 'ভাঙ্গুড়া', 'Bhangura'),
(229, 29, 'চাটমোহর', 'Chatmohar'),
(230, 29, 'ফরিদপুর', 'Faridpur'),
(231, 29, 'ঈশ্বরদী', 'Ishwardi'),
(232, 29, 'সাঁথিয়া', 'Santhia'),
(233, 29, 'সুজানগর', 'Sujanagar'),
(234, 30, 'সিরাজগঞ্জ সদর', 'Sirajganj Sadar'),
(235, 30, 'বেলকুচি', 'Belkuchi'),
(236, 30, 'চৌহালী', 'Chauhali'),
(237, 30, 'কামারখন্দ', 'Kamarkhanda'),
(238, 30, 'কাজীপুর', 'Kazipur'),
(239, 30, 'রায়গঞ্জ', 'Raiganj'),
(240, 30, 'শাহজাদপুর', 'Shahjadpur'),
(241, 30, 'তাড়াশ', 'Tarash'),
(242, 30, 'উল্লাপাড়া', 'Ullapara'),
(243, 31, 'বগুড়া সদর', 'Bogura Sadar'),
(244, 31, 'আদমদিঘী', 'Adamdighi'),
(245, 31, 'ধুনট', 'Dhunat'),
(246, 31, 'দুপচাঁচিয়া', 'Dupchanchia'),
(247, 31, 'গাবতলী', 'Gabtali'),
(248, 31, 'কাহালু', 'Kahaloo'),
(249, 31, 'নন্দীগ্রাম', 'Nandigram'),
(250, 31, 'সারিয়াকান্দি', 'Sariakandi'),
(251, 31, 'শাজাহানপুর', 'Shajahanpur'),
(252, 31, 'শেরপুর', 'Sherpur'),
(253, 31, 'শিবগঞ্জ', 'Shibganj'),
(254, 31, 'সোনাতলা', 'Sonatala'),
(255, 32, 'জয়পুরহাট সদর', 'Joypurhat Sadar'),
(256, 32, 'আক্কেলপুর', 'Akkelpur'),
(257, 32, 'কালাই', 'Kalai'),
(258, 32, 'ক্ষেতলাল', 'Khetlal'),
(259, 32, 'পাঁচবিবি', 'Panchbibi'),
(260, 33, 'খুলনা সদর', 'Khulna Sadar'),
(261, 33, 'বটিয়াঘাটা', 'Batiaghata'),
(262, 33, 'দাকোপ', 'Dakop'),
(263, 33, 'ডুমুরিয়া', 'Dumuria'),
(264, 33, 'দিঘলিয়া', 'Dighalia'),
(265, 33, 'কয়রা', 'Koyra'),
(266, 33, 'পাইকগাছা', 'Paikgachha'),
(267, 33, 'ফুলতলা', 'Phultala'),
(268, 33, 'রূপসা', 'Rupsa'),
(269, 33, 'তেরখাদা', 'Terokhada'),
(270, 34, 'বাগেরহাট সদর', 'Bagerhat Sadar'),
(271, 34, 'চিতলমারী', 'Chitalmari'),
(272, 34, 'ফকিরহাট', 'Fakirhat'),
(273, 34, 'কচুয়া', 'Kachua'),
(274, 34, 'মোল্লাহাট', 'Mollahat'),
(275, 34, 'মোংলা', 'Mongla'),
(276, 34, 'মোড়েলগঞ্জ', 'Morrelganj'),
(277, 34, 'রামপাল', 'Rampal'),
(278, 34, 'শরণখোলা', 'Sarankhola'),
(279, 35, 'সাতক্ষীরা সদর', 'Satkhira Sadar'),
(280, 35, 'আশাশুনি', 'Assasuni'),
(281, 35, 'দেবহাটা', 'Debhata'),
(282, 35, 'কালীগঞ্জ', 'Kaliganj'),
(283, 35, 'কলারোয়া', 'Kalaroa'),
(284, 35, 'শ্যামনগর', 'Shyamnagar'),
(285, 35, 'তালা', 'Tala'),
(286, 36, 'যশোর সদর', 'Jashore Sadar'),
(287, 36, 'অভয়নগর', 'Abhaynagar'),
(288, 36, 'বাঘারপাড়া', 'Bagherpara'),
(289, 36, 'চৌগাছা', 'Chaugachha'),
(290, 36, 'ঝিকরগাছা', 'Jhikargachha'),
(291, 36, 'কেশবপুর', 'Keshabpur'),
(292, 36, 'মনিরামপুর', 'Monirampur'),
(293, 36, 'শার্শা', 'Sharsha'),
(294, 37, 'ঝিনাইদহ সদর', 'Jhenaidah Sadar'),
(295, 37, 'হরিণাকুণ্ডু', 'Harinakunda'),
(296, 37, 'কালীগঞ্জ', 'Kaliganj'),
(297, 37, 'কোটচাঁদপুর', 'Kotchandpur'),
(298, 37, 'মহেশপুর', 'Maheshpur'),
(299, 37, 'শৈলকুপা', 'Shailkupa'),
(300, 38, 'মাগুরা সদর', 'Magura Sadar'),
(301, 38, 'মহম্মদপুর', 'Mohammadpur'),
(302, 38, 'শালিখা', 'Shalikha'),
(303, 38, 'শ্রীপুর', 'Sreepur'),
(304, 39, 'নড়াইল সদর', 'Narail Sadar'),
(305, 39, 'কালিয়া', 'Kalia'),
(306, 39, 'লোহাগড়া', 'Lohagara'),
(307, 40, 'কুষ্টিয়া সদর', 'Kushtia Sadar'),
(308, 40, 'ভেড়ামারা', 'Bheramara'),
(309, 40, 'দৌলতপুর', 'Daulatpur'),
(310, 40, 'খোকসা', 'Khoksa'),
(311, 40, 'কুমারখালী', 'Kumarkhali'),
(312, 40, 'মিরপুর', 'Mirpur'),
(313, 41, 'চুয়াডাঙ্গা সদর', 'Chuadanga Sadar'),
(314, 41, 'আলমডাঙ্গা', 'Alamdanga'),
(315, 41, 'দামুড়হুদা', 'Damurhuda'),
(316, 41, 'জীবননগর', 'Jibannagar'),
(317, 42, 'মেহেরপুর সদর', 'Meherpur Sadar'),
(318, 42, 'গাংনী', 'Gangni'),
(319, 42, 'মুজিবনগর', 'Mujibnagar'),
(320, 43, 'বরিশাল সদর', 'Barishal Sadar'),
(321, 43, 'আগৈলঝাড়া', 'Agailjhara'),
(322, 43, 'বাবুগঞ্জ', 'Babuganj'),
(323, 43, 'বাকেরগঞ্জ', 'Bakerganj'),
(324, 43, 'বানারীপাড়া', 'Banaripara'),
(325, 43, 'গৌরনদী', 'Gournadi'),
(326, 43, 'হিজলা', 'Hizla'),
(327, 43, 'মেহেন্দিগঞ্জ', 'Mehendiganj'),
(328, 43, 'মুলাদী', 'Muladi'),
(329, 43, 'উজিরপুর', 'Wazirpur'),
(330, 44, 'ভোলা সদর', 'Bhola Sadar'),
(331, 44, 'বোরহানউদ্দিন', 'Borhanuddin'),
(332, 44, 'চরফ্যাশন', 'Char Fasson'),
(333, 44, 'দৌলতখান', 'Daulatkhan'),
(334, 44, 'লালমোহন', 'Lalmohan'),
(335, 44, 'মনপুরা', 'Monpura'),
(336, 44, 'তজুমদ্দিন', 'Tazumuddin'),
(337, 45, 'ঝালকাঠি সদর', 'Jhalokathi Sadar'),
(338, 45, 'কাঁঠালিয়া', 'Kathalia'),
(339, 45, 'নলছিটি', 'Nalchity'),
(340, 45, 'রাজাপুর', 'Rajapur'),
(341, 46, 'পটুয়াখালী সদর', 'Patuakhali Sadar'),
(342, 46, 'বাউফল', 'Bauphal'),
(343, 46, 'দশমিনা', 'Dashmina'),
(344, 46, 'দুমকি', 'Dumki'),
(345, 46, 'গলাচিপা', 'Galachipa'),
(346, 46, 'কলাপাড়া', 'Kalapara'),
(347, 46, 'মির্জাগঞ্জ', 'Mirzaganj'),
(348, 46, 'রাঙ্গাবালী', 'Rangabali'),
(349, 47, 'পিরোজপুর সদর', 'Pirojpur Sadar'),
(350, 47, 'ভান্ডারিয়া', 'Bhandaria'),
(351, 47, 'কাউখালী', 'Kawkhali'),
(352, 47, 'মঠবাড়িয়া', 'Mathbaria'),
(353, 47, 'নাজিরপুর', 'Nazirpur'),
(354, 47, 'নেছারাবাদ (স্বরূপকাঠি)', 'Nesarabad (Swarupkathi)'),
(355, 47, 'ইন্দুরকানী', 'Indurkani'),
(356, 48, 'বরগুনা সদর', 'Barguna Sadar'),
(357, 48, 'আমতলী', 'Amtali'),
(358, 48, 'বামনা', 'Bamna'),
(359, 48, 'বেতাগী', 'Betagi'),
(360, 48, 'পাথরঘাটা', 'Patharghata'),
(361, 48, 'তালতলী', 'Taltali'),
(362, 49, 'সিলেট সদর', 'Sylhet Sadar'),
(363, 49, 'বালাগঞ্জ', 'Balaganj'),
(364, 49, 'বিশ্বনাথ', 'Bishwanath'),
(365, 49, 'কোম্পানীগঞ্জ', 'Companiganj'),
(366, 49, 'দক্ষিণ সুরমা', 'Dakshin Surma'),
(367, 49, 'ফেঞ্চুগঞ্জ', 'Fenchuganj'),
(368, 49, 'গোলাপগঞ্জ', 'Golapganj'),
(369, 49, 'গোয়াইনঘাট', 'Gowainghat'),
(370, 49, 'জৈন্তাপুর', 'Jaintiapur'),
(371, 49, 'কানাইঘাট', 'Kanaighat'),
(372, 49, 'ওসমানীনগর', 'Osmaninagar'),
(373, 49, 'জকিগঞ্জ', 'Zakiganj'),
(374, 49, 'বিয়ানীবাজার', 'Beanibazar'),
(375, 50, 'মৌলভীবাজার সদর', 'Moulvibazar Sadar'),
(376, 50, 'বড়লেখা', 'Barlekha'),
(377, 50, 'জুড়ী', 'Juri'),
(378, 50, 'কমলগঞ্জ', 'Kamalganj'),
(379, 50, 'কুলাউড়া', 'Kulaura'),
(380, 50, 'রাজনগর', 'Rajnagar'),
(381, 50, 'শ্রীমঙ্গল', 'Sreemangal'),
(382, 51, 'হবিগঞ্জ সদর', 'Habiganj Sadar'),
(383, 51, 'আজমিরীগঞ্জ', 'Ajmiriganj'),
(384, 51, 'বানিয়াচং', 'Baniachang'),
(385, 51, 'বাহুবল', 'Bahubal'),
(386, 51, 'চুনারুঘাট', 'Chunarughat'),
(387, 51, 'লাখাই', 'Lakhai'),
(388, 51, 'মাধবপুর', 'Madhabpur'),
(389, 51, 'নবীগঞ্জ', 'Nabiganj'),
(390, 51, 'শায়েস্তাগঞ্জ', 'Shayestaganj'),
(391, 52, 'সুনামগঞ্জ সদর', 'Sunamganj Sadar'),
(392, 52, 'বিশ্বম্ভরপুর', 'Biswambarpur'),
(393, 52, 'ছাতক', 'Chhatak'),
(394, 52, 'দিরাই', 'Dirai'),
(395, 52, 'ধর্মপাশা', 'Dharmapasha'),
(396, 52, 'দোয়ারাবাজার', 'Dowarabazar'),
(397, 52, 'জগন্নাথপুর', 'Jagannathpur'),
(398, 52, 'জামালগঞ্জ', 'Jamalganj'),
(399, 52, 'মধ্যনগর', 'Madhyanagar'),
(400, 52, 'শাল্লা', 'Shalla'),
(401, 52, 'তাহিরপুর', 'Tahirpur'),
(402, 53, 'রংপুর সদর', 'Rangpur Sadar'),
(403, 53, 'বদরগঞ্জ', 'Badarganj'),
(404, 53, 'গঙ্গাচড়া', 'Gangachara'),
(405, 53, 'কাউনিয়া', 'Kaunia'),
(406, 53, 'মিঠাপুকুর', 'Mithapukur'),
(407, 53, 'পীরগঞ্জ', 'Pirganj'),
(408, 53, 'পীরগাছা', 'Pirgachha'),
(409, 53, 'তারাগঞ্জ', 'Taraganj'),
(410, 54, 'দিনাজপুর সদর', 'Dinajpur Sadar'),
(411, 54, 'বিরামপুর', 'Birampur'),
(412, 54, 'বীরগঞ্জ', 'Birganj'),
(413, 54, 'বোচাগঞ্জ', 'Bochaganj'),
(414, 54, 'চিরিরবন্দর', 'Chirirbandar'),
(415, 54, 'ফুলবাড়ী', 'Phulbari'),
(416, 54, 'ঘোড়াঘাট', 'Ghoraghat'),
(417, 54, 'হাকিমপুর', 'Hakimpur'),
(418, 54, 'কাহারোল', 'Kaharole'),
(419, 54, 'খানসামা', 'Khansama'),
(420, 54, 'নবাবগঞ্জ', 'Nawabganj'),
(421, 54, 'পার্বতীপুর', 'Parbatipur'),
(422, 55, 'কুড়িগ্রাম সদর', 'Kurigram Sadar'),
(423, 55, 'ভূরুঙ্গামারী', 'Bhurungamari'),
(424, 55, 'চর রাজিবপুর', 'Char Rajibpur'),
(425, 55, 'চিলমারী', 'Chilmari'),
(426, 55, 'ফুলবাড়ী', 'Phulbari'),
(427, 55, 'নাগেশ্বরী', 'Nageshwari'),
(428, 55, 'রাজারহাট', 'Rajarhat'),
(429, 55, 'রৌমারী', 'Roumari'),
(430, 55, 'উলিপুর', 'Ulipur'),
(431, 56, 'গাইবান্ধা সদর', 'Gaibandha Sadar'),
(432, 56, 'ফুলছড়ি', 'Phulchhari'),
(433, 56, 'গোবিন্দগঞ্জ', 'Gobindaganj'),
(434, 56, 'পলাশবাড়ী', 'Palashbari'),
(435, 56, 'সাদুল্লাপুর', 'Sadullapur'),
(436, 56, 'সাঘাটা', 'Saghata'),
(437, 56, 'সুন্দরগঞ্জ', 'Sundarganj'),
(438, 57, 'লালমনিরহাট সদর', 'Lalmonirhat Sadar'),
(439, 57, 'আদিতমারী', 'Aditmari'),
(440, 57, 'হাতীবান্ধা', 'Hatibandha'),
(441, 57, 'কালীগঞ্জ', 'Kaliganj'),
(442, 57, 'পাটগ্রাম', 'Patgram'),
(443, 58, 'নীলফামারী সদর', 'Nilphamari Sadar'),
(444, 58, 'ডোমার', 'Domar'),
(445, 58, 'ডিমলা', 'Dimla'),
(446, 58, 'জলঢাকা', 'Jaldhaka'),
(447, 58, 'কিশোরগঞ্জ', 'Kishoreganj'),
(448, 58, 'সৈয়দপুর', 'Saidpur'),
(449, 59, 'পঞ্চগড় সদর', 'Panchagarh Sadar'),
(450, 59, 'আটোয়ারী', 'Atwari'),
(451, 59, 'বোদা', 'Boda'),
(452, 59, 'দেবীগঞ্জ', 'Debiganj'),
(453, 59, 'তেঁতুলিয়া', 'Tetulia'),
(454, 60, 'ঠাকুরগাঁও সদর', 'Thakurgaon Sadar'),
(455, 60, 'বালিয়াডাঙ্গী', 'Baliadangi'),
(456, 60, 'হরিপুর', 'Haripur'),
(457, 60, 'পীরগঞ্জ', 'Pirganj'),
(458, 60, 'রাণীশংকৈল', 'Ranisankail'),
(459, 61, 'ময়মনসিংহ সদর', 'Mymensingh Sadar'),
(460, 61, 'ভালুকা', 'Bhaluka'),
(461, 61, 'ধোবাউড়া', 'Dhobaura'),
(462, 61, 'ফুলবাড়ীয়া', 'Phulpur'),
(463, 61, 'গফরগাঁও', 'Gafargaon'),
(464, 61, 'গৌরীপুর', 'Gauripur'),
(465, 61, 'হালুয়াঘাট', 'Haluaghat'),
(466, 61, 'ঈশ্বরগঞ্জ', 'Ishwarganj'),
(467, 61, 'মুক্তাগাছা', 'Muktagacha'),
(468, 61, 'নান্দাইল', 'Nandail'),
(469, 61, 'ফুলপুর', 'Phulpur'),
(470, 61, 'তারাকান্দা', 'Tarakanda'),
(471, 61, 'ত্রিশাল', 'Trishal'),
(472, 62, 'জামালপুর সদর', 'Jamalpur Sadar'),
(473, 62, 'বকশীগঞ্জ', 'Bakshiganj'),
(474, 62, 'দেওয়ানগঞ্জ', 'Dewanganj'),
(475, 62, 'ইসলামপুর', 'Islampur'),
(476, 62, 'মাদারগঞ্জ', 'Madarganj'),
(477, 62, 'মেলান্দহ', 'Melandaha'),
(478, 62, 'সরিষাবাড়ী', 'Sarishabari'),
(479, 63, 'নেত্রকোনা সদর', 'Netrokona Sadar'),
(480, 63, 'আটপাড়া', 'Atpara'),
(481, 63, 'বারহাট্টা', 'Barhatta'),
(482, 63, 'দুর্গাপুর', 'Durgapur'),
(483, 63, 'খালিয়াজুড়ি', 'Khaliajuri'),
(484, 63, 'কলমাকান্দা', 'Kalmakanda'),
(485, 63, 'কেন্দুয়া', 'Kendua'),
(486, 63, 'মদন', 'Madan'),
(487, 63, 'মোহনগঞ্জ', 'Mohanganj'),
(488, 63, 'পূর্বধলা', 'Purbadhala'),
(489, 64, 'শেরপুর সদর', 'Sherpur Sadar'),
(490, 64, 'ঝিনাইগাতী', 'Jhenaigati'),
(491, 64, 'নকলা', 'Nakla'),
(492, 64, 'নালিতাবাড়ী', 'Nalitabari'),
(493, 64, 'শ্রীবরদী', 'Sreebardi');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','User','Authenticator','Manager') DEFAULT 'User',
  `account_status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `gender`, `mobile`, `email`, `password`, `role`, `account_status`, `created_at`) VALUES
(1, 'Jibon', 'Raihan', 'Male', '01843118076', 'mdraihanjourdar@gmail.com', '$2y$10$QZ1w2kFWkb5x7zt5QEK5UOwDos/XItjp5IbO0v/VT4/rF7wIcxwsa', 'User', 'Active', '2026-08-02 10:03:41'),
(2, 'Tanvir', 'Rahman', 'Male', '01521754540', 'mdraihan123@gmail.com', '$2y$10$oVBLkdkpQr7fvUp8PfYRvuGgLPLRfEVpui89CVYOL6X71pLMilXT.', 'User', 'Active', '2026-08-02 10:11:57'),
(3, 'Kanon', 'Ansary', 'Male', '01572905563', 'kanonansary@gmail.com', '$2y$10$8nmXGPU3wAS3emzUKbtvBOJa9S.bw505KCs4SY98s7IdDYowg53.K', 'User', 'Active', '2026-08-02 10:43:06'),
(4, 'Tanvir', 'Rahman', 'Male', '01828385074', 'tanvirrahman@gmail.com', '$2y$10$yGUFidS1YCzHz5RgSCWvFu.Jv2bTtFri.6R3FRZ2jb4iJIJuhjPwa', 'User', 'Active', '2026-08-02 16:21:00'),
(5, 'Washim', 'Akram', 'Male', '01575887225', 'wasim05@gamil.com', '$2y$10$0GCIndQYG35XSz.DpG6WTeNlTwFsoO7La6.S4RJ2hkAy6AuVad6ii', 'User', 'Active', '2026-08-03 10:06:24'),
(6, 'Kanon', 'Ansary', 'Male', '01572905534', 'kanon@gmail.com', '$2y$10$h5U5hCbsQmtWtPy.Qpwhu.IBf8d6TgMOqq1imgPygJVg925BQBQom', 'User', 'Active', '2026-08-04 04:26:57'),
(7, 'Foysal Ahmed', 'Dinar', 'Male', '01862221867', 'dinar@gmail.com', '$2y$10$S14kEx15H/coMZMErjwAkOOrk6zD7PfGqYmHVmeawV/kyI.MCRQRy', 'User', 'Active', '2026-08-04 04:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `user_health_conditions`
--

CREATE TABLE `user_health_conditions` (
  `user_health_condition_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `condition_id` int(11) NOT NULL,
  `status` enum('Controlled','Uncontrolled','Recovered','Carrier','Positive','Negative','Unknown') DEFAULT 'Unknown',
  `severity` enum('Mild','Moderate','Severe') DEFAULT NULL,
  `diagnosed_date` date DEFAULT NULL,
  `treatment_status` enum('None','Ongoing','Completed') DEFAULT 'None',
  `disclosure_required` tinyint(1) DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `religion` enum('Islam','Hinduism','Christianity','Buddhism','Other') NOT NULL,
  `madhhab` enum('Ahle Hadith','Hanafi','Shafi','Maliki','Hanbali','Salafi','Not Applicable') DEFAULT 'Not Applicable',
  `marital_status` enum('Single','Divorced','Widowed') NOT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `height_cm` smallint(6) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `complexion` enum('Fair','Medium','Wheatish','Dark') DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Bangladesh',
  `division_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `upazila_id` int(11) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `post_office` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `highest_education` enum('Primary','JDC/JSC','Dakhil (SSC)','SSC','Alim (HSC)','HSC','Fazil (Bachelor)','Bachelor','Kamil (Masters)','Masters','MBBS','Engineering','MPhil','PhD','Other') DEFAULT NULL,
  `profession` enum('Student','Teacher','Doctor','Nurse','Pharmacist','Engineer','Lawyer','Government Job','Private Job','Business','Banker','Police','Army','Navy','Air Force','Imam','Muazzin','Madrasa Teacher','Islamic Scholar','Freelancer','Agriculture','Expatriate','Unemployed','Other') DEFAULT NULL,
  `occupation_details` varchar(150) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `father_profession` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `mother_profession` varchar(100) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_relation` enum('Father','Mother','Brother','Uncle','Other') DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `family_type` enum('Nuclear','Joint') DEFAULT NULL,
  `family_status` enum('Lower Middle Class','Middle Class','Upper Middle Class','Upper Class') DEFAULT NULL,
  `siblings` tinyint(4) DEFAULT NULL,
  `division` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `upazila` varchar(100) DEFAULT NULL,
  `address_details` text DEFAULT NULL,
  `smoking_status` enum('Never','Occasionally','Regular','Quit') DEFAULT 'Never',
  `prayer_status` enum('Regular','Sometimes','Rarely') DEFAULT NULL,
  `beard_status` enum('Yes','No','Not Applicable') DEFAULT 'Not Applicable',
  `hijab_status` enum('Yes','No','Not Applicable') DEFAULT 'Not Applicable',
  `hijab_details` text DEFAULT NULL,
  `mahram_maintained` tinyint(1) DEFAULT NULL,
  `nid_number` varchar(30) DEFAULT NULL,
  `verification_status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `profile_visibility` enum('Public','Hidden') DEFAULT 'Public',
  `photo_visibility` enum('Everyone','Verified Users','Matched Users','Hidden') DEFAULT 'Verified Users',
  `blur_photo` enum('Yes','No') DEFAULT 'No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `date_of_birth`, `religion`, `madhhab`, `marital_status`, `bio`, `photo`, `height_cm`, `weight_kg`, `complexion`, `country`, `division_id`, `district_id`, `upazila_id`, `area`, `post_office`, `postal_code`, `highest_education`, `profession`, `occupation_details`, `monthly_income`, `father_name`, `father_profession`, `mother_name`, `mother_profession`, `guardian_name`, `guardian_relation`, `guardian_contact`, `family_type`, `family_status`, `siblings`, `division`, `district`, `upazila`, `address_details`, `smoking_status`, `prayer_status`, `beard_status`, `hijab_status`, `hijab_details`, `mahram_maintained`, `nid_number`, `verification_status`, `profile_visibility`, `photo_visibility`, `blur_photo`, `created_at`, `updated_at`) VALUES
(1, 3, 'Kanon', 'Ansary', 'Male', '2004-06-08', 'Islam', 'Hanafi', '', '', NULL, 170, 70.00, 'Medium', 'Bangladesh', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Md M Ansary', 'Agriculture (Farmer)', 'Ms M khatun', 'Housewife', 'Md M Ansary', 'Father', '01512121212', 'Joint', 'Middle Class', 3, NULL, NULL, NULL, NULL, 'Never', 'Regular', 'Yes', 'Not Applicable', '', 1, NULL, 'Pending', 'Public', 'Verified Users', 'No', '2026-08-02 16:06:03', '2026-08-02 16:08:10'),
(2, 4, 'Tanvir', 'Rahman', 'Male', '2002-11-17', 'Islam', 'Salafi', '', 'I am a student', 'USR_4_9b56ff0d6a32bcfa.jpg', 170, 80.00, 'Wheatish', 'Bangladesh', 4, 40, 307, 'বলরামপুর, মনোহরদিয়া', 'সাধুগঞ্জ', '৭২১০', NULL, NULL, NULL, NULL, 'Md M Ansary', 'Agriculture (Farmer)', 'Ms M khatun', 'Housewife', 'Md M Ansary', 'Father', '01512121212', 'Joint', 'Middle Class', 3, NULL, NULL, NULL, NULL, 'Never', 'Regular', '', 'Not Applicable', '', 1, NULL, 'Pending', 'Public', 'Everyone', 'Yes', '2026-08-02 16:33:11', '2026-08-02 20:20:01'),
(3, 1, 'Jibon', 'Raihan', 'Male', '2002-09-15', 'Islam', 'Salafi', '', 'I&#039;m a student', 'USR_1_d90a2cc50d90f3aa.webp', 163, 62.00, 'Medium', 'Bangladesh', 4, 40, 307, 'বলরামপুর, মনোহরদিয়া', 'সাধুগঞ্জ', '৭২১০', '', NULL, NULL, NULL, 'Md Titon Joardar', 'Agriculture (Farmer)', 'Mst Reneka Khatun', 'Housewife', 'Md Titon Joardar', 'Father', '01790865068', 'Nuclear', 'Lower Middle Class', 0, NULL, NULL, NULL, NULL, 'Never', 'Regular', '', 'Not Applicable', '', 0, NULL, 'Pending', 'Public', 'Matched Users', 'No', '2026-08-03 13:30:51', '2026-08-04 20:59:38'),
(4, 6, 'Kanon', 'Ansary', 'Male', '2003-11-03', '', 'Ahle Hadith', '', 'I&#039;m a student', 'USR_6_0294d64cd1261257.webp', 180, 76.00, 'Medium', 'Bangladesh', 1, 13, 80, 'Hosenpur', 'Hosenpur', '2026', '', NULL, NULL, NULL, 'Rahim Mia', 'Agriculture (Farmer)', 'Mst Amena Khatun', 'Housewife', 'Rahim Mia', 'Father', '01569696969', 'Nuclear', 'Middle Class', 1, NULL, NULL, NULL, NULL, 'Regular', 'Sometimes', '', 'Not Applicable', '', 0, NULL, 'Pending', 'Public', 'Matched Users', 'No', '2026-08-04 04:29:13', '2026-08-04 04:33:59'),
(5, 7, 'Foysal Ahmed', 'Dinar', 'Male', '2004-01-01', '', 'Hanafi', '', '', 'USR_7_6582eb0018e76006.webp', 183, 78.00, 'Medium', 'Bangladesh', 3, 25, 193, 'মচমইল', 'মচমইল', '৬২৫০', '', NULL, NULL, NULL, 'Amzad Hosen', 'Teacher', 'Shahinur Khatun', 'Teacher', 'Amzad Hosen', 'Father', '01325358760', 'Joint', 'Upper Middle Class', 0, NULL, NULL, NULL, NULL, 'Never', 'Regular', '', 'Not Applicable', '', 0, NULL, 'Pending', 'Public', 'Matched Users', 'No', '2026-08-04 04:42:21', '2026-08-04 06:15:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_trait_answers`
--

CREATE TABLE `user_trait_answers` (
  `answer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer` varchar(255) NOT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `verification_logs`
--

CREATE TABLE `verification_logs` (
  `log_id` int(11) NOT NULL,
  `authenticator_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `action` enum('Verified','Rejected') NOT NULL,
  `remarks` text DEFAULT NULL,
  `action_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `fk_booking_user` (`user_id`),
  ADD KEY `fk_booking_manager` (`manager_id`);

--
-- Indexes for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_booking_detail` (`booking_id`),
  ADD KEY `fk_booking_provider` (`provider_id`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`bookmark_id`),
  ADD UNIQUE KEY `uq_bookmark` (`user_id`,`bookmarked_user_id`),
  ADD KEY `fk_bookmarked_user` (`bookmarked_user_id`);

--
-- Indexes for table `chat_requests`
--
ALTER TABLE `chat_requests`
  ADD PRIMARY KEY (`chat_request_id`),
  ADD KEY `fk_chat_match` (`match_id`),
  ADD KEY `fk_chat_sender` (`sender_user_id`),
  ADD KEY `fk_chat_receiver` (`receiver_user_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `fk_conversation_chat_request` (`chat_request_id`),
  ADD KEY `fk_conversation_user1` (`user1_id`),
  ADD KEY `fk_conversation_user2` (`user2_id`);

--
-- Indexes for table `conversation_messages`
--
ALTER TABLE `conversation_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_message_conversation` (`conversation_id`),
  ADD KEY `fk_message_sender` (`sender_user_id`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `division_id` (`division_id`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `health_conditions`
--
ALTER TABLE `health_conditions`
  ADD PRIMARY KEY (`condition_id`),
  ADD UNIQUE KEY `condition_name` (`condition_name`);

--
-- Indexes for table `health_profiles`
--
ALTER TABLE `health_profiles`
  ADD PRIMARY KEY (`health_profile_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`match_id`),
  ADD KEY `fk_match_sender` (`sender_user_id`),
  ADD KEY `fk_match_receiver` (`receiver_user_id`),
  ADD KEY `fk_match_unmatched_by` (`unmatched_by`);

--
-- Indexes for table `search_preferences`
--
ALTER TABLE `search_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD PRIMARY KEY (`provider_id`),
  ADD KEY `fk_provider_service` (`service_id`);

--
-- Indexes for table `trait_questions`
--
ALTER TABLE `trait_questions`
  ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `upazilas`
--
ALTER TABLE `upazilas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `district_id` (`district_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `mobile` (`mobile`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_health_conditions`
--
ALTER TABLE `user_health_conditions`
  ADD PRIMARY KEY (`user_health_condition_id`),
  ADD UNIQUE KEY `uq_user_condition` (`user_id`,`condition_id`),
  ADD KEY `fk_uhc_condition` (`condition_id`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD KEY `fk_profile_user` (`user_id`);

--
-- Indexes for table `user_trait_answers`
--
ALTER TABLE `user_trait_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `uq_user_question` (`user_id`,`question_id`),
  ADD KEY `fk_trait_question` (`question_id`);

--
-- Indexes for table `verification_logs`
--
ALTER TABLE `verification_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_verification_profile` (`profile_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_details`
--
ALTER TABLE `booking_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `bookmark_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_requests`
--
ALTER TABLE `chat_requests`
  MODIFY `chat_request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversation_messages`
--
ALTER TABLE `conversation_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `health_conditions`
--
ALTER TABLE `health_conditions`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `health_profiles`
--
ALTER TABLE `health_profiles`
  MODIFY `health_profile_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `match_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_preferences`
--
ALTER TABLE `search_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_providers`
--
ALTER TABLE `service_providers`
  MODIFY `provider_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trait_questions`
--
ALTER TABLE `trait_questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `upazilas`
--
ALTER TABLE `upazilas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=494;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_health_conditions`
--
ALTER TABLE `user_health_conditions`
  MODIFY `user_health_condition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_trait_answers`
--
ALTER TABLE `user_trait_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `verification_logs`
--
ALTER TABLE `verification_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_booking_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD CONSTRAINT `fk_booking_detail` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`booking_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_booking_provider` FOREIGN KEY (`provider_id`) REFERENCES `service_providers` (`provider_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `fk_bookmark_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bookmarked_user` FOREIGN KEY (`bookmarked_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chat_requests`
--
ALTER TABLE `chat_requests`
  ADD CONSTRAINT `fk_chat_match` FOREIGN KEY (`match_id`) REFERENCES `matches` (`match_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `fk_conversation_chat_request` FOREIGN KEY (`chat_request_id`) REFERENCES `chat_requests` (`chat_request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversation_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_conversation_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `conversation_messages`
--
ALTER TABLE `conversation_messages`
  ADD CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `health_profiles`
--
ALTER TABLE `health_profiles`
  ADD CONSTRAINT `fk_health_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `fk_match_receiver` FOREIGN KEY (`receiver_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_match_unmatched_by` FOREIGN KEY (`unmatched_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `search_preferences`
--
ALTER TABLE `search_preferences`
  ADD CONSTRAINT `fk_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service_providers`
--
ALTER TABLE `service_providers`
  ADD CONSTRAINT `fk_provider_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_health_conditions`
--
ALTER TABLE `user_health_conditions`
  ADD CONSTRAINT `fk_uhc_condition` FOREIGN KEY (`condition_id`) REFERENCES `health_conditions` (`condition_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uhc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_trait_answers`
--
ALTER TABLE `user_trait_answers`
  ADD CONSTRAINT `fk_trait_question` FOREIGN KEY (`question_id`) REFERENCES `trait_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_trait_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `verification_logs`
--
ALTER TABLE `verification_logs`
  ADD CONSTRAINT `fk_verification_profile` FOREIGN KEY (`profile_id`) REFERENCES `user_profiles` (`profile_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
