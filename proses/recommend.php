<?php
require_once '../config/database.php';
require_once '../function/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkLogin();
    checkRole('petugas');

    $pengajuan_id = (int) ($_POST['pengajuan_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $conn = getDBConnection();

    $stmt = $conn->prepare('SELECT kelayakan, persentase_saw, jenis_kredit FROM hasil_saw WHERE pengajuan_id = ? LIMIT 1');
    $stmt->bind_param('i', $pengajuan_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        header('Location: ../petugas/rankings.php?error=1');
        exit();
    }

    $recommendation = ($result['kelayakan'] ?? '') === 'layak' ? 'accepted' : 'rejected';
    $decisionText = $recommendation === 'accepted' ? 'Disetujui' : 'Ditolak';
    $eligibilityText = ($result['kelayakan'] ?? 'tidak_layak') === 'layak' ? 'Layak' : 'Tidak Layak';

    $stmt = $conn->prepare('UPDATE pengajuan SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $recommendation, $pengajuan_id);
    $stmt->execute();

    $aksi = sprintf(
        'Rekomendasi Otomatis: %s (%s)%s',
        $decisionText,
        $eligibilityText,
        $notes !== '' ? ' - ' . $notes : ''
    );
    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('INSERT INTO riwayat_pengajuan (pengajuan_id, aksi, dilakukan_oleh) VALUES (?, ?, ?)');
    $stmt->bind_param('isi', $pengajuan_id, $aksi, $user_id);
    $stmt->execute();

    header('Location: ../petugas/rankings.php?success=1');
    exit();
}
?>
