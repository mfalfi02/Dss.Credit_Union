<?php
// Proses penyimpanan nilai kriteria petugas untuk satu pengajuan.
require_once '../config/database.php';
require_once '../function/auth.php';
require_once '../function/saw.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi akses hanya untuk petugas yang sudah login.
    checkLogin();
    checkRole('petugas');

    $pengajuan_id = (int) ($_POST['pengajuan_id'] ?? 0);
    $criteria_values = $_POST['criteria'] ?? [];
    $jenis_kredit = $_POST['jenis_kredit'] ?? '';

    if ($pengajuan_id <= 0 || empty($criteria_values) || !in_array($jenis_kredit, ['KTA', 'KUR'], true)) {
        header('Location: ../petugas/input_criteria.php?error=1');
        exit();
    }

    // Cek apakah pengajuan dan kriteria yang diinput memang sesuai jenis kreditnya.
    $conn = getDBConnection();

    $stmt = $conn->prepare('SELECT jenis_kredit FROM pengajuan WHERE id = ?');
    $stmt->bind_param('i', $pengajuan_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app || $app['jenis_kredit'] !== $jenis_kredit) {
        header('Location: ../petugas/input_criteria.php?error=2');
        exit();
    }

    $allowedCriteria = [];
    $stmt = $conn->prepare("SELECT id FROM kriteria WHERE jenis_kredit = ? OR jenis_kredit = 'BOTH'");
    $stmt->bind_param('s', $jenis_kredit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allowedCriteria[(int) $row['id']] = true;
    }

    $conn->begin_transaction();

    try {
        // Simpan atau perbarui nilai setiap kriteria yang diizinkan.
        foreach ($criteria_values as $kriteria_id => $nilai) {
            $kriteria_id = (int) $kriteria_id;
            if (!isset($allowedCriteria[$kriteria_id])) {
                continue;
            }

            $nilai = (float) $nilai;
            $stmt = $conn->prepare(
                'INSERT INTO penilaian (pengajuan_id, kriteria_id, nilai)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
            );
            $stmt->bind_param('iid', $pengajuan_id, $kriteria_id, $nilai);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE pengajuan SET status = 'verified' WHERE id = ?");
        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();

        $aksi = 'Isi nilai kriteria untuk ' . $jenis_kredit;
        $user_id = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('INSERT INTO riwayat_pengajuan (pengajuan_id, aksi, dilakukan_oleh) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $pengajuan_id, $aksi, $user_id);
        $stmt->execute();

        $saw = new SAWCalculator($conn);
        $saw->calculateRanking($jenis_kredit);

        $conn->commit();
        header('Location: ../petugas/input_criteria.php?success=1');
        exit();
    } catch (Throwable $e) {
        // Rollback jika penyimpanan nilai kriteria gagal.
        $conn->rollback();
        header('Location: ../petugas/input_criteria.php?error=1');
        exit();
    }
}
?>
