<?php
// Konfigurasi email untuk pengiriman kode verifikasi via Gmail SMTP.
// Nilai bisa diisi lewat environment variable agar kredensial tidak disimpan langsung di source code.

require_once __DIR__ . '/env.php';

if (!function_exists('mail_config_value')) {
    function mail_config_value(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return ($value === false || trim($value) === '') ? $default : trim($value);
    }
}

define('MAIL_HOST', mail_config_value('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) mail_config_value('MAIL_PORT', '587'));
define('MAIL_USERNAME', mail_config_value('MAIL_USERNAME', 'spkkreditcu@gmail.com'));
define('MAIL_PASSWORD', mail_config_value('MAIL_PASSWORD', 'cypv lcko alqg mzqc'));
define('MAIL_FROM_EMAIL', mail_config_value('MAIL_FROM_EMAIL', 'spkkreditcu@gmail.com'));
define('MAIL_FROM_NAME', mail_config_value('MAIL_FROM_NAME', 'SPK Kredit CU'));
define('MAIL_ENCRYPTION', mail_config_value('MAIL_ENCRYPTION', 'tls'));
?>
