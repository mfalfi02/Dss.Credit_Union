<?php
// Konfigurasi koneksi database pusat untuk seluruh aplikasi.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'spk_kredit_cu');

// Membuka koneksi MySQL dengan charset UTF-8 agar data aman ditampilkan dan disimpan.
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}
?>
