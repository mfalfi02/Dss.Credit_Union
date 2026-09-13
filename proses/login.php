<?php
// Proses autentikasi login dan pengalihan dashboard berdasarkan role pengguna.
require_once '../config/database.php';
require_once '../function/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input login dari form.
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Cocokkan kredensial ke tabel users dan roles.
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT u.id, u.password, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['role'] === 'anggota') {
                $memberStmt = $conn->prepare(
                    "SELECT a.email, u.email_verified_at
                     FROM users u
                     JOIN anggota a ON a.user_id = u.id
                     WHERE u.id = ?"
                );
                $memberStmt->bind_param("i", $user['id']);
                $memberStmt->execute();
                $memberResult = $memberStmt->get_result()->fetch_assoc();

                if ($memberResult && empty($memberResult['email_verified_at'])) {
                    header('Location: ../anggota/verify_email.php?status=pending&email=' . urlencode($memberResult['email']));
                    exit();
                }
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            // Reset session ID agar login lebih aman.
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Arahkan pengguna ke dashboard sesuai perannya.
            switch ($user['role']) {
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'petugas':
                    header('Location: ../petugas/dashboard.php');
                    break;
                case 'anggota':
                    header('Location: ../anggota/dashboard.php');
                    break;
            }
            exit();
        }
    }

    // Jika login gagal, kembali ke halaman awal dengan pesan error.
    header('Location: ../index.php?error=1');
    exit();
}
?>
