<?php
// Dasbor admin menampilkan ringkasan sistem, tren pengajuan, distribusi SAW, dan aktivitas terbaru.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once 'ui.php';

$conn = getDBConnection();

$currentUserName = 'Admin';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!empty($user['username'])) {
            $currentUserName = $user['username'];
        }
    }
}

$totals = [
    'applications' => 0,
    'members' => 0,
    'criteria' => 0,
    'saw' => 0,
];

foreach ([
    'applications' => 'SELECT COUNT(*) AS total FROM pengajuan',
    'members' => 'SELECT COUNT(*) AS total FROM anggota',
    'criteria' => "SELECT COUNT(*) AS total FROM kriteria WHERE jenis_kredit IN ('KTA', 'KUR', 'BOTH')",
    'saw' => 'SELECT COUNT(*) AS total FROM hasil_saw',
] as $key => $sql) {
    $result = $conn->query($sql);
    if ($result) {
        $totals[$key] = (int) ($result->fetch_assoc()['total'] ?? 0);
    }
}

function metricTrend(mysqli $conn, string $table, string $dateColumn = 'created_at'): float
{
    $allowedTables = ['pengajuan', 'anggota', 'kriteria', 'hasil_saw'];
    $allowedColumns = ['created_at'];
    if (!in_array($table, $allowedTables, true) || !in_array($dateColumn, $allowedColumns, true)) {
        return 0.0;
    }

    $sql = "SELECT
                SUM(CASE WHEN $dateColumn >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN 1 ELSE 0 END) AS current_total,
                SUM(CASE WHEN $dateColumn >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')
                          AND $dateColumn < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') THEN 1 ELSE 0 END) AS previous_total
            FROM $table";
    $result = $conn->query($sql);
    if (!$result) {
        return 0.0;
    }

    $row = $result->fetch_assoc();
    $current = (int) ($row['current_total'] ?? 0);
    $previous = (int) ($row['previous_total'] ?? 0);
    if ($previous === 0) {
        return $current > 0 ? 100.0 : 0.0;
    }

    return (($current - $previous) / $previous) * 100;
}

function statusLabel($status): string
{
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Siap Validasi',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst((string) $status),
    };
}

function statusBadgeClass($status): string
{
    return match ($status) {
        'pending' => 'status-warning',
        'verified' => 'status-info',
        'document_rejected' => 'status-danger',
        'accepted' => 'status-success',
        'rejected' => 'status-danger',
        default => 'status-muted',
    };
}

function formatRelativeTime(?string $dateTime): string
{
    if (empty($dateTime)) {
        return '-';
    }

    $timestamp = strtotime($dateTime);
    if (!$timestamp) {
        return '-';
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'Baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }

    return floor($diff / 86400) . ' hari lalu';
}

function activityIcon(string $text): array
{
    $lower = strtolower($text);
    if (str_contains($lower, 'tolak') || str_contains($lower, 'rejected')) {
        return ['fa-circle-xmark', 'activity-danger'];
    }
    if (str_contains($lower, 'terima') || str_contains($lower, 'accepted') || str_contains($lower, 'setuju')) {
        return ['fa-circle-check', 'activity-success'];
    }
    if (str_contains($lower, 'dokumen') || str_contains($lower, 'verifikasi')) {
        return ['fa-file-shield', 'activity-primary'];
    }
    if (str_contains($lower, 'saw') || str_contains($lower, 'ranking') || str_contains($lower, 'penilaian')) {
        return ['fa-chart-line', 'activity-warning'];
    }

    return ['fa-bell', 'activity-purple'];
}

$metricTrends = [
    'applications' => metricTrend($conn, 'pengajuan'),
    'members' => metricTrend($conn, 'anggota'),
    'criteria' => metricTrend($conn, 'kriteria'),
    'saw' => metricTrend($conn, 'hasil_saw'),
];

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime(date('Y-m-01') . " -$i month");
    $key = date('Y-m', $time);
    $months[$key] = [
        'label' => date('M Y', $time),
        'submitted' => 0,
        'accepted' => 0,
        'rejected' => 0,
    ];
}

$chartResult = $conn->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            COUNT(*) AS submitted,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN status IN ('rejected', 'document_rejected') THEN 1 ELSE 0 END) AS rejected
     FROM pengajuan
     WHERE created_at >= DATE_FORMAT(CURRENT_DATE - INTERVAL 5 MONTH, '%Y-%m-01')
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month_key ASC"
);
if ($chartResult) {
    while ($row = $chartResult->fetch_assoc()) {
        $key = $row['month_key'];
        if (isset($months[$key])) {
            $months[$key]['submitted'] = (int) ($row['submitted'] ?? 0);
            $months[$key]['accepted'] = (int) ($row['accepted'] ?? 0);
            $months[$key]['rejected'] = (int) ($row['rejected'] ?? 0);
        }
    }
}

$distribution = [
    'Sangat Layak (>= 80)' => ['count' => 0, 'color' => '#16c784'],
    'Layak (60 - 79)' => ['count' => 0, 'color' => '#2f7df6'],
    'Cukup Layak (40 - 59)' => ['count' => 0, 'color' => '#f8b72b'],
    'Kurang Layak (< 40)' => ['count' => 0, 'color' => '#f0525f'],
];
$distributionTotal = 0;
$distributionResult = $conn->query(
    "SELECT
        SUM(CASE WHEN persentase_saw >= 80 THEN 1 ELSE 0 END) AS very_good,
        SUM(CASE WHEN persentase_saw >= 60 AND persentase_saw < 80 THEN 1 ELSE 0 END) AS good,
        SUM(CASE WHEN persentase_saw >= 40 AND persentase_saw < 60 THEN 1 ELSE 0 END) AS enough,
        SUM(CASE WHEN persentase_saw < 40 THEN 1 ELSE 0 END) AS low,
        COUNT(*) AS total
     FROM hasil_saw
     WHERE persentase_saw IS NOT NULL"
);
if ($distributionResult) {
    $row = $distributionResult->fetch_assoc();
    $distribution['Sangat Layak (>= 80)']['count'] = (int) ($row['very_good'] ?? 0);
    $distribution['Layak (60 - 79)']['count'] = (int) ($row['good'] ?? 0);
    $distribution['Cukup Layak (40 - 59)']['count'] = (int) ($row['enough'] ?? 0);
    $distribution['Kurang Layak (< 40)']['count'] = (int) ($row['low'] ?? 0);
    $distributionTotal = (int) ($row['total'] ?? 0);
}

$recentApplications = [];
$recentResult = $conn->query(
    "SELECT p.id, p.jenis_kredit, p.status, p.jumlah_pinjaman, p.created_at, a.nama,
            h.persentase_saw, h.kelayakan
     FROM pengajuan p
     JOIN anggota a ON p.anggota_id = a.id
     LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
     ORDER BY p.created_at DESC
     LIMIT 5"
);
if ($recentResult) {
    $recentApplications = $recentResult->fetch_all(MYSQLI_ASSOC);
}

$activities = [];
$activityResult = $conn->query(
    "SELECT r.aksi, r.timestamp, p.id AS pengajuan_id, p.jenis_kredit, a.nama
     FROM riwayat_pengajuan r
     JOIN pengajuan p ON p.id = r.pengajuan_id
     JOIN anggota a ON a.id = p.anggota_id
     ORDER BY r.timestamp DESC
     LIMIT 5"
);
if ($activityResult) {
    $activities = $activityResult->fetch_all(MYSQLI_ASSOC);
}
if (empty($activities)) {
    foreach ($recentApplications as $app) {
        $activities[] = [
            'aksi' => 'Pengajuan baru dari ' . $app['nama'],
            'timestamp' => $app['created_at'],
            'pengajuan_id' => $app['id'],
            'jenis_kredit' => $app['jenis_kredit'],
            'nama' => $app['nama'],
        ];
    }
}

$chartLabels = array_column($months, 'label');
$chartSubmitted = array_column($months, 'submitted');
$chartAccepted = array_column($months, 'accepted');
$chartRejected = array_column($months, 'rejected');
$distributionLabels = array_keys($distribution);
$distributionCounts = array_map(static fn($item) => $item['count'], array_values($distribution));
$distributionColors = array_map(static fn($item) => $item['color'], array_values($distribution));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SPK Kredit CU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo adminPageStyles(); ?>
    <style>
        :root {
            --page-bg: #f5f7fb;
            --card-bg: #ffffff;
            --border: #e7edf7;
            --text: #16213d;
            --muted: #69789a;
            --blue: #2f7df6;
            --green: #16c784;
            --purple: #8358e8;
            --orange: #ffae1f;
            --red: #f0525f;
            --shadow: 0 18px 42px rgba(31, 45, 78, .09);
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 18% -10%, rgba(47, 125, 246, .13), transparent 30%),
                radial-gradient(circle at 94% 8%, rgba(22, 199, 132, .10), transparent 26%),
                var(--page-bg);
            color: var(--text);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .dashboard-shell {
            width: min(100% - 32px, 1480px);
            margin: 0 auto;
            padding: 22px 0 34px;
        }

        .top-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 22px;
        }

        .page-title {
            font-size: clamp(1.6rem, 2.4vw, 2.15rem);
            font-weight: 800;
            letter-spacing: 0;
            margin: 0;
        }

        .page-subtitle {
            color: var(--muted);
            font-weight: 500;
            margin: .35rem 0 0;
        }

        .date-pill {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(31, 45, 78, .08);
            padding: .82rem 1rem;
            color: var(--text);
            font-weight: 700;
            white-space: nowrap;
        }

        .nav-strip {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .nav-strip a {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            text-decoration: none;
            color: #526386;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, .8);
            border-radius: 8px;
            padding: .58rem .78rem;
            font-weight: 700;
            font-size: .88rem;
        }

        .nav-strip a.active,
        .nav-strip a:hover {
            color: #fff;
            background: var(--blue);
            border-color: var(--blue);
        }

        .metric-card,
        .panel {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .metric-card {
            min-height: 116px;
            padding: 22px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            overflow: hidden;
        }

        .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.25rem;
            box-shadow: 0 12px 22px rgba(31, 45, 78, .16);
        }

        .metric-blue { background: linear-gradient(135deg, #1f6fff, #4c8dff); }
        .metric-green { background: linear-gradient(135deg, #10b981, #21d07a); }
        .metric-purple { background: linear-gradient(135deg, #7248dd, #9b6cff); }
        .metric-orange { background: linear-gradient(135deg, #ff9d16, #ffc044); }

        .metric-label {
            color: #617092;
            font-weight: 800;
            font-size: .88rem;
            margin-bottom: .35rem;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 850;
            line-height: 1;
        }

        .metric-trend {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            color: var(--green);
            font-size: .82rem;
            font-weight: 800;
            margin-top: .6rem;
        }

        .metric-trend.down {
            color: var(--red);
        }

        .sparkline {
            width: 86px;
            height: 38px;
        }

        .panel {
            padding: 24px;
            height: 100%;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 18px;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: .7rem;
            font-size: 1.08rem;
            font-weight: 850;
            margin: 0;
        }

        .panel-title i {
            color: var(--blue);
        }

        .soft-select,
        .soft-button {
            border: 1px solid #d9e2f2;
            background: #fff;
            color: #28508f;
            border-radius: 8px;
            padding: .55rem .85rem;
            font-weight: 800;
            font-size: .86rem;
            text-decoration: none;
        }

        .chart-box {
            height: 280px;
            position: relative;
        }

        .donut-grid {
            display: grid;
            grid-template-columns: minmax(210px, 280px) 1fr;
            gap: 24px;
            align-items: center;
        }

        .donut-box {
            height: 230px;
            position: relative;
        }

        .distribution-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 16px;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid #eef2f8;
            color: #33415f;
            font-weight: 700;
        }

        .distribution-row:last-child {
            border-bottom: 0;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: .55rem;
        }

        .table {
            color: #273553;
        }

        .table thead th {
            color: #273553;
            font-size: .82rem;
            border-bottom: 1px solid #e8eef7;
            padding: .9rem .65rem;
            white-space: nowrap;
        }

        .table tbody td {
            border-color: #edf2f8;
            padding: .85rem .65rem;
            font-weight: 650;
            vertical-align: middle;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            padding: .32rem .55rem;
            font-size: .74rem;
            font-weight: 850;
            color: #fff;
        }

        .status-success { background: #18a565; }
        .status-danger { background: #ef4452; }
        .status-warning { background: #f7a719; }
        .status-info { background: #2f7df6; }
        .status-muted { background: #64748b; }

        .row-action {
            color: #0f274c;
            border: 0;
            background: transparent;
            padding: .25rem;
        }

        .activity-list {
            display: grid;
            gap: 0;
        }

        .activity-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 14px;
            align-items: start;
            padding: 12px 0;
            border-bottom: 1px solid #edf2f8;
        }

        .activity-item:last-child {
            border-bottom: 0;
        }

        .activity-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: grid;
            place-items: center;
        }

        .activity-success { color: #0fa76a; background: #dff8ec; }
        .activity-primary { color: #2f7df6; background: #e4efff; }
        .activity-warning { color: #ef9f13; background: #fff1d4; }
        .activity-purple { color: #8358e8; background: #efe8ff; }
        .activity-danger { color: #ef4452; background: #ffe1e5; }

        .activity-title {
            font-weight: 850;
            color: #273553;
            line-height: 1.25;
        }

        .activity-meta {
            color: #657392;
            font-size: .85rem;
            margin-top: .14rem;
        }

        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--muted);
            border: 1px dashed #dbe4f1;
            border-radius: 8px;
            background: #fbfdff;
        }

        @media (max-width: 1199.98px) {
            .metric-card {
                grid-template-columns: auto 1fr;
            }
            .sparkline {
                grid-column: 1 / -1;
                width: 100%;
            }
            .donut-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-shell {
                width: min(100% - 18px, 1480px);
                padding-top: 14px;
            }
            .top-row,
            .panel-header {
                flex-direction: column;
                align-items: stretch;
            }
            .date-pill,
            .soft-button,
            .soft-select {
                width: 100%;
                justify-content: center;
            }
            .panel {
                padding: 18px;
            }
            .chart-box {
                height: 230px;
            }
            .activity-item {
                grid-template-columns: auto 1fr;
            }
            .activity-time {
                grid-column: 2;
            }
        }
    </style>
</head>
<body>
    <?php echo renderAdminHeader('dashboard', 'Selamat datang, ' . $currentUserName, 'Berikut ringkasan data sistem pendukung keputusan kredit.'); ?>

    <main class="dashboard-shell">
        <section class="row g-3 mb-4">
            <?php
            $metricCards = [
                ['label' => 'Total Pengajuan', 'value' => $totals['applications'], 'trend' => $metricTrends['applications'], 'icon' => 'fa-users', 'color' => 'metric-blue', 'spark' => [18, 30, 24, 42, 35, 58, 49]],
                ['label' => 'Total Anggota', 'value' => $totals['members'], 'trend' => $metricTrends['members'], 'icon' => 'fa-user', 'color' => 'metric-green', 'spark' => [22, 30, 28, 40, 34, 52, 46]],
                ['label' => 'Kriteria Aktif', 'value' => $totals['criteria'], 'trend' => $metricTrends['criteria'], 'icon' => 'fa-clipboard-check', 'color' => 'metric-purple', 'spark' => [24, 33, 40, 31, 50, 36, 30]],
                ['label' => 'Hasil SAW', 'value' => $totals['saw'], 'trend' => $metricTrends['saw'], 'icon' => 'fa-chart-pie', 'color' => 'metric-orange', 'spark' => [20, 34, 28, 46, 38, 52, 44]],
            ];
            ?>
            <?php foreach ($metricCards as $card): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="metric-card">
                        <div class="metric-icon <?php echo $card['color']; ?>"><i class="fas <?php echo $card['icon']; ?>"></i></div>
                        <div>
                            <div class="metric-label"><?php echo htmlspecialchars($card['label']); ?></div>
                            <div class="metric-value"><?php echo (int) $card['value']; ?></div>
                            <div class="metric-trend <?php echo $card['trend'] < 0 ? 'down' : ''; ?>">
                                <i class="fas <?php echo $card['trend'] < 0 ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                <?php echo number_format(abs($card['trend']), 1); ?>% dari bulan lalu
                            </div>
                        </div>
                        <svg class="sparkline" viewBox="0 0 92 42" role="img" aria-label="Tren <?php echo htmlspecialchars($card['label']); ?>">
                            <polyline fill="none" stroke="<?php echo $card['trend'] < 0 ? '#f0525f' : '#2f7df6'; ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                                points="<?php foreach ($card['spark'] as $index => $value) { echo ($index * 15) . ',' . (42 - $value / 1.45) . ' '; } ?>" />
                        </svg>
                    </article>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-12 col-xl-7">
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title"><i class="fas fa-chart-line"></i>Grafik Pengajuan Kredit</h2>
                        <select class="soft-select" aria-label="Rentang grafik">
                            <option>6 Bulan Terakhir</option>
                        </select>
                    </div>
                    <div class="chart-box">
                        <canvas id="applicationChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title"><i class="fas fa-circle-notch"></i>Distribusi Skor SAW</h2>
                    </div>
                    <div class="donut-grid">
                        <div class="donut-box">
                            <canvas id="distributionChart"></canvas>
                        </div>
                        <div>
                            <?php foreach ($distribution as $label => $item): ?>
                                <?php $percentage = $distributionTotal > 0 ? ($item['count'] / $distributionTotal) * 100 : 0; ?>
                                <div class="distribution-row">
                                    <div><span class="dot" style="background: <?php echo $item['color']; ?>"></span><?php echo htmlspecialchars($label); ?></div>
                                    <div><?php echo (int) $item['count']; ?></div>
                                    <div class="text-muted"><?php echo number_format($percentage, 1); ?>%</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="text-muted fw-semibold small mb-0 mt-3">Data berdasarkan pengajuan yang telah dinilai SAW.</p>
                </div>
            </div>
        </section>

        <section class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Pengajuan Terbaru</h2>
                        <a class="soft-button" href="applications.php">Lihat Semua</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>ID Pengajuan</th>
                                    <th>Nama Anggota</th>
                                    <th>Jenis Kredit</th>
                                    <th>Jumlah</th>
                                    <th>Skor SAW</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentApplications as $app): ?>
                                    <tr>
                                        <td>#<?php echo (int) $app['id']; ?></td>
                                        <td><?php echo htmlspecialchars($app['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($app['jenis_kredit']); ?></td>
                                        <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                        <td><?php echo $app['persentase_saw'] !== null ? number_format((float) $app['persentase_saw'], 2) : '-'; ?></td>
                                        <td><span class="status-badge <?php echo statusBadgeClass($app['status']); ?>"><?php echo statusLabel($app['status']); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($app['created_at'])); ?></td>
                                        <td><a class="row-action" href="applications.php" aria-label="Buka pengajuan #<?php echo (int) $app['id']; ?>"><i class="fas fa-ellipsis-vertical"></i></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentApplications)): ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">Belum ada pengajuan.</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Aktivitas Terbaru</h2>
                        <a class="soft-button" href="reports.php">Lihat Semua</a>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <?php [$icon, $className] = activityIcon((string) $activity['aksi']); ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $className; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                                <div>
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['aksi']); ?></div>
                                    <div class="activity-meta">Pengajuan #<?php echo (int) $activity['pengajuan_id']; ?> · <?php echo htmlspecialchars($activity['jenis_kredit']); ?></div>
                                </div>
                                <div class="activity-time text-muted fw-semibold small"><?php echo formatRelativeTime($activity['timestamp'] ?? null); ?></div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activities)): ?>
                            <div class="empty-state">Belum ada aktivitas terbaru.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const labels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const submittedData = <?php echo json_encode($chartSubmitted); ?>;
        const acceptedData = <?php echo json_encode($chartAccepted); ?>;
        const rejectedData = <?php echo json_encode($chartRejected); ?>;
        const distributionLabels = <?php echo json_encode($distributionLabels, JSON_UNESCAPED_UNICODE); ?>;
        const distributionData = <?php echo json_encode($distributionCounts); ?>;
        const distributionColors = <?php echo json_encode($distributionColors); ?>;
        const distributionTotal = <?php echo (int) $distributionTotal; ?>;

        Chart.defaults.font.family = 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#69789a';

        new Chart(document.getElementById('applicationChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Pengajuan',
                        data: submittedData,
                        borderColor: '#2f7df6',
                        backgroundColor: 'rgba(47, 125, 246, .10)',
                        fill: true,
                        tension: .42,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                    },
                    {
                        label: 'Disetujui',
                        data: acceptedData,
                        borderColor: '#16c784',
                        backgroundColor: 'rgba(22, 199, 132, .10)',
                        fill: true,
                        tension: .42,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                    },
                    {
                        label: 'Ditolak',
                        data: rejectedData,
                        borderColor: '#f0525f',
                        backgroundColor: 'rgba(240, 82, 95, .08)',
                        fill: false,
                        tension: .42,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            font: { weight: 700 },
                            padding: 24
                        }
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                scales: {
                    x: { grid: { color: 'rgba(226, 232, 240, .55)' }, border: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e9eef7' }, border: { display: false } }
                }
            }
        });

        const centerTextPlugin = {
            id: 'centerText',
            afterDraw(chart) {
                const {ctx, chartArea} = chart;
                if (!chartArea) return;
                const x = (chartArea.left + chartArea.right) / 2;
                const y = (chartArea.top + chartArea.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.fillStyle = '#69789a';
                ctx.font = '700 13px Inter, system-ui, sans-serif';
                ctx.fillText('Total', x, y - 14);
                ctx.fillStyle = '#16213d';
                ctx.font = '850 26px Inter, system-ui, sans-serif';
                ctx.fillText(String(distributionTotal), x, y + 10);
                ctx.fillStyle = '#69789a';
                ctx.font = '700 13px Inter, system-ui, sans-serif';
                ctx.fillText('Pengajuan', x, y + 32);
                ctx.restore();
            }
        };

        new Chart(document.getElementById('distributionChart'), {
            type: 'doughnut',
            data: {
                labels: distributionLabels,
                datasets: [{
                    data: distributionData,
                    backgroundColor: distributionColors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return `${context.label}: ${context.parsed}`;
                            }
                        }
                    }
                }
            },
            plugins: [centerTextPlugin]
        });
    </script>
</body>
</html>
