-- Smart LMS MySQL Schema
-- Part 5: Raport (Report Cards)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Report Components ────────────────────────────────
DROP TABLE IF EXISTS `report_components`;
CREATE TABLE `report_components` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Ulangan Harian, UTS, UAS, Sikap',
  `weight` DOUBLE NOT NULL COMMENT 'bobot persen, e.g. 30',
  `source_type` VARCHAR(20) NOT NULL COMMENT 'manual | exam',
  `exam_type` VARCHAR(50) COMMENT 'uts, uas, dll',
  `sort_order` INT DEFAULT 0,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Student Scores ───────────────────────────────────
DROP TABLE IF EXISTS `student_scores`;
CREATE TABLE `student_scores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `component_id` INT UNSIGNED NOT NULL,
  `score` DOUBLE COMMENT 'nilai 0-100',
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_semester_id` (`semester_id`),
  INDEX `idx_component_id` (`component_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`component_id`) REFERENCES `report_components`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Raports ──────────────────────────────────────────
DROP TABLE IF EXISTS `raports`;
CREATE TABLE `raports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `notes` TEXT COMMENT 'catatan wali kelas',
  `rank` INT COMMENT 'ranking di kelas',
  
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_semester_id` (`semester_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Raport Items ─────────────────────────────────────
DROP TABLE IF EXISTS `raport_items`;
CREATE TABLE `raport_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `raport_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `score` DOUBLE NOT NULL,
  `grade` VARCHAR(5) COMMENT 'A, B, C, D',
  `kb` TEXT COMMENT 'Kompetensi Dasar',
  `teacher_id` INT UNSIGNED NOT NULL,
  
  INDEX `idx_raport_id` (`raport_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_teacher_id` (`teacher_id`),
  
  FOREIGN KEY (`raport_id`) REFERENCES `raports`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
