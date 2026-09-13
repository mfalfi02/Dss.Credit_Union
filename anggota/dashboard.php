<?php
// Inisialisasi sesi dan validasi akses untuk role anggota.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';
require_once '../function/notification.php';

// Ambil data pengguna aktif dan ringkasan pengajuan untuk ditampilkan di dashboard.
$conn = getDBConnection();
$currentUserName = 'Anggota';
$member = null;
$applications = [];
$notifications = [];
$unreadNotificationCount = 0;
$latestApplication = null;
$totalApplications = 0;
$approvedApplications = 0;
$activeLoanAmount = 0;
$pendingApplications = 0;
$loanMinimumViolationApplications = [];

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare(
        'SELECT a.id AS anggota_id, a.nama, u.username
         FROM anggota a
         JOIN users u ON a.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
        if (!empty($member['nama'])) {
            $currentUserName = $member['nama'];
        } elseif (!empty($member['username'])) {
            $currentUserName = $member['username'];
        }
    }

    if ($member) {
        $stmt = $conn->prepare(
            "SELECT p.*,
                    h.skor_terbobot, h.persentase_saw, h.kelayakan,
                    (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) AS dokumen_count
             FROM pengajuan p
             LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
             WHERE p.anggota_id = ?
             ORDER BY p.created_at DESC"
        );
        if ($stmt) {
            $stmt->bind_param('i', $member['anggota_id']);
            $stmt->execute();
            $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $notifications = getMemberNotifications($conn, (int) $member['anggota_id'], 5);
        $unreadNotificationCount = getUnreadNotificationCount($conn, (int) $member['anggota_id']);
    }
}

$totalApplications = count($applications);
foreach ($applications as $application) {
    if ($application['status'] === 'accepted') {
        $approvedApplications++;
        $activeLoanAmount += (float) $application['jumlah_pinjaman'];
    }
    if ($application['status'] === 'pending') {
        $pendingApplications++;
    }
}
$latestApplication = $applications[0] ?? null;

function dashboardMinimumLoanAmount(string $jenisKredit): int
{
    return $jenisKredit === 'KUR' ? 5000000 : 1000000;
}

function dashboardStatusLabel($status)
{
    return match ($status) {
        'pending' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'document_rejected' => 'Dokumen Ditolak',
        'accepted' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => ucfirst((string) $status),
    };
}

function dashboardStatusClass($status)
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

function dashboardDate($date)
{
    if (!$date) {
        return '-';
    }

    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function dashboardDeadlineLabel($date)
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

function rupiah($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

foreach ($applications as $application) {
    $jenisKredit = (string) ($application['jenis_kredit'] ?? '');
    $minimumPinjaman = dashboardMinimumLoanAmount($jenisKredit);
    $jumlahPinjaman = (float) ($application['jumlah_pinjaman'] ?? 0);

    if ($minimumPinjaman > 0 && $jumlahPinjaman > 0 && $jumlahPinjaman < $minimumPinjaman) {
        $loanMinimumViolationApplications[] = [
            'id' => (int) $application['id'],
            'jenis_kredit' => $jenisKredit,
            'jumlah_pinjaman' => $jumlahPinjaman,
            'minimum_pinjaman' => $minimumPinjaman,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Metadata dasar halaman dan library tampilan -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor Anggota - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1769ff;
            --primary-dark: #1048c9;
            --ink: #10204a;
            --muted: #65708d;
            --line: #e7eef8;
            --surface: rgba(255, 255, 255, 0.92);
            --soft-blue: #eef6ff;
            --soft-green: #eafaf3;
            --soft-orange: #fff3df;
            --soft-purple: #f2edff;
        }

        * {
            letter-spacing: 0;
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

        a {
            text-decoration: none;
        }

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
            background: rgba(255, 255, 255, 0.82);
            border-right: 1px solid var(--line);
            backdrop-filter: blur(18px);
        }

        .brand-mark,
        .avatar,
        .icon-box,
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 16px;
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

        .nav-section {
            margin-top: 26px;
        }

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

        .side-link.active i {
            color: #fff;
        }

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

        .user-chip-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-chip-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .user-chip-notify {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #1769ff;
            background: #eaf2ff;
            border: 1px solid #d7e6ff;
            position: relative;
        }

        .user-chip-notify .badge {
            position: absolute;
            top: -6px;
            right: -6px;
            font-size: .62rem;
            line-height: 1;
            padding: .28rem .42rem;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg, #1769ff, #7aa7ff);
            font-weight: 800;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(330px, .95fr);
            gap: 24px;
        }

        .content-stack {
            display: grid;
            gap: 20px;
        }

        .panel {
            background: var(--surface);
            border: 1px solid rgba(222, 232, 246, 0.88);
            border-radius: 8px;
            box-shadow: 0 18px 46px rgba(34, 58, 95, 0.08);
        }

        .hero-panel {
            position: relative;
            overflow: hidden;
            min-height: 290px;
            padding: 34px;
            color: #fff;
            background:
                linear-gradient(112deg, rgba(17, 74, 207, 0.96), rgba(22, 121, 255, 0.95) 54%, rgba(36, 160, 255, 0.92));
        }

        .hero-panel::before {
            content: "";
            position: absolute;
            top: -45px;
            right: 210px;
            width: 150px;
            height: 430px;
            background: rgba(255, 255, 255, 0.10);
            transform: rotate(24deg);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 580px;
        }

        .hero-label {
            font-weight: 750;
            margin-bottom: 18px;
        }

        .hero-title {
            font-size: clamp(2rem, 4vw, 3.05rem);
            line-height: 1.1;
            font-weight: 850;
            margin-bottom: 16px;
        }

        .hero-copy {
            max-width: 460px;
            color: rgba(255, 255, 255, 0.86);
            font-size: 1.03rem;
            margin-bottom: 24px;
        }

        .btn-hero {
            min-width: 190px;
            padding: 12px 18px;
            border-radius: 8px;
            color: var(--primary);
            font-weight: 800;
            background: #fff;
            border: 0;
        }

        .hero-visual {
            position: absolute;
            right: 30px;
            bottom: 0;
            width: 280px;
            height: 245px;
            z-index: 1;
        }

        .person-head,
        .person-body,
        .phone,
        .coin,
        .check-bubble,
        .mini-card {
            position: absolute;
        }

        .person-head {
            right: 74px;
            top: 18px;
            width: 86px;
            height: 86px;
            border-radius: 42% 42% 48% 48%;
            background: #ffd5bd;
            box-shadow: inset 0 -10px 0 rgba(225, 109, 82, 0.14);
        }

        .person-head::before {
            content: "";
            position: absolute;
            inset: -12px -8px 42px -9px;
            border-radius: 46px 46px 28px 28px;
            background: #1d2e62;
        }

        .person-head::after {
            content: "";
            position: absolute;
            left: 24px;
            top: 46px;
            width: 36px;
            height: 16px;
            border-bottom: 4px solid #d75d56;
            border-radius: 0 0 28px 28px;
        }

        .person-body {
            right: 25px;
            bottom: -16px;
            width: 170px;
            height: 150px;
            border-radius: 72px 72px 8px 8px;
            background: linear-gradient(135deg, #4fa1ff, #1d58db);
        }

        .phone {
            right: 145px;
            bottom: 76px;
            width: 48px;
            height: 74px;
            border-radius: 10px;
            background: #1a2753;
            transform: rotate(-10deg);
            box-shadow: 0 16px 22px rgba(9, 21, 56, 0.24);
        }

        .mini-card {
            left: 10px;
            top: 88px;
            width: 86px;
            height: 58px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: 0 16px 28px rgba(15, 44, 109, 0.18);
        }

        .mini-card::before,
        .mini-card::after {
            content: "";
            position: absolute;
            left: 16px;
            height: 7px;
            border-radius: 8px;
            background: #8ebdff;
        }

        .mini-card::before {
            top: 17px;
            width: 48px;
        }

        .mini-card::after {
            top: 33px;
            width: 32px;
        }

        .check-bubble {
            left: 16px;
            top: 22px;
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg, #3fc3ff, #1769ff);
            box-shadow: 0 14px 28px rgba(8, 48, 133, 0.22);
        }

        .coin {
            left: 68px;
            bottom: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 850;
            background: linear-gradient(135deg, #ffbd38, #ff8d20);
            border: 5px solid rgba(255, 255, 255, 0.36);
        }

        .section-title {
            font-size: 1.08rem;
            font-weight: 850;
            margin: 0;
        }

        .summary-card {
            padding: 24px;
        }

        .metric {
            display: flex;
            gap: 16px;
            align-items: center;
            padding: 14px 0;
        }

        .metric + .metric,
        .notice + .notice,
        .loan-row + .loan-row {
            border-top: 1px solid var(--line);
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            font-size: 1.05rem;
        }

        .icon-blue {
            color: #1769ff;
            background: var(--soft-blue);
        }

        .icon-green {
            color: #0d9f68;
            background: var(--soft-green);
        }

        .icon-orange {
            color: #e28700;
            background: var(--soft-orange);
        }

        .icon-purple {
            color: #7554e8;
            background: var(--soft-purple);
        }

        .metric-label {
            color: var(--muted);
            font-size: .88rem;
            margin-bottom: 2px;
        }

        .metric-value {
            font-size: 1.25rem;
            font-weight: 850;
        }

        .latest-card {
            padding: 22px;
        }

        .progress-flow {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 22px;
        }

        .flow-item {
            position: relative;
            text-align: center;
            color: #73809d;
            font-size: .78rem;
            font-weight: 750;
        }

        .flow-dot {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            background: #dce5f2;
            margin-bottom: 8px;
        }

        .flow-item.done .flow-dot {
            background: #1769ff;
        }

        .flow-item.active .flow-dot {
            background: #ffae25;
        }

        .score-box {
            padding-left: 22px;
            border-left: 1px solid var(--line);
        }

        .score-value {
            font-size: 1.9rem;
            font-weight: 850;
        }

        .loan-products,
        .quick-actions {
            padding: 20px;
        }

        .loan-row {
            display: grid;
            grid-template-columns: 46px minmax(0, 1fr) auto auto;
            gap: 14px;
            align-items: center;
            padding: 15px 0;
        }

        .loan-name {
            font-weight: 850;
            margin-bottom: 2px;
        }

        .rate {
            min-width: 110px;
            text-align: right;
            color: #25365f;
            font-weight: 850;
        }

        .round-action {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            background: var(--primary);
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .action-tile {
            min-height: 96px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 14px;
            border-radius: 8px;
            color: var(--ink);
            background: #f3f7fc;
            font-weight: 800;
            text-align: center;
        }

        .notice {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr);
            gap: 14px;
            padding: 16px 0;
        }

        .timeline-panel {
            padding: 20px;
            background: rgba(238, 246, 255, 0.82);
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .step {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 10px;
            align-items: start;
            padding: 14px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.82);
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            color: #1769ff;
            background: #dcecff;
            font-weight: 850;
        }

        .step-title {
            font-size: .86rem;
            font-weight: 850;
            margin-bottom: 3px;
        }

        .step-text {
            color: var(--muted);
            font-size: .78rem;
            margin: 0;
        }

        .empty-state {
            padding: 20px;
            border-radius: 8px;
            background: #f5f9fe;
            color: var(--muted);
        }

        @media (max-width: 1199.98px) {
            .member-layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
                height: auto;
                padding: 18px;
            }

            .sidebar-nav,
            .help-card {
                display: none;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .main-panel {
                padding: 22px 16px 30px;
            }

            .top-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .user-chip {
                width: 100%;
            }

            .hero-panel {
                min-height: 0;
                padding: 26px 22px;
            }

            .hero-visual {
                display: none;
            }

            .progress-flow,
            .steps {
                grid-template-columns: 1fr;
            }

            .latest-card .row {
                gap: 20px;
            }

            .score-box {
                padding-left: 0;
                border-left: 0;
                border-top: 1px solid var(--line);
                padding-top: 18px;
            }

            .loan-row {
                grid-template-columns: 42px minmax(0, 1fr) auto;
            }

            .rate {
                grid-column: 2 / 4;
                text-align: left;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }
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
                    <a href="dashboard.php" class="side-link active">
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
                <a href="status.php" class="btn btn-sm btn-primary">Cek Status</a>
            </div>
        </aside>

        <main class="main-panel">
            <header class="top-row">
                <div>
                    <div class="page-kicker">Selamat datang kembali,</div>
                    <h1 class="page-title"><?php echo htmlspecialchars($currentUserName); ?></h1>
                    <p class="page-subtitle">Kelola pengajuan pinjaman Anda dan pantau statusnya kapan saja.</p>
                </div>
                <div class="user-chip">
                    <div class="user-chip-main">
                        <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($currentUserName, 0, 1))); ?></div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($currentUserName); ?></div>
                            <div class="user-chip-meta">
                                <span class="small text-success fw-semibold">Anggota Aktif</span>
                                <a href="notifikasi.php" class="user-chip-notify" aria-label="Lihat notifikasi">
                                    <i class="fas fa-bell"></i>
                                    <?php if ($unreadNotificationCount > 0): ?>
                                        <span class="badge text-bg-danger rounded-pill"><?php echo $unreadNotificationCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-grid">
                <div class="content-stack">
                    <?php if (!empty($loanMinimumViolationApplications)): ?>
                        <section class="panel" style="border: 1px solid #f3c15b; background: #fff8e6;">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div>
                                    <div class="fw-bold text-dark mb-1">
                                        <i class="fas fa-triangle-exclamation text-warning me-2"></i>
                                        Ada pengajuan di bawah batas minimum
                                    </div>
                                    <div class="small-muted mb-0">
                                        Kami mendeteksi <?php echo count($loanMinimumViolationApplications); ?> pengajuan lama yang nominalnya masih di bawah batas minimum pinjaman.
                                    </div>
                                </div>
                                <a href="status.php" class="btn btn-warning fw-semibold">Cek Detail Pengajuan</a>
                            </div>
                            <div class="mt-3 d-grid gap-2">
                                <?php foreach ($loanMinimumViolationApplications as $violation): ?>
                                    <div class="bg-white rounded-3 border px-3 py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div class="fw-semibold">
                                            #<?php echo (int) $violation['id']; ?> -
                                            <?php echo htmlspecialchars($violation['jenis_kredit']); ?>
                                        </div>
                                        <div class="small-muted">
                                            Pinjaman Minimum (<?php echo htmlspecialchars($violation['jenis_kredit']); ?>) adalah <?php echo rupiah($violation['minimum_pinjaman']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="panel hero-panel">
                        <div class="hero-content">
                            <div class="hero-label">Ajukan pinjaman sekarang</div>
                            <h2 class="hero-title">Wujudkan rencana Anda bersama Credit Union</h2>
                            <p class="hero-copy">Ajukan KTA atau KUR melalui proses yang ringkas, jelas, dan mudah dipantau.</p>
                            <a href="submit_application.php" class="btn btn-hero">
                                Ajukan Sekarang <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                        <div class="hero-visual" aria-hidden="true">
                            <div class="check-bubble"><i class="fas fa-check fa-xl"></i></div>
                            <div class="mini-card"></div>
                            <div class="coin">Rp</div>
                            <div class="person-body"></div>
                            <div class="person-head"></div>
                            <div class="phone"></div>
                        </div>
                    </section>

                    <section class="panel latest-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h2 class="section-title">Pengajuan Terbaru</h2>
                                <div class="small-muted">Status terakhir dari pengajuan milik Anda</div>
                            </div>
                            <?php if ($latestApplication): ?>
                                <span class="badge text-bg-<?php echo dashboardStatusClass($latestApplication['status']); ?>">
                                    <?php echo dashboardStatusLabel($latestApplication['status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($latestApplication): ?>
                            <div class="row align-items-center">
                                <div class="col-lg-8">
                                    <div class="fw-bold fs-5 mb-1">
                                        <?php echo htmlspecialchars($latestApplication['jenis_kredit']); ?> - <?php echo rupiah($latestApplication['jumlah_pinjaman']); ?>
                                    </div>
                                    <div class="small-muted mb-3">
                                        ID: PK-<?php echo date('Y', strtotime($latestApplication['created_at'])); ?>-<?php echo str_pad((string) $latestApplication['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                    <div class="progress-flow">
                                        <div class="flow-item done">
                                            <span class="flow-dot"><i class="fas fa-file-signature"></i></span>
                                            <div>Pengajuan</div>
                                        </div>
                                        <div class="flow-item <?php echo in_array($latestApplication['status'], ['pending'], true) ? 'active' : 'done'; ?>">
                                            <span class="flow-dot"><i class="fas fa-folder-open"></i></span>
                                            <div>Verifikasi</div>
                                        </div>
                                        <div class="flow-item <?php echo in_array($latestApplication['status'], ['verified', 'accepted', 'rejected'], true) ? 'done' : ''; ?>">
                                            <span class="flow-dot"><i class="fas fa-chart-simple"></i></span>
                                            <div>Penilaian</div>
                                        </div>
                                        <div class="flow-item <?php echo in_array($latestApplication['status'], ['accepted', 'rejected'], true) ? 'done' : ''; ?>">
                                            <span class="flow-dot"><i class="fas fa-square-check"></i></span>
                                            <div>Keputusan</div>
                                        </div>
                                        <div class="flow-item <?php echo $latestApplication['status'] === 'accepted' ? 'done' : ''; ?>">
                                            <span class="flow-dot"><i class="fas fa-money-bill-transfer"></i></span>
                                            <div>Pencairan</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="score-box">
                                        <div class="small-muted">Skor SAW Sementara</div>
                                        <div class="score-value">
                                            <?php echo $latestApplication['persentase_saw'] !== null ? number_format((float) $latestApplication['persentase_saw'], 2) . '%' : '-'; ?>
                                        </div>
                                        <div class="mb-3">
                                            <?php if ($latestApplication['kelayakan']): ?>
                                                <span class="badge text-bg-<?php echo $latestApplication['kelayakan'] === 'layak' ? 'success' : 'danger'; ?>">
                                                    <?php echo $latestApplication['kelayakan'] === 'layak' ? 'Layak' : 'Belum Layak'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge text-bg-light border">Belum Dinilai</span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="status.php" class="btn btn-outline-primary w-100">Lihat Detail</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                Belum ada pengajuan. Mulai dari menu pengajuan untuk mengisi data KTA atau KUR.
                            </div>
                        <?php endif; ?>
                    </section>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <section class="panel loan-products h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="section-title">Produk Pinjaman</h2>
                                    <a href="submit_application.php" class="small fw-bold">Ajukan <i class="fas fa-arrow-right ms-1"></i></a>
                                </div>
                                <div class="loan-row">
                                    <div class="icon-box icon-blue"><i class="fas fa-id-card"></i></div>
                                    <div>
                                        <div class="loan-name">KTA</div>
                                        <div class="small-muted">Kredit tanpa agunan untuk kebutuhan konsumtif.</div>
                                    </div>
                                    <div class="rate">Konsumtif</div>
                                    <a class="round-action" href="submit_application.php?jenis=KTA" aria-label="Ajukan KTA">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                                <div class="loan-row">
                                    <div class="icon-box icon-green"><i class="fas fa-briefcase"></i></div>
                                    <div>
                                        <div class="loan-name">KUR</div>
                                        <div class="small-muted">Kredit usaha rakyat untuk pengembangan usaha.</div>
                                    </div>
                                    <div class="rate">Usaha</div>
                                    <a class="round-action" href="submit_application.php?jenis=KUR" aria-label="Ajukan KUR">
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </section>
                        </div>
                        <div class="col-lg-5">
                            <section class="panel quick-actions h-100">
                                <h2 class="section-title mb-3">Aksi Cepat</h2>
                                <div class="action-grid">
                                    <a class="action-tile" href="submit_application.php">
                                        <span class="icon-box icon-blue"><i class="fas fa-plus"></i></span>
                                        <span>Ajukan Pinjaman</span>
                                    </a>
                                    <a class="action-tile" href="status.php">
                                        <span class="icon-box icon-orange"><i class="fas fa-clock-rotate-left"></i></span>
                                        <span>Cek Status</span>
                                    </a>
                                </div>
                            </section>
                        </div>
                    </div>

                    <section class="panel timeline-panel">
                        <h2 class="section-title">Alur Pengajuan Pinjaman</h2>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>
                                    <div class="step-title">Ajukan</div>
                                    <p class="step-text">Pilih jenis pinjaman dan isi formulir.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>
                                    <div class="step-title">Lengkapi</div>
                                    <p class="step-text">Unggah dokumen sesuai persyaratan.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div>
                                    <div class="step-title">Verifikasi</div>
                                    <p class="step-text">Dokumen diperiksa oleh petugas.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div>
                                    <div class="step-title">Penilaian</div>
                                    <p class="step-text">Pengajuan dinilai dengan metode SAW.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">5</div>
                                <div>
                                    <div class="step-title">Keputusan</div>
                                    <p class="step-text">Lihat hasil pengajuan di status.</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="content-stack">
                    <section class="panel summary-card">
                        <h2 class="section-title mb-2">Ringkasan Saya</h2>
                        <div class="metric">
                            <div class="icon-box icon-blue"><i class="fas fa-file-circle-check"></i></div>
                            <div>
                                <div class="metric-label">Total Pengajuan</div>
                                <div class="metric-value"><?php echo $totalApplications; ?></div>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="icon-box icon-green"><i class="fas fa-check"></i></div>
                            <div>
                                <div class="metric-label">Pengajuan Disetujui</div>
                                <div class="metric-value"><?php echo $approvedApplications; ?></div>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="icon-box icon-purple"><i class="fas fa-wallet"></i></div>
                            <div>
                                <div class="metric-label">Total Pinjaman Disetujui</div>
                                <div class="metric-value"><?php echo rupiah($activeLoanAmount); ?></div>
                            </div>
                        </div>
                        <a href="status.php" class="fw-bold d-inline-flex align-items-center gap-2 mt-2">
                            Lihat Ringkasan Lengkap <i class="fas fa-arrow-right"></i>
                        </a>
                    </section>

                    <section class="panel summary-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h2 class="section-title">Notifikasi</h2>
                            <?php if ($unreadNotificationCount > 0): ?>
                                <span class="badge text-bg-danger"><?php echo $unreadNotificationCount; ?> belum dibaca</span>
                            <?php endif; ?>
                            <a href="notifikasi.php" class="small fw-bold">Lihat Semua</a>
                        </div>
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notice">
                                    <div class="icon-box icon-green"><i class="fas fa-bell"></i></div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($notification['judul']); ?></div>
                                        <div class="small-muted mb-1">
                                            <?php echo htmlspecialchars($notification['pesan']); ?>
                                        </div>
                                        <div class="small-muted">
                                            Tenggat: <?php echo dashboardDate($notification['deadline_at']); ?>
                                            (<?php echo dashboardDeadlineLabel($notification['deadline_at']); ?>)
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">Belum ada notifikasi pengajuan.</div>
                        <?php endif; ?>
                    </section>

                    
                </aside>
            </div>
        </main>
    </div>

    <!-- Script Bootstrap untuk komponen interaktif jika dibutuhkan -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
