<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit();
    }
}

function checkRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: ../index.php');
        exit();
    }
}

function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    header('Location: ../index.php');
    exit();
}
?>
