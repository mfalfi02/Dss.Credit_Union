<?php
// Inisialisasi sesi dan validasi akses untuk role anggota.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';

// Ambil data pengguna aktif untuk ditampilkan di dashboard.
$conn = getDBConnection();
$currentUserName = 'Anggota';

if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare(
        'SELECT a.nama, u.username
         FROM anggota a
         JOIN users u ON a.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!empty($user['nama'])) {
            $currentUserName = $user['nama'];
        } elseif (!empty($user['username'])) {
            $currentUserName = $user['username'];
        }
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
        /* Latar belakang utama dashboard anggota */
        body {
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, 0.14), transparent 28%),
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 24%),
                linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
            min-height: 100vh;
        }

        /* Header navigasi atas */
        .topbar {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        }

        .topbar .nav-link,
        .topbar .navbar-brand {
            color: #fff !important;
        }

        /* Kartu utama pada dashboard */
        .hero-card,
        .choice-card,
        .status-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
        }

        /* Kartu sambutan pengguna */
        .hero-card {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        /* Ornamen dekoratif pada kartu sambutan */
        .hero-card::after {
            content: "";
            position: absolute;
            inset: auto -12% -38% auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .4rem .85rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-size: .875rem;
        }

        /* Kartu fitur untuk aksi utama anggota */
        .choice-card {
            transition: transform .2s ease, box-shadow .2s ease;
            height: 100%;
        }

        .choice-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        /* Ikon di dalam kartu fitur */
        .choice-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
        }

        .kta-icon { background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%); }
        .kur-icon { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .status-icon { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); }

        /* Judul bagian yang dipakai di beberapa kartu */
        .soft-title {
            color: #0f172a;
            font-weight: 800;
        }

        /* Layout fleksibel khusus kartu status agar isi mudah menyesuaikan tinggi */
        .status-feature-card .card-body {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        /* Tombol kartu status didorong ke bawah agar konsisten */
        .status-feature-card .status-actions {
            margin-top: auto;
        }

        /* Container dua kartu utama agar sama tinggi */
        .dashboard-shell {
            align-items: stretch;
        }

        /* Penyesuaian padding pada layar tablet */
        @media (max-width: 991.98px) {
            .hero-card .card-body {
                padding: 1.25rem;
            }

            .choice-card .card-body,
            .status-card .card-body {
                padding: 1.25rem;
            }
        }

        /* Penyesuaian khusus layar kecil agar tombol dan badge lebih nyaman dibaca */
        @media (max-width: 575.98px) {
            .hero-badge {
                width: 100%;
                justify-content: center;
            }

            .choice-card .btn,
            .status-card .btn {
                width: 100%;
            }

            .status-feature-card .card-body {
                gap: .75rem;
            }

            .choice-card .d-flex,
            .status-card .d-flex {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar atas: nama dashboard dan tombol logout -->
    <nav class="navbar navbar-expand-lg topbar navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="dashboard.php">
                <i class="fas fa-user-group me-2"></i>Dasbor Anggota
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">
                    <i class="fas fa-right-from-bracket me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        <!-- Hero/welcome section: sapaan pengguna dan tombol cepat ke status -->
        <section class="card hero-card mb-4">
            <div class="card-body p-4 p-lg-5 position-relative">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="hero-badge mb-3">
                            <i class="fas fa-sparkles"></i>
                            <span>Mulai pengajuan dari pilihan jenis pinjaman</span>
                        </div>
                        <h1 class="display-6 fw-bold mb-3">Selamat datang, <?php echo htmlspecialchars($currentUserName); ?></h1>
                        <p class="lead mb-0 text-white-75" style="max-width: 44rem;">
                            Aplikasi Pelayanan pengajuan pinjaman Credit Union Lintang Tipo Jeruju
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                    </div>
                </div>
            </div>
        </section>

        <!-- Dua fitur utama: ajukan kredit dan pantau status pengajuan -->
        <section class="row g-4 mb-4 dashboard-shell">
            <div class="col-12 col-lg-6">
                <!-- Kartu fitur: ajukan kredit/pinjaman -->
                <div class="card choice-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="choice-icon kta-icon"><i class="fas fa-hand-holding-dollar"></i></div>
                            <span class="badge text-bg-primary">Ajukan</span>
                        </div>
                        <h4 class="soft-title mb-2">Ajukan Kredit / Pinjaman</h4>
                        <p class="text-muted mb-4">
                            Mulai pengajuan dari satu menu saja. Nanti Anda akan memilih jenis pinjaman KTA atau KUR di dalam form, lalu mengisi data yang sesuai.
                        </p>
                        <a href="submit_application.php" class="btn btn-primary w-100">
                            Mulai Pengajuan
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <!-- Kartu fitur: status pengajuan -->
                <div class="card choice-card h-100 status-feature-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="choice-icon status-icon"><i class="fas fa-clock-rotate-left"></i></div>
                            <span class="badge text-bg-info">Pantau</span>
                        </div>
                        <h4 class="soft-title mb-2">Status Pengajuan</h4>
                        <p class="text-muted mb-4">
                            Lihat perkembangan pengajuan yang sudah dibuat, termasuk status, hasil SAW, dan jumlah dokumen.
                        </p>
                        <div class="status-actions">
                            <a href="status.php" class="btn btn-outline-primary w-100">
                                Buka Status
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Ringkasan alur penggunaan aplikasi dari awal sampai pantau status -->
        <section class="card status-card">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div>
                        <h4 class="soft-title mb-1">Alur Pengajuan yang Mudah</h4>
                        <p class="text-muted mb-0">Masuk ke menu pengajuan, pilih KTA atau KUR, isi data, unggah dokumen, lalu pantau hasilnya di status pengajuan.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge text-bg-light border">1. Buka pengajuan</span>
                        <span class="badge text-bg-light border">2. Pilih KTA / KUR</span>
                        <span class="badge text-bg-light border">3. Isi data & dokumen</span>
                        <span class="badge text-bg-light border">4. Pantau status</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Script Bootstrap untuk komponen interaktif jika dibutuhkan -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
