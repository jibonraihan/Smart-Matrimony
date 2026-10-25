-- Smart Matrimony: Admin-managed service catalog
-- BACK UP the database before running this migration. Run once in phpMyAdmin.
-- This migration preserves all existing services, packages, bookings and package codes.

ALTER TABLE `services`
  ADD COLUMN `service_name_bn` varchar(100) NOT NULL DEFAULT '' AFTER `service_name`,
  ADD COLUMN `description_bn` text DEFAULT NULL AFTER `description`,
  ADD COLUMN `icon_class` varchar(60) NOT NULL DEFAULT 'fa-heart' AFTER `description_bn`,
  ADD COLUMN `service_prefix` varchar(5) NOT NULL DEFAULT '' AFTER `icon_class`,
  ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `service_prefix`;

UPDATE `services` SET
  `service_name_bn` = CASE `service_id`
    WHEN 1 THEN 'ফটোগ্রাফি' WHEN 2 THEN 'খাবার পরিবেশন' WHEN 3 THEN 'সাজসজ্জা'
    WHEN 4 THEN 'গাড়ি ভাড়া' WHEN 5 THEN 'মেকআপ শিল্পী' WHEN 6 THEN 'বিয়ে পরিকল্পনাকারী'
    WHEN 7 THEN 'কনভেনশন সেন্টার' WHEN 8 THEN 'রিসোর্ট / লন' WHEN 9 THEN 'মেহেদি শিল্পী'
    WHEN 10 THEN 'বিয়ের ফুল' WHEN 11 THEN 'বাসরঘর সাজসজ্জা' WHEN 12 THEN 'বিয়ের পোশাক'
    WHEN 13 THEN 'বরের পোশাক' WHEN 14 THEN 'কনের সাজসজ্জার সামগ্রী' WHEN 15 THEN 'সাউন্ড ও আলোকসজ্জা'
    WHEN 16 THEN 'নিমন্ত্রণপত্র ও মুদ্রণ' ELSE `service_name_bn` END,
  `description_bn` = CASE `service_id`
    WHEN 1 THEN 'বিয়ের ছবি ও স্মৃতি ধারণের সেবা'
    WHEN 2 THEN 'বিয়ের খাবার প্রস্তুত ও আপ্যায়ন সেবা'
    WHEN 3 THEN 'বিয়ের মঞ্চ, হল ও অনুষ্ঠানস্থল সাজানোর সেবা'
    WHEN 4 THEN 'বিয়ের অনুষ্ঠানের জন্য গাড়ি ভাড়ার সেবা'
    WHEN 5 THEN 'কনের মেকআপ ও সাজসজ্জার সেবা'
    WHEN 6 THEN 'বিয়ের আয়োজন ও সম্পূর্ণ পরিকল্পনার সেবা'
    WHEN 7 THEN 'বিয়ের হল ও কনভেনশন সেন্টার ভাড়ার ব্যবস্থা'
    WHEN 8 THEN 'খোলা লন ও রিসোর্টে বিয়ের আয়োজন'
    WHEN 9 THEN 'কনে ও পরিবারের মেহেদি সাজের সেবা'
    WHEN 10 THEN 'বিয়ের তোড়া ও ফুলের সাজসজ্জা'
    WHEN 11 THEN 'বাসরঘর ও কক্ষ সাজানোর সেবা'
    WHEN 12 THEN 'কনের বিয়ের পোশাক ও সাজের সামগ্রী'
    WHEN 13 THEN 'বরের বিয়ের পোশাক ও সাজের সামগ্রী'
    WHEN 14 THEN 'গয়না, জুতা ও কনের সাজের উপকরণ'
    WHEN 15 THEN 'বিয়ের সাউন্ড সিস্টেম ও আলোকসজ্জা'
    WHEN 16 THEN 'বিয়ের নিমন্ত্রণপত্র তৈরি ও মুদ্রণ'
    ELSE COALESCE(`description_bn`, '') END,
  `icon_class` = CASE `service_id`
    WHEN 1 THEN 'fa-camera-retro' WHEN 2 THEN 'fa-utensils' WHEN 3 THEN 'fa-wand-magic-sparkles'
    WHEN 4 THEN 'fa-car' WHEN 5 THEN 'fa-brush' WHEN 6 THEN 'fa-clipboard-list'
    WHEN 7 THEN 'fa-building-columns' WHEN 8 THEN 'fa-tree' WHEN 9 THEN 'fa-hand-sparkles'
    WHEN 10 THEN 'fa-seedling' WHEN 11 THEN 'fa-bed' WHEN 12 THEN 'fa-shirt'
    WHEN 13 THEN 'fa-user-tie' WHEN 14 THEN 'fa-gem' WHEN 15 THEN 'fa-lightbulb'
    WHEN 16 THEN 'fa-envelope-open-text' ELSE 'fa-heart' END,
  `service_prefix` = CASE `service_id`
    WHEN 1 THEN 'PG' WHEN 2 THEN 'CT' WHEN 3 THEN 'DR' WHEN 4 THEN 'CR'
    WHEN 5 THEN 'MUA' WHEN 6 THEN 'WP' WHEN 7 THEN 'CC' WHEN 8 THEN 'RL'
    WHEN 9 THEN 'MHA' WHEN 10 THEN 'WF' WHEN 11 THEN 'BGD' WHEN 12 THEN 'WD'
    WHEN 13 THEN 'GD' WHEN 14 THEN 'BA' WHEN 15 THEN 'SL' WHEN 16 THEN 'IP'
    ELSE CONCAT('S', `service_id`) END;

ALTER TABLE `services` ADD UNIQUE KEY `uq_services_service_prefix` (`service_prefix`);

-- Seed sequence counters from existing package codes so future numbers continue safely.
INSERT INTO `service_package_sequences` (`service_id`, `last_number`)
SELECT s.service_id,
       COALESCE(MAX(CAST(SUBSTRING_INDEX(sp.package_code, '-', -1) AS UNSIGNED)), 0)
FROM services s
LEFT JOIN service_providers sp ON sp.service_id = s.service_id
GROUP BY s.service_id
ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number));

DROP TRIGGER IF EXISTS `sm_service_providers_package_code_bi`;
DROP TRIGGER IF EXISTS `sm_service_providers_package_code_bu`;

DELIMITER $$
CREATE TRIGGER `sm_service_providers_package_code_bi`
BEFORE INSERT ON `service_providers` FOR EACH ROW
BEGIN
  DECLARE next_number INT UNSIGNED;
  DECLARE prefix_code VARCHAR(5);
  IF NEW.`package_code` IS NULL OR TRIM(NEW.`package_code`) = '' THEN
    SELECT `service_prefix` INTO prefix_code FROM `services` WHERE `service_id` = NEW.`service_id` LIMIT 1;
    IF prefix_code IS NULL OR TRIM(prefix_code) = '' THEN SET prefix_code = 'PKG'; END IF;
    INSERT INTO `service_package_sequences` (`service_id`, `last_number`)
    VALUES (NEW.`service_id`, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE `last_number` = LAST_INSERT_ID(`last_number` + 1);
    SET next_number = LAST_INSERT_ID();
    SET NEW.`package_code` = CONCAT(prefix_code, '-', LPAD(next_number, 3, '0'));
  END IF;
END$$

CREATE TRIGGER `sm_service_providers_package_code_bu`
BEFORE UPDATE ON `service_providers` FOR EACH ROW
BEGIN
  DECLARE next_number INT UNSIGNED;
  DECLARE prefix_code VARCHAR(5);
  IF NOT (NEW.`service_id` <=> OLD.`service_id`) THEN
    SELECT `service_prefix` INTO prefix_code FROM `services` WHERE `service_id` = NEW.`service_id` LIMIT 1;
    IF prefix_code IS NULL OR TRIM(prefix_code) = '' THEN SET prefix_code = 'PKG'; END IF;
    INSERT INTO `service_package_sequences` (`service_id`, `last_number`)
    VALUES (NEW.`service_id`, LAST_INSERT_ID(1))
    ON DUPLICATE KEY UPDATE `last_number` = LAST_INSERT_ID(`last_number` + 1);
    SET next_number = LAST_INSERT_ID();
    SET NEW.`package_code` = CONCAT(prefix_code, '-', LPAD(next_number, 3, '0'));
  ELSE
    SET NEW.`package_code` = OLD.`package_code`;
  END IF;
END$$
DELIMITER ;

-- No service or package is deleted by this migration. Existing bookings stay intact.
