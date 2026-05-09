-- Alter script for existing database
-- Use this only if you want to add the new SAW columns without rebuilding the database.

USE spk_kredit_cu;

UPDATE pengajuan
SET status = 'rejected'
WHERE status = 'tertolak';

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS persentase_saw DECIMAL(6,2) NULL AFTER skor_terbobot;

ALTER TABLE dokumen
    MODIFY COLUMN jenis ENUM('ktp', 'slip_gaji', 'surat_kerja', 'kartu_pelajar', 'kartu_keluarga', 'jaminan', 'foto_usaha', 'izin_usaha', 'laporan_usaha') NOT NULL;

ALTER TABLE hasil_saw
    ADD COLUMN IF NOT EXISTS kelayakan ENUM('layak', 'tidak_layak') NULL AFTER persentase_saw;

ALTER TABLE pengajuan
    MODIFY COLUMN status ENUM('pending', 'verified', 'accepted', 'rejected') NOT NULL DEFAULT 'pending';
