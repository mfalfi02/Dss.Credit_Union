<?php
// Halaman pendaftaran anggota baru dengan tampilan form yang responsif.
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <!-- Metadata dan library tampilan untuk form pendaftaran -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - SPK Kredit</title>
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
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(18px);
            opacity: 0.5;
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
            max-width: min(560px, 100%);
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

        .btn-register {
            border: 0;
            border-radius: 1rem;
            padding: 0.95rem 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 18px 35px rgba(37, 99, 235, 0.18);
        }

        .btn-register:hover {
            filter: brightness(1.03);
        }

        .register-links a {
            color: #2563eb;
            text-decoration: none;
        }

        .register-links a:hover {
            text-decoration: underline;
        }

        .status-alert {
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.92);
            color: #0f172a;
            border-radius: 1rem;
        }

        .info-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .75rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.06);
            border: 1px solid rgba(37, 99, 235, 0.10);
            color: #0f172a;
            font-size: .88rem;
        }

        @media (max-width: 991.98px) {
            .hero-panel {
                padding: 1rem 0 0;
            }

            .login-card {
                margin-left: 0;
                max-width: none;
            }

            .display-copy {
                max-width: none;
            }
        }

        @media (max-width: 575.98px) {
            .info-chip {
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
        <!-- Elemen dekoratif dan layout utama halaman register -->
        <div class="orb one"></div>
        <div class="orb two"></div>

        <div class="container py-4 py-lg-5">
            <div class="row align-items-center g-4 g-lg-5 min-vh-100">
                <div class="col-lg-6">
                    <section class="hero-panel">
                        <!-- Panel informasi manfaat pendaftaran -->
                        <div class="brand mb-4">
                            <div class="brand-badge">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">SPK Kredit CU</div>
                                <div class="small text-muted">Metode SAW</div>
                            </div>
                        </div>

                        <span class="info-chip mb-3">
                            <i class="fas fa-shield-heart"></i>
                            Pendaftaran anggota baru
                        </span>

                        <h1 class="display-4 display-copy mb-3">
                            Buat akun untuk mulai mengajukan pinjaman dengan lebih mudah.
                        </h1>
                        <p class="hero-copy mb-4">
                            Lengkapi data diri, lalu gunakan akun ini untuk masuk ke sistem dan melanjutkan pengajuan KTA atau KUR.
                        </p>

                        <div class="feature-list">
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-id-card"></i></div>
                                <div>
                                    <div class="fw-semibold text-dark">Data anggota</div>
                                    <div class="text-muted small">Nama, kontak, dan data dasar tersimpan terhubung ke akun.</div>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon"><i class="fas fa-lock"></i></div>
                                <div>
                                    <div class="fw-semibold text-dark">Aman dan sederhana</div>
                                    <div class="text-muted small">Pendaftaran dibuat singkat agar cepat dipakai login.</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-lg-6 d-flex justify-content-lg-end">
                    <section class="card login-card">
                        <div class="card-body">
                            <!-- Form pendaftaran akun anggota -->
                            <div class="mb-4">
                                <p class="login-subtitle mb-1 text-uppercase small fw-semibold" style="letter-spacing: .16em;">Create account</p>
                                <h2 class="login-title h3 mb-2">Daftar sebagai Anggota</h2>
                                <p class="login-subtitle mb-0">Isi data di bawah untuk membuat akun baru.</p>
                            </div>

                            <?php if ($error == 1): ?>
                                <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                                    <i class="fas fa-circle-exclamation mt-1 text-warning"></i>
                                    <div>Nama pengguna, kata sandi, dan nama wajib diisi.</div>
                                </div>
                            <?php elseif ($error == 2): ?>
                                <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                                    <i class="fas fa-circle-exclamation mt-1 text-danger"></i>
                                    <div>Nama pengguna sudah digunakan.</div>
                                </div>
                            <?php elseif ($error == 3): ?>
                                <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                                    <i class="fas fa-circle-exclamation mt-1 text-danger"></i>
                                    <div>Pendaftaran gagal. Coba lagi.</div>
                                </div>
                            <?php endif; ?>

                            <form action="../proses/register.php" method="POST" autocomplete="on">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="username">Nama Pengguna</label>
                                        <input type="text" id="username" name="username" class="form-control form-control-lg" placeholder="Masukkan nama pengguna" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="password">Kata Sandi</label>
                                        <input type="password" id="password" name="password" class="form-control form-control-lg" placeholder="Masukkan kata sandi" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="nama">Nama Lengkap</label>
                                        <input type="text" id="nama" name="nama" class="form-control form-control-lg" placeholder="Masukkan nama lengkap" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="alamat">Alamat</label>
                                        <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Masukkan alamat"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="no_hp">No HP</label>
                                        <input type="text" id="no_hp" name="no_hp" class="form-control form-control-lg" placeholder="Masukkan no HP">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-control form-control-lg" placeholder="Masukkan email">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="tanggal_lahir">Tanggal Lahir</label>
                                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control form-control-lg">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-register btn-lg w-100 mt-4">
                                    <i class="fas fa-user-check me-2"></i>Daftar
                                </button>
                            </form>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 register-links">
                                <a href="../index.php">
                                    <i class="fas fa-arrow-left me-1"></i>Kembali ke Masuk
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
