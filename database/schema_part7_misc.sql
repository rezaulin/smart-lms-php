-- Smart LMS MySQL Schema
-- Part 7: Notifications, AI, Calendar, Logs

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Notifications ────────────────────────────────────
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) COMMENT 'info, success, warning, error',
  `category` VARCHAR(50) COMMENT 'exam, attendance, billing, system',
  `read_at` TIMESTAMP NULL,
  `data` JSON COMMENT 'extra metadata',
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_read_at` (`read_at`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Notification Queue (WA/Telegram) ─────────────────
DROP TABLE IF EXISTS `notification_queue`;
CREATE TABLE `notification_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `school_id` INT UNSIGNED NOT NULL,
  `target_phone` VARCHAR(20) NOT NULL,
  `provider` VARCHAR(50) NOT NULL COMMENT 'fonnte, wablas, telegram, baileys',
  `message` TEXT NOT NULL,
  `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, sent, failed',
  `sent_at` TIMESTAMP NULL,
  `error` TEXT,
  `retry_count` INT DEFAULT 0,
  `metadata` JSON,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Assistant Logs (Command history) ─────────────────
DROP TABLE IF EXISTS `assistant_logs`;
CREATE TABLE `assistant_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `school_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `command` TEXT NOT NULL,
  `intent` VARCHAR(100),
  `entities` JSON,
  `executed` BOOLEAN DEFAULT FALSE,
  `result` TEXT,
  `error` TEXT,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_created_at` (`created_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── AI Configs ───────────────────────────────────────
DROP TABLE IF EXISTS `ai_configs`;
CREATE TABLE `ai_configs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'OpenAI, Gemini, xAI',
  `auth_type` VARCHAR(20) DEFAULT 'apikey' COMMENT 'apikey | oauth',
  `base_url` VARCHAR(500),
  `api_key` VARCHAR(500),
  `session_token` VARCHAR(5000),
  `model` VARCHAR(100) NOT NULL,
  `active` BOOLEAN DEFAULT FALSE,
  `is_global` BOOLEAN DEFAULT FALSE COMMENT 'true = superadmin',
  `school_id` INT UNSIGNED,
  
  INDEX `idx_is_global` (`is_global`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── AI Quotas (Monthly limit) ────────────────────────
DROP TABLE IF EXISTS `ai_quotas`;
CREATE TABLE `ai_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `monthly_limit` INT DEFAULT 100,
  `used_this_month` INT DEFAULT 0,
  `reset_at` TIMESTAMP NOT NULL,
  
  UNIQUE INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── AI Jobs (Async tasks) ────────────────────────────
DROP TABLE IF EXISTS `ai_jobs`;
CREATE TABLE `ai_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `kind` VARCHAR(40) COMMENT 'generate_questions, grade_essay, rpp, prota',
  `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, running, done, failed',
  `progress` INT DEFAULT 0 COMMENT '0..100',
  `message` TEXT,
  `input` TEXT COMMENT 'JSON request',
  `result` TEXT COMMENT 'JSON result',
  `error` TEXT,
  `started_at` TIMESTAMP NULL,
  `finished_at` TIMESTAMP NULL,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_kind` (`kind`),
  INDEX `idx_status` (`status`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Calendar Events ──────────────────────────────────
DROP TABLE IF EXISTS `calendar_events`;
CREATE TABLE `calendar_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `type` VARCHAR(50) COMMENT 'ujian, libur, kegiatan, pembelajaran',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `color` VARCHAR(20) DEFAULT '#3b82f6',
  `school_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_semester_id` (`semester_id`),
  INDEX `idx_start_date` (`start_date`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Password Reset Logs ──────────────────────────────
DROP TABLE IF EXISTS `password_reset_logs`;
CREATE TABLE `password_reset_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `school_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reset_by` INT UNSIGNED NOT NULL COMMENT 'admin user_id',
  `old_password_hash` VARCHAR(255),
  `reason` VARCHAR(255),
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_reset_by` (`reset_by`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reset_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Bank Accounts (for payment) ──────────────────────
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(100) NOT NULL COMMENT 'BCA, BRI, Mandiri',
  `account_number` VARCHAR(50) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `branch` VARCHAR(100),
  `is_active` BOOLEAN DEFAULT TRUE,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
