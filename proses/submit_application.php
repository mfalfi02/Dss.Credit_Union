<?php
require_once '../config/database.php';
require_once '../function/auth.php';
require_once '../function/saw.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkLogin();
    checkRole('anggota');

    $user_id = (int) $_SESSION['user_id'];
    $jenis_kredit = $_POST['jenis_kredit'] ?? '';
    $jumlah_pinjaman = (float) ($_POST['jumlah_pinjaman'] ?? 0);

    if (!in_array($jenis_kredit, ['KTA', 'KUR'], true) || $jumlah_pinjaman <= 0) {
        header('Location: ../anggota/submit_application.php?error=2');
        exit();
    }

    $detail_pinjaman = [
        'jenis_kredit' => $jenis_kredit,
        'jumlah_pinjaman' => $jumlah_pinjaman,
    ];
    if ($jenis_kredit === 'KTA') {
        $tujuan_pinjaman = trim($_POST['tujuan_pinjaman_kta'] ?? '');
        $status_pekerjaan = trim($_POST['status_pekerjaan_kta'] ?? '');
        $status_pekerjaan_lainnya = trim($_POST['status_pekerjaan_lainnya_kta'] ?? '');
        $nama_tempat_kerja = trim($_POST['nama_tempat_kerja_kta'] ?? '');
        $lama_bekerja = (int) ($_POST['lama_bekerja_bulan_kta'] ?? 0);
        $jumlah_tanggungan = (int) ($_POST['jumlah_tanggungan_kta'] ?? 0);
        $penghasilan_bulanan = (float) ($_POST['penghasilan_bulanan_kta'] ?? 0);
        $pengeluaran_bulanan = (float) ($_POST['pengeluaran_bulanan_kta'] ?? 0);
        $beban_cicilan_bulanan = (float) ($_POST['beban_cicilan_bulanan_kta'] ?? 0);
        $is_student = $status_pekerjaan === 'Pelajar/Mahasiswa';

        if ($tujuan_pinjaman === '' || $status_pekerjaan === '' || $penghasilan_bulanan <= 0) {
            header('Location: ../anggota/submit_application.php?error=2');
            exit();
        }

        if ($status_pekerjaan === 'Lainnya') {
            if ($status_pekerjaan_lainnya === '') {
                header('Location: ../anggota/submit_application.php?error=2');
                exit();
            }
        }

        if ($is_student) {
            if ($jumlah_tanggungan < 0) {
                header('Location: ../anggota/submit_application.php?error=2');
                exit();
            }
        } elseif ($nama_tempat_kerja === '' || $lama_bekerja <= 0) {
            header('Location: ../anggota/submit_application.php?error=2');
            exit();
        }

        $detail_pinjaman = [
            'tujuan_pinjaman' => $tujuan_pinjaman,
            'status_pekerjaan' => $status_pekerjaan === 'Lainnya' ? $status_pekerjaan_lainnya : $status_pekerjaan,
            'status_pekerjaan_asli' => $status_pekerjaan,
            'status_pekerjaan_custom' => $status_pekerjaan === 'Lainnya' ? $status_pekerjaan_lainnya : null,
            'penghasilan_bulanan' => $penghasilan_bulanan,
            'pengeluaran_bulanan' => $pengeluaran_bulanan,
            'beban_cicilan_bulanan' => $beban_cicilan_bulanan,
            'jumlah_tanggungan' => $is_student ? $jumlah_tanggungan : null,
            'nama_tempat_kerja' => $is_student ? null : $nama_tempat_kerja,
            'lama_bekerja_bulan' => $is_student ? null : $lama_bekerja,
        ];
    } else {
        $nama_usaha = trim($_POST['nama_usaha_kur'] ?? '');
        $bidang_usaha = trim($_POST['bidang_usaha_kur'] ?? '');
        $alamat_usaha = trim($_POST['alamat_usaha_kur'] ?? '');
        $lama_usaha_bulan = (int) ($_POST['lama_usaha_bulan_kur'] ?? 0);
        $omzet_bulanan = (float) ($_POST['omzet_bulanan_kur'] ?? 0);
        $laba_bersih_bulanan = (float) ($_POST['laba_bersih_bulanan_kur'] ?? 0);
        $jumlah_karyawan = (int) ($_POST['jumlah_karyawan_kur'] ?? 0);
        $legalitas_usaha = trim($_POST['legalitas_usaha_kur'] ?? '');
        $tujuan_dana = trim($_POST['tujuan_dana_kur'] ?? '');

        if ($nama_usaha === '' || $bidang_usaha === '' || $alamat_usaha === '' || $lama_usaha_bulan <= 0 || $omzet_bulanan <= 0 || $laba_bersih_bulanan <= 0 || $legalitas_usaha === '' || $tujuan_dana === '') {
            header('Location: ../anggota/submit_application.php?error=2');
            exit();
        }

        $detail_pinjaman = [
            'nama_usaha' => $nama_usaha,
            'bidang_usaha' => $bidang_usaha,
            'alamat_usaha' => $alamat_usaha,
            'lama_usaha_bulan' => $lama_usaha_bulan,
            'omzet_bulanan' => $omzet_bulanan,
            'laba_bersih_bulanan' => $laba_bersih_bulanan,
            'jumlah_karyawan' => $jumlah_karyawan,
            'legalitas_usaha' => $legalitas_usaha,
            'tujuan_dana' => $tujuan_dana,
        ];
    }

    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT id FROM anggota WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if (!$member) {
        header('Location: ../anggota/dashboard.php?error=1');
        exit();
    }

    try {
        $anggota_id = (int) $member['id'];
        $detail_json = json_encode($detail_pinjaman, JSON_UNESCAPED_UNICODE);

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO pengajuan (anggota_id, jenis_kredit, jumlah_pinjaman, detail_pinjaman) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $anggota_id, $jenis_kredit, $jumlah_pinjaman, $detail_json);
        $stmt->execute();
        $pengajuan_id = $conn->insert_id;

        $conn->commit();

        $saw = new SAWCalculator($conn);
        $saw->calculateRanking($jenis_kredit);
    } catch (Throwable $e) {
        if ($conn->errno === 0) {
            $conn->rollback();
        }
        header('Location: ../anggota/submit_application.php?error=1');
        exit();
    }

    $upload_errors = [];
    try {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $files = $jenis_kredit === 'KTA'
            ? ($status_pekerjaan === 'Pelajar/Mahasiswa'
                ? [
                    'ktp' => 'ktp',
                    'kartu_pelajar' => 'kartu_pelajar',
                    'kartu_keluarga' => 'kartu_keluarga',
                    'jaminan' => 'jaminan',
                ]
                : [
                    'ktp' => 'ktp',
                    'slip_gaji' => 'slip_gaji',
                    'surat_kerja' => 'surat_kerja',
                    'jaminan' => 'jaminan',
                ])
            : [
                'ktp' => 'ktp',
                'foto_usaha' => 'foto_usaha',
                'izin_usaha' => 'izin_usaha',
                'laporan_usaha' => 'laporan_usaha',
                'jaminan' => 'jaminan',
            ];

        foreach ($files as $input_name => $jenis) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
                $file = $_FILES[$input_name];
                if (in_array($file['type'], $allowed_types) && $file['size'] <= 5000000) {
                    $filename = uniqid('', true) . '_' . basename($file['name']);
                    $path = $upload_dir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $stmt = $conn->prepare("INSERT INTO dokumen (pengajuan_id, nama_file, path_file, jenis) VALUES (?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param("isss", $pengajuan_id, $file['name'], $path, $jenis);
                            if (!$stmt->execute()) {
                                $upload_errors[] = $jenis;
                                error_log('Gagal simpan dokumen ' . $jenis . ' untuk pengajuan ' . $pengajuan_id . ': ' . $stmt->error);
                            }
                        } else {
                            $upload_errors[] = $jenis;
                            error_log('Gagal prepare dokumen ' . $jenis . ' untuk pengajuan ' . $pengajuan_id . ': ' . $conn->error);
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Upload dokumen gagal untuk pengajuan ' . $pengajuan_id . ': ' . $e->getMessage());
    }

    $redirect = '../anggota/status.php?success=1';
    if (!empty($upload_errors)) {
        $redirect .= '&warning=1';
    }
    header('Location: ' . $redirect);
    exit();
}
?>
