<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once 'ui.php';

$conn = getDBConnection();

$totals = [
    'applications' => 0,
    'members' => 0,
    'criteria' => 0,
    'saw' => 0,
];

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

$recentApplications = [];
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
        'verified' => 'Siap Validasi Final',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst((string) $status),
    };
}

function statusBadgeClass($status)
{
    return match ($status) {
        'pending' => 'warning',
        'verified' => 'info',
        'document_rejected' => 'danger',
        'accepted' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
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
    <?php echo adminPageStyles(); ?>
</head>
<body>
    <?php echo renderAdminHeader('dashboard', 'Beranda Admin', 'Kelola data sistem.'); ?>

    <div class="container admin-shell pb-4">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Pengajuan</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['applications']; ?></div>
                        <div class="text-muted small">Semua jenis pinjaman</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Anggota</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['members']; ?></div>
                        <div class="text-muted small">Pengguna aktif anggota</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Kriteria Aktif</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['criteria']; ?></div>
                        <div class="text-muted small">KTA dan KUR</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Hasil SAW</div>
                        <div class="fs-3 fw-bold"><?php echo $totals['saw']; ?></div>
                        <div class="text-muted small">Peringkat tersimpan</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card admin-card h-100">
                    <div class="card-body">
                        <h4 class="admin-section-title mb-1">Akses Cepat</h4>
                        <p class="text-muted mb-4">Menu Fitur pengolahan sistem.</p>
                        <div class="d-grid gap-2">
                            <a href="users.php" class="btn btn-primary">Kelola Staf</a>
                            <a href="members.php" class="btn btn-outline-primary">Kelola Anggota</a>
                            <a href="applications.php" class="btn btn-outline-primary">Kelola Pengajuan</a>
                            <a href="criteria.php" class="btn btn-outline-primary">Kelola Kriteria</a>
                            <a href="saw_results.php" class="btn btn-outline-primary">Lihat SAW</a>
                            <a href="reports.php" class="btn btn-outline-primary">Buat Laporan</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card admin-card h-100">
                    <div class="card-body">
                        <?php echo renderAdminSectionCard('Pengajuan Terbaru', 'Lima pengajuan terakhir untuk memantau aktivitas sistem.', [
                            ['label' => 'Buka Semua', 'href' => 'applications.php', 'class' => 'btn btn-outline-primary btn-sm'],
                        ]); ?>
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
                                        <td><span class="badge bg-<?php echo statusBadgeClass($app['status']); ?>"><?php echo statusLabel($app['status']); ?></span></td>
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
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
