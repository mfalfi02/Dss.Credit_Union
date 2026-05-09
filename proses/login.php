<?php
require_once '../config/database.php';
require_once '../function/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT u.id, u.password, r.name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
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

    // Invalid login
    header('Location: ../index.php?error=1');
    exit();
}
?>
