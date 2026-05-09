<?php
require_once '../function/auth.php';
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'petugas'], true)) {
    header('Location: ../index.php');
    exit();
}

$doc_id = (int) ($_GET['id'] ?? 0);
if ($doc_id <= 0) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan');
}

$conn = getDBConnection();
$stmt = $conn->prepare('SELECT nama_file, path_file, jenis FROM dokumen WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan');
}

$uploadsDir = realpath(__DIR__ . '/../uploads');
$filePath = realpath($doc['path_file']);

if ($uploadsDir === false || $filePath === false || strpos($filePath, $uploadsDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('File tidak ditemukan');
}

$mime = function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream';
$download = isset($_GET['download']) && $_GET['download'] === '1';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($doc['nama_file']) . '"');
readfile($filePath);
exit();
?>
