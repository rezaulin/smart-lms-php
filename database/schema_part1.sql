-- Smart LMS MySQL Schema
-- Converted from Golang GORM models to MySQL
-- Part 1: Core Tables (Schools, Users, Academic)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Schools ──────────────────────────────────────────
DROP TABLE IF EXISTS `schools`;
CREATE TABLE `schools` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `address` TEXT,
  `phone` VARCHAR(20),
  `email` VARCHAR(255),
  `website` VARCHAR(255),
  `npsn` VARCHAR(20),
  `level` VARCHAR(50) COMMENT 'SD, SMP, SMA, SMK',
  `header_logo` VARCHAR(500),
  `header_text` TEXT,
  `header_color` VARCHAR(20) DEFAULT '#1e40af',
  
  -- Document assets
  `logo_url` TEXT,
  `yayasan_name` VARCHAR(255),
  `kabupaten` VARCHAR(100),
  `kode_pos` VARCHAR(10),
  `kepala_name` VARCHAR(255),
  `kepala_nip` VARCHAR(50),
  `kepala_ttd` TEXT,
  `bendahara_name` VARCHAR(255),
  `bendahara_nip` VARCHAR(50),
  `bendahara_ttd` TEXT,
  `stempel_url` TEXT,
  
  -- Google Drive OAuth
  `google_drive_access_token` TEXT,
  `google_drive_refresh_token` TEXT,
  `google_drive_token_expiry` TIMESTAMP NULL,
  `google_drive_folder_id` VARCHAR(255),
  
  -- GPS & Anti-fake location
  `latitude` DOUBLE,
  `longitude` DOUBLE,
  `attendance_radius_m` INT DEFAULT 150,
  `gps_required` BOOLEAN DEFAULT FALSE,
  `gps_max_accuracy_m` INT DEFAULT 100,
  `gps_max_location_age_s` INT DEFAULT 60,
  
  INDEX `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Users ────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `student_id` VARCHAR(6),
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL COMMENT 'admin_pusat, admin_cabang, guru, siswa, orang_tua',
  `phone` VARCHAR(20),
  `avatar` VARCHAR(500),
  `active` BOOLEAN DEFAULT TRUE,
  `school_id` INT UNSIGNED,
  `must_change_password` BOOLEAN DEFAULT FALSE,
  `password_changed_at` TIMESTAMP NULL,
  
  UNIQUE INDEX `idx_email` (`email`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_role` (`role`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Semesters ────────────────────────────────────────
DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Ganjil 2025/2026',
  `year` VARCHAR(20) NOT NULL COMMENT '2025/2026',
  `period` VARCHAR(20) NOT NULL COMMENT 'ganjil/genap',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `active` BOOLEAN DEFAULT FALSE,
  `school_id` INT UNSIGNED NOT NULL,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Subjects ─────────────────────────────────────────
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `level` VARCHAR(10) COMMENT 'X, XI, XII or all',
  `school_id` INT UNSIGNED NOT NULL,
  
  UNIQUE INDEX `idx_code` (`code`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Teachers ─────────────────────────────────────────
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `nip` VARCHAR(30) NOT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  
  UNIQUE INDEX `idx_user_id` (`user_id`),
  UNIQUE INDEX `idx_nip` (`nip`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Teacher-Subject Many-to-Many ─────────────────────
DROP TABLE IF EXISTS `teacher_subjects`;
CREATE TABLE `teacher_subjects` (
  `teacher_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  
  PRIMARY KEY (`teacher_id`, `subject_id`),
  INDEX `idx_subject_id` (`subject_id`),
  
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Classes ──────────────────────────────────────────
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'X IPA 1',
  `level` VARCHAR(10) NOT NULL COMMENT 'X, XI, XII',
  `major` VARCHAR(50) COMMENT 'IPA, IPS, etc',
  `capacity` INT DEFAULT 36,
  `school_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED COMMENT 'wali kelas',
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Students ─────────────────────────────────────────
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `nis` VARCHAR(30),
  `nisn` VARCHAR(20) NOT NULL,
  `class_id` INT UNSIGNED,
  `school_id` INT UNSIGNED NOT NULL,
  `gender` VARCHAR(10) COMMENT 'L/P',
  `birth_date` DATE,
  `address` TEXT,
  
  UNIQUE INDEX `idx_user_id` (`user_id`),
  UNIQUE INDEX `idx_nisn` (`nisn`),
  INDEX `idx_nis` (`nis`),
  INDEX `idx_class_id` (`class_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Parents ──────────────────────────────────────────
DROP TABLE IF EXISTS `parents`;
CREATE TABLE `parents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `relation` VARCHAR(20) COMMENT 'ayah, ibu, wali',
  `school_id` INT UNSIGNED NOT NULL,
  
  UNIQUE INDEX `idx_user_id` (`user_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Parent Access (Login via Code) ───────────────────
DROP TABLE IF EXISTS `parent_access`;
CREATE TABLE `parent_access` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `access_code` VARCHAR(6) NOT NULL,
  `parent_name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20),
  `relation` VARCHAR(20) COMMENT 'ayah, ibu, wali',
  `school_id` INT UNSIGNED NOT NULL,
  
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_access_code` (`access_code`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
