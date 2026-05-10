# SPK Kredit CU Lantang Tipo Jeruju - Metode SAW

Sistem Pendukung Keputusan (SPK) untuk penerimaan kredit KTA dan KUR menggunakan metode Simple Additive Weighting (SAW).

## Petunjuk Instalasi

1. **Instal XAMPP atau MySQL Server**
   - Pastikan MySQL berjalan di port default (3306)
   - Untuk XAMPP: Start Apache dan MySQL dari XAMPP Control Panel

2. **Pengaturan Basis Data**
   - Buka terminal dan navigasi ke folder project
   - Jalankan rebuild database:
     - `mysql -u root -p < database/reset.sql`
     - `mysql -u root -p < database/seed.sql`
   - Masukkan password MySQL root Anda

3. **Konfigurasi Basis Data**
   - Edit `config/database.php` jika perlu mengubah kredensial DB

4. **Akses Sistem**
   - Buka `index.php` di browser
   - Masuk default admin: nama pengguna `admin`, kata sandi `admin123`
   - Masuk default anggota: nama pengguna `anggota`, kata sandi `anggota123`

## Struktur Folder

- `/config` - Konfigurasi database
- `/assets` - CSS, JS, gambar
- `/uploads` - Unggah dokumen (dilindungi `.htaccess`)
- `/admin` - Halaman admin
- `/petugas` - Halaman petugas
- `/anggota` - Halaman anggota
- `/proses` - Logika pemrosesan
- `/function` - Fungsi helper dan SAW calculator
- `/template` - Template HTML
- `/auth` - Script autentikasi
- `/database` - Schema dan migrasi DB

## Peran

- **Admin**: Kelola pengguna, anggota, aplikasi, kriteria, laporan
- **Petugas**: Verifikasi dokumen, isi nilai kriteria, lihat peringkat
- **Anggota**: Daftar, ajukan kredit, unggah dokumen, lihat status

## Data Default

- Database: `spk_kredit_cu`
- Admin default: `admin`
- Password default admin: `admin123`

## Metode SAW

Kriteria default:

- Riwayat Tabungan (30% - benefit)
- Pendapatan (25% - benefit)
- Jaminan Aset (20% - benefit)
- Usia (15% - benefit)
- Riwayat Pinjaman (10% - benefit)

Proses:

1. Normalisasi nilai
2. Pemberian bobot
3. Penjumlahan skor
4. Peringkat berdasarkan skor tertinggi

## Teknologi

- PHP Native
- MySQL
- Bootstrap 5
- JavaScript
- DataTables
- SweetAlert
- Font Awesome

## Rencana Pengembangan

1. ✅ Siapkan lingkungan dan skema basis data
2. ✅ Implementasi auth dan roles
3. 🔄 Bangun pendaftaran/pengajuan anggota
4. 🔄 Develop criteria management
5. 🔄 Implement SAW algorithm
6. 🔄 Tambahkan unggah berkas dan verifikasi
7. 🔄 Buat dasbor dan laporan
8. 🔄 Pengujian, audit keamanan, dan penerapan

## Fitur Keamanan

- Hash kata sandi dengan bcrypt
- Prepared statement untuk mencegah SQL injection
- Manajemen sesi
- Kontrol akses berbasis peran
- Validasi unggah berkas
- Perlindungan CSRF (implementasi lanjutan)
- Pencegahan XSS dengan `htmlspecialchars`

## Kontribusi

- Gunakan branch `feature/` untuk pengembangan
- Pull request untuk penggabungan ke `main`
- Ikuti konvensi penamaan dan struktur folder
