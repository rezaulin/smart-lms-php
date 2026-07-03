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
