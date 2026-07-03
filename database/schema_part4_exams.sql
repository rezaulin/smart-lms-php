-- Smart LMS MySQL Schema
-- Part 4: Exams & Attempts

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Exams ────────────────────────────────────────────
DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `subject_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `question_bank_id` INT UNSIGNED,
  `school_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `start_time` TIMESTAMP NOT NULL,
  `end_time` TIMESTAMP NOT NULL,
  `duration` INT NOT NULL COMMENT 'minutes',
  `total_questions` INT NOT NULL,
  `shuffle_questions` BOOLEAN DEFAULT FALSE,
  `show_results` BOOLEAN DEFAULT FALSE,
  `lock_tab` BOOLEAN DEFAULT TRUE,
  `max_tab_switches` INT DEFAULT 3,
  `exam_type` VARCHAR(30) COMMENT 'ulangan_harian, uts, uas',
  `status` VARCHAR(20) DEFAULT 'draft' COMMENT 'draft, active, finished',
  
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_class_id` (`class_id`),
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_question_bank_id` (`question_bank_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_semester_id` (`semester_id`),
  INDEX `idx_exam_type` (`exam_type`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Exam Attempts ────────────────────────────────────
DROP TABLE IF EXISTS `exam_attempts`;
CREATE TABLE `exam_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `exam_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NULL,
  `score` DOUBLE,
  `status` VARCHAR(20) DEFAULT 'in_progress' COMMENT 'in_progress, submitted, graded',
  `tab_switches` INT DEFAULT 0,
  `flagged` BOOLEAN DEFAULT FALSE COMMENT 'curang',
  
  INDEX `idx_exam_id` (`exam_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_status` (`status`),
  
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Exam Answers ─────────────────────────────────────
DROP TABLE IF EXISTS `exam_answers`;
CREATE TABLE `exam_answers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `exam_attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `answer` TEXT,
  `is_correct` BOOLEAN,
  `score` DOUBLE,
  `ai_score` DOUBLE COMMENT 'AI essay grading',
  `ai_feedback` TEXT,
  `feedback` TEXT COMMENT 'Teacher manual grading comment',
  
  INDEX `idx_exam_attempt_id` (`exam_attempt_id`),
  INDEX `idx_question_id` (`question_id`),
  
  FOREIGN KEY (`exam_attempt_id`) REFERENCES `exam_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
