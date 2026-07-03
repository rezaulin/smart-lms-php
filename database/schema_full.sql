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
-- Smart LMS MySQL Schema
-- Part 2: Attendance (Schedules, Sessions, Presence)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Schedules (Jadwal Pelajaran) ─────────────────────
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `subject_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT NOT NULL COMMENT '1=Senin, 7=Minggu',
  `start_time` VARCHAR(5) NOT NULL COMMENT '07:00',
  `end_time` VARCHAR(5) NOT NULL COMMENT '08:30',
  `room` VARCHAR(50),
  `kind` VARCHAR(20) DEFAULT 'mapel' COMMENT 'mapel | harian (absen pagi)',
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_semester_id` (`semester_id`),
  INDEX `idx_class_id` (`class_id`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_day_of_week` (`day_of_week`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Attendance Sessions ──────────────────────────────
DROP TABLE IF EXISTS `attendance_sessions`;
CREATE TABLE `attendance_sessions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `schedule_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `opened_by` INT UNSIGNED NOT NULL COMMENT 'user_id guru',
  `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL,
  `status` VARCHAR(20) DEFAULT 'open' COMMENT 'open | closed',
  `qr_token` VARCHAR(100) NOT NULL,
  `qr_expires` TIMESTAMP NULL,
  `method` VARCHAR(20) DEFAULT 'manual' COMMENT 'manual | qr | mixed',
  `note` TEXT,
  
  UNIQUE INDEX `idx_session_schedule_date` (`schedule_id`, `date`),
  UNIQUE INDEX `idx_qr_token` (`qr_token`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_date` (`date`),
  INDEX `idx_opened_by` (`opened_by`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Presence (Kehadiran per siswa) ───────────────────
DROP TABLE IF EXISTS `presences`;
CREATE TABLE `presences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `session_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL COMMENT 'hadir | izin | sakit | alfa | terlambat',
  `marked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `marked_by` VARCHAR(20) COMMENT 'self | teacher',
  `marked_by_id` INT UNSIGNED COMMENT 'user_id yang nandain',
  `note` VARCHAR(255),
  `ip_address` VARCHAR(45),
  `device` VARCHAR(255),
  `photo_url` VARCHAR(500),
  `late_min` INT DEFAULT 0 COMMENT 'menit keterlambatan',
  
  UNIQUE INDEX `idx_presence_session_student` (`session_id`, `student_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_marked_at` (`marked_at`),
  
  FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Teacher Location Logs (GPS tracking) ─────────────
DROP TABLE IF EXISTS `teacher_location_logs`;
CREATE TABLE `teacher_location_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `teacher_id` INT UNSIGNED NOT NULL,
  `session_id` INT UNSIGNED NOT NULL,
  `latitude` DOUBLE NOT NULL,
  `longitude` DOUBLE NOT NULL,
  `accuracy` DOUBLE COMMENT 'GPS accuracy in meters',
  `distance_from_school` DOUBLE COMMENT 'meters',
  `within_radius` BOOLEAN DEFAULT FALSE,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  
  INDEX `idx_teacher_id` (`teacher_id`),
  INDEX `idx_session_id` (`session_id`),
  INDEX `idx_created_at` (`created_at`),
  
  FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Holidays (Hari Libur) ────────────────────────────
DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'Hari Raya Idul Fitri, Libur Semester',
  `date` DATE NOT NULL,
  `type` VARCHAR(50) COMMENT 'nasional | sekolah | khusus',
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_date` (`date`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
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
-- Smart LMS MySQL Schema
-- Part 6: Billing/SPP (Payment System)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Jenis Tagihan (Master) ───────────────────────────
DROP TABLE IF EXISTS `jenis_tagihan`;
CREATE TABLE `jenis_tagihan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `nama` VARCHAR(255) NOT NULL COMMENT 'SPP, Seragam, Study Tour',
  `kode` VARCHAR(50) COMMENT 'SPP, SRG, ST',
  `deskripsi` TEXT,
  `nominal_default` DOUBLE NOT NULL COMMENT 'default nominal',
  `periode` VARCHAR(50) NOT NULL COMMENT 'bulanan | sekali | tahunan',
  `apply_potongan` BOOLEAN DEFAULT FALSE COMMENT 'auto apply potongan siswa',
  `aktif` BOOLEAN DEFAULT TRUE,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tagihan (Per Student) ────────────────────────────
DROP TABLE IF EXISTS `tagihan`;
CREATE TABLE `tagihan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `jenis_tagihan_id` INT UNSIGNED NOT NULL,
  `periode` VARCHAR(50) NOT NULL COMMENT '2026-05 untuk SPP, 2026-Ganjil untuk semester',
  `nominal` DOUBLE NOT NULL COMMENT 'nominal asli',
  `keringanan` DOUBLE DEFAULT 0 COMMENT 'diskon rupiah',
  `keringanan_note` VARCHAR(255) COMMENT 'Yatim, KIP, Saudara kandung',
  `terbayar` DOUBLE DEFAULT 0 COMMENT 'sum dari pembayaran (cache)',
  `jatuh_tempo` DATE,
  `status` VARCHAR(20) DEFAULT 'belum_bayar' COMMENT 'belum_bayar | sebagian | lunas | batal',
  `catatan` TEXT,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_jenis_tagihan_id` (`jenis_tagihan_id`),
  INDEX `idx_periode` (`periode`),
  INDEX `idx_status` (`status`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jenis_tagihan_id`) REFERENCES `jenis_tagihan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Pembayaran (Payment History) ─────────────────────
DROP TABLE IF EXISTS `pembayaran`;
CREATE TABLE `pembayaran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `tagihan_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL COMMENT 'denormalized',
  `nominal_bayar` DOUBLE NOT NULL,
  `tanggal_bayar` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metode` VARCHAR(50) DEFAULT 'cash' COMMENT 'cash | transfer | qris | va',
  `bukti_url` VARCHAR(500) COMMENT 'upload bukti',
  `petugas_id` INT UNSIGNED COMMENT 'user ID admin',
  `petugas_nama` VARCHAR(255) COMMENT 'cache nama',
  `nomor_kuitansi` VARCHAR(100),
  `catatan` TEXT,
  `void` BOOLEAN DEFAULT FALSE,
  `void_reason` TEXT,
  `void_at` TIMESTAMP NULL,
  `void_by` INT UNSIGNED,
  
  UNIQUE INDEX `idx_nomor_kuitansi` (`nomor_kuitansi`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_tagihan_id` (`tagihan_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_tanggal_bayar` (`tanggal_bayar`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tagihan_id`) REFERENCES `tagihan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Potongan (Discount Master) ───────────────────────
DROP TABLE IF EXISTS `potongan`;
CREATE TABLE `potongan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `nama` VARCHAR(255) NOT NULL COMMENT 'Yatim, Anak Guru, Saudara Kandung',
  `kode` VARCHAR(50) COMMENT 'YTM, ANG, SDR',
  `deskripsi` TEXT,
  `nominal` DOUBLE NOT NULL COMMENT 'potongan Rp per tagihan',
  `aktif` BOOLEAN DEFAULT TRUE,
  
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Student-Potongan Many-to-Many ────────────────────
DROP TABLE IF EXISTS `student_potongan`;
CREATE TABLE `student_potongan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `school_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `potongan_id` INT UNSIGNED NOT NULL,
  `catatan` TEXT COMMENT 'SK Yatim no. 123, Anak guru pak Joko',
  
  UNIQUE INDEX `idx_student_potongan` (`student_id`, `potongan_id`),
  INDEX `idx_school_id` (`school_id`),
  INDEX `idx_potongan_id` (`potongan_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  
  FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`potongan_id`) REFERENCES `potongan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
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
