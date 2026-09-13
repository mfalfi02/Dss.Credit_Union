<?php
// Proses verifikasi email anggota melalui token atau kode OTP.
require_once '../config/database.php';
require_once '../function/mailer.php';

function redirectVerify(array $params = []): void
{
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    header('Location: ../anggota/verify_email.php' . $query);
    exit();
}

function redirectLogin(array $params = []): void
{
    $query = !empty($params) ? '?' . http_build_query($params) : '';
    header('Location: ../index.php' . $query);
    exit();
}

function findMemberByEmail(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare(
        "SELECT u.id, u.email_verified_at, u.verification_token_hash, u.verification_code_hash, u.verification_expires_at, a.email, a.nama
         FROM users u
         JOIN anggota a ON a.user_id = u.id
         WHERE a.email = ?
         LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row ?: null;
}

function markEmailVerified(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare(
        "UPDATE users
         SET email_verified_at = NOW(),
             verification_token_hash = NULL,
             verification_code_hash = NULL,
             verification_expires_at = NULL,
             verification_sent_at = NULL
         WHERE id = ?"
    );
    $stmt->bind_param('i', $userId);

    return $stmt->execute();
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = trim($_GET['token']);
    if ($token === '') {
        redirectVerify(['status' => 'error', 'error' => 'token']);
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT u.id, a.email
         FROM users u
         JOIN anggota a ON a.user_id = u.id
         WHERE u.verification_token_hash = ?
           AND u.email_verified_at IS NULL
           AND (u.verification_expires_at IS NULL OR u.verification_expires_at >= NOW())
         LIMIT 1"
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if (!$member) {
        redirectVerify(['status' => 'error', 'error' => 'token']);
    }

    if (!markEmailVerified($conn, (int) $member['id'])) {
        redirectVerify(['status' => 'error', 'error' => 'save', 'email' => $member['email']]);
    }

    redirectLogin(['success' => 'verified', 'email' => $member['email']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectVerify();
}

$action = $_POST['action'] ?? '';

if ($action === 'verify_token') {
    $token = trim($_POST['token'] ?? '');

    if ($token === '') {
        redirectVerify(['status' => 'error', 'error' => 'token_missing']);
    }

    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT u.id, a.email
         FROM users u
         JOIN anggota a ON a.user_id = u.id
         WHERE u.verification_token_hash = ?
           AND u.email_verified_at IS NULL
           AND (u.verification_expires_at IS NULL OR u.verification_expires_at >= NOW())
         LIMIT 1"
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if (!$member) {
        redirectVerify(['status' => 'error', 'error' => 'token']);
    }

    if (!markEmailVerified($conn, (int) $member['id'])) {
        redirectVerify(['status' => 'error', 'error' => 'save', 'email' => $member['email']]);
    }

    redirectLogin(['success' => 'verified', 'email' => $member['email']]);
}

if ($action === 'verify_code') {
    $email = trim($_POST['email'] ?? '');
    $code = preg_replace('/\D+/', '', trim($_POST['code'] ?? ''));

    if ($email === '' || $code === '') {
        redirectVerify(['status' => 'error', 'error' => 'missing', 'email' => $email]);
    }

    $member = findMemberByEmail($conn, $email);
    if (!$member) {
        redirectVerify(['status' => 'error', 'error' => 'not_found', 'email' => $email]);
    }

    if (!empty($member['email_verified_at'])) {
        redirectVerify(['status' => 'verified', 'email' => $email]);
    }

    if (empty($member['verification_expires_at']) || strtotime($member['verification_expires_at']) < time()) {
        redirectVerify(['status' => 'expired', 'email' => $email]);
    }

    if (!hash_equals((string) $member['verification_code_hash'], hash('sha256', $code))) {
        redirectVerify(['status' => 'error', 'error' => 'code', 'email' => $email]);
    }

    if (!markEmailVerified($conn, (int) $member['id'])) {
        redirectVerify(['status' => 'error', 'error' => 'save', 'email' => $email]);
    }

    redirectLogin(['success' => 'verified', 'email' => $email]);
}

if ($action === 'resend_code') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        redirectVerify(['status' => 'error', 'error' => 'missing', 'email' => $email]);
    }

    $member = findMemberByEmail($conn, $email);
    if (!$member) {
        redirectVerify(['status' => 'error', 'error' => 'not_found', 'email' => $email]);
    }

    if (!empty($member['email_verified_at'])) {
        redirectVerify(['status' => 'verified', 'email' => $email]);
    }

    $verificationToken = bin2hex(random_bytes(32));
    $verificationCode = (string) random_int(100000, 999999);
    $verificationTokenHash = hash('sha256', $verificationToken);
    $verificationCodeHash = hash('sha256', $verificationCode);
    $verificationExpiresAt = date('Y-m-d H:i:s', time() + 86400);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "UPDATE users
             SET verification_token_hash = ?, verification_code_hash = ?, verification_expires_at = ?, verification_sent_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('sssi', $verificationTokenHash, $verificationCodeHash, $verificationExpiresAt, $member['id']);
        $stmt->execute();

        if (!sendVerificationEmail($email, $member['nama'], $verificationCode, $verificationToken)) {
            $conn->rollback();
            redirectVerify(['status' => 'error', 'error' => 'mail', 'email' => $email]);
        }

        $conn->commit();
        redirectVerify(['status' => 'resent', 'email' => $email]);
    } catch (Throwable $e) {
        $conn->rollback();
        redirectVerify(['status' => 'error', 'error' => 'save', 'email' => $email]);
    }
}

redirectVerify(['status' => 'error', 'error' => 'action']);
?>
