<?php
// Helper autentikasi untuk memulai sesi, memeriksa login, dan memutus sesi.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan pengguna sudah login sebelum mengakses halaman tertentu.
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit();
    }
}

// Batasi halaman berdasarkan role yang diizinkan.
function checkRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: ../index.php');
        exit();
    }
}

// Hapus data sesi dan arahkan pengguna kembali ke halaman awal.
function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    header('Location: ../index.php');
    exit();
}
?>
