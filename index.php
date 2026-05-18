<?php
// Halaman awal aplikasi yang mengarahkan pengguna ke dashboard sesuai role.
session_start();

// Jika sesi aktif, langsung arahkan pengguna ke halaman yang sesuai.
if (!empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit();
        case 'petugas':
            header('Location: petugas/dashboard.php');
            exit();
        case 'anggota':
            header('Location: anggota/dashboard.php');
            exit();
    }
}

// Penanda status untuk pesan sukses atau gagal login.
$loginError = isset($_GET['error']);
$loginSuccess = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Metadata halaman awal dan library tampilan -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Kredit CU Lantang Tipo Jeruju</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-1: #ffffff;
            --bg-2: #f8fbff;
            --bg-3: #eef4fb;
            --accent: #2563eb;
            --accent-2: #0ea5e9;
            --text: #0f172a;
            --muted: rgba(15, 23, 42, 0.68);
            --card: rgba(255, 255, 255, 0.92);
            --border: rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 20%, rgba(37, 99, 235, 0.08), transparent 24%),
                radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.08), transparent 22%),
                linear-gradient(180deg, var(--bg-1), var(--bg-2) 52%, var(--bg-3));
            overflow-x: hidden;
        }

        .page-shell {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(18px);
            opacity: 0.55;
            pointer-events: none;
        }

        .orb.one {
            width: 180px;
            height: 180px;
            left: -40px;
            top: 90px;
            background: rgba(37, 99, 235, 0.08);
        }

        .orb.two {
            width: 240px;
            height: 240px;
            right: -70px;
            top: 220px;
            background: rgba(14, 165, 233, 0.08);
        }

        .hero-panel {
            position: relative;
            padding: clamp(1.5rem, 3vw, 3rem) clamp(0rem, 2vw, 2rem);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.65rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(12px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .brand-badge {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.18);
        }

        .display-copy {
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.04;
            max-width: 14ch;
            color: #0f172a;
        }

        .hero-copy {
            color: var(--muted);
            max-width: 32rem;
            font-size: 1.05rem;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.85rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.06);
            border: 1px solid rgba(37, 99, 235, 0.10);
            color: #0f172a;
            font-size: 0.92rem;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.9rem;
            margin-top: 1.6rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            padding: 0.95rem 1rem;
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.84);
            border: 1px solid rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(37, 99, 235, 0.08);
            color: var(--accent);
            flex: 0 0 auto;
        }

        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1.75rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(18px);
            width: 100%;
            max-width: min(520px, 100%);
            margin-left: auto;
        }

        .login-card .card-body {
            padding: clamp(1.35rem, 2.4vw, 2rem);
        }

        .login-title {
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #0f172a;
        }

        .login-subtitle {
            color: var(--muted);
        }

        .form-label {
            color: rgba(15, 23, 42, 0.82);
            font-weight: 600;
        }

        .form-control {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            color: #0f172a;
            border-radius: 1rem;
            padding: 0.9rem 1rem;
        }

        .form-control::placeholder {
            color: rgba(15, 23, 42, 0.38);
        }

        .form-control:focus {
            background: #fff;
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.10);
            color: #0f172a;
        }

        .input-group .btn {
            border-radius: 0 1rem 1rem 0;
            border: 1px solid rgba(15, 23, 42, 0.12);
            background: #fff;
            color: #334155;
        }

        .btn-login {
            border: 0;
            border-radius: 1rem;
            padding: 0.95rem 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 18px 35px rgba(37, 99, 235, 0.18);
        }

        .btn-login:hover {
            filter: brightness(1.03);
        }

        .login-links a {
            color: #2563eb;
            text-decoration: none;
        }

        .login-links a:hover {
            text-decoration: underline;
        }

        .status-alert {
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.92);
            color: #0f172a;
            border-radius: 1rem;
        }

        .role-mini {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.06);
            border: 1px solid rgba(37, 99, 235, 0.10);
            color: #0f172a;
            font-size: 0.88rem;
        }

        @media (max-width: 991.98px) {
            .hero-panel {
                padding: 1rem 0 0;
            }

            .login-card .card-body {
                padding: 1.4rem;
            }

            .login-card {
                margin-left: 0;
            }

            .display-copy {
                max-width: none;
            }
        }

        @media (max-width: 575.98px) {
            .pill {
                width: 100%;
                justify-content: center;
            }

            .feature-item {
                padding: 0.85rem 0.9rem;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <div class="orb one"></div>
        <div class="orb two"></div>

        <div class="container py-4 py-lg-5">
            <div class="row align-items-center g-4 g-lg-5 min-vh-100">
                <div class="col-lg-6">
                    <section class="hero-panel">
                        <div class="brand mb-4">
                            <div class="brand-badge">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">SPK Kredit CU</div>
                                <div class="small text-muted">Metode SAW</div>
                            </div>
                        </div>

                        <span class="role-mini mb-3">
                            <i class="fas fa-shield-heart"></i>
                            Login Sistem
                        </span>

                        <h1 class="display-4 display-copy mb-3">
                            Sistem pengajuan pinjaman CU Lintang Tipo Jeruju.
                        </h1>
                        <p class="hero-copy mb-4">
                            .
                        </p>

                        <div class="pill-row mb-4">
                            <span class="pill"><i class="fas fa-user-shield"></i> Admin</span>
                            <span class="pill"><i class="fas fa-clipboard-check"></i> Petugas</span>
                            <span class="pill"><i class="fas fa-user-group"></i> Anggota</span>
                        </div>
                    </section>
                </div>

                <div class="col-lg-6 d-flex justify-content-lg-end">
                    <section class="card login-card">
                        <div class="card-body">
                            <div class="mb-4">
                                <p class="login-subtitle mb-1 text-uppercase small fw-semibold" style="letter-spacing: .16em;">Welcome back</p>
                                <h2 class="login-title h3 mb-2">Masuk ke Sistem</h2>
                                <p class="login-subtitle mb-0">Gunakan akun yang sudah terdaftar untuk mengakses dashboard.</p>
                            </div>

                            <?php if ($loginError): ?>
                                <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                                    <i class="fas fa-circle-exclamation mt-1 text-warning"></i>
                                    <div>Login gagal. Periksa nama pengguna dan kata sandi.</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($loginSuccess): ?>
                                <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                                    <i class="fas fa-circle-check mt-1 text-success"></i>
                                    <div>Pendaftaran berhasil. Silakan masuk.</div>
                                </div>
                            <?php endif; ?>

                            <form action="proses/login.php" method="POST" autocomplete="on">
                                <div class="mb-3">
                                    <label class="form-label" for="username">Nama Pengguna</label>
                                    <input type="text" id="username" name="username" class="form-control form-control-lg" placeholder="Masukkan nama pengguna" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="password">Kata Sandi</label>
                                    <div class="input-group input-group-lg">
                                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan kata sandi" required>
                                        <button type="button" class="btn" id="togglePassword" aria-label="Tampilkan kata sandi">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-login btn-lg w-100">
                                    <i class="fas fa-right-to-bracket me-2"></i>Masuk Sekarang
                                </button>
                            </form>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 login-links">
                                <a href="anggota/register.php">
                                    <i class="fas fa-user-plus me-1"></i>Daftar sebagai Anggota
                                </a>
                                <a href="#info">
                                    <i class="fas fa-circle-info me-1"></i>Lihat informasi
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section id="info" class="row g-3 mt-3 mt-lg-4 pb-4">
                <div class="col-md-4">
                    <div class="p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.86); border: 1px solid rgba(15,23,42,0.08);">
                        <div class="fw-semibold text-dark mb-1">KTA</div>
                        <div class="small text-muted">Pengajuan kredit berbasis kemampuan bayar pribadi dan dokumen pendukung.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.86); border: 1px solid rgba(15,23,42,0.08);">
                        <div class="fw-semibold text-dark mb-1">KUR</div>
                        <div class="small text-muted">Pengajuan yang fokus pada kelayakan usaha, omzet, dan legalitas usaha.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded-4 h-100" style="background: rgba(255,255,255,0.86); border: 1px solid rgba(15,23,42,0.08);">
                        <div class="fw-semibold text-dark mb-1">SAW</div>
                        <div class="small text-muted">Penilaian otomatis yang membantu keputusan menjadi lebih konsisten.</div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            this.innerHTML = isPassword
                ? '<i class="fas fa-eye-slash"></i>'
                : '<i class="fas fa-eye"></i>';
            this.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
    </script>
</body>
</html>
