-- Alter script for existing database
-- Use this only if you want to add the new SAW columns without rebuilding the database.

USE spk_kredit_cu;

UPDATE pengajuan
SET status = 'document_rejected'
WHERE status = 'tertolak';

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS persentase_saw DECIMAL(6,2) NULL AFTER skor_terbobot;

ALTER TABLE dokumen
    MODIFY COLUMN jenis ENUM('ktp', 'slip_gaji', 'surat_kerja', 'kartu_pelajar', 'kartu_keluarga', 'jaminan', 'foto_usaha', 'izin_usaha', 'laporan_usaha') NOT NULL;

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS kelayakan ENUM('layak', 'tidak_layak') NULL AFTER persentase_saw;

ALTER TABLE pengajuan
    MODIFY COLUMN status ENUM('pending', 'verified', 'document_rejected', 'accepted', 'rejected') NOT NULL DEFAULT 'pending';

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
