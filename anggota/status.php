<?php
// Halaman anggota untuk memantau semua pengajuan dan detail hasilnya.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';
require_once '../function/notification.php';
$conn = getDBConnection();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT a.id AS anggota_id, a.nama
     FROM anggota a
     WHERE a.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

$applications = [];
$notifications = [];
$unreadNotificationCount = 0;
$autoOpenApplication = null;
if ($member) {
    $sql = "SELECT p.*,
            h.skor_terbobot, h.persentase_saw, h.kelayakan, h.ranking,
            (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) as dokumen_count
            FROM pengajuan p
            JOIN anggota a ON p.anggota_id = a.id
            LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
            WHERE a.user_id = ?
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $notifications = getMemberNotifications($conn, (int) $member['anggota_id'], 5);
    $unreadNotificationCount = getUnreadNotificationCount($conn, (int) $member['anggota_id']);

    $detailId = (int) ($_GET['detail'] ?? 0);
    if ($detailId > 0) {
        foreach ($applications as $app) {
            if ((int) $app['id'] === $detailId) {
                $autoOpenApplication = $app;
                break;
            }
        }
    }
}

function summarize_status_detail($detailJson, $jenisKredit)
{
    $detail = json_decode($detailJson ?? '', true);
    if (!is_array($detail)) {
        return '-';
    }

    $parts = [];
    $addPart = static function (array &$parts, $value): void {
        if ($value === null || $value === '' || $value === '-') {
            return;
        }
        $parts[] = htmlspecialchars((string) $value);
    };

    if ($jenisKredit === 'KTA') {
        $addPart($parts, $detail['tujuan_pinjaman'] ?? '');
        $addPart($parts, $detail['status_pekerjaan_asli'] ?? '');
        if (($detail['status_pekerjaan_asli'] ?? '') === 'Pelajar/Mahasiswa') {
            $addPart($parts, 'Tanggungan ' . (int) ($detail['jumlah_tanggungan'] ?? 0));
        } else {
            $addPart($parts, $detail['nama_tempat_kerja'] ?? '');
            $addPart($parts, !empty($detail['lama_bekerja_bulan']) ? (int) $detail['lama_bekerja_bulan'] . ' bulan kerja' : '');
        }
        $addPart($parts, !empty($detail['penghasilan_bulanan']) ? 'Rp ' . number_format((float) $detail['penghasilan_bulanan'], 0, ',', '.') : '');
        return $parts ? implode(' - ', array_slice($parts, 0, 3)) : '-';
    }

    $addPart($parts, $detail['nama_usaha'] ?? '');
    $addPart($parts, $detail['bidang_usaha'] ?? '');
    $addPart($parts, !empty($detail['omzet_bulanan']) ? 'Omzet Rp ' . number_format((float) $detail['omzet_bulanan'], 0, ',', '.') : '');
    $addPart($parts, !empty($detail['laba_bersih_bulanan']) ? 'Laba Rp ' . number_format((float) $detail['laba_bersih_bulanan'], 0, ',', '.') : '');

    return $parts ? implode(' - ', array_slice($parts, 0, 3)) : '-';
}

function eligibilityLabel($value)
{
    return $value === 'layak' ? 'Memenuhi Batas Minimum' : 'Belum Memenuhi Batas Minimum';
}

function statusLabel($status)
{
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Terverifikasi',
        'document_rejected' => 'Dokumen Ditolak',
        'accepted' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => ucfirst((string) $status),
    };
}

function statusBadge($status)
{
    return match ($status) {
        'pending' => 'warning',
        'verified' => 'info',
        'accepted' => 'success',
        'rejected', 'document_rejected' => 'danger',
        default => 'secondary',
    };
}

function statusDateLabel($date)
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }

    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

$pendingCount = count(array_filter($applications, static fn($app) => $app['status'] === 'pending'));
$approvedCount = count(array_filter($applications, static fn($app) => $app['status'] === 'accepted'));
$currentUserName = $member['nama'] ?? 'Anggota CU';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pengajuan - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #1769ff;
            --ink: #10204a;
            --muted: #65708d;
            --line: #e7eef8;
            --surface: rgba(255, 255, 255, 0.94);
            --shadow: 0 18px 46px rgba(34, 58, 95, 0.08);
        }

        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at 72% 0%, rgba(23, 105, 255, 0.12), transparent 30%),
                radial-gradient(circle at 18% 18%, rgba(24, 184, 148, 0.10), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #edf4fc 100%);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }

        a { text-decoration: none; }

        .member-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 28px 22px;
            background: rgba(255, 255, 255, 0.86);
            border-right: 1px solid var(--line);
            backdrop-filter: blur(18px);
        }

        .brand-mark,
        .avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(135deg, #1769ff, #22a7ff);
            box-shadow: 0 12px 22px rgba(23, 105, 255, 0.22);
        }

        .brand-title {
            font-size: 1.02rem;
            font-weight: 800;
            margin-bottom: 0;
        }

        .brand-subtitle,
        .small-muted {
            color: var(--muted);
            font-size: .82rem;
        }

        .nav-section { margin-top: 26px; }

        .nav-caption {
            color: #7a86a3;
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 10px 10px;
        }

        .side-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 46px;
            padding: 0 14px;
            border-radius: 8px;
            color: #26375f;
            font-size: .93rem;
            font-weight: 650;
        }

        .side-link i {
            width: 18px;
            text-align: center;
            color: #5a6b91;
        }

        .side-link.active {
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #0f7bff);
            box-shadow: 0 14px 26px rgba(23, 105, 255, 0.24);
        }

        .side-link.active i { color: #fff; }

        .help-card {
            margin-top: auto;
            padding: 18px;
            border-radius: 8px;
            background: linear-gradient(135deg, #edf5ff, #dfeeff);
            border: 1px solid #d8e7ff;
        }

        .main-panel {
            min-width: 0;
            padding: 32px 36px 40px;
        }

        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        .page-kicker {
            color: #667498;
            font-size: .9rem;
            margin-bottom: 4px;
        }

        .page-title {
            font-size: clamp(1.65rem, 3vw, 2.25rem);
            font-weight: 850;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #506080;
            margin: 0;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid var(--line);
            box-shadow: 0 12px 30px rgba(33, 56, 92, 0.07);
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg, #1769ff, #7aa7ff);
            font-weight: 800;
        }

        .panel {
            background: var(--surface);
            border: 1px solid rgba(222, 232, 246, 0.88);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .status-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .summary-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-item {
            padding: 18px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid var(--line);
        }

        .summary-label {
            color: var(--muted);
            font-size: .86rem;
            font-weight: 700;
        }

        .summary-value {
            font-size: 1.55rem;
            font-weight: 850;
        }

        .status-card { padding: 22px; }

        .notification-card { padding: 22px; margin-bottom: 20px; }

        .notification-list {
            display: grid;
            gap: 14px;
        }

        .notification-item {
            padding: 16px;
            border-radius: 8px;
            background: #f8fbff;
            border: 1px solid #e6eef8;
        }

        .notification-item.overdue {
            background: linear-gradient(135deg, #fff5f5, #ffecec);
            border-color: #f6b5b5;
        }

        .notification-meta {
            color: var(--muted);
            font-size: .82rem;
        }

        .table-responsive {
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }

        .table thead th {
            background: #f8fbff;
            color: #526386;
            border-bottom: 1px solid #e8eef7;
            font-size: .82rem;
            padding: .9rem .75rem;
            white-space: nowrap;
        }

        .table tbody td {
            border-color: #edf2f8;
            padding: .85rem .75rem;
            font-weight: 600;
            vertical-align: middle;
        }

        .detail-text {
            max-width: 320px;
            min-width: 220px;
        }

        .btn,
        .form-control,
        .form-select,
        .modal-content {
            border-radius: 8px;
        }

        div.dataTables_wrapper div.dataTables_length label,
        div.dataTables_wrapper div.dataTables_filter label,
        div.dataTables_wrapper div.dataTables_info {
            color: var(--muted);
            font-weight: 650;
        }

        .empty-state {
            padding: 42px 20px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed #dbe4f1;
            border-radius: 8px;
            background: #fbfdff;
        }

        @media (max-width: 1199.98px) {
            .member-layout { grid-template-columns: 1fr; }
            .sidebar {
                position: static;
                height: auto;
                padding: 18px;
            }
            .sidebar-nav,
            .help-card { display: none; }
        }

        @media (max-width: 767.98px) {
            .main-panel { padding: 22px 16px 30px; }
            .top-row {
                align-items: stretch;
                flex-direction: column;
            }
            .user-chip { width: 100%; }
            .summary-strip { grid-template-columns: 1fr; }
            .status-card { padding: 16px; }
            .status-actions .btn { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <div class="member-layout">
        <aside class="sidebar d-flex flex-column">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-mark"><i class="fas fa-user-shield"></i></div>
                <div>
                    <p class="brand-title">SPK Kredit</p>
                    <div class="brand-subtitle">CU Lintang Tipo Jeruju</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <p class="nav-caption">Utama</p>
                    <a href="dashboard.php" class="side-link">
                        <i class="fas fa-house"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-section">
                    <p class="nav-caption">Pengajuan</p>
                    <a href="submit_application.php" class="side-link">
                        <i class="fas fa-circle-plus"></i>
                        <span>Ajukan Pinjaman</span>
                    </a>
                    <a href="status.php" class="side-link active">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Status Pengajuan</span>
                    </a>
                    <a href="notifikasi.php" class="side-link">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($unreadNotificationCount > 0): ?>
                            <span class="badge text-bg-danger ms-auto"><?php echo $unreadNotificationCount; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="nav-section">
                    <p class="nav-caption">Akun</p>
                    <a href="../proses/logout.php" class="side-link">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>

            <div class="help-card">
                <div class="fw-bold mb-1">Butuh Bantuan?</div>
                <p class="small-muted mb-3">Hubungi petugas CU untuk bantuan pengajuan.</p>
                <a href="submit_application.php" class="btn btn-sm btn-primary">Ajukan Baru</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="top-row">
                <div>
                    <div class="page-kicker">Riwayat pinjaman</div>
                    <h1 class="page-title">Status Pengajuan</h1>
                    <p class="page-subtitle">Pantau status pengajuan KTA dan KUR milikmu.</p>
                </div>
                <div class="user-chip">
                    <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($currentUserName, 0, 1))); ?></div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($currentUserName); ?></div>
                        <div class="small text-success fw-semibold">Anggota Aktif</div>
                    </div>
                </div>
            </header>

            <div class="status-actions">
                <a href="dashboard.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
                <a href="submit_application.php" class="btn btn-primary">Ajukan Baru</a>
            </div>

            <?php if (!$member): ?>
                <div class="alert alert-warning">
                    Akun ini belum terhubung ke data anggota. Silakan lengkapi profil anggota terlebih dahulu.
                </div>
            <?php else: ?>
                <section class="summary-strip">
                    <div class="summary-item">
                        <div class="summary-label">Total Pengajuan</div>
                        <div class="summary-value"><?php echo count($applications); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Menunggu Proses</div>
                        <div class="summary-value"><?php echo $pendingCount; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Disetujui</div>
                        <div class="summary-value"><?php echo $approvedCount; ?></div>
                    </div>
                </section>

                <section class="panel notification-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h2 class="section-title mb-1">Notifikasi Pencairan</h2>
                            <div class="small-muted">Pesan terbaru terkait pencairan pinjaman dan tenggat 7 hari.</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($unreadNotificationCount > 0): ?>
                                <span class="badge text-bg-danger"><?php echo $unreadNotificationCount; ?> belum dibaca</span>
                            <?php endif; ?>
                            <a href="notifikasi.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            Belum ada notifikasi pencairan.
                        </div>
                    <?php else: ?>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo notificationIsOverdue($notification['deadline_at']) ? 'overdue' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="fw-bold mb-1">
                                                <?php if ((int) $notification['is_read'] === 0): ?>
                                                    <span class="badge text-bg-danger me-2">Baru</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($notification['judul']); ?>
                                            </div>
                                            <div class="small-muted mb-2"><?php echo htmlspecialchars($notification['pesan']); ?></div>
                                            <div class="notification-meta">
                                                Pengajuan #<?php echo (int) $notification['pengajuan_id']; ?> ·
                                                Tenggat <?php echo statusDateLabel($notification['deadline_at']); ?> ·
                                                <?php echo (int) $notification['is_read'] === 1 ? 'Sudah dibaca' : 'Belum dibaca'; ?>
                                            </div>
                                        </div>
                                        <a href="status.php?detail=<?php echo (int) $notification['pengajuan_id']; ?>" class="btn btn-sm btn-outline-secondary">Lihat Detail Pengajuan</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (empty($applications)): ?>
                    <div class="panel">
                        <div class="empty-state">
                            <div class="text-muted mb-2">Belum ada pengajuan.</div>
                            <p class="mb-3">Silakan ajukan kredit terlebih dahulu untuk melihat statusnya di sini.</p>
                            <a href="submit_application.php" class="btn btn-primary">Ajukan Sekarang</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="panel status-card">
                        <div class="table-responsive">
                            <table id="applicationsTable" class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Jenis</th>
                                        <th>Jumlah Pinjaman</th>
                                        <th>Detail Singkat</th>
                                        <th>Status</th>
                                        <th>Persentase</th>
                                        <th>Kelayakan</th>
                                        <th>Tanggal</th>
                                        <th>Dokumen</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($applications as $app): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <span class="badge text-bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>">
                                                    <?php echo htmlspecialchars($app['jenis_kredit']); ?>
                                                </span>
                                            </td>
                                            <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                            <td class="small text-muted detail-text">
                                                <span class="d-inline-block text-truncate w-100" title="<?php echo htmlspecialchars(summarize_status_detail($app['detail_pinjaman'], $app['jenis_kredit'])); ?>">
                                                    <?php echo summarize_status_detail($app['detail_pinjaman'], $app['jenis_kredit']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge text-bg-<?php echo statusBadge($app['status']); ?>">
                                                    <?php echo statusLabel($app['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $app['persentase_saw'] !== null ? number_format((float) $app['persentase_saw'], 2) . '%' : '-'; ?></td>
                                            <td>
                                                <?php if ($app['kelayakan']): ?>
                                                    <span class="badge text-bg-<?php echo $app['kelayakan'] === 'layak' ? 'success' : 'danger'; ?> text-wrap">
                                                        <?php echo eligibilityLabel($app['kelayakan']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                            <td><?php echo (int) $app['dokumen_count']; ?> berkas</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="showDetail(<?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="fas fa-eye me-1"></i>Lihat
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent" class="row g-3"></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const autoOpenApplication = <?php echo $autoOpenApplication ? json_encode($autoOpenApplication, JSON_UNESCAPED_UNICODE) : 'null'; ?>;

        $(document).ready(function() {
            let table = null;
            if ($('#applicationsTable').length) {
                table = $('#applicationsTable').DataTable({
                    autoWidth: false,
                    scrollX: true,
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ data',
                        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                        paginate: {
                            previous: 'Sebelumnya',
                            next: 'Berikutnya'
                        },
                        zeroRecords: 'Data tidak ditemukan'
                    }
                });
            }

            if (autoOpenApplication) {
                setTimeout(function() {
                    showDetail(autoOpenApplication);
                }, 250);
            }
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showDetail(app) {
            let detail = {};
            try {
                detail = typeof app.detail_pinjaman === 'string' ? JSON.parse(app.detail_pinjaman || '{}') : (app.detail_pinjaman || {});
            } catch (e) {
                detail = {};
            }

            const statusMap = {
                pending: 'Menunggu',
                verified: 'Terverifikasi',
                document_rejected: 'Dokumen Ditolak',
                accepted: 'Disetujui',
                rejected: 'Ditolak',
            };

            const items = [
                ['ID', app.id],
                ['Jenis Kredit', app.jenis_kredit],
                ['Jumlah Pinjaman', 'Rp ' + Number(app.jumlah_pinjaman).toLocaleString('id-ID')],
                ['Status', statusMap[app.status] || app.status],
                ['Persentase', app.persentase_saw ? Number(app.persentase_saw).toFixed(2) + '%' : '-'],
                ['Kelayakan', app.kelayakan ? (app.kelayakan === 'layak' ? 'Memenuhi Batas Minimum' : 'Belum Memenuhi Batas Minimum') : '-'],
                ['Tanggal', new Date(app.created_at).toLocaleDateString('id-ID')],
                ['Dokumen', app.dokumen_count + ' file'],
            ];

            if (app.jenis_kredit === 'KTA') {
                items.push(['Tujuan', detail.tujuan_pinjaman || '-']);
                items.push(['Status Pekerjaan', detail.status_pekerjaan_asli || '-']);
                if (detail.status_pekerjaan_custom) {
                    items.push(['Keterangan', detail.status_pekerjaan_custom]);
                }
                if (detail.status_pekerjaan_asli === 'Pelajar/Mahasiswa') {
                    items.push(['Jumlah Tanggungan', detail.jumlah_tanggungan ?? '-']);
                } else {
                    items.push(['Pekerjaan', detail.status_pekerjaan || '-']);
                    items.push(['Tempat Kerja', detail.nama_tempat_kerja || '-']);
                    items.push(['Lama Bekerja', detail.lama_bekerja_bulan ? detail.lama_bekerja_bulan + ' bulan' : '-']);
                }
                items.push(['Penghasilan', detail.penghasilan_bulanan ? 'Rp ' + Number(detail.penghasilan_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Pengeluaran', detail.pengeluaran_bulanan ? 'Rp ' + Number(detail.pengeluaran_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Cicilan', detail.beban_cicilan_bulanan ? 'Rp ' + Number(detail.beban_cicilan_bulanan).toLocaleString('id-ID') : '-']);
            } else {
                items.push(['Nama Usaha', detail.nama_usaha || '-']);
                items.push(['Bidang Usaha', detail.bidang_usaha || '-']);
                items.push(['Alamat Usaha', detail.alamat_usaha || '-']);
                items.push(['Lama Usaha', detail.lama_usaha_bulan ? detail.lama_usaha_bulan + ' bulan' : '-']);
                items.push(['Omzet', detail.omzet_bulanan ? 'Rp ' + Number(detail.omzet_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Laba', detail.laba_bersih_bulanan ? 'Rp ' + Number(detail.laba_bersih_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Legalitas', detail.legalitas_usaha || '-']);
                items.push(['Tujuan Dana', detail.tujuan_dana || '-']);
            }

            const html = items.map(function(item) {
                return `
                    <div class="col-md-6">
                        <div class="p-3 border rounded h-100 bg-light">
                            <div class="text-muted small">${escapeHtml(item[0])}</div>
                            <div class="fw-semibold">${escapeHtml(item[1])}</div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#detailContent').html(html);
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    </script>
</body>
</html>
