<?php
// Halaman anggota untuk melihat semua notifikasi pencairan dan status baca.
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
     WHERE a.user_id = ? LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
    $action = $_POST['action'] ?? '';
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if ($action === 'mark_read' && $notificationId > 0) {
        markNotificationAsRead($conn, $notificationId, (int) $member['anggota_id']);
        header('Location: notifikasi.php?success=1');
        exit();
    }

    if ($action === 'mark_all_read') {
        markAllNotificationsAsRead($conn, (int) $member['anggota_id']);
        header('Location: notifikasi.php?success=2');
        exit();
    }

    header('Location: notifikasi.php');
    exit();
}

$notifications = [];
$unreadCount = 0;
if ($member) {
    $notifications = getMemberNotifications($conn, (int) $member['anggota_id'], 0);
    $unreadCount = getUnreadNotificationCount($conn, (int) $member['anggota_id']);
}

function notificationDateLabel($date)
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

function notificationDueText($date)
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if (!$timestamp) {
        return '-';
    }

    $remaining = $timestamp - time();
    if ($remaining < 0) {
        return 'Tenggat terlewat';
    }

    $days = (int) floor($remaining / 86400);
    if ($days <= 0) {
        return 'Hari ini';
    }

    return $days . ' hari lagi';
}

function notificationStatusClass($isRead)
{
    return (int) $isRead === 1 ? 'secondary' : 'danger';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .avatar,
        .icon-box {
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
            font-size: clamp(1.65rem, 3vw, 2.3rem);
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

        .notification-card { padding: 22px; }

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

        .empty-state {
            padding: 42px 20px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed #dbe4f1;
            border-radius: 8px;
            background: #fbfdff;
        }

        .btn,
        .form-control,
        .form-select,
        .modal-content {
            border-radius: 8px;
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
        }
    </style>
</head>
<body>
    <div class="member-layout">
        <aside class="sidebar d-flex flex-column">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-mark"><i class="fas fa-user-group"></i></div>
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
                    <a href="status.php" class="side-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Status Pengajuan</span>
                    </a>
                    <a href="notifikasi.php" class="side-link active">
                        <i class="fas fa-bell"></i>
                        <span>Notifikasi</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge text-bg-light ms-auto"><?php echo $unreadCount; ?></span>
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
                <a href="status.php" class="btn btn-sm btn-primary">Cek Status</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="top-row">
                <div>
                    <div class="page-kicker">Pesan dari CU</div>
                    <h1 class="page-title">Notifikasi</h1>
                    <p class="page-subtitle">Daftar semua notifikasi terkait pencairan pinjaman Anda.</p>
                </div>
                <div class="user-chip">
                    <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($member['nama'] ?? 'A', 0, 1))); ?></div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($member['nama'] ?? 'Anggota CU'); ?></div>
                        <div class="small text-success fw-semibold">Anggota Aktif</div>
                    </div>
                </div>
            </header>

            <?php if (!$member): ?>
                <div class="alert alert-warning">
                    Akun ini belum terhubung ke data anggota. Silakan lengkapi profil anggota terlebih dahulu.
                </div>
            <?php else: ?>
                <section class="summary-strip">
                    <div class="summary-item">
                        <div class="summary-label">Total Notifikasi</div>
                        <div class="summary-value"><?php echo count($notifications); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Belum Dibaca</div>
                        <div class="summary-value"><?php echo $unreadCount; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Sudah Dibaca</div>
                        <div class="summary-value"><?php echo max(0, count($notifications) - $unreadCount); ?></div>
                    </div>
                </section>

                <section class="panel notification-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h2 class="section-title mb-1">Daftar Notifikasi</h2>
                            <div class="small-muted">Pilih notifikasi untuk menandainya sebagai sudah dibaca.</div>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" class="m-0">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Tandai Semua Dibaca</button>
                            </form>
                        <?php endif; ?>
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
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                                <span class="badge text-bg-<?php echo notificationStatusClass($notification['is_read']); ?>">
                                                    <?php echo (int) $notification['is_read'] === 1 ? 'Sudah Dibaca' : 'Belum Dibaca'; ?>
                                                </span>
                                                <span class="badge text-bg-light border">Pengajuan #<?php echo (int) $notification['pengajuan_id']; ?></span>
                                                <span class="badge text-bg-info">
                                                    <?php echo htmlspecialchars($notification['jenis_kredit']); ?>
                                                </span>
                                            </div>
                                            <div class="fw-bold mb-1"><?php echo htmlspecialchars($notification['judul']); ?></div>
                                            <div class="small-muted mb-2"><?php echo htmlspecialchars($notification['pesan']); ?></div>
                                            <div class="notification-meta">
                                                Tenggat: <?php echo notificationDateLabel($notification['deadline_at']); ?> ·
                                                <?php echo notificationDueText($notification['deadline_at']); ?> ·
                                                Dibuat <?php echo notificationDateLabel($notification['created_at']); ?>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column gap-2">
                                            <?php if ((int) $notification['is_read'] === 0): ?>
                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo (int) $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary">Tandai Dibaca</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn btn-sm btn-outline-secondary disabled">Sudah Dibaca</span>
                                            <?php endif; ?>
                                            <a href="status.php?detail=<?php echo (int) $notification['pengajuan_id']; ?>" class="btn btn-sm btn-outline-secondary">Lihat Detail Pengajuan</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
