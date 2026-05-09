<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Daftar sebagai Anggota</h1>
        <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="alert alert-warning text-center">Nama pengguna, kata sandi, dan nama wajib diisi.</div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 2): ?>
            <div class="alert alert-danger text-center">Nama pengguna sudah digunakan.</div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] == 3): ?>
            <div class="alert alert-danger text-center">Pendaftaran gagal. Coba lagi.</div>
        <?php endif; ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Formulir Pendaftaran</div>
                    <div class="card-body">
                        <form action="../proses/register.php" method="POST">
                            <div class="mb-3">
                                <label>Nama Pengguna</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Kata Sandi</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Alamat</label>
                                <textarea name="alamat" class="form-control"></textarea>
                            </div>
                            <div class="mb-3">
                                <label>No HP</label>
                                <input type="text" name="no_hp" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label>Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Daftar</button>
                        </form>
                        <a href="../index.php" class="btn btn-link">Kembali ke Masuk</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
