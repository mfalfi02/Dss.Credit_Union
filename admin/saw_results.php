<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once '../function/saw.php';
require_once 'ui.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate_saw'])) {
    $type = $_POST['jenis_kredit'] ?? '';
    if (in_array($type, ['KTA', 'KUR'], true)) {
        $saw->calculateRanking($type);
    } else {
        $saw->calculateRanking();
    }
    header('Location: saw_results.php?success=1');
    exit();
}

$result = $conn->query(
    'SELECT h.pengajuan_id, h.jenis_kredit, h.skor_normalisasi, h.skor_terbobot, h.persentase_saw, h.kelayakan, h.ranking,
            p.jumlah_pinjaman, p.created_at, p.anggota_id,
            a.nama, u.username
     FROM hasil_saw h
     JOIN pengajuan p ON h.pengajuan_id = p.id
     JOIN anggota a ON p.anggota_id = a.id
     JOIN users u ON a.user_id = u.id
     WHERE p.status = \'accepted\'
     ORDER BY h.jenis_kredit ASC, h.ranking ASC'
);
$rankings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$grouped = ['KTA' => [], 'KUR' => []];
foreach ($rankings as $row) {
    $grouped[$row['jenis_kredit']][] = $row;
}

$memberSummaries = ['KTA' => [], 'KUR' => []];
foreach ($grouped as $type => $rows) {
    $seen = [];
    foreach ($rows as $row) {
        $anggotaKey = (int) ($row['anggota_id'] ?? 0);
        if (!isset($seen[$anggotaKey])) {
            $seen[$anggotaKey] = [
                'nama' => $row['nama'],
                'username' => $row['username'],
                'anggota_id' => (int) $row['anggota_id'],
                'jumlah_pengajuan' => 0,
                'pengajuan_terbaik' => $row['pengajuan_id'],
                'ranking_terbaik' => $row['ranking'],
                'persentase_terbaik' => $row['persentase_saw'],
                'kelayakan_terbaik' => $row['kelayakan'],
                'jumlah_pinjaman_terbaik' => $row['jumlah_pinjaman'],
            ];
        }

        $seen[$anggotaKey]['jumlah_pengajuan']++;
        if ((float) $row['persentase_saw'] > (float) $seen[$anggotaKey]['persentase_terbaik']) {
            $seen[$anggotaKey]['pengajuan_terbaik'] = $row['pengajuan_id'];
            $seen[$anggotaKey]['ranking_terbaik'] = $row['ranking'];
            $seen[$anggotaKey]['persentase_terbaik'] = $row['persentase_saw'];
            $seen[$anggotaKey]['kelayakan_terbaik'] = $row['kelayakan'];
            $seen[$anggotaKey]['jumlah_pinjaman_terbaik'] = $row['jumlah_pinjaman'];
        }
    }

    $memberSummaries[$type] = array_values($seen);
    usort($memberSummaries[$type], static function ($a, $b) {
        return ($b['persentase_terbaik'] <=> $a['persentase_terbaik']) ?: ($a['nama'] <=> $b['nama']);
    });
}

function typeBadge($type)
{
    return $type === 'KTA' ? 'primary' : 'success';
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
    <title>Hasil SAW - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <?php echo adminPageStyles(); ?>
    <style>
        body {
            background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
        }
        .hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #0f766e 100%);
            color: #fff;
            border: 0;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }
        .section-card {
            border: 0;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
            border-radius: 1rem;
        }
    </style>
</head>
<body>
    <?php echo renderAdminHeader('saw', 'Hasil SAW', 'Ranking pinjaman KTA dan KUR dipisah agar mudah dibaca.', [
        ['label' => 'Hitung Ulang KTA', 'class' => 'btn btn-light', 'href' => '#kta'],
        ['label' => 'Hitung Ulang KUR', 'class' => 'btn btn-outline-light', 'href' => '#kur'],
    ]); ?>

    <div class="container admin-shell">
        <?php foreach (['KTA', 'KUR'] as $type): ?>
            <div class="card admin-card mb-4" id="<?php echo strtolower($type); ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1">Peringkat <?php echo $type; ?></h4>
                        <p class="text-muted mb-0">Pengajuan yang sudah diterima CU untuk jenis <?php echo $type; ?>.</p>
                        </div>
                        <span class="badge bg-<?php echo typeBadge($type); ?>"><?php echo $type; ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle sawTable">
                            <thead>
                                <tr>
                                    <th>Peringkat</th>
                                    <th>ID Pengajuan</th>
                                    <th>Anggota</th>
                                    <th>Jumlah</th>
                                    <th>Persentase</th>
                                    <th>Rekomendasi Sistem</th>
                                    <th>Normalisasi</th>
                                    <th>Terbobot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grouped[$type] as $rank): ?>
                                    <tr>
                                        <td><span class="badge text-bg-primary">#<?php echo (int) $rank['ranking']; ?></span></td>
                                        <td><?php echo (int) $rank['pengajuan_id']; ?></td>
                                        <td><?php echo htmlspecialchars($rank['nama']); ?></td>
                                        <td>Rp <?php echo number_format((float) $rank['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                        <td><?php echo number_format((float) $rank['persentase_saw'], 2); ?>%</td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $rank['kelayakan'] === 'layak' ? 'success' : 'danger'; ?> text-wrap" style="white-space: normal;">
                                                <?php echo eligibilityLabel($rank['kelayakan'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format((float) $rank['skor_normalisasi'], 4); ?></td>
                                        <td><?php echo number_format((float) $rank['skor_terbobot'], 4); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (empty($grouped[$type])): ?>
                        <div class="alert alert-light border text-muted mb-0">
                            Belum ada hasil SAW untuk <?php echo $type; ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card admin-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1">Ringkasan Per Anggota - <?php echo $type; ?></h4>
                            <p class="text-muted mb-0">Menampilkan pengajuan terbaik dari setiap anggota pada jenis pinjaman yang sama.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Ranking</th>
                                        <th>Nama Anggota</th>
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
                                            <td><?php echo (int) $summary['jumlah_pengajuan']; ?></td>
                                            <td>#<?php echo (int) $summary['pengajuan_terbaik']; ?></td>
                                            <td><?php echo number_format((float) $summary['persentase_terbaik'], 2); ?>%</td>
                                            <td>
                                                <span class="badge text-bg-<?php echo $summary['kelayakan_terbaik'] === 'layak' ? 'success' : 'danger'; ?> text-wrap" style="white-space: normal;">
                                                    <?php echo eligibilityLabel($summary['kelayakan_terbaik'] ?? ''); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($memberSummaries[$type])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Belum ada ringkasan anggota untuk <?php echo $type; ?>.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                        </table>
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
            $('.sawTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 5
            });
        });
    </script>
</body>
</html>
