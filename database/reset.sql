-- Reset script for a clean rebuild
-- Run this when you want to recreate the database from scratch.

DROP DATABASE IF EXISTS spk_kredit_cu;

CREATE DATABASE spk_kredit_cu
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE spk_kredit_cu;

CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    verification_token_hash CHAR(64) NULL DEFAULT NULL,
    verification_code_hash CHAR(64) NULL DEFAULT NULL,
    verification_expires_at DATETIME NULL DEFAULT NULL,
    verification_sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role_id),
    KEY idx_users_email_verified_at (email_verified_at),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE anggota (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    nomor_anggota VARCHAR(50) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    alamat TEXT NULL,
    no_hp VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    tanggal_lahir DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_anggota_nomor (nomor_anggota),
    UNIQUE KEY uq_anggota_user (user_id),
    KEY idx_anggota_user (user_id),
    CONSTRAINT fk_anggota_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pengajuan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    anggota_id INT UNSIGNED NOT NULL,
    jenis_kredit ENUM('KTA', 'KUR') NOT NULL,
    jumlah_pinjaman DECIMAL(15,2) NOT NULL,
    detail_pinjaman JSON NULL,
    status ENUM('pending', 'verified', 'document_rejected', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pengajuan_anggota (anggota_id),
    KEY idx_pengajuan_status (status),
    KEY idx_pengajuan_jenis (jenis_kredit),
    CONSTRAINT fk_pengajuan_anggota
        FOREIGN KEY (anggota_id) REFERENCES anggota(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kriteria (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    bobot DECIMAL(5,2) NOT NULL,
    jenis ENUM('benefit', 'cost') NOT NULL,
    jenis_kredit ENUM('KTA', 'KUR', 'BOTH') NOT NULL DEFAULT 'BOTH',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kriteria_jenis_kredit (jenis_kredit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE penilaian (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengajuan_id INT UNSIGNED NOT NULL,
    kriteria_id INT UNSIGNED NOT NULL,
    nilai DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_penilaian_pengajuan_kriteria (pengajuan_id, kriteria_id),
    KEY idx_penilaian_pengajuan (pengajuan_id),
    KEY idx_penilaian_kriteria (kriteria_id),
    CONSTRAINT fk_penilaian_pengajuan
        FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_penilaian_kriteria
        FOREIGN KEY (kriteria_id) REFERENCES kriteria(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hasil_saw (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengajuan_id INT UNSIGNED NOT NULL,
    jenis_kredit ENUM('KTA', 'KUR') NOT NULL,
    skor_normalisasi DECIMAL(10,4) NULL,
    skor_terbobot DECIMAL(10,4) NULL,
    persentase_saw DECIMAL(6,2) NULL,
    kelayakan ENUM('layak', 'tidak_layak') NULL,
    ranking INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hasil_saw_pengajuan (pengajuan_id),
    KEY idx_hasil_saw_pengajuan (pengajuan_id),
    KEY idx_hasil_saw_jenis (jenis_kredit),
    CONSTRAINT fk_hasil_saw_pengajuan
        FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dokumen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengajuan_id INT UNSIGNED NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    path_file VARCHAR(500) NOT NULL,
    jenis ENUM('ktp', 'slip_gaji', 'surat_kerja', 'kartu_pelajar', 'kartu_keluarga', 'jaminan', 'foto_usaha', 'izin_usaha', 'laporan_usaha') NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dokumen_pengajuan (pengajuan_id),
    CONSTRAINT fk_dokumen_pengajuan
        FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifikasi (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    anggota_id INT UNSIGNED NOT NULL,
    pengajuan_id INT UNSIGNED NOT NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    deadline_at DATETIME NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifikasi_pengajuan (pengajuan_id),
    KEY idx_notifikasi_anggota (anggota_id),
    KEY idx_notifikasi_deadline (deadline_at),
    KEY idx_notifikasi_is_read (is_read),
    CONSTRAINT fk_notifikasi_anggota
        FOREIGN KEY (anggota_id) REFERENCES anggota(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_notifikasi_pengajuan
        FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE riwayat_pengajuan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pengajuan_id INT UNSIGNED NOT NULL,
    aksi VARCHAR(150) NOT NULL,
    dilakukan_oleh INT UNSIGNED NOT NULL,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_riwayat_pengajuan (pengajuan_id),
    KEY idx_riwayat_user (dilakukan_oleh),
    CONSTRAINT fk_riwayat_pengajuan
        FOREIGN KEY (pengajuan_id) REFERENCES pengajuan(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_riwayat_user
        FOREIGN KEY (dilakukan_oleh) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE laporan (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    generated_by INT UNSIGNED NOT NULL,
    jenis_laporan VARCHAR(100) NOT NULL,
    data JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_laporan_user (generated_by),
    CONSTRAINT fk_laporan_user
        FOREIGN KEY (generated_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
