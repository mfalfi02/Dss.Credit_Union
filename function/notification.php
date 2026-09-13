<?php
// Helper notifikasi untuk pengajuan yang sudah diterima CU.

if (!function_exists('formatNotificationCurrency')) {
    function formatNotificationCurrency($value)
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }
}

if (!function_exists('formatNotificationDate')) {
    function formatNotificationDate($dateTime)
    {
        if ($dateTime instanceof DateTimeInterface) {
            $timestamp = $dateTime->getTimestamp();
        } else {
            $timestamp = strtotime((string) $dateTime);
        }

        if (!$timestamp) {
            return '-';
        }

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    }
}

if (!function_exists('syncAcceptanceNotification')) {
    function syncAcceptanceNotification(mysqli $conn, $pengajuan_id)
    {
        $pengajuan_id = (int) $pengajuan_id;

        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifikasi'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return;
        }

        $stmt = $conn->prepare(
            'SELECT p.status, p.jenis_kredit, p.jumlah_pinjaman, a.id AS anggota_id, a.nama
             FROM pengajuan p
             JOIN anggota a ON a.id = p.anggota_id
             WHERE p.id = ? LIMIT 1'
        );

        if (!$stmt) {
            error_log('Gagal menyiapkan query notifikasi untuk pengajuan ' . $pengajuan_id);
            return;
        }

        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();

        if (!$application) {
            error_log('Data pengajuan tidak ditemukan untuk notifikasi: ' . $pengajuan_id);
            return;
        }

        if (($application['status'] ?? '') !== 'accepted') {
            $stmt = $conn->prepare('DELETE FROM notifikasi WHERE pengajuan_id = ?');
            if (!$stmt) {
                error_log('Gagal menyiapkan hapus notifikasi untuk pengajuan ' . $pengajuan_id);
                return;
            }

            $stmt->bind_param('i', $pengajuan_id);
            $stmt->execute();
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
        $deadline = $now->modify('+7 days');
        $deadlineSql = $deadline->format('Y-m-d H:i:s');
        $deadlineLabel = formatNotificationDate($deadline);
        $jumlah = formatNotificationCurrency($application['jumlah_pinjaman']);
        $jenis = (string) $application['jenis_kredit'];

        $judul = 'Jadwal Pencairan Pinjaman';
        $pesan = sprintf(
            'Pengajuan %s Anda sebesar %s telah diterima CU. Silakan datang ke kantor CU untuk melakukan pencairan dan pengambilan pinjaman paling lambat %s.',
            $jenis,
            $jumlah,
            $deadlineLabel
        );

        $stmt = $conn->prepare(
            'INSERT INTO notifikasi (anggota_id, pengajuan_id, judul, pesan, deadline_at, is_read, read_at)
             VALUES (?, ?, ?, ?, ?, 0, NULL)
             ON DUPLICATE KEY UPDATE
                anggota_id = VALUES(anggota_id),
                judul = VALUES(judul),
                pesan = VALUES(pesan),
                deadline_at = VALUES(deadline_at),
                is_read = 0,
                read_at = NULL,
                updated_at = CURRENT_TIMESTAMP'
        );

        if (!$stmt) {
            error_log('Gagal menyiapkan simpan notifikasi untuk pengajuan ' . $pengajuan_id);
            return;
        }

        $anggota_id = (int) $application['anggota_id'];
        $stmt->bind_param('iisss', $anggota_id, $pengajuan_id, $judul, $pesan, $deadlineSql);
        $stmt->execute();
    }
}

if (!function_exists('notificationTableExists')) {
    function notificationTableExists(mysqli $conn): bool
    {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'notifikasi'");
        return (bool) ($tableCheck && $tableCheck->num_rows > 0);
    }
}

if (!function_exists('notificationIsOverdue')) {
    function notificationIsOverdue($date): bool
    {
        if (!$date) {
            return false;
        }

        $timestamp = strtotime((string) $date);
        return $timestamp !== false && $timestamp < time();
    }
}

if (!function_exists('getMemberNotifications')) {
    function getMemberNotifications(mysqli $conn, int $anggota_id, int $limit = 5, bool $onlyUnread = false): array
    {
        if ($anggota_id <= 0 || !notificationTableExists($conn)) {
            return [];
        }

        $limitClause = '';
        if ($limit > 0) {
            $limitClause = ' LIMIT ' . (int) $limit;
        }

        $sql = "SELECT n.id, n.judul, n.pesan, n.deadline_at, n.is_read, n.read_at, n.created_at,
                       p.id AS pengajuan_id, p.jenis_kredit, p.jumlah_pinjaman, p.status
                FROM notifikasi n
                JOIN pengajuan p ON p.id = n.pengajuan_id
                WHERE n.anggota_id = ?" . ($onlyUnread ? " AND n.is_read = 0" : "") . "
                ORDER BY n.created_at DESC" . $limitClause;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('Gagal menyiapkan query daftar notifikasi.');
            return [];
        }

        $stmt->bind_param('i', $anggota_id);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount(mysqli $conn, int $anggota_id): int
    {
        if ($anggota_id <= 0 || !notificationTableExists($conn)) {
            return 0;
        }

        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifikasi WHERE anggota_id = ? AND is_read = 0');
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $anggota_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead(mysqli $conn, int $notification_id, int $anggota_id): bool
    {
        if ($notification_id <= 0 || $anggota_id <= 0 || !notificationTableExists($conn)) {
            return false;
        }

        $stmt = $conn->prepare(
            'UPDATE notifikasi
             SET is_read = 1, read_at = CURRENT_TIMESTAMP
             WHERE id = ? AND anggota_id = ?'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $notification_id, $anggota_id);
        return $stmt->execute();
    }
}

if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead(mysqli $conn, int $anggota_id): bool
    {
        if ($anggota_id <= 0 || !notificationTableExists($conn)) {
            return false;
        }

        $stmt = $conn->prepare(
            'UPDATE notifikasi
             SET is_read = 1, read_at = CURRENT_TIMESTAMP
             WHERE anggota_id = ? AND is_read = 0'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $anggota_id);
        return $stmt->execute();
    }
}
