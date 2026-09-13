<?php
// Halaman anggota untuk mengisi form pengajuan KTA atau KUR beserta dokumen.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';
$conn = getDBConnection();

// Get member data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM anggota WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$currentUserName = !empty($member['nama']) ? $member['nama'] : 'Anggota CU';

$selectedJenis = $_GET['jenis'] ?? '';
if (!in_array($selectedJenis, ['KTA', 'KUR'], true)) {
    $selectedJenis = '';
}

$errorMessage = '';
if (isset($_GET['error'])) {
    switch ((string) $_GET['error']) {
        case '1':
            $errorMessage = 'Pengajuan gagal diproses. Cek koneksi database atau unggahan dokumen kamu.';
            break;
        case '2':
            $errorMessage = 'Masih ada data yang belum lengkap. Silakan lengkapi form sesuai jenis pinjaman yang dipilih.';
            break;
        case '3':
            $errorMessage = 'Jumlah pinjaman belum memenuhi minimum untuk jenis pinjaman yang dipilih.';
            break;
        case '4':
            $errorMessage = 'Jumlah pinjaman melebihi batas maksimum untuk jenis pinjaman yang dipilih.';
            break;
        default:
            $errorMessage = 'Pengajuan gagal diproses.';
            break;
    }
}

$errorFields = [];
if (!empty($_GET['fields'])) {
    $errorFields = array_values(array_filter(array_map('trim', explode(',', (string) $_GET['fields']))));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Pengajuan - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1769ff;
            --primary-dark: #0f4fd6;
            --success: #10b66f;
            --ink: #10204a;
            --muted: #65708d;
            --line: #e6edf7;
            --surface: rgba(255, 255, 255, 0.94);
        }

        * {
            letter-spacing: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            color: var(--ink);
            background:
                radial-gradient(circle at 76% 2%, rgba(23, 105, 255, 0.10), transparent 30%),
                radial-gradient(circle at 18% 18%, rgba(16, 182, 111, 0.08), transparent 28%),
                linear-gradient(180deg, #f9fbff 0%, #eef4fb 100%);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }

        a {
            text-decoration: none;
        }

        .application-layout {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: 100vh;
        }

        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 28px 20px;
            background: rgba(255, 255, 255, 0.88);
            border-right: 1px solid var(--line);
            backdrop-filter: blur(18px);
        }

        .brand-mark,
        .nav-icon,
        .user-avatar,
        .choice-icon,
        .benefit-check,
        .step-number,
        .notice-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #2d9bff);
            box-shadow: 0 12px 24px rgba(23, 105, 255, 0.24);
        }

        .brand-title {
            font-size: 1.08rem;
            font-weight: 850;
            margin: 0;
        }

        .brand-subtitle,
        .small-muted {
            color: var(--muted);
            font-size: .83rem;
        }

        .nav-section {
            margin-top: 28px;
        }

        .nav-caption {
            color: #7e89a5;
            font-size: .72rem;
            font-weight: 850;
            margin: 0 0 10px 12px;
            text-transform: uppercase;
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
            font-weight: 700;
        }

        .nav-icon {
            width: 20px;
            color: #5c6c91;
        }

        .side-link.active {
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #0f7bff);
            box-shadow: 0 14px 26px rgba(23, 105, 255, 0.24);
        }

        .side-link.active .nav-icon {
            color: #fff;
        }

        .help-card {
            margin-top: auto;
            padding: 18px;
            border-radius: 8px;
            background: linear-gradient(135deg, #edf5ff, #deecff);
            border: 1px solid #d7e7ff;
        }

        .main-area {
            min-width: 0;
        }

        .app-topbar {
            height: 86px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 0 38px;
            background: rgba(255, 255, 255, 0.84);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(18px);
        }

        .menu-button,
        .notification-button {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--ink);
            background: #fff;
            border: 1px solid var(--line);
        }

        .notification-button {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            color: #fff;
            background: #ef233c;
            font-size: .68rem;
            font-weight: 850;
            line-height: 18px;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 16px;
            border-left: 1px solid var(--line);
        }

        .user-avatar {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), #7aa7ff);
            font-weight: 850;
        }

        .user-avatar::after {
            content: "";
            position: absolute;
            right: 1px;
            bottom: 3px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #16c172;
            border: 2px solid #fff;
        }

        .content-wrap {
            padding: 30px 34px 44px;
        }

        .breadcrumb-line {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #667498;
            font-size: .9rem;
            margin-bottom: 14px;
        }

        .breadcrumb-line span:last-child {
            color: var(--primary);
            font-weight: 800;
        }

        .page-title {
            font-size: clamp(1.75rem, 3vw, 2.35rem);
            font-weight: 850;
            margin: 0 0 6px;
        }

        .page-subtitle {
            color: #506080;
            margin: 0;
        }

        .application-stepper {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0;
            max-width: 1180px;
            margin: 28px auto 26px;
        }

        .step-item {
            position: relative;
            display: grid;
            justify-items: center;
            gap: 10px;
            color: #66718f;
            font-size: .88rem;
            font-weight: 750;
            text-align: center;
        }

        .step-item::before {
            content: "";
            position: absolute;
            top: 22px;
            left: 0;
            width: 50%;
            height: 2px;
            background: #cfd8e7;
            transform: translateX(-50%);
        }

        .step-item::after {
            content: "";
            position: absolute;
            top: 22px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #cfd8e7;
            z-index: 0;
        }

        .step-item:first-child::before,
        .step-item:last-child::after {
            display: none;
        }

        .step-item.active {
            color: var(--primary);
        }

        .step-item.active::after {
            background: var(--primary);
        }

        .step-item.done {
            color: #0b9f62;
        }

        .step-item.done::after {
            background: #0b9f62;
        }

        .step-number {
            position: relative;
            z-index: 1;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            color: #4c5875;
            background: #fff;
            border: 2px solid #cfd8e7;
            box-shadow: 0 10px 22px rgba(42, 64, 102, 0.08);
            font-size: 1.02rem;
            font-weight: 850;
        }

        .step-item.active .step-number {
            color: #fff;
            border-color: var(--primary);
            background: linear-gradient(135deg, var(--primary), #0f7bff);
            box-shadow: 0 12px 24px rgba(23, 105, 255, 0.28);
        }

        .step-item.done .step-number {
            color: #fff;
            border-color: #0b9f62;
            background: linear-gradient(135deg, #0b9f62, #18c47f);
        }

        .selection-panel,
        .panel-card {
            border: 1px solid rgba(222, 232, 246, 0.9);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 18px 46px rgba(34, 58, 95, 0.08);
        }

        .selection-panel {
            padding: 34px;
            margin-bottom: 26px;
        }

        .section-title {
            color: var(--ink);
            font-weight: 850;
        }

        .choice-card {
            position: relative;
            height: 100%;
            width: 100%;
            min-height: 360px;
            padding: 28px;
            text-align: left;
            appearance: none;
            cursor: pointer;
            border-radius: 8px;
            background: #fff;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }

        .choice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.11);
        }

        .choice-card.active {
            box-shadow: 0 18px 40px rgba(23, 105, 255, 0.16);
        }

        .choice-card.kta-card {
            border: 1.5px solid rgba(23, 105, 255, 0.45);
        }

        .choice-card.kur-card {
            border: 1.5px solid rgba(16, 182, 111, 0.45);
        }

        .choice-icon {
            width: 66px;
            height: 66px;
            border-radius: 14px;
            color: #fff;
            font-size: 1.3rem;
        }

        .kta-icon {
            background: linear-gradient(135deg, #1769ff, #0f7bff);
        }

        .kur-icon {
            background: linear-gradient(135deg, #0ba85d, #09c37a);
        }

        .loan-art {
            position: absolute;
            top: 32px;
            right: 32px;
            width: 120px;
            height: 96px;
        }

        .money-stack,
        .shop-art {
            position: absolute;
            inset: 0;
        }

        .money-stack::before,
        .money-stack::after {
            content: "";
            position: absolute;
            border-radius: 10px;
            background: linear-gradient(135deg, #6eb5ff, #1769ff);
            box-shadow: 0 12px 20px rgba(23, 105, 255, 0.22);
        }

        .money-stack::before {
            width: 76px;
            height: 52px;
            left: 12px;
            top: 28px;
        }

        .money-stack::after {
            width: 58px;
            height: 58px;
            right: 8px;
            top: 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffbd38, #ff8d20);
            border: 5px solid rgba(255, 255, 255, 0.7);
        }

        .shop-art::before {
            content: "";
            position: absolute;
            left: 22px;
            bottom: 8px;
            width: 76px;
            height: 58px;
            border-radius: 8px;
            background: linear-gradient(135deg, #28c783, #079e5d);
            box-shadow: 0 12px 20px rgba(11, 168, 93, 0.2);
        }

        .shop-art::after {
            content: "";
            position: absolute;
            left: 14px;
            top: 10px;
            width: 92px;
            height: 28px;
            border-radius: 8px 8px 14px 14px;
            background: repeating-linear-gradient(90deg, #12b96f 0 18px, #b8f2d4 18px 36px);
            box-shadow: 0 8px 15px rgba(11, 168, 93, 0.18);
        }

        .coin-row {
            position: absolute;
            right: 3px;
            bottom: 4px;
            width: 42px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(180deg, #ffd465, #f3a51e);
            box-shadow: 0 -11px 0 -4px #ffd465, 0 -22px 0 -8px #f3a51e;
        }

        .benefit-list {
            display: grid;
            gap: 13px;
            padding: 18px;
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.95), rgba(245, 249, 255, 0.92));
            border: 1px solid #e4edf9;
        }

        .kur-card .benefit-list {
            background: linear-gradient(135deg, rgba(239, 253, 246, 0.95), rgba(246, 252, 249, 0.92));
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #23345f;
            font-size: .93rem;
        }

        .benefit-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            color: #fff;
            background: var(--primary);
            font-size: .72rem;
        }

        .kur-card .benefit-check {
            background: var(--success);
        }

        .choice-cta {
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 18px;
            border-radius: 8px;
            color: #fff;
            font-weight: 850;
        }

        .kta-card .choice-cta {
            background: linear-gradient(135deg, var(--primary), #0f7bff);
        }

        .kur-card .choice-cta {
            background: linear-gradient(135deg, #0ba85d, #08bc74);
        }

        .comparison-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            max-width: 760px;
            margin: 34px auto 0;
            padding: 18px 22px;
            border-radius: 8px;
            background: #f7faff;
            border: 1px solid var(--line);
        }

        .notice-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            color: var(--primary);
            background: #e8f1ff;
        }

        .form-section {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(16px);
            pointer-events: none;
            transition: max-height .45s ease, opacity .35s ease, transform .35s ease;
        }

        .form-section.is-visible {
            max-height: 5000px;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .wizard-pane {
            display: none;
        }

        .wizard-pane.active {
            display: block;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .review-item {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f8fbff;
        }

        .review-label {
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .review-value {
            color: var(--ink);
            font-weight: 800;
            word-break: break-word;
        }

        .field-invalid .form-label {
            color: #dc3545;
            font-weight: 700;
        }

        .field-invalid .form-control,
        .field-invalid .form-select,
        .field-invalid .form-control:focus,
        .field-invalid .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.12);
        }

        .field-feedback {
            display: block;
            margin-top: .35rem;
            color: #dc3545;
            font-size: .875rem;
        }

        @media (max-width: 1199.98px) {
            .application-layout {
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
        }

        @media (max-width: 767.98px) {
            .app-topbar {
                height: auto;
                align-items: flex-start;
                padding: 18px;
            }

            .user-chip {
                padding-left: 0;
                border-left: 0;
            }

            .content-wrap {
                padding: 22px 16px 34px;
            }

            .application-stepper {
                grid-template-columns: 1fr;
                gap: 14px;
                margin: 22px 0;
            }

            .step-item {
                grid-template-columns: 46px minmax(0, 1fr);
                justify-items: start;
                text-align: left;
            }

            .step-item::before,
            .step-item::after {
                display: none;
            }

            .selection-panel {
                padding: 22px;
            }

            .choice-card {
                min-height: 0;
                padding: 22px;
            }

            .loan-art {
                position: relative;
                top: auto;
                right: auto;
                margin: 18px 0 4px;
            }

            .comparison-box {
                align-items: flex-start;
                flex-direction: column;
            }

            .review-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="application-layout">
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
                        <span class="nav-icon"><i class="fas fa-house"></i></span>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-section">
                    <p class="nav-caption">Pengajuan</p>
                    <a href="submit_application.php" class="side-link active">
                        <span class="nav-icon"><i class="fas fa-circle-plus"></i></span>
                        <span>Ajukan Pinjaman</span>
                    </a>
                    <a href="status.php" class="side-link">
                        <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
                        <span>Status Pengajuan</span>
                    </a>
                </div>
                <div class="nav-section">
                    <p class="nav-caption">Akun</p>
                    <a href="../proses/logout.php" class="side-link">
                        <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
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

        <div class="main-area">
            <header class="app-topbar">
                <a class="menu-button" href="dashboard.php" aria-label="Kembali ke dashboard">
                    <i class="fas fa-bars"></i>
                </a>
                <div class="d-flex align-items-center gap-3 ms-auto">
                    <a class="notification-button" href="status.php" aria-label="Lihat status pengajuan">
                        <i class="fas fa-bell"></i>
                        <?php if ($errorMessage !== ''): ?>
                            <span class="notification-badge">!</span>
                        <?php endif; ?>
                    </a>
                    <div class="user-chip">
                        <div class="user-avatar"><?php echo htmlspecialchars(strtoupper(substr($currentUserName, 0, 1))); ?></div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($currentUserName); ?></div>
                            <div class="small text-success fw-semibold">Anggota Aktif</div>
                        </div>
                        <i class="fas fa-chevron-down small-muted"></i>
                    </div>
                </div>
            </header>

            <main class="content-wrap">
                <div class="breadcrumb-line">
                    <a href="dashboard.php" class="text-muted">Dashboard</a>
                    <i class="fas fa-chevron-right small"></i>
                    <span>Pengajuan</span>
                    <i class="fas fa-chevron-right small"></i>
                    <span>Ajukan Pinjaman</span>
                </div>

                <div>
                    <h1 class="page-title">Ajukan Pengajuan Kredit</h1>
                    <p class="page-subtitle">Pilih jenis pinjaman yang sesuai dengan kebutuhan Anda.</p>
                </div>

                <div class="application-stepper">
                    <div class="step-item active" data-step="1">
                        <span class="step-number">1</span>
                        <span>Pilih Jenis Pinjaman</span>
                    </div>
                    <div class="step-item" data-step="2">
                        <span class="step-number">2</span>
                        <span>Isi Data & Informasi</span>
                    </div>
                    <div class="step-item" data-step="3">
                        <span class="step-number">3</span>
                        <span>Unggah Dokumen</span>
                    </div>
                    <div class="step-item" data-step="4">
                        <span class="step-number">4</span>
                        <span>Review & Kirim</span>
                    </div>
                </div>

                <?php if ($errorMessage !== '' && empty($errorFields)): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-0">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <section class="selection-panel" id="loanSelection">
                    <div class="text-center mb-4">
                        <h2 class="section-title fs-4 mb-2">Pilih jenis pinjaman yang ingin diajukan</h2>
                        <p class="page-subtitle">Silakan pilih salah satu jenis pinjaman di bawah ini untuk melanjutkan proses pengajuan.</p>
                    </div>

                    <div class="alert alert-danger d-none mb-4" id="selectionAlert">
                        <i class="fas fa-triangle-exclamation me-2"></i>Silakan pilih KTA atau KUR terlebih dahulu sebelum lanjut.
                    </div>

                    <div class="row g-4 justify-content-center">
                        <div class="col-xl-5 col-lg-6">
                            <button type="button" class="choice-card kta-card <?php echo $selectedJenis === 'KTA' ? 'active' : ''; ?>" data-jenis="KTA">
                                <div class="loan-art" aria-hidden="true">
                                    <div class="money-stack"></div>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="choice-icon kta-icon"><i class="fas fa-user"></i></div>
                                    <div>
                                        <h3 class="section-title fs-2 mb-1">KTA</h3>
                                        <div class="fw-bold">Kredit Tanpa Agunan</div>
                                    </div>
                                </div>
                                <p class="text-muted mb-4">Kredit tanpa agunan untuk berbagai kebutuhan pribadi Anda dengan proses cepat dan mudah.</p>
                                <div class="benefit-list">
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Tanpa agunan / jaminan</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Data penilaian berbasis kemampuan bayar</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Dokumen KTP dan bukti penghasilan</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Cocok untuk kebutuhan pribadi</span></div>
                                </div>
                                <div class="choice-cta">Pilih KTA <i class="fas fa-arrow-right"></i></div>
                            </button>
                        </div>

                        <div class="col-xl-5 col-lg-6">
                            <button type="button" class="choice-card kur-card <?php echo $selectedJenis === 'KUR' ? 'active' : ''; ?>" data-jenis="KUR">
                                <div class="loan-art" aria-hidden="true">
                                    <div class="shop-art"></div>
                                    <div class="coin-row"></div>
                                </div>
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="choice-icon kur-icon"><i class="fas fa-store"></i></div>
                                    <div>
                                        <h3 class="section-title fs-2 mb-1">KUR</h3>
                                        <div class="fw-bold">Kredit Usaha Rakyat</div>
                                    </div>
                                </div>
                                <p class="text-muted mb-4">Kredit untuk modal usaha dengan data usaha, omzet, laba, dan legalitas sebagai dasar penilaian.</p>
                                <div class="benefit-list">
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Untuk modal kerja atau investasi usaha</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Data usaha dan omzet ikut dinilai</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Dokumen KTP, foto usaha, dan legalitas</span></div>
                                    <div class="benefit-item"><span class="benefit-check"><i class="fas fa-check"></i></span><span>Mendukung pengembangan UMKM</span></div>
                                </div>
                                <div class="choice-cta">Pilih KUR <i class="fas fa-arrow-right"></i></div>
                            </button>
                        </div>
                    </div>

                    <div class="comparison-box">
                        <div class="d-flex align-items-center gap-3">
                            <div class="notice-icon"><i class="fas fa-circle-info"></i></div>
                            <div>
                                <div class="fw-bold">Masih bingung memilih jenis pinjaman?</div>
                                <div class="small-muted">KTA untuk kebutuhan pribadi, KUR untuk kebutuhan usaha.</div>
                            </div>
                        </div>
                        <a href="status.php" class="btn btn-outline-primary">Lihat Status</a>
                    </div>
                </section>

                <div class="form-section row justify-content-center <?php echo $selectedJenis !== '' ? 'is-visible' : ''; ?>" id="formSection">
                    <div class="col-xl-10">
                <form action="../proses/submit_application.php" method="POST" enctype="multipart/form-data" novalidate class="card panel-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                            <div>
                                <h3 class="section-title mb-1">Lengkapi Data Pengajuan</h3>
                                <p class="text-muted mb-0">Form di bawah akan menyesuaikan pilihan jenis pinjaman.</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge text-bg-light border">1. Pilih jenis</span>
                                <span class="badge text-bg-light border">2. Isi data</span>
                                <span class="badge text-bg-light border">3. Unggah dokumen</span>
                            </div>
                        </div>

                        <input type="hidden" name="jenis_kredit" id="jenis_kredit" value="<?php echo htmlspecialchars($selectedJenis); ?>">

                        <div class="wizard-pane active" data-pane="data">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kredit</label>
                                <div class="form-control bg-light">
                                    <strong id="jenisLabel"><?php echo $selectedJenis === 'KTA' ? 'KTA - Kredit Tanpa Agunan' : ($selectedJenis === 'KUR' ? 'KUR - Kredit Usaha Rakyat' : 'Belum dipilih'); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Pinjaman</label>
                                <input type="number" name="jumlah_pinjaman" class="form-control" min="1000000" max="300000000" step="10000" required>
                                <div class="form-text" id="jumlahPinjamanHelp">Minimum pinjaman akan menyesuaikan jenis yang dipilih.</div>
                                <div class="small mt-1" id="jumlahPinjamanStatus"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-primary mb-0 py-3">
                                    <strong>Tips:</strong> isi sesuai kondisi sebenarnya agar hasil penilaian lebih akurat.
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4 mb-0" id="jenisInfo">
                            KTA fokus ke kemampuan bayar pribadi. KUR fokus ke kelayakan dan performa usaha.
                        </div>

                        <div id="ktaFields" class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Data KTA</h5>
                                    <p class="text-muted mb-0">Isi data pribadi, penghasilan, dan dokumen pendukung.</p>
                                </div>
                                <span class="badge bg-primary">Pribadi</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tujuan Pinjaman</label>
                                    <input type="text" name="tujuan_pinjaman_kta" class="form-control" placeholder="Contoh: renovasi rumah, biaya pendidikan">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status Pekerjaan</label>
                                    <select name="status_pekerjaan_kta" class="form-select">
                                        <option value="">Pilih status</option>
                                        <option value="Karyawan">Karyawan</option>
                                        <option value="Wiraswasta">Wiraswasta</option>
                                        <option value="Honorer">Honorer</option>
                                        <option value="Pelajar/Mahasiswa">Pelajar/Mahasiswa</option>
                                        <option value="Pensiunan">Pensiunan</option>
                                        <option value="Buruh">Buruh</option>
                                        <option value="Tidak Bekerja">Tidak Bekerja</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="statusPekerjaanLainnyaWrap" style="display: none;">
                                    <label class="form-label">Status Pekerjaan Lainnya</label>
                                    <input type="text" name="status_pekerjaan_lainnya_kta" class="form-control" placeholder="Tulis status pekerjaan lain">
                                </div>
                                <div class="col-md-6" id="namaTempatKerjaWrap">
                                    <label class="form-label">Nama Tempat Kerja / Perusahaan</label>
                                    <input type="text" name="nama_tempat_kerja_kta" class="form-control" placeholder="Contoh: PT Maju Bersama">
                                </div>
                                <div class="col-md-6" id="lamaBekerjaWrap">
                                    <label class="form-label">Lama Bekerja (bulan)</label>
                                    <input type="number" name="lama_bekerja_bulan_kta" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-6" id="jumlahTanggunganWrap" style="display: none;">
                                    <label class="form-label">Jumlah Tanggungan</label>
                                    <input type="number" name="jumlah_tanggungan_kta" class="form-control" min="0" step="1" placeholder="Contoh: 2">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Penghasilan Bulanan</label>
                                    <input type="number" name="penghasilan_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pengeluaran Bulanan</label>
                                    <input type="number" name="pengeluaran_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Beban Cicilan Bulanan</label>
                                    <input type="number" name="beban_cicilan_bulanan_kta" class="form-control" min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div id="kurFields" class="mt-4" style="display: none;">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Data KUR</h5>
                                    <p class="text-muted mb-0">Isi data usaha secara jelas dan konsisten.</p>
                                </div>
                                <span class="badge bg-success">Usaha</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Usaha</label>
                                    <input type="text" name="nama_usaha_kur" class="form-control" placeholder="Contoh: Toko Sembako Maju">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bidang Usaha</label>
                                    <input type="text" name="bidang_usaha_kur" class="form-control" placeholder="Contoh: perdagangan, kuliner">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Alamat Usaha</label>
                                    <input type="text" name="alamat_usaha_kur" class="form-control" placeholder="Contoh: Jl. Jeruju No. 12">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lama Usaha (bulan)</label>
                                    <input type="number" name="lama_usaha_bulan_kur" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Omzet Bulanan</label>
                                    <input type="number" name="omzet_bulanan_kur" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Laba Bersih Bulanan</label>
                                    <input type="number" name="laba_bersih_bulanan_kur" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jumlah Karyawan (opsional)</label>
                                    <input type="number" name="jumlah_karyawan_kur" class="form-control" min="0" step="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Legalitas Usaha</label>
                                    <select name="legalitas_usaha_kur" class="form-select">
                                        <option value="">Pilih legalitas</option>
                                        <option value="NIB">NIB</option>
                                        <option value="SIUP">SIUP</option>
                                        <option value="SKU">SKU</option>
                                        <option value="Belum Ada">Belum Ada</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tujuan Dana</label>
                                    <input type="text" name="tujuan_dana_kur" class="form-control" placeholder="Contoh: modal kerja, tambah stok">
                                </div>
                            </div>
                        </div>

                        </div>

                        <div class="wizard-pane" data-pane="docs">
                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Dokumen Pendukung</h5>
                                    <p class="text-muted mb-0">Upload dokumen sesuai jenis pinjaman.</p>
                                </div>
                            </div>

                            <div id="ktaDocs" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">KTP</label>
                                    <input type="file" name="ktp" class="form-control" accept="image/*,.pdf" required>
                                </div>
                                <div class="col-md-4" id="ktaSlipGajiWrap">
                                    <label class="form-label">Slip Gaji / Bukti Penghasilan</label>
                                    <input type="file" name="slip_gaji" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaSuratKerjaWrap">
                                    <label class="form-label">Surat Keterangan Kerja</label>
                                    <input type="file" name="surat_kerja" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaKartuPelajarWrap" style="display: none;">
                                    <label class="form-label">Kartu Pelajar / Mahasiswa</label>
                                    <input type="file" name="kartu_pelajar" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4" id="ktaKartuKeluargaWrap" style="display: none;">
                                    <label class="form-label">Kartu Keluarga</label>
                                    <input type="file" name="kartu_keluarga" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jaminan (opsional)</label>
                                    <input type="file" name="jaminan" class="form-control" accept="image/*,.pdf">
                                </div>
                            </div>

                            <div id="kurDocs" class="row g-3" style="display: none;">
                                <div class="col-md-4">
                                    <label class="form-label">KTP <span class="text-danger">*</span></label>
                                    <input type="file" name="ktp" class="form-control" accept="image/*,.pdf" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Foto Usaha</label>
                                    <input type="file" name="foto_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Izin / Legalitas Usaha</label>
                                    <input type="file" name="izin_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Laporan Omzet / Catatan Usaha</label>
                                    <input type="file" name="laporan_usaha" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Agunan Tambahan (opsional)</label>
                                    <input type="file" name="jaminan" class="form-control" accept="image/*,.pdf">
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-success mb-0 py-2">
                                        Untuk KUR, minimal unggah KTP. Foto usaha, izin usaha, dan laporan usaha tetap disarankan agar verifikasi lebih lancar.
                                    </div>
                                </div>
                            </div>
                        </div>

                        </div>
                        </div>

                        <div class="wizard-pane" data-pane="review">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h5 class="mb-1">Review Pengajuan</h5>
                                    <p class="text-muted mb-0">Periksa kembali ringkasan sebelum pengajuan dikirim.</p>
                                </div>
                                <span class="badge bg-primary">Siap Kirim</span>
                            </div>
                            <div class="review-grid" id="reviewGrid"></div>
                            <div class="alert alert-info mt-4 mb-0">
                                Pastikan data dan dokumen sudah sesuai. Setelah dikirim, pengajuan akan diproses oleh petugas.
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="dashboard.php" class="btn btn-outline-secondary" id="backToDashboard">Kembali</a>
                                <button type="button" class="btn btn-outline-secondary d-none" id="prevWizard">Sebelumnya</button>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-primary px-4" id="nextWizard">Lanjut</button>
                                <button type="submit" class="btn btn-success px-4 d-none" id="submitWizard">Kirim Pengajuan</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
                </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const jenisInfo = document.getElementById('jenisInfo');
        const jenisSelect = document.getElementById('jenis_kredit');
        const jenisLabel = document.getElementById('jenisLabel');
        const formSection = document.getElementById('formSection');
        const ktaFields = document.getElementById('ktaFields');
        const kurFields = document.getElementById('kurFields');
        const ktaDocs = document.getElementById('ktaDocs');
        const kurDocs = document.getElementById('kurDocs');
        const statusPekerjaanSelect = ktaFields.querySelector('select[name="status_pekerjaan_kta"]');
        const statusPekerjaanLainnyaWrap = document.getElementById('statusPekerjaanLainnyaWrap');
        const statusPekerjaanLainnyaInput = ktaFields.querySelector('input[name="status_pekerjaan_lainnya_kta"]');
        const namaTempatKerjaWrap = document.getElementById('namaTempatKerjaWrap');
        const namaTempatKerjaInput = ktaFields.querySelector('input[name="nama_tempat_kerja_kta"]');
        const lamaBekerjaWrap = document.getElementById('lamaBekerjaWrap');
        const lamaBekerjaInput = ktaFields.querySelector('input[name="lama_bekerja_bulan_kta"]');
        const jumlahTanggunganWrap = document.getElementById('jumlahTanggunganWrap');
        const jumlahTanggunganInput = ktaFields.querySelector('input[name="jumlah_tanggungan_kta"]');
        const ktaSlipGajiWrap = document.getElementById('ktaSlipGajiWrap');
        const ktaSuratKerjaWrap = document.getElementById('ktaSuratKerjaWrap');
        const ktaKartuPelajarWrap = document.getElementById('ktaKartuPelajarWrap');
        const ktaKartuKeluargaWrap = document.getElementById('ktaKartuKeluargaWrap');
        const ktaSlipGajiInput = ktaDocs.querySelector('input[name="slip_gaji"]');
        const ktaSuratKerjaInput = ktaDocs.querySelector('input[name="surat_kerja"]');
        const ktaKartuPelajarInput = ktaDocs.querySelector('input[name="kartu_pelajar"]');
        const ktaKartuKeluargaInput = ktaDocs.querySelector('input[name="kartu_keluarga"]');
        const choiceCards = document.querySelectorAll('.choice-card[data-jenis]');
        const pengajuanForm = document.querySelector('form[action="../proses/submit_application.php"]');
        const loanSelection = document.getElementById('loanSelection');
        const stepItems = document.querySelectorAll('.step-item[data-step]');
        const wizardPanes = document.querySelectorAll('.wizard-pane[data-pane]');
        const prevWizard = document.getElementById('prevWizard');
        const nextWizard = document.getElementById('nextWizard');
        const submitWizard = document.getElementById('submitWizard');
        const backToDashboard = document.getElementById('backToDashboard');
        const reviewGrid = document.getElementById('reviewGrid');
        const jumlahPinjamanInput = pengajuanForm.querySelector('input[name="jumlah_pinjaman"]');
        const jumlahPinjamanHelp = document.getElementById('jumlahPinjamanHelp');
        const jumlahPinjamanStatus = document.getElementById('jumlahPinjamanStatus');
        const selectionAlert = document.getElementById('selectionAlert');
        const serverErrorFields = <?php echo json_encode($errorFields, JSON_UNESCAPED_UNICODE); ?>;
        let currentWizardStep = jenisSelect.value ? 2 : 1;

        function getFieldWrapper(input) {
            return input.closest('.col-md-4, .col-md-6, .col-12, .col-lg-4');
        }

        function clearFieldState(input) {
            const wrapper = getFieldWrapper(input);
            if (!wrapper) {
                return;
            }

            wrapper.classList.remove('field-invalid');
            input.classList.remove('is-invalid');
            input.setAttribute('aria-invalid', 'false');

            const label = wrapper.querySelector('label');
            if (label) {
                label.classList.remove('text-danger');
            }

            const feedback = wrapper.querySelector('.field-feedback');
            if (feedback) {
                feedback.remove();
            }
        }

        function clearSelectionAlert() {
            if (selectionAlert) {
                selectionAlert.classList.add('d-none');
            }
        }

        function showSelectionAlert() {
            if (selectionAlert) {
                selectionAlert.classList.remove('d-none');
            }
        }

        function setFieldError(input, message) {
            const wrapper = getFieldWrapper(input);
            if (!wrapper) {
                return;
            }

            wrapper.classList.add('field-invalid');
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');

            const label = wrapper.querySelector('label');
            if (label) {
                label.classList.add('text-danger');
            }

            let feedback = wrapper.querySelector('.field-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'field-feedback';
                wrapper.appendChild(feedback);
            }
            feedback.textContent = message || 'Wajib diisi.';
        }

        function validateRequiredFields(scope = pengajuanForm, visibleOnly = true) {
            const requiredFields = Array.from(scope.querySelectorAll('input[required], select[required], textarea[required]'))
                .filter((field) => !field.disabled && (!visibleOnly || field.offsetParent !== null));

            let firstInvalid = null;

            requiredFields.forEach((field) => {
                clearFieldState(field);

                const isEmptyFile = field.type === 'file' && (!field.files || field.files.length === 0);
                const isEmptyText = field.type !== 'file' && String(field.value || '').trim() === '';
                const isInvalid = isEmptyFile || isEmptyText;

                if (isInvalid) {
                    if (!firstInvalid) {
                        firstInvalid = field;
                    }

                    const label = getFieldWrapper(field)?.querySelector('label');
                    const labelText = label ? label.textContent.replace('*', '').trim() : 'Field ini';
                    setFieldError(field, `${labelText} belum diisi.`);
                }
            });

            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            return true;
        }

        function getLoanLimits(jenis) {
            if (jenis === 'KUR') {
                return { minimum: 5000000, maximum: 300000000 };
            }

            return { minimum: 1000000, maximum: 100000000 };
        }

        function updateJumlahPinjamanConstraint() {
            const jenis = jenisSelect.value;
            const limits = getLoanLimits(jenis);
            jumlahPinjamanInput.min = String(limits.minimum);
            jumlahPinjamanInput.max = String(limits.maximum);
            jumlahPinjamanInput.setAttribute('aria-describedby', 'jumlahPinjamanHelp');

            if (jenis === 'KTA') {
                jumlahPinjamanHelp.textContent = 'Minimum KTA: Rp 1.000.000. Maksimum KTA: Rp 100.000.000.';
            } else if (jenis === 'KUR') {
                jumlahPinjamanHelp.textContent = 'Minimum KUR: Rp 5.000.000. Maksimum KUR: Rp 300.000.000.';
            } else {
                jumlahPinjamanHelp.textContent = 'Minimum pinjaman akan menyesuaikan jenis yang dipilih.';
            }

            updateJumlahPinjamanStatus();
        }

        function updateJumlahPinjamanStatus() {
            if (!jumlahPinjamanStatus) {
                return;
            }

            const jenis = jenisSelect.value;
            const limits = getLoanLimits(jenis);
            const amount = Number(jumlahPinjamanInput.value || 0);

            if (!jenis || amount <= 0) {
                jumlahPinjamanStatus.textContent = '';
                jumlahPinjamanStatus.className = 'small mt-1';
                return;
            }

            if (amount > limits.maximum) {
                const labelText = jenis === 'KUR' ? 'KUR' : 'KTA';
                jumlahPinjamanStatus.textContent = `Jumlah pinjaman melebihi maksimum Rp ${limits.maximum.toLocaleString('id-ID')} untuk ${labelText}.`;
                jumlahPinjamanStatus.className = 'small mt-1 text-danger fw-semibold';
                return;
            }

            if (amount < limits.minimum) {
                const labelText = jenis === 'KUR' ? 'KUR' : 'KTA';
                jumlahPinjamanStatus.textContent = `Jumlah pinjaman masih di bawah minimum Rp ${limits.minimum.toLocaleString('id-ID')} untuk ${labelText}.`;
                jumlahPinjamanStatus.className = 'small mt-1 text-warning fw-semibold';
                return;
            }

            jumlahPinjamanStatus.textContent = `Jumlah pinjaman masih dalam batas ${jenis === 'KUR' ? 'KUR' : 'KTA'}.`;
            jumlahPinjamanStatus.className = 'small mt-1 text-success fw-semibold';
        }

        function validateLoanAmount() {
            const jenis = jenisSelect.value;
            const limits = getLoanLimits(jenis);
            const amount = Number(jumlahPinjamanInput.value || 0);

            clearFieldState(jumlahPinjamanInput);

            if (!jenis || amount <= 0) {
                return true;
            }

            if (amount < limits.minimum) {
                const labelText = jenis === 'KUR' ? 'KUR' : 'KTA';
                setFieldError(jumlahPinjamanInput, `Jumlah pinjaman minimal Rp ${limits.minimum.toLocaleString('id-ID')} untuk ${labelText}.`);
                jumlahPinjamanInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            if (amount > limits.maximum) {
                const labelText = jenis === 'KUR' ? 'KUR' : 'KTA';
                setFieldError(jumlahPinjamanInput, `Jumlah pinjaman maksimal Rp ${limits.maximum.toLocaleString('id-ID')} untuk ${labelText}.`);
                jumlahPinjamanInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }

            return true;
        }

        function setChoiceActive(jenis) {
            choiceCards.forEach((card) => {
                card.classList.toggle('active', card.getAttribute('data-jenis') === jenis);
            });
        }

        function openFormWithJenis(jenis) {
            if (!['KTA', 'KUR'].includes(jenis)) {
                return;
            }

            jenisSelect.value = jenis;
            clearSelectionAlert();
            setChoiceActive(jenis);
            updateJenisInfo();
            formSection.classList.add('is-visible');
            setWizardStep(2);
        }

        function setWizardStep(step) {
            currentWizardStep = step;
            stepItems.forEach((item) => {
                const itemStep = Number(item.getAttribute('data-step'));
                item.classList.toggle('active', itemStep === step);
                item.classList.toggle('done', itemStep < step);
            });

            loanSelection.style.display = step === 1 ? 'block' : 'none';
            formSection.classList.toggle('is-visible', step > 1);
            wizardPanes.forEach((pane) => {
                const paneName = pane.getAttribute('data-pane');
                pane.classList.toggle('active',
                    (step === 2 && paneName === 'data') ||
                    (step === 3 && paneName === 'docs') ||
                    (step === 4 && paneName === 'review')
                );
            });

            prevWizard.classList.toggle('d-none', step <= 2);
            backToDashboard.classList.toggle('d-none', step > 2);
            nextWizard.classList.toggle('d-none', step === 4);
            submitWizard.classList.toggle('d-none', step !== 4);

            if (step === 4) {
                buildReview();
            }

            const target = step === 1 ? loanSelection : formSection;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function formatCurrency(value) {
            const number = Number(value || 0);
            return number > 0 ? 'Rp ' + number.toLocaleString('id-ID') : '-';
        }

        function fieldValue(selector) {
            const field = pengajuanForm.querySelector(selector);
            return field && field.value ? field.value : '-';
        }

        function fileLabel(selector) {
            const field = pengajuanForm.querySelector(selector);
            return field && field.files && field.files.length ? field.files[0].name : '-';
        }

        function addReviewItem(items, label, value) {
            items.push([label, value || '-']);
        }

        function buildReview() {
            const jenis = jenisSelect.value;
            const items = [];
            addReviewItem(items, 'Jenis Kredit', jenis === 'KTA' ? 'KTA - Kredit Tanpa Agunan' : 'KUR - Kredit Usaha Rakyat');
            addReviewItem(items, 'Jumlah Pinjaman', formatCurrency(pengajuanForm.querySelector('input[name="jumlah_pinjaman"]').value));

            if (jenis === 'KTA') {
                addReviewItem(items, 'Tujuan Pinjaman', fieldValue('input[name="tujuan_pinjaman_kta"]'));
                addReviewItem(items, 'Status Pekerjaan', fieldValue('select[name="status_pekerjaan_kta"]'));
                addReviewItem(items, 'Tempat Kerja', fieldValue('input[name="nama_tempat_kerja_kta"]'));
                addReviewItem(items, 'Penghasilan Bulanan', formatCurrency(fieldValue('input[name="penghasilan_bulanan_kta"]')));
                addReviewItem(items, 'Beban Cicilan', formatCurrency(fieldValue('input[name="beban_cicilan_bulanan_kta"]')));
                addReviewItem(items, 'KTP', fileLabel('#ktaDocs input[name="ktp"]'));
                addReviewItem(items, 'Bukti Penghasilan', fileLabel('input[name="slip_gaji"]'));
                addReviewItem(items, 'Surat Kerja', fileLabel('input[name="surat_kerja"]'));
            } else {
                addReviewItem(items, 'Nama Usaha', fieldValue('input[name="nama_usaha_kur"]'));
                addReviewItem(items, 'Bidang Usaha', fieldValue('input[name="bidang_usaha_kur"]'));
                addReviewItem(items, 'Alamat Usaha', fieldValue('input[name="alamat_usaha_kur"]'));
                addReviewItem(items, 'Lama Usaha', fieldValue('input[name="lama_usaha_bulan_kur"]') + ' bulan');
                addReviewItem(items, 'Omzet Bulanan', formatCurrency(fieldValue('input[name="omzet_bulanan_kur"]')));
                addReviewItem(items, 'Laba Bersih', formatCurrency(fieldValue('input[name="laba_bersih_bulanan_kur"]')));
                addReviewItem(items, 'Legalitas', fieldValue('select[name="legalitas_usaha_kur"]'));
                addReviewItem(items, 'KTP', fileLabel('#kurDocs input[name="ktp"]'));
                addReviewItem(items, 'Foto Usaha', fileLabel('input[name="foto_usaha"]'));
                addReviewItem(items, 'Izin Usaha', fileLabel('input[name="izin_usaha"]'));
            }

            reviewGrid.innerHTML = items.map(([label, value]) => `
                <div class="review-item">
                    <div class="review-label">${label}</div>
                    <div class="review-value">${String(value).replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                </div>
            `).join('');
        }

        function goNextWizard() {
            if (currentWizardStep === 1) {
                if (!jenisSelect.value) {
                    showSelectionAlert();
                    loanSelection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    return;
                }

                clearSelectionAlert();
                openFormWithJenis(jenisSelect.value);
                return;
            }

            if (currentWizardStep === 2 && !validateRequiredFields(document.querySelector('[data-pane="data"]'))) {
                return;
            }
            if (currentWizardStep === 3 && !validateRequiredFields(document.querySelector('[data-pane="docs"]'))) {
                return;
            }
            setWizardStep(Math.min(4, currentWizardStep + 1));
        }

        function goPreviousWizard() {
            setWizardStep(Math.max(2, currentWizardStep - 1));
        }

        function updateStatusPekerjaanField() {
            const selectedStatus = statusPekerjaanSelect.value;
            const isLainnya = selectedStatus === 'Lainnya';
            const isStudent = selectedStatus === 'Pelajar/Mahasiswa';
            statusPekerjaanLainnyaWrap.style.display = isLainnya ? 'block' : 'none';
            statusPekerjaanLainnyaInput.required = isLainnya;
            statusPekerjaanLainnyaInput.disabled = !isLainnya;
            if (!isLainnya) {
                statusPekerjaanLainnyaInput.value = '';
            }

            namaTempatKerjaWrap.style.display = isStudent ? 'none' : 'block';
            lamaBekerjaWrap.style.display = isStudent ? 'none' : 'block';
            jumlahTanggunganWrap.style.display = isStudent ? 'block' : 'none';

            namaTempatKerjaInput.required = !isStudent;
            lamaBekerjaInput.required = !isStudent;
            jumlahTanggunganInput.required = isStudent;

            namaTempatKerjaInput.disabled = isStudent;
            lamaBekerjaInput.disabled = isStudent;
            jumlahTanggunganInput.disabled = !isStudent;

            if (isStudent) {
                namaTempatKerjaInput.value = '';
                lamaBekerjaInput.value = '';
            } else {
                jumlahTanggunganInput.value = '';
            }

            ktaSlipGajiWrap.style.display = isStudent ? 'none' : 'block';
            ktaSuratKerjaWrap.style.display = isStudent ? 'none' : 'block';
            ktaKartuPelajarWrap.style.display = isStudent ? 'block' : 'none';
            ktaKartuKeluargaWrap.style.display = isStudent ? 'block' : 'none';

            ktaSlipGajiInput.required = !isStudent;
            ktaSuratKerjaInput.required = !isStudent;
            ktaKartuPelajarInput.required = isStudent;
            ktaKartuKeluargaInput.required = isStudent;
        }

        function updateJenisInfo() {
            const jenis = jenisSelect.value;
            updateJumlahPinjamanConstraint();
            if (!jenis) {
                jenisInfo.textContent = 'Pilih KTA atau KUR di atas untuk mulai mengisi pengajuan.';
                ktaFields.style.display = 'none';
                kurFields.style.display = 'none';
                ktaDocs.style.display = 'none';
                kurDocs.style.display = 'none';
                return;
            }

            const isKTA = jenis === 'KTA';
            const ktaInputs = ktaFields.querySelectorAll('input, select');
            const kurInputs = kurFields.querySelectorAll('input, select');
            const ktaDocInputs = ktaDocs.querySelectorAll('input');
            const kurDocInputs = kurDocs.querySelectorAll('input');

            if (jenis === 'KTA') {
                jenisInfo.textContent = 'KTA fokus ke kemampuan bayar pribadi. Untuk pelajar/mahasiswa, data tanggungan dan dokumen mahasiswa akan diprioritaskan.';
                ktaFields.style.display = 'block';
                kurFields.style.display = 'none';
                ktaDocs.style.display = 'flex';
                kurDocs.style.display = 'none';
            } else {
                jenisInfo.textContent = 'KUR fokus ke usaha. Data usaha, omzet, laba, legalitas, dan foto usaha perlu diisi selengkap mungkin.';
                ktaFields.style.display = 'none';
                kurFields.style.display = 'block';
                ktaDocs.style.display = 'none';
                kurDocs.style.display = 'flex';
            }

            jenisLabel.textContent = isKTA
                ? 'KTA - Kredit Tanpa Agunan'
                : 'KUR - Kredit Usaha Rakyat';

            ktaInputs.forEach((input) => {
                input.required = false;
                input.disabled = !isKTA;
            });
            kurInputs.forEach((input) => {
                input.required = false;
                input.disabled = isKTA;
            });
            ktaDocInputs.forEach((input) => {
                input.required = false;
                input.disabled = !isKTA;
            });
            kurDocInputs.forEach((input) => {
                input.required = false;
                input.disabled = isKTA;
            });

            if (isKTA) {
                ktaFields.querySelector('input[name="tujuan_pinjaman_kta"]').required = true;
                ktaFields.querySelector('select[name="status_pekerjaan_kta"]').required = true;
                ktaFields.querySelector('input[name="penghasilan_bulanan_kta"]').required = true;
                ktaFields.querySelector('input[name="beban_cicilan_bulanan_kta"]').required = true;
                ktaDocInputs[0].required = true;

                if (statusPekerjaanSelect.value === 'Pelajar/Mahasiswa') {
                    ktaKartuPelajarInput.required = true;
                    ktaKartuKeluargaInput.required = true;
                    ktaSlipGajiInput.required = false;
                    ktaSuratKerjaInput.required = false;
                } else {
                    ktaSlipGajiInput.required = true;
                    ktaSuratKerjaInput.required = true;
                    ktaKartuPelajarInput.required = false;
                    ktaKartuKeluargaInput.required = false;
                }

                ktaSlipGajiWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'none' : 'block';
                ktaSuratKerjaWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'none' : 'block';
                ktaKartuPelajarWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'block' : 'none';
                ktaKartuKeluargaWrap.style.display = statusPekerjaanSelect.value === 'Pelajar/Mahasiswa' ? 'block' : 'none';
            } else {
                kurFields.querySelector('input[name="nama_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="bidang_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="alamat_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="lama_usaha_bulan_kur"]').required = true;
                kurFields.querySelector('input[name="omzet_bulanan_kur"]').required = true;
                kurFields.querySelector('input[name="laba_bersih_bulanan_kur"]').required = true;
                kurFields.querySelector('input[name="jumlah_karyawan_kur"]').required = false;
                kurFields.querySelector('select[name="legalitas_usaha_kur"]').required = true;
                kurFields.querySelector('input[name="tujuan_dana_kur"]').required = true;
                kurDocInputs[0].required = true;
            }

            updateStatusPekerjaanField();
        }

        statusPekerjaanSelect.addEventListener('change', updateStatusPekerjaanField);
        jumlahPinjamanInput.addEventListener('input', updateJumlahPinjamanStatus);
        jumlahPinjamanInput.addEventListener('change', updateJumlahPinjamanStatus);
        updateJenisInfo();

        if (jenisSelect.value) {
            setChoiceActive(jenisSelect.value);
            formSection.classList.add('is-visible');
            setWizardStep(2);
        } else {
            setWizardStep(1);
        }

        choiceCards.forEach((card) => {
            card.addEventListener('click', function() {
                openFormWithJenis(this.getAttribute('data-jenis'));
            });
        });

        nextWizard.addEventListener('click', goNextWizard);
        prevWizard.addEventListener('click', goPreviousWizard);

        pengajuanForm.addEventListener('submit', function(event) {
            setWizardStep(2);
            if (!validateRequiredFields(document.querySelector('[data-pane="data"]'))) {
                event.preventDefault();
                return;
            }
            if (!validateLoanAmount()) {
                event.preventDefault();
                return;
            }

            setWizardStep(3);
            if (!validateRequiredFields(document.querySelector('[data-pane="docs"]'))) {
                event.preventDefault();
                return;
            }

            setWizardStep(4);
        });

        pengajuanForm.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => clearFieldState(field));
            field.addEventListener('change', () => clearFieldState(field));
        });

        if (Array.isArray(serverErrorFields) && serverErrorFields.length > 0) {
            const currentJenis = jenisSelect.value;
            if (currentJenis) {
                updateJenisInfo();
                setChoiceActive(currentJenis);
                setWizardStep(2);
            }

            const firstServerInvalid = serverErrorFields
                .map((fieldName) => pengajuanForm.querySelector(`[name="${fieldName}"]`))
                .find((field) => field && field.offsetParent !== null);

            serverErrorFields.forEach((fieldName) => {
                const field = pengajuanForm.querySelector(`[name="${fieldName}"]`);
                if (field && field.offsetParent !== null) {
                    setFieldError(field, 'Field ini belum diisi.');
                }
            });

            if (firstServerInvalid) {
                firstServerInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    </script>
</body>
</html>
