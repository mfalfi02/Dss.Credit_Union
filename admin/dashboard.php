<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
$conn = getDBConnection();

$summaryRows = [];
$summaryResult = $conn->query(
    "SELECT jenis_kredit,
            COUNT(*) AS total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) AS verified_count,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
            ROUND(AVG(jumlah_pinjaman), 0) AS avg_amount
     FROM pengajuan
     GROUP BY jenis_kredit"
);
if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $summaryRows[$row['jenis_kredit']] = $row;
    }
}

$totals = [
    'applications' => 0,
    'members' => 0,
    'criteria' => 0,
    'saw' => 0,
];

$recentApplications = [];

foreach ([
    'applications' => 'SELECT COUNT(*) AS total FROM pengajuan',
    'members' => 'SELECT COUNT(*) AS total FROM anggota',
    'criteria' => "SELECT COUNT(*) AS total FROM kriteria WHERE jenis_kredit IN ('KTA', 'KUR', 'BOTH')",
    'saw' => 'SELECT COUNT(*) AS total FROM hasil_saw',
] as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $totals[$key] = (int) $result->fetch_assoc()['total'];
    }
}

$recentResult = $conn->query(
    "SELECT p.id, p.jenis_kredit, p.status, p.jumlah_pinjaman, p.created_at, a.nama
     FROM pengajuan p
     JOIN anggota a ON p.anggota_id = a.id
     ORDER BY p.created_at DESC
     LIMIT 5"
);
if ($recentResult) {
    $recentApplications = $recentResult->fetch_all(MYSQLI_ASSOC);
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
    <title>Dasbor Admin - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
        }
        .hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #0f766e 100%);
            color: #fff;
        }
        .metric-card {
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
            <a class="navbar-brand" href="#">Dasbor Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="card hero border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h2 class="mb-1">Selamat datang, Admin</h2>
                        <p class="mb-0 text-white-50">Ringkasan sistem pinjaman KTA dan KUR terpisah.</p>
                    </div>
                    <div class="text-md-end">
                        <a href="applications.php" class="btn btn-light me-2">Lihat Pengajuan</a>
                        <a href="saw_results.php" class="btn btn-outline-light">Lihat SAW</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Pengajuan</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['applications']; ?></div>
                        <div class="text-muted small">Semua jenis pinjaman</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Anggota</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['members']; ?></div>
                        <div class="text-muted small">Pengguna aktif anggota</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Kriteria Aktif</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['criteria']; ?></div>
                        <div class="text-muted small">KTA, KUR, dan umum</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Hasil SAW</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['saw']; ?></div>
                        <div class="text-muted small">Peringkat tersimpan</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php foreach (['KTA' => 'primary', 'KUR' => 'success'] as $type => $color): ?>
            <?php $row = $summaryRows[$type] ?? null; ?>
            <div class="col-lg-6">
                <div class="card section-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-muted small">Ringkasan <?php echo $type; ?></div>
                                <h4 class="mb-0"><?php echo $type; ?></h4>
                            </div>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo $type; ?></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="text-muted small">Total Pengajuan</div>
                                    <div class="fs-4 fw-bold"><?php echo (int) ($row['total_applications'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <div class="text-muted small">Rata-rata Pinjaman</div>
                                    <div class="fs-4 fw-bold">Rp <?php echo number_format((float) ($row['avg_amount'] ?? 0), 0, ',', '.'); ?></div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded text-center">
                                <div class="text-muted small">Menunggu</div>
                                    <div class="fw-bold"><?php echo (int) ($row['pending_count'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded text-center">
                                <div class="text-muted small">Terverifikasi</div>
                                    <div class="fw-bold"><?php echo (int) ($row['verified_count'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded text-center">
                                <div class="text-muted small">Disetujui</div>
                                    <div class="fw-bold"><?php echo (int) ($row['accepted_count'] ?? 0); ?></div>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 border rounded text-center">
                                <div class="text-muted small">Ditolak</div>
                                    <div class="fw-bold"><?php echo (int) ($row['rejected_count'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-1">Pengajuan Terbaru</h4>
                        <p class="text-muted mb-0">Lima pengajuan terakhir untuk memantau aktivitas sistem.</p>
                    </div>
                    <a href="applications.php" class="btn btn-outline-primary btn-sm">Buka Semua</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Jenis</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApplications as $app): ?>
                            <tr>
                                <td>#<?php echo (int) $app['id']; ?></td>
                                <td><?php echo htmlspecialchars($app['nama']); ?></td>
                                <td><span class="badge bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>"><?php echo htmlspecialchars($app['jenis_kredit']); ?></span></td>
                                <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                <td><span class="badge bg-<?php echo $app['status'] === 'pending' ? 'warning' : ($app['status'] === 'verified' ? 'info' : ($app['status'] === 'accepted' ? 'success' : 'danger')); ?>"><?php echo statusLabel($app['status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($app['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentApplications)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Kelola Pengguna</h5>
                        <a href="users.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Kelola Anggota</h5>
                        <a href="members.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Kelola Pengajuan</h5>
                        <a href="applications.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Kelola Kriteria</h5>
                        <a href="criteria.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Lihat Perhitungan SAW</h5>
                        <a href="saw_results.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Buat Laporan</h5>
                        <a href="reports.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
