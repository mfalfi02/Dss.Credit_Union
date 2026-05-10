<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('petugas');
require_once '../config/database.php';

$conn = getDBConnection();

$currentUserName = 'Petugas';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!empty($user['username'])) {
            $currentUserName = $user['username'];
        }
    }
}

$stats = [
    'total_pengajuan' => 0,
    'pending' => 0,
    'verified' => 0,
    'hasil_saw' => 0,
];

$queries = [
    'total_pengajuan' => "SELECT COUNT(*) AS total FROM pengajuan",
    'pending' => "SELECT COUNT(*) AS total FROM pengajuan WHERE status = 'pending'",
    'verified' => "SELECT COUNT(*) AS total FROM pengajuan WHERE status = 'verified'",
    'hasil_saw' => "SELECT COUNT(*) AS total FROM hasil_saw",
];

foreach ($queries as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $stats[$key] = (int) ($result->fetch_assoc()['total'] ?? 0);
    }
}

$recentApplications = [];
$recentResult = $conn->query(
    "SELECT p.id, p.jenis_kredit, p.jumlah_pinjaman, p.status, p.created_at, a.nama,
            (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) AS dokumen_count
     FROM pengajuan p
     JOIN anggota a ON p.anggota_id = a.id
     ORDER BY p.created_at DESC
     LIMIT 6"
);
if ($recentResult) {
    $recentApplications = $recentResult->fetch_all(MYSQLI_ASSOC);
}

function statusBadgeClass(string $status): string
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

function statusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Menunggu Verifikasi',
        'verified' => 'Siap Validasi Final',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst($status),
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Petugas - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --petugas-bg: #f5f7fb;
            --petugas-card: #ffffff;
            --petugas-border: rgba(15, 23, 42, 0.08);
            --petugas-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
            --petugas-primary: #15803d;
            --petugas-primary-soft: #dcfce7;
            --petugas-accent: #0f766e;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(21, 128, 61, 0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.10), transparent 26%),
                linear-gradient(180deg, #f8fafc 0%, var(--petugas-bg) 100%);
            min-height: 100vh;
        }

        .topbar {
            background: linear-gradient(135deg, #166534 0%, #0f766e 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .topbar .nav-link,
        .topbar .navbar-brand {
            color: #fff !important;
        }

        .hero {
            background: linear-gradient(135deg, #166534 0%, #0f766e 100%);
            color: #fff;
            border: 0;
            border-radius: 1.25rem;
            box-shadow: var(--petugas-shadow);
            overflow: hidden;
            position: relative;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto -10% -35% auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            filter: blur(2px);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            padding: .45rem .85rem;
            font-size: .875rem;
        }

        .action-card,
        .task-card {
            background: var(--petugas-card);
            border: 1px solid var(--petugas-border);
            border-radius: 1rem;
            box-shadow: var(--petugas-shadow);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--petugas-primary-soft);
            color: var(--petugas-primary);
            font-size: 1.15rem;
        }

        .action-card {
            transition: transform .2s ease, box-shadow .2s ease;
            height: 100%;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        .soft-section-title {
            color: #0f172a;
            font-weight: 700;
        }

        .table thead th {
            font-size: .875rem;
            color: #475569;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .table td {
            vertical-align: middle;
        }

        .dashboard-grid {
            display: grid;
            gap: 1rem;
        }

        @media (max-width: 991.98px) {
            .hero .card-body {
                padding: 1.25rem;
            }

            .action-card,
            .task-card {
                border-radius: 1rem;
            }
        }

        @media (max-width: 575.98px) {
            .hero-badge {
                width: 100%;
                justify-content: center;
            }

            .action-card .btn,
            .task-card .btn {
                width: 100%;
            }

            .table {
                min-width: 720px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg topbar">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="dashboard.php">
                <i class="fas fa-layer-group me-2"></i>Dasbor Petugas
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">
                    <i class="fas fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        <section class="hero card mb-4">
            <div class="card-body p-4 p-lg-5 position-relative">
                <div class="row align-items-center g-4">
                    <div class="col-lg-12">
                        <div class="hero-badge mb-3">
                            <i class="fas fa-sparkles"></i>
                            <span>Control center petugas</span>
                        </div>
                        <h1 class="display-6 fw-bold mb-3">Selamat datang, <?php echo htmlspecialchars($currentUserName); ?></h1>
                        <p class="lead mb-4 text-white-75" style="max-width: 46rem;">
                            Periksa dan Kelola Pengajuan Pinjaman.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-12 col-lg-6">
                <div class="action-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="metric-icon"><i class="fas fa-file-circle-check"></i></div>
                        <span class="badge text-bg-light">Langkah 1</span>
                    </div>
                    <h5 class="soft-section-title">Verifikasi Dokumen</h5>
                    <p class="text-muted mb-4">Cek dokumen pengajuan yang masuk sebelum nilai kriteria dihitung.</p>
                    <a href="verify_documents.php" class="btn btn-success w-100">
                        Buka Verifikasi
                    </a>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="action-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="metric-icon"><i class="fas fa-ranking-star"></i></div>
                        <span class="badge text-bg-light">Langkah 2</span>
                    </div>
                    <h5 class="soft-section-title">Lihat Peringkat</h5>
                    <p class="text-muted mb-4">Pantau ranking SAW per jenis kredit dan ringkasan per anggota.</p>
                    <a href="rankings.php" class="btn btn-success w-100">
                        Buka Peringkat
                    </a>
                </div>
            </div>
        </section>

        <section class="task-card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <div>
                    <h4 class="soft-section-title mb-1">Antrian Pengajuan Terbaru</h4>
                    <p class="text-muted mb-0">Daftar pengajuan terbaru agar petugas cepat lanjut ke tahap berikutnya.</p>
                </div>
                <a href="verify_documents.php" class="btn btn-outline-success">
                    Lihat Semua Pengajuan
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Anggota</th>
                            <th>Jenis Kredit</th>
                            <th>Jumlah</th>
                            <th>Dokumen</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentApplications)): ?>
                            <?php foreach ($recentApplications as $app): ?>
                                <tr>
                                    <td>#<?php echo (int) $app['id']; ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($app['nama']); ?></td>
                                    <td>
                                        <span class="badge text-bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>">
                                            <?php echo htmlspecialchars($app['jenis_kredit']); ?>
                                        </span>
                                    </td>
                                    <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                    <td><?php echo (int) $app['dokumen_count']; ?> berkas</td>
                                    <td>
                                        <span class="badge text-bg-<?php echo statusBadgeClass((string) $app['status']); ?>">
                                            <?php echo statusLabel((string) $app['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan yang masuk.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
