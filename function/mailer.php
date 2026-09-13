<?php
// Helper sederhana untuk mengirim email lewat Gmail SMTP tanpa dependensi tambahan.
require_once __DIR__ . '/../config/mail.php';

function getAppBaseUrl(string $path = ''): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');

    $url = $scheme . '://' . $host . $basePath;

    return $path !== '' ? $url . '/' . ltrim($path, '/') : $url;
}

function smtpReadResponse($socket): array
{
    $lines = [];
    $code = 0;

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }

        $lines[] = rtrim($line, "\r\n");
        if (preg_match('/^(\d{3})([\s-])/', $line, $matches)) {
            $code = (int) $matches[1];
            if ($matches[2] === ' ') {
                break;
            }
        }
    }

    return [$code, implode("\n", $lines)];
}

function smtpSendCommand($socket, string $command, array $expectedCodes = [250]): array
{
    if ($command !== '') {
        fwrite($socket, $command . "\r\n");
    }

    [$code, $message] = smtpReadResponse($socket);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP error {$code}: {$message}");
    }

    return [$code, $message];
}

function sendVerificationEmail(string $toEmail, string $toName, string $verificationCode, string $verificationToken): bool
{
    if (
        MAIL_USERNAME === 'your-gmail-address@gmail.com'
        || MAIL_PASSWORD === 'your-google-app-password'
        || trim(MAIL_USERNAME) === ''
        || trim(MAIL_PASSWORD) === ''
    ) {
        error_log('Email verifikasi dibatalkan: MAIL_USERNAME atau MAIL_PASSWORD belum dikonfigurasi.');
        return false;
    }

    $verifyUrl = getAppBaseUrl('proses/verify_email.php?token=' . urlencode($verificationToken));
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($verificationCode, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

    $subject = 'Kode Verifikasi Email SPK Kredit CU';
    $htmlBody = '
        <html>
            <body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #0f172a;">
                <div style="max-width: 640px; margin: 0 auto; padding: 24px;">
                    <h2 style="margin: 0 0 12px;">Verifikasi Email Anda</h2>
                    <p>Halo ' . $safeName . ',</p>
                    <p>Akun SPK Kredit CU untuk ' . $safeEmail . ' sudah dibuat. Silakan verifikasi email Anda dengan kode berikut:</p>
                    <div style="font-size: 32px; font-weight: 700; letter-spacing: 6px; margin: 20px 0; padding: 18px 22px; border: 1px solid #dbeafe; display: inline-block; border-radius: 12px; background: #eff6ff;">' . $safeCode . '</div>
                    <p>Atau klik tautan verifikasi berikut:</p>
                    <p><a href="' . $safeUrl . '">' . $safeUrl . '</a></p>
                    <p style="color: #475569;">Kode ini berlaku selama 24 jam. Jika Anda tidak merasa membuat akun, abaikan email ini.</p>
                </div>
            </body>
        </html>';

    $textBody = implode("\n", [
        'Verifikasi Email SPK Kredit CU',
        '',
        'Halo ' . $toName . ',',
        'Akun Anda sudah dibuat. Gunakan kode verifikasi berikut:',
        $verificationCode,
        '',
        'Token verifikasi:',
        $verificationToken,
        '',
        'Atau buka tautan ini:',
        $verifyUrl,
        '',
        'Kode berlaku selama 24 jam.',
    ]);

    $boundary = '=_Part_' . bin2hex(random_bytes(8));
    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'To: ' . $toName . ' <' . $toEmail . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $textBody . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlBody . "\r\n\r\n"
        . '--' . $boundary . "--\r\n";

    $socket = @stream_socket_client(
        'tcp://' . MAIL_HOST . ':' . MAIL_PORT,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log("Email verifikasi gagal: tidak bisa terhubung ke SMTP {$errno} {$errstr}.");
        return false;
    }

    try {
        stream_set_timeout($socket, 30);
        [$code] = smtpReadResponse($socket);
        if ($code !== 220) {
            throw new RuntimeException('SMTP banner not received.');
        }

        $hostname = gethostname() ?: 'localhost';
        smtpSendCommand($socket, 'EHLO ' . $hostname, [250]);
        smtpSendCommand($socket, 'STARTTLS', [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Unable to start TLS session.');
        }

        smtpSendCommand($socket, 'EHLO ' . $hostname, [250]);
        smtpSendCommand($socket, 'AUTH LOGIN', [334]);
        smtpSendCommand($socket, base64_encode(MAIL_USERNAME), [334]);
        smtpSendCommand($socket, base64_encode(MAIL_PASSWORD), [235]);
        smtpSendCommand($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250]);
        smtpSendCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtpSendCommand($socket, 'DATA', [354]);

        fwrite($socket, $message . "\r\n.\r\n");
        [$finalCode] = smtpReadResponse($socket);
        if ($finalCode !== 250) {
            throw new RuntimeException('SMTP send failed.');
        }

        smtpSendCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable $e) {
        error_log('Email verifikasi gagal: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}
?>
