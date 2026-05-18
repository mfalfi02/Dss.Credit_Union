<?php
// Halaman anggota untuk mengisi form pengajuan KTA atau KUR beserta dokumen.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';
$conn = getDBConnection();

// Get member data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM anggota WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

$selectedJenis = $_GET['jenis'] ?? '';
if (!in_array($selectedJenis, ['KTA', 'KUR'], true)) {
    $selectedJenis = '';
}

$errorMessage = '';
if (isset($_GET['error'])) {
    switch ((string) $_GET['error']) {
        case '1':
            $errorMessage = 'Pengajuan gagal diproses. Cek koneksi database atau unggahan dokumen kamu.';
            break;
        case '2':
            $errorMessage = 'Masih ada data yang belum lengkap. Silakan lengkapi form sesuai jenis pinjaman yang dipilih.';
            break;
        default:
            $errorMessage = 'Pengajuan gagal diproses.';
            break;
    }
}

$errorFields = [];
if (!empty($_GET['fields'])) {
    $errorFields = array_values(array_filter(array_map('trim', explode(',', (string) $_GET['fields']))));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Pengajuan - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, 0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(37, 99, 235, 0.10), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .topbar .nav-link,
        .topbar .navbar-brand {
            color: #fff !important;
        }

        .hero-card,
        .panel-card,
        .choice-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        .hero-card {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            inset: auto -12% -38% auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .4rem .85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-size: .875rem;
        }

        .choice-card {
            background: #fff;
            transition: transform .2s ease, box-shadow .2s ease;
            height: 100%;
            width: 100%;
            border: 0;
            text-align: left;
            appearance: none;
            cursor: pointer;
        }

        .choice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        .choice-card.active {
            border: 2px solid rgba(37, 99, 235, 0.35);
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.16);
        }

        .choice-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
        }

        .kta-icon { background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); }
        .kur-icon { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }

        .panel-card {
            background: #fff;
        }

        .section-title {
            color: #0f172a;
            font-weight: 800;
        }

        .form-section {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(16px);
            pointer-events: none;
            transition: max-height .45s ease, opacity .35s ease, transform .35s ease;
        }

        .form-section.is-visible {
            max-height: 5000px;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .field-invalid .form-label {
            color: #dc3545;
            font-weight: 700;
        }

        .field-invalid .form-control,
        .field-invalid .form-select,
        .field-invalid .form-control:focus,
        .field-invalid .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.12);
        }

        .field-feedback {
            display: block;
            margin-top: .35rem;
            color: #dc3545;
            font-size: .875rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg topbar navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="dashboard.php">
                <i class="fas fa-user-group me-2"></i>Dasbor Anggota
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <main class="container py-4 py-lg-5">
        <section class="card hero-card mb-4">
            <div class="card-body p-4 p-lg-5 position-relative">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="hero-badge mb-3">
                            <i class="fas fa-wand-magic-sparkles"></i>
                            <span>Langkah pengajuan yang mudah</span>
                        </div>
                        <h1 class="display-6 fw-bold mb-3">Form Pengajuan Kredit</h1>
                        <p class="lead mb-0 text-white-75" style="max-width: 46rem;">
                            Pilih jenis pinjaman dulu, lalu isi data pribadi atau data usaha sesuai kebutuhan. Tampilan akan menyesuaikan otomatis agar lebih mudah dipahami.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="status.php" class="btn btn-light btn-lg">
                            <i class="fas fa-list-check me-2"></i>Lihat Status Pengajuan
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4 justify-content-center mb-4">
            <div class="col-12">
                <div class="alert alert-light border text-center mb-0">
                    Pilih salah satu kartu di bawah untuk menampilkan form pengajuan di bagian bawah.
                </div>
            </div>
            <?php if ($errorMessage !== '' && empty($errorFields)): ?>
                <div class="col-12">
                    <div class="alert alert-danger border-0 shadow-sm mb-0">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-md-6 col-lg-4">
                <button type="button" class="choice-card p-4 w-100 text-start <?php echo $selectedJenis === 'KTA' ? 'active' : ''; ?>" data-jenis="KTA">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="choice-icon kta-icon"><i class="fas fa-id-card"></i></div>
                            <span class="badge text-bg-primary">Pribadi</span>
                        </div>
                        <h4 class="section-title mb-2">KTA</h4>
                        <p class="text-muted mb-0">Fokus pada kemampuan bayar pribadi dan dokumen pendukung yang relevan.</p>
                </button>
            </div>
            <div class="col-md-6 col-lg-4">
                <button type="button" class="choice-card p-4 w-100 text-start <?php echo $selectedJenis === 'KUR' ? 'active' : ''; ?>" data-jenis="KUR">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="choice-icon kur-icon"><i class="fas fa-store"></i></div>
                            <span class="badge text-bg-success">Usaha</span>
                        </div>
                        <h4 class="section-title mb-2">KUR</h4>
                        <p class="text-muted mb-0">Fokus pada usaha, omzet, laba, dan kelengkapan legalitas usaha.</p>
                </button>
            </div>
        </div>

        <div class="form-section row justify-content-center <?php echo $selectedJenis !== '' ? 'is-visible' : ''; ?>" id="formSection">
            <div class="col-lg-10">
                <form action="../proses/submit_application.php" method="POST" enctype="multipart/form-data" novalidate class="card panel-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                            <div>
                                <h3 class="section-title mb-1">Lengkapi Data Pengajuan</h3>
                                <p class="text-muted mb-0">Form di bawah akan menyesuaikan pilihan jenis pinjaman.</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge text-bg-light border">1. Pilih jenis</span>
                                <span class="badge text-bg-light border">2. Isi data</span>
                                <span class="badge text-bg-light border">3. Unggah dokumen</span>
                            </div>
                        </div>

                        <input type="hidden" name="jenis_kredit" id="jenis_kredit" value="<?php echo htmlspecialchars($selectedJenis); ?>">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kredit</label>
                                <div class="form-control bg-light">
                                    <strong id="jenisLabel"><?php echo $selectedJenis === 'KTA' ? 'KTA - Kredit Tanpa Agunan' : ($selectedJenis === 'KUR' ? 'KUR - Kredit Usaha Rakyat' : 'Belum dipilih'); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Pinjaman</label>
                                <input type="number" name="jumlah_pinjaman" class="form-control" min="100000" step="10000" required>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-primary mb-0 py-3">
                                    <strong>Tips:</strong> isi sesuai kondisi sebenarnya agar hasil penilaian lebih akurat.
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4 mb-0" id="jenisInfo">
                            KTA fokus ke kemampuan bayar pribadi. KUR fokus ke kelayakan dan performa usaha.
                        </div>

                        <div id="ktaFields" class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Data KTA</h5>
                                    <p class="text-muted mb-0">Isi data pribadi, penghasilan, dan dokumen pendukung.</p>
                                </div>
                                <span class="badge bg-primary">Pribadi</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tujuan Pinjaman</label>
                                    <input type="text" name="tujuan_pinjaman_kta" class="form-control" placeholder="Contoh: renovasi rumah, biaya pendidikan">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Pekerjaan</label>
                                    <select name="status_pekerjaan_kta" class="form-select">
                                        <option value="">Pilih status</option>
                                        <option value="Karyawan">Karyawan</option>
                                        <option value="Wiraswasta">Wiraswasta</option>
                                        <option value="Honorer">Honorer</option>
                                        <option value="Pelajar/Mahasiswa">Pelajar/Mahasiswa</option>
                                        <option value="Pensiunan">Pensiunan</option>
                                        <option value="Buruh">Buruh</option>
                                        <option value="Tidak Bekerja">Tidak Bekerja</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="statusPekerjaanLainnyaWrap" style="display: none;">
                                    <label class="form-label">Status Pekerjaan Lainnya</label>
                                    <input type="text" name="status_pekerjaan_lainnya_kta" class="form-control" placeholder="Tulis status pekerjaan lain">
                                </div>
                                <div class="col-md-6" id="namaTempatKerjaWrap">
                                    <label class="form-label">Nama Tempat Kerja / Perusahaan</label>
                                    <input type="text" name="nama_tempat_kerja_kta" class="form-control" placeholder="Contoh: PT Maju Bersama">
                                </div>
                                <div class="col-md-6" id="lamaBekerjaWrap">
                                    <label class="form-label">Lama Bekerja (bulan)</label>
                                    <input type="number" name="lama_bekerja_bulan_kta" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-6" id="jumlahTanggunganWrap" style="display: none;">
                                    <label class="form-label">Jumlah Tanggungan</label>
                                    <input type="number" name="jumlah_tanggungan_kta" class="form-control" min="0" step="1" placeholder="Contoh: 2">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penghasilan Bulanan</label>
                                    <input type="number" name="penghasilan_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pengeluaran Bulanan</label>
                                    <input type="number" name="pengeluaran_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Beban Cicilan Bulanan</label>
                                    <input type="number" name="beban_cicilan_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div id="kurFields" class="mt-4" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Data KUR</h5>
                                    <p class="text-muted mb-0">Isi data usaha secara jelas dan konsisten.</p>
                                </div>
                                <span class="badge bg-success">Usaha</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Usaha</label>
                                    <input type="text" name="nama_usaha_kur" class="form-control" placeholder="Contoh: Toko Sembako Maju">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bidang Usaha</label>
                                    <input type="text" name="bidang_usaha_kur" class="form-control" placeholder="Contoh: perdagangan, kuliner">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat Usaha</label>
                                    <input type="text" name="alamat_usaha_kur" class="form-control" placeholder="Contoh: Jl. Jeruju No. 12">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lama Usaha (bulan)</label>
                                    <input type="number" name="lama_usaha_bulan_kur" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Omzet Bulanan</label>
                                    <input type="number" name="omzet_bulanan_kur" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Laba Bersih Bulanan</label>
                                    <input type="number" name="laba_bersih_bulanan_kur" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jumlah Karyawan (opsional)</label>
                                    <input type="number" name="jumlah_karyawan_kur" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Legalitas Usaha</label>
                                    <select name="legalitas_usaha_kur" class="form-select">
                                        <option value="">Pilih legalitas</option>
                                        <option value="NIB">NIB</option>
                                        <option value="SIUP">SIUP</option>
                                        <option value="SKU">SKU</option>
                                        <option value="Belum Ada">Belum Ada</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tujuan Dana</label>
                                    <input type="text" name="tujuan_dana_kur" class="form-control" placeholder="Contoh: modal kerja, tambah stok">
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Dokumen Pendukung</h5>
                                    <p class="text-muted mb-0">Upload dokumen sesuai jenis pinjaman.</p>
                                </div>
                            </div>

                            <div id="ktaDocs" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">KTP</label>
                                    <input type="file" name="ktp" class="form-control" accept="image/*,.pdf" required>
                                </div>
                                <div class="col-md-4" id="ktaSlipGajiWrap">
                                    <label class="form-label">Slip Gaji / Bukti Penghasilan</label>
                                    <input type="file" name="slip_gaji" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaSuratKerjaWrap">
                                    <label class="form-label">Surat Keterangan Kerja</label>
                                    <input type="file" name="surat_kerja" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaKartuPelajarWrap" style="display: none;">
                                    <label class="form-label">Kartu Pelajar / Mahasiswa</label>
                                    <input type="file" name="kartu_pelajar" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaKartuKeluargaWrap" style="display: none;">
                                    <label class="form-label">Kartu Keluarga</label>
                                    <input type="file" name="kartu_keluarga" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jaminan (opsional)</label>
                                    <input type="file" name="jaminan" class="form-control" accept="image/*,.pdf">
                                </div>
                            </div>

                            <div id="kurDocs" class="row g-3" style="display: none;">
                                <div class="col-md-4">
                                    <label class="form-label">KTP <span class="text-danger">*</span></label>
                                    <input type="file" name="ktp" class="form-control" accept="image/*,.pdf" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Foto Usaha</label>
                                    <input type="file" name="foto_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Izin / Legalitas Usaha</label>
                                    <input type="file" name="izin_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Laporan Omzet / Catatan Usaha</label>
                                    <input type="file" name="laporan_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Agunan Tambahan (opsional)</label>
                                    <input type="file" name="jaminan" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-success mb-0 py-2">
                                        Untuk KUR, minimal unggah KTP. Foto usaha, izin usaha, dan laporan usaha tetap disarankan agar verifikasi lebih lancar.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                            <a href="dashboard.php" class="btn btn-outline-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary px-4">Ajukan Pengajuan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const jenisInfo = document.getElementById('jenisInfo');
        const jenisSelect = document.getElementById('jenis_kredit');
        const jenisLabel = document.getElementById('jenisLabel');
        const formSection = document.getElementById('formSection');
        const ktaFields = document.getElementById('ktaFields');
        const kurFields = document.getElementById('kurFields');
        const ktaDocs = document.getElementById('ktaDocs');
        const kurDocs = document.getElementById('kurDocs');
        const statusPekerjaanSelect = ktaFields.querySelector('select[name="status_pekerjaan_kta"]');
        const statusPekerjaanLainnyaWrap = document.getElementById('statusPekerjaanLainnyaWrap');
        const statusPekerjaanLainnyaInput = ktaFields.querySelector('input[name="status_pekerjaan_lainnya_kta"]');
        const namaTempatKerjaWrap = document.getElementById('namaTempatKerjaWrap');
        const namaTempatKerjaInput = ktaFields.querySelector('input[name="nama_tempat_kerja_kta"]');
        const lamaBekerjaWrap = document.getElementById('lamaBekerjaWrap');
        const lamaBekerjaInput = ktaFields.querySelector('input[name="lama_bekerja_bulan_kta"]');
        const jumlahTanggunganWrap = document.getElementById('jumlahTanggunganWrap');
        const jumlahTanggunganInput = ktaFields.querySelector('input[name="jumlah_tanggungan_kta"]');
        const ktaSlipGajiWrap = document.getElementById('ktaSlipGajiWrap');
        const ktaSuratKerjaWrap = document.getElementById('ktaSuratKerjaWrap');
        const ktaKartuPelajarWrap = document.getElementById('ktaKartuPelajarWrap');
        const ktaKartuKeluargaWrap = document.getElementById('ktaKartuKeluargaWrap');
        const ktaSlipGajiInput = ktaDocs.querySelector('input[name="slip_gaji"]');
        const ktaSuratKerjaInput = ktaDocs.querySelector('input[name="surat_kerja"]');
        const ktaKartuPelajarInput = ktaDocs.querySelector('input[name="kartu_pelajar"]');
        const ktaKartuKeluargaInput = ktaDocs.querySelector('input[name="kartu_keluarga"]');
        const choiceCards = document.querySelectorAll('.choice-card[data-jenis]');
        const pengajuanForm = document.querySelector('form[action="../proses/submit_application.php"]');
        const serverErrorFields = <?php echo json_encode($errorFields, JSON_UNESCAPED_UNICODE); ?>;

        function getFieldWrapper(input) {
            return input.closest('.col-md-4, .col-md-6, .col-12, .col-lg-4');
        }

        function clearFieldState(input) {
            const wrapper = getFieldWrapper(input);
            if (!wrapper) {
                return;
            }

            wrapper.classList.remove('field-invalid');
            input.classList.remove('is-invalid');
            input.setAttribute('aria-invalid', 'false');

            const label = wrapper.querySelector('label');
            if (label) {
                label.classList.remove('text-danger');
            }

            const feedback = wrapper.querySelector('.field-feedback');
            if (feedback) {
                feedback.remove();
            }
        }

        function setFieldError(input, message) {
            const wrapper = getFieldWrapper(input);
            if (!wrapper) {
                return;
            }

            wrapper.classList.add('field-invalid');
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');

            const label = wrapper.querySelector('label');
            if (label) {
                label.classList.add('text-danger');
            }

            let feedback = wrapper.querySelector('.field-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'field-feedback';
                wrapper.appendChild(feedback);
            }
            feedback.textContent = message || 'Wajib diisi.';
        }

        function validateRequiredFields() {
            const requiredFields = Array.from(pengajuanForm.querySelectorAll('input[required], select[required], textarea[required]'))
                .filter((field) => !field.disabled && field.offsetParent !== null);

            let firstInvalid = null;

            requiredFields.forEach((field) => {
                clearFieldState(field);

                const isEmptyFile = field.type === 'file' && (!field.files || field.files.length === 0);
                const isEmptyText = field.type !== 'file' && String(field.value || '').trim() === '';
                const isInvalid = isEmptyFile || isEmptyText;

                if (isInvalid) {
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }

                    const label = getFieldWrapper(field)?.querySelector('label');
                    const labelText = label ? label.textContent.replace('*', '').trim() : 'Field ini';
                    setFieldError(field, `${labelText} belum diisi.`);
                }
            });

            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            return true;
        }

        function setChoiceActive(jenis) {
            choiceCards.forEach((card) => {
                card.classList.toggle('active', card.getAttribute('data-jenis') === jenis);
            });
        }

        function openFormWithJenis(jenis) {
            if (!['KTA', 'KUR'].includes(jenis)) {
                return;
            }

            jenisSelect.value = jenis;
            setChoiceActive(jenis);
            updateJenisInfo();
            formSection.classList.add('is-visible');
            formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function updateStatusPekerjaanField() {
            const selectedStatus = statusPekerjaanSelect.value;
            const isLainnya = selectedStatus === 'Lainnya';
            const isStudent = selectedStatus === 'Pelajar/Mahasiswa';
            statusPekerjaanLainnyaWrap.style.display = isLainnya ? 'block' : 'none';
            statusPekerjaanLainnyaInput.required = isLainnya;
            statusPekerjaanLainnyaInput.disabled = !isLainnya;
            if (!isLainnya) {
                statusPekerjaanLainnyaInput.value = '';
            }

            namaTempatKerjaWrap.style.display = isStudent ? 'none' : 'block';
            lamaBekerjaWrap.style.display = isStudent ? 'none' : 'block';
            jumlahTanggunganWrap.style.display = isStudent ? 'block' : 'none';

            namaTempatKerjaInput.required = !isStudent;
            lamaBekerjaInput.required = !isStudent;
            jumlahTanggunganInput.required = isStudent;

            namaTempatKerjaInput.disabled = isStudent;
            lamaBekerjaInput.disabled = isStudent;
            jumlahTanggunganInput.disabled = !isStudent;

            if (isStudent) {
                namaTempatKerjaInput.value = '';
                lamaBekerjaInput.value = '';
            } else {
                jumlahTanggunganInput.value = '';
            }

            ktaSlipGajiWrap.style.display = isStudent ? 'none' : 'block';
            ktaSuratKerjaWrap.style.display = isStudent ? 'none' : 'block';
            ktaKartuPelajarWrap.style.display = isStudent ? 'block' : 'none';
            ktaKartuKeluargaWrap.style.display = isStudent ? 'block' : 'none';

            ktaSlipGajiInput.required = !isStudent;
            ktaSuratKerjaInput.required = !isStudent;
            ktaKartuPelajarInput.required = isStudent;
            ktaKartuKeluargaInput.required = isStudent;
        }

        function updateJenisInfo() {
            const jenis = jenisSelect.value;
            if (!jenis) {
                jenisInfo.textContent = 'Pilih KTA atau KUR di atas untuk mulai mengisi pengajuan.';
                ktaFields.style.display = 'none';
                kurFields.style.display = 'none';
                ktaDocs.style.display = 'none';
                kurDocs.style.display = 'none';
                return;
            }

            const isKTA = jenis === 'KTA';
            const ktaInputs = ktaFields.querySelectorAll('input, select');
            const kurInputs = kurFields.querySelectorAll('input, select');
            const ktaDocInputs = ktaDocs.querySelectorAll('input');
            const kurDocInputs = kurDocs.querySelectorAll('input');

            if (jenis === 'KTA') {
                jenisInfo.textContent = 'KTA fokus ke kemampuan bayar pribadi. Untuk pelajar/mahasiswa, data tanggungan dan dokumen mahasiswa akan diprioritaskan.';
                ktaFields.style.display = 'block';
                kurFields.style.display = 'none';
                ktaDocs.style.display = 'flex';
                kurDocs.style.display = 'none';
            } else {
                jenisInfo.textContent = 'KUR fokus ke usaha. Data usaha, omzet, laba, legalitas, dan foto usaha perlu diisi selengkap mungkin.';
                ktaFields.style.display = 'none';
                kurFields.style.display = 'block';
                ktaDocs.style.display = 'none';
                kurDocs.style.display = 'flex';
            }

            jenisLabel.textContent = isKTA
                ? 'KTA - Kredit Tanpa Agunan'
                : 'KUR - Kredit Usaha Rakyat';

            ktaInputs.forEach((input) => {
                input.required = false;
                input.disabled = !isKTA;
            });
            kurInputs.forEach((input) => {
                input.required = false;
                input.disabled = isKTA;
            });
            ktaDocInputs.forEach((input) => {
                input.required = false;
                input.disabled = !isKTA;
            });
            kurDocInputs.forEach((input) => {
                input.required = false;
                input.disabled = isKTA;
            });

            if (isKTA) {
                ktaFields.querySelector('input[name="tujuan_pinjaman_kta"]').required = true;
                ktaFields.querySelector('select[name="status_pekerjaan_kta"]').required = true;
                ktaFields.querySelector('input[name="penghasilan_bulanan_kta"]').required = true;
                ktaFields.querySelector('input[name="beban_cicilan_bulanan_kta"]').required = true;
                ktaDocInputs[0].required = true;

                if (statusPekerjaanSelect.value === 'Pelajar/Mahasiswa') {
                    ktaKartuPelajarInput.required = true;
                    ktaKartuKeluargaInput.required = true;
                    ktaSlipGajiInput.required = false;
                    ktaSuratKerjaInput.required = false;
                } else {
                    ktaSlipGajiInput.required = true;
                    ktaSuratKerjaInput.required = true;
                    ktaKartuPelajarInput.required = false;
                    ktaKartuKeluargaInput.required = false;
                }

                ktaSlipGajiWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'none' : 'block';
                ktaSuratKerjaWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'none' : 'block';
                ktaKartuPelajarWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'block' : 'none';
                ktaKartuKeluargaWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'block' : 'none';
            } else {
                kurFields.querySelector('input[name="nama_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="bidang_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="alamat_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="lama_usaha_bulan_kur"]').required = true;
                kurFields.querySelector('input[name="omzet_bulanan_kur"]').required = true;
                kurFields.querySelector('input[name="laba_bersih_bulanan_kur"]').required = true;
                kurFields.querySelector('input[name="jumlah_karyawan_kur"]').required = false;
                kurFields.querySelector('select[name="legalitas_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="tujuan_dana_kur"]').required = true;
                kurDocInputs[0].required = true;
            }

            updateStatusPekerjaanField();
        }

        statusPekerjaanSelect.addEventListener('change', updateStatusPekerjaanField);
        updateJenisInfo();

        if (jenisSelect.value) {
            setChoiceActive(jenisSelect.value);
            formSection.classList.add('is-visible');
            formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        choiceCards.forEach((card) => {
            card.addEventListener('click', function() {
                openFormWithJenis(this.getAttribute('data-jenis'));
            });
        });

        pengajuanForm.addEventListener('submit', function(event) {
            if (!validateRequiredFields()) {
                event.preventDefault();
            }
        });

        pengajuanForm.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => clearFieldState(field));
            field.addEventListener('change', () => clearFieldState(field));
        });

        if (Array.isArray(serverErrorFields) && serverErrorFields.length > 0) {
            const currentJenis = jenisSelect.value;
            if (currentJenis) {
                updateJenisInfo();
                setChoiceActive(currentJenis);
            }

            const firstServerInvalid = serverErrorFields
                .map((fieldName) => pengajuanForm.querySelector(`[name="${fieldName}"]`))
                .find((field) => field && field.offsetParent !== null);

            serverErrorFields.forEach((fieldName) => {
                const field = pengajuanForm.querySelector(`[name="${fieldName}"]`);
                if (field && field.offsetParent !== null) {
                    setFieldError(field, 'Field ini belum diisi.');
                }
            });

            if (firstServerInvalid) {
                firstServerInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    </script>
</body>
</html>
