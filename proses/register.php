<?php
// Proses pembuatan akun anggota baru beserta data profil dasarnya.
require_once '../config/database.php';
require_once '../function/auth.php';
require_once '../function/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan data dari form pendaftaran.
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nomor_anggota = trim($_POST['nomor_anggota'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

    if ($username === '' || $password === '' || $nomor_anggota === '' || $nama === '') {
        header('Location: ../anggota/register.php?error=1');
        exit();
    }

    if ($email === '') {
        header('Location: ../anggota/register.php?error=4');
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

        $stmt = $conn->prepare("SELECT a.id FROM anggota a WHERE a.nomor_anggota = ?");
        $stmt->bind_param("s", $nomor_anggota);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $conn->rollback();
            header('Location: ../anggota/register.php?error=7');
            exit();
        }

        $stmt = $conn->prepare("SELECT a.id FROM anggota a WHERE a.email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $conn->rollback();
            header('Location: ../anggota/register.php?error=5');
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, 3)");
        $stmt->bind_param("ss", $username, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO anggota (user_id, nomor_anggota, nama, alamat, no_hp, email, tanggal_lahir) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $user_id, $nomor_anggota, $nama, $alamat, $no_hp, $email, $tanggal_lahir);
        $stmt->execute();

        $verificationToken = bin2hex(random_bytes(32));
        $verificationCode = (string) random_int(100000, 999999);
        $verificationTokenHash = hash('sha256', $verificationToken);
        $verificationCodeHash = hash('sha256', $verificationCode);
        $verificationExpiresAt = date('Y-m-d H:i:s', time() + 86400);

        $stmt = $conn->prepare(
            "UPDATE users
             SET verification_token_hash = ?, verification_code_hash = ?, verification_expires_at = ?, verification_sent_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param("sssi", $verificationTokenHash, $verificationCodeHash, $verificationExpiresAt, $user_id);
        $stmt->execute();

        $conn->commit();
        if (!sendVerificationEmail($email, $nama, $verificationCode, $verificationToken)) {
            header('Location: ../anggota/verify_email.php?status=error&error=mail&email=' . urlencode($email));
            exit();
        }

        header('Location: ../anggota/verify_email.php?status=registered&email=' . urlencode($email) . '&username=' . urlencode($username));
        exit();
    } catch (Throwable $e) {
        // Rollback jika proses penyimpanan gagal di tengah jalan.
        $conn->rollback();
        header('Location: ../anggota/register.php?error=3');
        exit();
    }
}
?>
