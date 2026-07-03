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
