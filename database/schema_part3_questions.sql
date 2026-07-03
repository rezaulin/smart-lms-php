-- Smart LMS MySQL Schema
-- Part 3: Question Banks & Exams

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Topics (BAB/KD) ──────────────────────────────────
DROP TABLE IF EXISTS `topics`;
CREATE TABLE `topics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `level` VARCHAR(10) COMMENT 'X, XI, XII',
  `code` VARCHAR(50) COMMENT 'BAB 1, KD 3.1',
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Questions (Pool) ─────────────────────────────────
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `level` VARCHAR(10) COMMENT 'X, XI, XII',
  `author_id` INT UNSIGNED COMMENT 'teacher_id pembuat',
  `number` INT DEFAULT 0,
  `type` VARCHAR(20) NOT NULL COMMENT 'pilihan_ganda, essay, true_false, matching, fill_blank, multi_answer, numeric, ordering',
  `content` TEXT NOT NULL,
  `options` JSON COMMENT '[{"key":"A","text":"..."}]',
  `answer` TEXT COMMENT 'jawaban benar',
  `accepted_answers` TEXT COMMENT 'isian: alternatif jawaban (newline-separated)',
  `keywords` TEXT COMMENT 'essay: kata kunci untuk auto-correct',
  `explanation` TEXT,
  `difficulty` VARCHAR(20) DEFAULT 'sedang' COMMENT 'mudah, sedang, sulit',
  `points` INT DEFAULT 10,
  `visibility` VARCHAR(20) DEFAULT 'private' COMMENT 'private | school | public',
  `current_version` INT DEFAULT 1,
  
  -- Item analysis
  `discrimination` DOUBLE DEFAULT 0,
  `difficulty_idx` DOUBLE DEFAULT 0,
  `correct_count` INT DEFAULT 0,
  `total_attempts` INT DEFAULT 0,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_level` (`level`),
  INDEX `idx_author_id` (`author_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Question-Topic Many-to-Many ──────────────────────
DROP TABLE IF EXISTS `question_topics`;
CREATE TABLE `question_topics` (
  `question_id` INT UNSIGNED NOT NULL,
  `topic_id` INT UNSIGNED NOT NULL,
  
  PRIMARY KEY (`question_id`, `topic_id`),
  INDEX `idx_topic_id` (`topic_id`),
  
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Question Versions (History) ──────────────────────
DROP TABLE IF EXISTS `question_versions`;
CREATE TABLE `question_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `question_id` INT UNSIGNED NOT NULL,
  `version` INT NOT NULL,
  `content` TEXT NOT NULL,
  `options` JSON,
  `answer` TEXT,
  `changed_by` INT UNSIGNED COMMENT 'user_id',
  `change_note` TEXT,
  
  INDEX `idx_question_id` (`question_id`),
  INDEX `idx_version` (`version`),
  
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Question Banks ───────────────────────────────────
DROP TABLE IF EXISTS `question_banks`;
CREATE TABLE `question_banks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `subject_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED,
  `school_id` INT UNSIGNED NOT NULL,
  `level` VARCHAR(10) COMMENT 'X, XI, XII',
  `visibility` VARCHAR(20) DEFAULT 'private' COMMENT 'private | school | public',
  
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Question Bank Items (M2M dengan urutan) ──────────
DROP TABLE IF EXISTS `question_bank_items`;
CREATE TABLE `question_bank_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `question_bank_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `sort_order` INT DEFAULT 0,
  
  UNIQUE INDEX `idx_bank_question` (`question_bank_id`, `question_id`),
  INDEX `idx_question_id` (`question_id`),
  
  FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Import Reports (DOCX import tracking) ────────────
DROP TABLE IF EXISTS `import_reports`;
CREATE TABLE `import_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `question_bank_id` INT UNSIGNED NOT NULL,
  `imported_by` INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255),
  `total_imported` INT DEFAULT 0,
  `errors` TEXT COMMENT 'JSON array of errors',
  
  INDEX `idx_question_bank_id` (`question_bank_id`),
  
  FOREIGN KEY (`question_bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
