-- Seed data for the clean schema
-- Run after reset.sql or after creating the schema manually.

USE spk_kredit_cu;

INSERT IGNORE INTO roles (id, name) VALUES
(1, 'admin'),
(2, 'petugas'),
(3, 'anggota');

INSERT INTO users (id, username, password, role_id, email_verified_at) VALUES
(1, 'admin', '$2y$10$h09xwVapY1JRS2DgIXsog.tznX67Q88RFx79e1wSDEe5/mMwSU.yW', 1, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    role_id = VALUES(role_id),
    email_verified_at = VALUES(email_verified_at);

INSERT INTO users (id, username, password, role_id, email_verified_at) VALUES
(2, 'petugas', '$2y$10$Mw9sY/pmnwqvuWhhC2gU8eqla.BJ94rWaBnni1rxjthZengffrWO6', 2, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    role_id = VALUES(role_id),
    email_verified_at = VALUES(email_verified_at);

INSERT INTO users (id, username, password, role_id, email_verified_at) VALUES
(3, 'anggota', '$2y$10$Vfj9yVebTdUb6KAJClVZXuxZlDu48gcfOuE5exLQF1IBfDdqBojKa', 3, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    password = VALUES(password),
    role_id = VALUES(role_id),
    email_verified_at = VALUES(email_verified_at);

INSERT INTO anggota (id, user_id, nomor_anggota, nama, alamat, no_hp, email, tanggal_lahir) VALUES
(1, 3, 'ANG-0001', 'Anggota Demo', 'Jl. Jeruju Contoh No. 1', '081234567890', 'anggota@example.com', '1995-05-10')
ON DUPLICATE KEY UPDATE
    user_id = VALUES(user_id),
    nomor_anggota = VALUES(nomor_anggota),
    nama = VALUES(nama),
    alamat = VALUES(alamat),
    no_hp = VALUES(no_hp),
    email = VALUES(email),
    tanggal_lahir = VALUES(tanggal_lahir);

INSERT INTO pengajuan (id, anggota_id, jenis_kredit, jumlah_pinjaman, detail_pinjaman, status) VALUES
(1, 1, 'KTA', 5000000.00, '{"tujuan_pinjaman":"Modal usaha kecil","status_pekerjaan":"Karyawan","status_pekerjaan_asli":"Karyawan","status_pekerjaan_custom":null,"penghasilan_bulanan":6000000,"pengeluaran_bulanan":2500000,"beban_cicilan_bulanan":500000,"jumlah_tanggungan":null,"nama_tempat_kerja":"PT Contoh Sejahtera","lama_bekerja_bulan":24}', 'pending')
ON DUPLICATE KEY UPDATE
    anggota_id = VALUES(anggota_id),
    jenis_kredit = VALUES(jenis_kredit),
    jumlah_pinjaman = VALUES(jumlah_pinjaman),
    detail_pinjaman = VALUES(detail_pinjaman),
    status = VALUES(status);

INSERT INTO pengajuan (id, anggota_id, jenis_kredit, jumlah_pinjaman, detail_pinjaman, status) VALUES
(2, 1, 'KUR', 15000000.00, '{"nama_usaha":"Toko Sembako Maju","bidang_usaha":"Perdagangan","alamat_usaha":"Jl. Jeruju Contoh No. 1","lama_usaha_bulan":36,"omzet_bulanan":18000000,"laba_bersih_bulanan":4500000,"jumlah_karyawan":2,"legalitas_usaha":"NIB","tujuan_dana":"Tambah stok barang"}', 'pending')
ON DUPLICATE KEY UPDATE
    anggota_id = VALUES(anggota_id),
    jenis_kredit = VALUES(jenis_kredit),
    jumlah_pinjaman = VALUES(jumlah_pinjaman),
    detail_pinjaman = VALUES(detail_pinjaman),
    status = VALUES(status);

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
