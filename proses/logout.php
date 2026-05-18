<?php
// Proses keluar aplikasi dengan menghapus session aktif.
session_start();
require_once '../function/auth.php';
logout();
?>
