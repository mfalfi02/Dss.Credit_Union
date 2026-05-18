<?php
// Proses pembuatan akun anggota baru beserta data profil dasarnya.
require_once '../config/database.php';
require_once '../function/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan data dari form pendaftaran.
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

    if ($username === '' || $password === '' || $nama === '') {
        header('Location: ../anggota/register.php?error=1');
        exit();
    }

    // Simpan akun user dan data anggota dalam satu transaksi.
    $conn = getDBConnection();
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $conn->rollback();
            header('Location: ../anggota/register.php?error=2');
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, 3)");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO anggota (user_id, nama, alamat, no_hp, email, tanggal_lahir) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $nama, $alamat, $no_hp, $email, $tanggal_lahir);
        $stmt->execute();

        $conn->commit();
        header('Location: ../index.php?success=1');
        exit();
    } catch (Throwable $e) {
        // Rollback jika proses penyimpanan gagal di tengah jalan.
        $conn->rollback();
        header('Location: ../anggota/register.php?error=3');
        exit();
    }
}
?>
