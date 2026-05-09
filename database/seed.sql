-- Seed data for the clean schema
-- Run after reset.sql or after creating the schema manually.

USE spk_kredit_cu;

INSERT IGNORE INTO roles (id, name) VALUES
(1, 'admin'),
(2, 'petugas'),
(3, 'anggota');

INSERT INTO users (id, username, password, role_id) VALUES
(1, 'admin', '$2y$10$h09xwVapY1JRS2DgIXsog.tznX67Q88RFx79e1wSDEe5/mMwSU.yW', 1)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    role_id = VALUES(role_id);

INSERT IGNORE INTO kriteria (id, nama, bobot, jenis, jenis_kredit) VALUES
(1, 'Pendapatan', 0.30, 'benefit', 'KTA'),
(2, 'Riwayat Pinjaman', 0.20, 'benefit', 'KTA'),
(3, 'Usia', 0.15, 'benefit', 'KTA'),
(4, 'Stabilitas Pekerjaan', 0.20, 'benefit', 'KTA'),
(5, 'Beban Cicilan', 0.15, 'cost', 'KTA'),
(6, 'Lama Usaha', 0.20, 'benefit', 'KUR'),
(7, 'Omzet Usaha', 0.25, 'benefit', 'KUR'),
(8, 'Laba Bersih', 0.25, 'benefit', 'KUR'),
(9, 'Legalitas Usaha', 0.15, 'benefit', 'KUR'),
(10, 'Jumlah Karyawan', 0.15, 'benefit', 'KUR');
