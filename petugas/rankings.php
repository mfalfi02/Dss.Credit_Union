<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('petugas');
require_once '../config/database.php';
require_once '../function/saw.php';
$conn = getDBConnection();
$saw = new SAWCalculator($conn);

$activeTypes = $conn->query(
    "SELECT DISTINCT jenis_kredit
     FROM pengajuan
     WHERE status = 'accepted'"
);
if ($activeTypes) {
    while ($row = $activeTypes->fetch_assoc()) {
        if (!empty($row['jenis_kredit'])) {
            $saw->calculateRanking($row['jenis_kredit']);
        }
    }
}

// Get rankings
$sql = "SELECT h.pengajuan_id, h.skor_terbobot, h.ranking, p.jenis_kredit, p.jumlah_pinjaman, p.anggota_id, p.status, a.nama, p.created_at
        , h.persentase_saw, h.kelayakan, u.username
        FROM hasil_saw h
        JOIN pengajuan p ON h.pengajuan_id = p.id
        JOIN anggota a ON p.anggota_id = a.id
        JOIN users u ON a.user_id = u.id
        WHERE p.status = 'accepted'
        ORDER BY p.jenis_kredit ASC, h.ranking ASC";
$result = $conn->query($sql);
$rankings = $result->fetch_all(MYSQLI_ASSOC);

$memberSummaries = ['KTA' => [], 'KUR' => []];
foreach ($rankings as $row) {
    $type = $row['jenis_kredit'] ?? '';
    if (!isset($memberSummaries[$type])) {
        continue;
    }

    $anggotaKey = (int) ($row['anggota_id'] ?? 0);
    if (!isset($memberSummaries[$type][$anggotaKey])) {
        $memberSummaries[$type][$anggotaKey] = [
            'nama' => $row['nama'],
            'username' => $row['username'],
            'jumlah_pengajuan' => 0,
            'pengajuan_terbaik' => $row['pengajuan_id'],
            'ranking_terbaik' => $row['ranking'],
            'persentase_terbaik' => $row['persentase_saw'],
            'kelayakan_terbaik' => $row['kelayakan'],
            'jumlah_pinjaman_terbaik' => $row['jumlah_pinjaman'],
        ];
    }

    $memberSummaries[$type][$anggotaKey]['jumlah_pengajuan']++;
    if ((float) $row['persentase_saw'] > (float) $memberSummaries[$type][$anggotaKey]['persentase_terbaik']) {
        $memberSummaries[$type][$anggotaKey]['pengajuan_terbaik'] = $row['pengajuan_id'];
        $memberSummaries[$type][$anggotaKey]['ranking_terbaik'] = $row['ranking'];
        $memberSummaries[$type][$anggotaKey]['persentase_terbaik'] = $row['persentase_saw'];
        $memberSummaries[$type][$anggotaKey]['kelayakan_terbaik'] = $row['kelayakan'];
        $memberSummaries[$type][$anggotaKey]['jumlah_pinjaman_terbaik'] = $row['jumlah_pinjaman'];
    }
}

foreach ($memberSummaries as $type => $members) {
    $memberSummaries[$type] = array_values($members);
    usort($memberSummaries[$type], static function ($a, $b) {
        return ($b['persentase_terbaik'] <=> $a['persentase_terbaik']) ?: ($a['nama'] <=> $b['nama']);
    });
}

function eligibilityLabel($value)
{
    return $value === 'layak'
        ? 'Layak Direkomendasikan'
        : 'Belum Layak Direkomendasikan';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringkat SAW - Petugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #f7fbff 0%, #eef8f1 100%);
        }

        .page-shell {
            max-width: 1400px;
        }

        .hero-card {
            background: linear-gradient(135deg, #0f766e 0%, #2563eb 100%);
            color: #fff;
        }

        .section-card {
            border: 0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }

        .soft-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .6rem;
            margin: .15rem .25rem .15rem 0;
            border-radius: 999px;
            background: #f1f5f9;
            font-size: .85rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container page-shell">
            <a class="navbar-brand" href="dashboard.php">Dasbor Petugas</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container page-shell py-4">
        <div class="card hero-card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <h2 class="mb-2">Peringkat SAW</h2>
                        <p class="mb-0 text-white-50">
                            Lihat pengajuan yang sudah diterima CU berdasarkan hasil SAW dan validasi akhir petugas.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-house me-2"></i>Kembali ke Dashboard
                        </a>
                        <a href="verify_documents.php" class="btn btn-outline-light">
                            <i class="fas fa-file-circle-check me-2"></i>Verifikasi Dokumen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach (['KTA', 'KUR'] as $type): ?>
            <div class="card section-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Ranking <?php echo $type; ?></strong>
                    <span class="badge text-bg-<?php echo $type === 'KTA' ? 'primary' : 'success'; ?>"><?php echo $type; ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-4">
                        <table class="table table-striped" id="rankingsTable-<?php echo $type; ?>">
                            <thead>
                                <tr>
                                    <th>Peringkat</th>
                                    <th>ID Pengajuan</th>
                                    <th>Nama Anggota</th>
                                    <th>Username</th>
                                    <th>Jumlah</th>
                                    <th>Persentase</th>
                                    <th>Rekomendasi Sistem</th>
                                    <th>Skor SAW</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rankings as $rank): ?>
                                    <?php if (($rank['jenis_kredit'] ?? '') !== $type) { continue; } ?>
                                    <tr>
                                        <td><span class="badge text-bg-primary">#<?php echo (int) $rank['ranking']; ?></span></td>
                                        <td><?php echo $rank['pengajuan_id']; ?></td>
                                        <td><?php echo htmlspecialchars($rank['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($rank['username']); ?></td>
                                        <td>Rp <?php echo number_format($rank['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                        <td><?php echo number_format((float) $rank['persentase_saw'], 2); ?>%</td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $rank['kelayakan'] === 'layak' ? 'success' : 'danger'; ?> text-wrap" style="white-space: normal;">
                                                <?php echo eligibilityLabel($rank['kelayakan'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($rank['skor_terbobot'], 4); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rank['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-top pt-4">
                        <h5 class="mb-1">Ringkasan Per Anggota - <?php echo $type; ?></h5>
                        <p class="text-muted">Menampilkan pengajuan terbaik dari setiap anggota pada jenis pinjaman yang sama.</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Ranking</th>
                                        <th>Nama Anggota</th>
                                        <th>Username</th>
                                        <th>Jumlah Pengajuan</th>
                                        <th>Pengajuan Terbaik</th>
                                        <th>Persentase Terbaik</th>
                                        <th>Rekomendasi Sistem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberSummaries[$type] as $summary): ?>
                                        <tr>
                                            <td><span class="badge text-bg-primary">#<?php echo (int) $summary['ranking_terbaik']; ?></span></td>
                                            <td><?php echo htmlspecialchars($summary['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($summary['username']); ?></td>
                                            <td><?php echo (int) $summary['jumlah_pengajuan']; ?></td>
                                            <td>#<?php echo (int) $summary['pengajuan_terbaik']; ?></td>
                                            <td><?php echo number_format((float) $summary['persentase_terbaik'], 2); ?>%</td>
                                            <td>
                                                <span class="badge text-bg-<?php echo $summary['kelayakan_terbaik'] === 'layak' ? 'success' : 'danger'; ?>">
                                                    <?php echo eligibilityLabel($summary['kelayakan_terbaik'] ?? ''); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($memberSummaries[$type])): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">Belum ada ringkasan anggota untuk <?php echo $type; ?>.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            <?php foreach (['KTA', 'KUR'] as $type): ?>
            $('#rankingsTable-<?php echo $type; ?>').DataTable({
                "order": [[0, "asc"]]
            });
            <?php endforeach; ?>
        });
    </script>
</body>
</html>
