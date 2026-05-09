<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once '../function/saw.php';

$conn = getDBConnection();
$saw = new SAWCalculator($conn);

$activeTypes = $conn->query(
    "SELECT DISTINCT jenis_kredit
     FROM pengajuan
     WHERE status IN ('pending', 'verified', 'accepted')"
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
            p.jumlah_pinjaman, p.status, p.created_at,
            a.nama, u.username
     FROM hasil_saw h
     JOIN pengajuan p ON h.pengajuan_id = p.id
     JOIN anggota a ON p.anggota_id = a.id
     JOIN users u ON a.user_id = u.id
     ORDER BY h.jenis_kredit ASC, h.ranking ASC'
);
$rankings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$grouped = ['KTA' => [], 'KUR' => []];
foreach ($rankings as $row) {
    $grouped[$row['jenis_kredit']][] = $row;
}

function typeBadge($type)
{
    return $type === 'KTA' ? 'primary' : 'success';
}

function eligibilityLabel($value)
{
    return $value === 'layak' ? 'Layak' : 'Tidak Layak';
}

function statusLabel($status)
{
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Terverifikasi',
        'accepted' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => ucfirst((string) $status),
    };
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card hero mb-4">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h2 class="mb-1">Hasil SAW</h2>
                    <p class="mb-0 text-white-50">Hasil ranking KTA dan KUR dipisah agar mudah dibaca.</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="jenis_kredit" value="KTA">
                        <button type="submit" name="recalculate_saw" class="btn btn-light">
                            <i class="fas fa-sync"></i> Hitung Ulang KTA
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="jenis_kredit" value="KUR">
                        <button type="submit" name="recalculate_saw" class="btn btn-outline-light">
                            <i class="fas fa-sync"></i> Hitung Ulang KUR
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php foreach (['KTA', 'KUR'] as $type): ?>
            <div class="card section-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1">Peringkat <?php echo $type; ?></h4>
                            <p class="text-muted mb-0">Pengajuan yang sudah diverifikasi untuk jenis <?php echo $type; ?>.</p>
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
                                    <th>Nama Pengguna</th>
                                    <th>Jumlah</th>
                                    <th>Persentase</th>
                                    <th>Kelayakan</th>
                                    <th>Normalisasi</th>
                                    <th>Terbobot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($grouped[$type] as $rank): ?>
                                    <tr>
                                        <td><span class="badge text-bg-primary">#<?php echo (int) $rank['ranking']; ?></span></td>
                                        <td><?php echo (int) $rank['pengajuan_id']; ?></td>
                                        <td><?php echo htmlspecialchars($rank['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($rank['username']); ?></td>
                                        <td>Rp <?php echo number_format((float) $rank['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                        <td><?php echo number_format((float) $rank['persentase_saw'], 2); ?>%</td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $rank['kelayakan'] === 'layak' ? 'success' : 'danger'; ?>">
                                                <?php echo eligibilityLabel($rank['kelayakan'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format((float) $rank['skor_normalisasi'], 4); ?></td>
                                        <td><?php echo number_format((float) $rank['skor_terbobot'], 4); ?></td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $rank['status'] === 'verified' ? 'info' : 'secondary'; ?>">
                                                <?php echo statusLabel($rank['status']); ?>
                                            </span>
                                        </td>
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
