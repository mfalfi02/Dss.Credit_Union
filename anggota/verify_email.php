<?php
// Halaman verifikasi email anggota menggunakan token atau kode OTP.
$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';
$prefillEmail = trim($_GET['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - SPK Kredit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-1: #f8fbff;
            --bg-2: #eef4fb;
            --accent: #2563eb;
            --accent-2: #0ea5e9;
            --text: #0f172a;
            --muted: rgba(15, 23, 42, 0.68);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--text);
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 20%, rgba(37, 99, 235, 0.08), transparent 24%),
                radial-gradient(circle at 80% 20%, rgba(14, 165, 233, 0.08), transparent 22%),
                linear-gradient(180deg, #fff, var(--bg-1) 48%, var(--bg-2));
        }

        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
        }

        .panel {
            width: 100%;
            max-width: 720px;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 1.8rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.10);
            backdrop-filter: blur(18px);
        }

        .panel-body {
            padding: clamp(1.35rem, 2.6vw, 2.2rem);
        }

        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .85rem;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.06);
            border: 1px solid rgba(37, 99, 235, 0.10);
            color: var(--text);
            font-size: .9rem;
        }

        .title {
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .subtitle {
            color: var(--muted);
        }

        .form-control {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 1rem;
            padding: 0.9rem 1rem;
        }

        .form-control:focus {
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 0 0 0.22rem rgba(37, 99, 235, 0.10);
        }

        .btn-primary {
            border: 0;
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            box-shadow: 0 18px 35px rgba(37, 99, 235, 0.18);
        }

        .btn-outline-primary {
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            font-weight: 700;
        }

        .status-alert {
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.92);
            color: #0f172a;
            border-radius: 1rem;
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="panel">
            <div class="panel-body">
                <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center mb-4">
                    <div>
                        <span class="badge-soft mb-3">
                            <i class="fas fa-envelope-circle-check"></i>
                            Verifikasi Email Anggota
                        </span>
                        <h1 class="title h3 mb-2">Aktifkan akun Anda</h1>
                        <p class="subtitle mb-0">Masukkan kode dari Gmail atau klik link verifikasi yang kami kirim.</p>
                    </div>
                    <a href="../index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Masuk
                    </a>
                </div>

                <?php if ($status === 'registered'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-circle-check mt-1 text-success"></i>
                        <div>Kode verifikasi sudah dikirim. Silakan cek Gmail Anda.</div>
                    </div>
                <?php elseif ($status === 'pending'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-shield-halved mt-1 text-warning"></i>
                        <div>Akun Anda belum aktif. Silakan verifikasi email terlebih dahulu.</div>
                    </div>
                <?php elseif ($status === 'verified'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-circle-check mt-1 text-success"></i>
                        <div>Email berhasil diverifikasi. Sekarang Anda bisa masuk ke sistem.</div>
                    </div>
                <?php elseif ($status === 'resent'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-paper-plane mt-1 text-primary"></i>
                        <div>Kode verifikasi baru sudah dikirim ke email Anda.</div>
                    </div>
                <?php elseif ($status === 'expired'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-clock mt-1 text-warning"></i>
                        <div>Kode verifikasi sudah kedaluwarsa. Kirim ulang kode untuk melanjutkan.</div>
                    </div>
                <?php elseif ($status === 'error'): ?>
                    <div class="alert status-alert d-flex align-items-start gap-2 mb-3">
                        <i class="fas fa-triangle-exclamation mt-1 text-danger"></i>
                        <div>
                            <?php
                                echo match ($error) {
                                    'token' => 'Link verifikasi tidak valid atau sudah kedaluwarsa.',
                                    'token_missing' => 'Token verifikasi wajib diisi.',
                                    'code' => 'Kode verifikasi salah.',
                                    'mail' => 'Gagal mengirim email. Isi MAIL_USERNAME dan MAIL_PASSWORD dengan Gmail + App Password yang valid.',
                                    'not_found' => 'Email tidak ditemukan.',
                                    'missing' => 'Email dan kode wajib diisi.',
                                    default => 'Proses verifikasi gagal. Silakan coba lagi.',
                                };
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="p-4 rounded-4 border bg-white h-100">
                            <h2 class="h5 fw-bold mb-3">Masukkan kode atau token</h2>
                            <div class="row g-3">
                                <div class="col-12">
                                    <form action="../proses/verify_email.php" method="POST">
                                        <input type="hidden" name="action" value="verify_code">
                                        <div class="mb-3">
                                            <label class="form-label" for="email">Email</label>
                                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan email yang didaftarkan" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="code">Kode Verifikasi</label>
                                            <input type="text" id="code" name="code" class="form-control" inputmode="numeric" maxlength="6" placeholder="6 digit kode dari Gmail" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-lock-open me-2"></i>Verifikasi dengan Kode
                                        </button>
                                    </form>
                                </div>

                                <div class="col-12">
                                    <div class="border-top pt-3">
                                        <form action="../proses/verify_email.php" method="POST">
                                            <input type="hidden" name="action" value="verify_token">
                                            <div class="mb-3">
                                                <label class="form-label" for="token">Token Verifikasi</label>
                                                <input type="text" id="token" name="token" class="form-control" placeholder="Tempel token verifikasi dari email" required>
                                            </div>
                                            <button type="submit" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-key me-2"></i>Verifikasi dengan Token
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="p-4 rounded-4 border bg-white h-100">
                            <h2 class="h5 fw-bold mb-3">Kirim ulang kode</h2>
                            <p class="subtitle small mb-3">Gunakan jika email belum masuk atau kode sudah kedaluwarsa.</p>
                            <form action="../proses/verify_email.php" method="POST" class="mb-4">
                                <input type="hidden" name="action" value="resend_code">
                                <div class="mb-3">
                                    <label class="form-label" for="resend_email">Email</label>
                                    <input type="email" id="resend_email" name="email" class="form-control" value="<?php echo htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Masukkan email yang didaftarkan" required>
                                </div>
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Kirim Ulang Kode
                                </button>
                            </form>

                            <div class="small text-muted">
                                Pastikan email diakses lewat Gmail yang benar. Jika masih belum menerima pesan, cek folder spam atau coba lagi beberapa menit kemudian.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
