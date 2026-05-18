<?php
// Proses validasi final petugas untuk menerima atau menolak pengajuan.
require_once '../config/database.php';
require_once '../function/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan hanya petugas yang sedang login yang boleh memproses keputusan.
    checkLogin();
    checkRole('petugas');

    $pengajuan_id = (int) ($_POST['pengajuan_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($pengajuan_id <= 0 || !in_array($decision, ['accepted', 'rejected'], true)) {
        header('Location: ../petugas/rankings.php?error=2');
        exit();
    }

    // Ambil status dan hasil SAW untuk menulis riwayat keputusan.
    $conn = getDBConnection();

    $stmt = $conn->prepare(
        'SELECT p.status, p.jenis_kredit, p.jumlah_pinjaman, p.anggota_id,
                h.kelayakan, h.persentase_saw
         FROM pengajuan p
         LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $pengajuan_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result || ($result['status'] ?? '') !== 'verified') {
        header('Location: ../petugas/rankings.php?error=1');
        exit();
    }

    $recommendationLabel = ($result['kelayakan'] ?? 'tidak_layak') === 'layak'
        ? 'Rekomendasi Sistem: Layak'
        : 'Rekomendasi Sistem: Belum Layak';

    $decisionLabel = $decision === 'accepted' ? 'Diterima CU' : 'Ditolak CU';
    $decisionNote = $notes !== '' ? ' - ' . $notes : '';

    $conn->begin_transaction();

    try {
        // Update status pengajuan dan simpan catatan keputusan.
        $stmt = $conn->prepare('UPDATE pengajuan SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $decision, $pengajuan_id);
        $stmt->execute();

        $aksi = sprintf(
            'Validasi Final Petugas: %s (%s, %.2f%%)%s',
            $decisionLabel,
            $recommendationLabel,
            (float) ($result['persentase_saw'] ?? 0),
            $decisionNote
        );

        $user_id = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('INSERT INTO riwayat_pengajuan (pengajuan_id, aksi, dilakukan_oleh) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $pengajuan_id, $aksi, $user_id);
        $stmt->execute();

        $conn->commit();
        header('Location: ../petugas/rankings.php?success=1');
        exit();
    } catch (Throwable $e) {
        // Kembalikan transaksi jika ada kegagalan.
        $conn->rollback();
        header('Location: ../petugas/rankings.php?error=3');
        exit();
    }
}

header('Location: ../petugas/rankings.php');
exit();
