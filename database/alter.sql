-- Alter script for existing database
-- Use this only if you want to add the new SAW columns without rebuilding the database.

USE spk_kredit_cu;

UPDATE pengajuan
SET status = 'document_rejected'
WHERE status = 'tertolak';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER role_id,
    ADD COLUMN IF NOT EXISTS verification_token_hash CHAR(64) NULL DEFAULT NULL AFTER email_verified_at,
    ADD COLUMN IF NOT EXISTS verification_code_hash CHAR(64) NULL DEFAULT NULL AFTER verification_token_hash,
    ADD COLUMN IF NOT EXISTS verification_expires_at DATETIME NULL DEFAULT NULL AFTER verification_code_hash,
    ADD COLUMN IF NOT EXISTS verification_sent_at TIMESTAMP NULL DEFAULT NULL AFTER verification_expires_at;

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS persentase_saw DECIMAL(6,2) NULL AFTER skor_terbobot;

ALTER TABLE dokumen
    MODIFY COLUMN jenis ENUM('ktp', 'slip_gaji', 'surat_kerja', 'kartu_pelajar', 'kartu_keluarga', 'jaminan', 'foto_usaha', 'izin_usaha', 'laporan_usaha') NOT NULL;

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS kelayakan ENUM('layak', 'tidak_layak') NULL AFTER persentase_saw;

ALTER TABLE pengajuan
    MODIFY COLUMN status ENUM('pending', 'verified', 'document_rejected', 'accepted', 'rejected') NOT NULL DEFAULT 'pending';

ALTER TABLE anggota
    ADD COLUMN IF NOT EXISTS nomor_anggota VARCHAR(50) NULL DEFAULT NULL AFTER user_id;

CREATE TABLE IF NOT EXISTS notifikasi (
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

CREATE TABLE IF NOT EXISTS laporan (
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
