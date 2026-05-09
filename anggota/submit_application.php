<?php
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Pengajuan - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Anggota</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                            <div>
                                <h2 class="mb-1">Form Pengajuan Kredit</h2>
                                <p class="text-muted mb-0">
                                    Lengkapi data sesuai jenis pinjaman. KTA fokus pada kemampuan bayar pribadi, sedangkan KUR fokus pada kelayakan usaha.
                                </p>
                            </div>
                            <a href="status.php" class="btn btn-outline-info">Lihat Status Pengajuan</a>
                        </div>
                    </div>
                </div>

                <form action="../proses/submit_application.php" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kredit</label>
                                <select name="jenis_kredit" id="jenis_kredit" class="form-select" required>
                                    <option value="KTA">KTA - Kredit Tanpa Agunan</option>
                                    <option value="KUR">KUR - Kredit Usaha Rakyat</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Pinjaman</label>
                                <input type="number" name="jumlah_pinjaman" class="form-control" min="100000" step="10000" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Catatan</label>
                                <div class="form-control bg-light text-muted">Data akan otomatis disesuaikan dengan jenis pinjaman.</div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4 mb-0" id="jenisInfo">
                            KTA fokus ke kemampuan bayar pribadi. KUR fokus ke kelayakan dan performa usaha.
                        </div>

                        <div id="ktaFields" class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Detail KTA</h5>
                                    <p class="text-muted mb-0">Isi data pribadi dan kemampuan bayar.</p>
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
                                    <h5 class="mb-1">Detail KUR</h5>
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
                                    <label class="form-label">Jumlah Karyawan</label>
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
                                    <label class="form-label">KTP</label>
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
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">Ajukan Pengajuan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const jenisInfo = document.getElementById('jenisInfo');
        const jenisSelect = document.getElementById('jenis_kredit');
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
            const isKTA = jenisSelect.value === 'KTA';
            const ktaInputs = ktaFields.querySelectorAll('input, select');
            const kurInputs = kurFields.querySelectorAll('input, select');
            const ktaDocInputs = ktaDocs.querySelectorAll('input');
            const kurDocInputs = kurDocs.querySelectorAll('input');

            if (jenisSelect.value === 'KTA') {
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
                kurFields.querySelector('input[name="jumlah_karyawan_kur"]').required = true;
                kurFields.querySelector('select[name="legalitas_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="tujuan_dana_kur"]').required = true;
                kurDocInputs[0].required = true;
                kurDocInputs[1].required = true;
            }

            updateStatusPekerjaanField();
        }

        jenisSelect.addEventListener('change', updateJenisInfo);
        statusPekerjaanSelect.addEventListener('change', updateStatusPekerjaanField);
        updateJenisInfo();
    </script>
</body>
</html>
