<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPK Kredit CU Lantang Tipo Jeruju</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Sistem Pendukung Keputusan Kredit</h1>
        <p class="text-center">CU Lantang Tipo Jeruju - Metode SAW</p>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center">Login gagal. Periksa nama pengguna dan kata sandi.</div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success text-center">Pendaftaran berhasil. Silakan masuk.</div>
        <?php endif; ?>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Masuk</div>
                    <div class="card-body">
                        <form action="proses/login.php" method="POST">
                            <div class="mb-3">
                                <label>Nama Pengguna</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label>Kata Sandi</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Masuk</button>
                        </form>
                        <p class="mt-3">Belum punya akun? <a href="anggota/register.php">Daftar sebagai Anggota</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
</body>
</html>
