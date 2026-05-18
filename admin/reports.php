<?php
// Halaman laporan admin untuk merangkum data pengajuan dan hasil sistem.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';

$conn = getDBConnection();

function normalizeDateInput(?string $value): ?string
{
    // Validasi input tanggal agar aman dipakai pada filter laporan.
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }

    return $value;
}

function getReportFiltersFromRequest(): array
{
    // Ambil filter periode dari query string.
    return [
        'from' => normalizeDateInput($_GET['from'] ?? null),
        'to' => normalizeDateInput($_GET['to'] ?? null),
    ];
}

function buildDateRangeClause(string $column, ?string $from, ?string $to): array
{
    // Susun kondisi SQL berdasarkan rentang tanggal yang dipilih.
    $conditions = [];
    $params = [];
    $types = '';

    if ($from) {
        $conditions[] = "DATE($column) >= ?";
        $params[] = $from;
        $types .= 's';
    }

    if ($to) {
        $conditions[] = "DATE($column) <= ?";
        $params[] = $to;
        $types .= 's';
    }

    return [
        'sql' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
        'types' => $types,
    ];
}

function bindParams(mysqli_stmt $stmt, string $types, array $params): void
{
    // Helper bind parameter untuk prepared statement dinamis.
    if ($params === []) {
        return;
    }

    $bindArgs = [$types];
    foreach ($params as $index => $value) {
        $bindArgs[] = &$params[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindArgs);
}

function formatLoanAmount(float $amount): string
{
    // Format rupiah untuk tampilan laporan.
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatPeriodLabel(?string $from, ?string $to): string
{
    // Judul periode laporan yang mudah dibaca.
    if ($from && $to) {
        return $from . ' s.d. ' . $to;
    }

    if ($from) {
        return 'Mulai ' . $from;
    }

    if ($to) {
        return 'Sampai ' . $to;
    }

    return 'Semua periode';
}

function reportTypeLabel(string $type): string
{
    // Label jenis laporan untuk tampilan dan metadata.
    return match ($type) {
        'peminjaman' => 'Laporan Peminjaman',
        'summary' => 'Ringkasan',
        'monthly' => 'Bulanan',
        'audit' => 'Audit',
        default => ucfirst($type),
    };
}

function fetchLoanReportData(mysqli $conn, ?string $from, ?string $to): array
{
    // Ambil data laporan utama beserta ringkasan status pengajuan.
    $range = buildDateRangeClause('p.created_at', $from, $to);
    $sql = 'SELECT p.id, p.jenis_kredit, p.jumlah_pinjaman, p.created_at, p.status,
                   a.nama, u.username,
                   h.kelayakan, h.persentase_saw, h.ranking
            FROM pengajuan p
            JOIN anggota a ON p.anggota_id = a.id
            JOIN users u ON a.user_id = u.id
            LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id'
        . $range['sql'] .
        ' ORDER BY COALESCE(h.ranking, 999999) ASC, p.created_at DESC, p.id DESC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [
            'periode' => formatPeriodLabel($from, $to),
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_pengajuan' => 0,
                'total_pinjaman' => 0.0,
                'rata_rata_pinjaman' => 0.0,
                'diterima' => 0,
                'ditolak' => 0,
                'belum_terproses' => 0,
            ],
            'accepted' => [],
        'rejected' => [],
        'pending' => [],
        ];
    }

    bindParams($stmt, $range['types'], $range['params']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $accepted = [];
    $rejected = [];
    $pending = [];
    $totalLoan = 0.0;

    foreach ($rows as $row) {
        $totalLoan += (float) $row['jumlah_pinjaman'];
        $status = $row['status'] ?? 'pending';

        if ($status === 'accepted') {
            $accepted[] = $row;
        } elseif (in_array($status, ['rejected', 'document_rejected'], true)) {
            $rejected[] = $row;
        } else {
            $pending[] = $row;
        }
    }

    $count = count($rows);

    return [
        'periode' => formatPeriodLabel($from, $to),
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [
            'total_pengajuan' => $count,
            'total_pinjaman' => $totalLoan,
            'rata_rata_pinjaman' => $count > 0 ? $totalLoan / $count : 0.0,
            'diterima' => count($accepted),
            'ditolak' => count($rejected),
            'belum_terproses' => count($pending),
        ],
        'accepted' => $accepted,
        'rejected' => $rejected,
        'pending' => $pending,
    ];
}

function jsonReportPayload(array $report): string
{
    // Serialisasi data laporan agar bisa disimpan atau dipakai ulang.
    return json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function decodeReportPayload(?string $json): array
{
    // Kembalikan payload laporan ke bentuk array.
    if ($json === null || $json === '') {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function renderLoanTable(array $rows, string $emptyMessage): string
{
    // Bangun tabel HTML laporan untuk data pengajuan.
    if (empty($rows)) {
        return '<div class="alert alert-light border text-muted mb-0">' . htmlspecialchars($emptyMessage) . '</div>';
    }

    $html = '<div class="table-responsive"><table class="table table-striped align-middle mb-0">';
    $html .= '<thead><tr>
                <th>Peringkat</th>
                <th>ID</th>
                <th>Anggota</th>
                <th>Jenis</th>
                <th>Pinjaman</th>
                <th>SAW</th>
                <th>Rekomendasi Sistem</th>
                <th>Status Final</th>
                <th>Tanggal</th>
              </tr></thead><tbody>';

    foreach ($rows as $row) {
        $status = $row['status'] ?? 'pending';
        $kelayakan = $row['kelayakan'] ?? '';
        if ($kelayakan === 'layak') {
            $kelayakanLabel = 'Layak Direkomendasikan';
            $kelayakanClass = 'success';
        } elseif ($kelayakan === 'tidak_layak') {
            $kelayakanLabel = 'Belum Layak Direkomendasikan';
            $kelayakanClass = 'danger';
        } else {
            $kelayakanLabel = 'Belum Ada Rekomendasi';
            $kelayakanClass = 'secondary';
        }
        $statusClass = $status === 'accepted'
            ? 'success'
            : (in_array($status, ['rejected', 'document_rejected'], true) ? 'danger' : 'secondary');
        $ranking = $row['ranking'] !== null ? (int) $row['ranking'] : '-';
        $persentase = $row['persentase_saw'] !== null ? number_format((float) $row['persentase_saw'], 2) . '%' : '-';

        $html .= '<tr>';
        $html .= '<td><span class="badge text-bg-primary">' . htmlspecialchars((string) $ranking) . '</span></td>';
        $html .= '<td>' . (int) $row['id'] . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nama']) . '</td>';
        $html .= '<td><span class="badge text-bg-' . ($row['jenis_kredit'] === 'KTA' ? 'primary' : 'success') . '">' . htmlspecialchars($row['jenis_kredit']) . '</span></td>';
        $html .= '<td>' . htmlspecialchars(formatLoanAmount((float) $row['jumlah_pinjaman'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($persentase) . '</td>';
        $html .= '<td><span class="badge text-bg-' . $kelayakanClass . ' text-wrap" style="white-space: normal;">' . $kelayakanLabel . '</span></td>';
        $html .= '<td><span class="badge text-bg-' . $statusClass . '">' . htmlspecialchars(statusLabel($status)) . '</span></td>';
        $html .= '<td>' . htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))) . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';

    return $html;
}

function statusLabel(string $status): string
{
    // Label status pengajuan pada laporan.
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Siap Validasi Final',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst($status),
    };
}

function exportLoanReportCsv(array $report): void
{
    $filename = 'laporan_peminjaman_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Laporan Peminjaman']);
    fputcsv($out, ['Periode', $report['periode'] ?? '-']);
    fputcsv($out, ['Dibuat', $report['generated_at'] ?? '-']);
    fputcsv($out, []);
    fputcsv($out, ['Ringkasan']);
    fputcsv($out, ['Total Pengajuan', $report['summary']['total_pengajuan'] ?? 0]);
    fputcsv($out, ['Total Peminjaman', $report['summary']['total_pinjaman'] ?? 0]);
    fputcsv($out, ['Rata-rata Pinjaman', $report['summary']['rata_rata_pinjaman'] ?? 0]);
    fputcsv($out, ['Diterima', $report['summary']['diterima'] ?? 0]);
    fputcsv($out, ['Ditolak', $report['summary']['ditolak'] ?? 0]);
    fputcsv($out, ['Belum Ada Keputusan Final', $report['summary']['belum_terproses'] ?? 0]);
    fputcsv($out, []);
    fputcsv($out, ['Diterima CU']);
    fputcsv($out, ['Peringkat', 'ID', 'Anggota', 'Jenis', 'Pinjaman', 'SAW', 'Rekomendasi Sistem', 'Status Final', 'Tanggal']);
    foreach (($report['accepted'] ?? []) as $row) {
        fputcsv($out, [
            $row['ranking'] ?? '-',
            $row['id'] ?? '-',
            $row['nama'] ?? '-',
            $row['jenis_kredit'] ?? '-',
            $row['jumlah_pinjaman'] ?? '-',
            $row['persentase_saw'] ?? '-',
            'Layak Direkomendasikan',
            statusLabel((string) ($row['status'] ?? 'pending')),
            $row['created_at'] ?? '-',
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Ditolak CU / Dokumen']);
    fputcsv($out, ['Peringkat', 'ID', 'Anggota', 'Jenis', 'Pinjaman', 'SAW', 'Rekomendasi Sistem', 'Status Final', 'Tanggal']);
    foreach (($report['rejected'] ?? []) as $row) {
        fputcsv($out, [
            $row['ranking'] ?? '-',
            $row['id'] ?? '-',
            $row['nama'] ?? '-',
            $row['jenis_kredit'] ?? '-',
            $row['jumlah_pinjaman'] ?? '-',
            $row['persentase_saw'] ?? '-',
            'Belum Layak Direkomendasikan',
            statusLabel((string) ($row['status'] ?? 'pending')),
            $row['created_at'] ?? '-',
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['Belum Ada Keputusan Final']);
    fputcsv($out, ['Peringkat', 'ID', 'Anggota', 'Jenis', 'Pinjaman', 'SAW', 'Rekomendasi Sistem', 'Status Final', 'Tanggal']);
    foreach (($report['pending'] ?? []) as $row) {
        fputcsv($out, [
            $row['ranking'] ?? '-',
            $row['id'] ?? '-',
            $row['nama'] ?? '-',
            $row['jenis_kredit'] ?? '-',
            $row['jumlah_pinjaman'] ?? '-',
            $row['persentase_saw'] ?? '-',
            ($row['kelayakan'] ?? '') === 'layak' ? 'Layak Direkomendasikan' : 'Belum Layak Direkomendasikan',
            statusLabel((string) ($row['status'] ?? 'pending')),
            $row['created_at'] ?? '-',
        ]);
    }

    fclose($out);
}

function saveReport(mysqli $conn, array $report): void
{
    $json = jsonReportPayload($report);
    $type = 'peminjaman';
    $stmt = $conn->prepare('INSERT INTO laporan (generated_by, jenis_laporan, data) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $_SESSION['user_id'], $type, $json);
    $stmt->execute();
}

function fetchSavedReports(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT l.id, l.jenis_laporan, l.data, l.created_at, u.username
         FROM laporan l
         JOIN users u ON l.generated_by = u.id
         ORDER BY l.created_at DESC'
    );

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

$filters = getReportFiltersFromRequest();
$currentReport = fetchLoanReportData($conn, $filters['from'], $filters['to']);
$savedReports = fetchSavedReports($conn);
$hasSavedReports = !empty($savedReports);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportLoanReportCsv($currentReport);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_report'])) {
            $from = normalizeDateInput($_POST['from_date'] ?? null);
            $to = normalizeDateInput($_POST['to_date'] ?? null);
            $report = fetchLoanReportData($conn, $from, $to);
            saveReport($conn, $report);

            $query = http_build_query(array_filter([
                'from' => $from,
                'to' => $to,
            ], static fn($value) => $value !== null && $value !== ''));

            header('Location: reports.php' . ($query ? '?' . $query : ''));
            exit();
        } elseif (isset($_POST['delete_report'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare('DELETE FROM laporan WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
            }

            $query = http_build_query(array_filter([
                'from' => $filters['from'],
                'to' => $filters['to'],
            ], static fn($value) => $value !== null && $value !== ''));

            header('Location: reports.php' . ($query ? '?' . $query : ''));
            exit();
        }
    } catch (Throwable $e) {
        header('Location: reports.php?error=1');
        exit();
    }
}

$filterQuery = http_build_query(array_filter([
    'from' => $filters['from'],
    'to' => $filters['to'],
], static fn($value) => $value !== null && $value !== ''));

$exportUrl = 'reports.php?export=csv' . ($filterQuery ? '&' . $filterQuery : '');
$resetUrl = 'reports.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Peminjaman - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 100%);
        }
        .hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #0f766e 100%);
            color: #fff;
            border: 0;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }
        .section-card {
            border: 0;
            box-shadow: 0 10px 25px rgba(15, 23, 42, .06);
            border-radius: 1rem;
        }
        .metric-card {
            border: 0;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            .section-card, .metric-card, .hero {
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-house me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card hero mb-4 no-print">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
                    <div>
                        <h2 class="mb-1">Laporan Peminjaman</h2>
                        <p class="mb-0 text-white-50">Daftar pengajuan yang diterima dan ditolak berdasarkan hasil SAW, lengkap dengan total dan rata-rata pinjaman pada periode tertentu.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn btn-light">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </a>
                        <button type="button" class="btn btn-outline-light" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card mb-4 no-print">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-7">
                        <form method="GET" class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label">Dari</label>
                                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($filters['from'] ?? ''); ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Sampai</label>
                                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($filters['to'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2 d-grid">
                                <label class="form-label invisible">Terapkan</label>
                                <button type="submit" class="btn btn-primary">Terapkan</button>
                            </div>
                            <div class="col-12">
                                <a href="<?php echo htmlspecialchars($resetUrl); ?>" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <form method="POST" class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label">Simpan Laporan Periode Ini</label>
                                <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($filters['from'] ?? ''); ?>">
                                <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($filters['to'] ?? ''); ?>">
                                <div class="form-control bg-light text-muted">
                                    <?php echo htmlspecialchars(formatPeriodLabel($filters['from'], $filters['to'])); ?>
                                </div>
                            </div>
                            <div class="col-md-4 d-grid">
                                <label class="form-label invisible">Simpan</label>
                                <button type="submit" name="generate_report" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Pengajuan</div>
                        <div class="fs-3 fw-bold"><?php echo (int) $currentReport['summary']['total_pengajuan']; ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($currentReport['periode']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Total Peminjaman</div>
                        <div class="fs-3 fw-bold"><?php echo htmlspecialchars(formatLoanAmount((float) $currentReport['summary']['total_pinjaman'])); ?></div>
                        <div class="text-muted small">Semua pengajuan pada periode ini</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Rata-rata Pinjaman</div>
                        <div class="fs-3 fw-bold"><?php echo htmlspecialchars(formatLoanAmount((float) $currentReport['summary']['rata_rata_pinjaman'])); ?></div>
                        <div class="text-muted small">Rata-rata per pengajuan</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Keputusan Final</div>
                        <div class="fs-6 fw-bold text-success">Diterima CU: <?php echo (int) $currentReport['summary']['diterima']; ?></div>
                        <div class="fs-6 fw-bold text-danger">Ditolak: <?php echo (int) $currentReport['summary']['ditolak']; ?></div>
                        <div class="text-muted small">Belum ada keputusan final: <?php echo (int) $currentReport['summary']['belum_terproses']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1">Pengajuan Diterima CU</h4>
                        <p class="text-muted mb-0">Pengajuan yang sudah divalidasi final dan diterima oleh CU.</p>
                    </div>
                    <span class="badge bg-success">Diterima CU</span>
                </div>
                <?php echo renderLoanTable($currentReport['accepted'], 'Belum ada pengajuan yang diterima pada periode ini.'); ?>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1">Pengajuan Ditolak</h4>
                        <p class="text-muted mb-0">Pengajuan yang ditolak dokumen atau ditolak pada validasi final.</p>
                    </div>
                    <span class="badge bg-danger">Ditolak</span>
                </div>
                <?php echo renderLoanTable($currentReport['rejected'], 'Belum ada pengajuan yang ditolak pada periode ini.'); ?>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1">Belum Ada Keputusan Final</h4>
                        <p class="text-muted mb-0">Pengajuan yang sudah diverifikasi dokumen tetapi belum diputuskan final oleh petugas.</p>
                    </div>
                    <span class="badge bg-secondary">Pending</span>
                </div>
                <?php echo renderLoanTable($currentReport['pending'], 'Belum ada pengajuan yang menunggu keputusan final pada periode ini.'); ?>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                    <div>
                        <h4 class="mb-1">Arsip Laporan</h4>
                        <p class="text-muted mb-0">Laporan yang sudah disimpan sebelumnya.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="reportsTable" class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Jenis</th>
                                <th>Dibuat Oleh</th>
                                <th>Periode</th>
                                <th>Diterima</th>
                                <th>Ditolak</th>
                                <th>Dibuat</th>
                                <th class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($savedReports as $report): ?>
                                <?php $payload = decodeReportPayload($report['data']); ?>
                                <tr data-report-row="1">
                                    <td><?php echo (int) $report['id']; ?></td>
                                    <td><?php echo htmlspecialchars(reportTypeLabel($report['jenis_laporan'])); ?></td>
                                    <td><?php echo htmlspecialchars($report['username']); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($payload['periode'] ?? '-')); ?></td>
                                    <td><?php echo (int) ($payload['summary']['diterima'] ?? 0); ?></td>
                                    <td><?php echo (int) ($payload['summary']['ditolak'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                                    <td class="no-print">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus laporan ini?')">
                                            <input type="hidden" name="id" value="<?php echo (int) $report['id']; ?>">
                                            <button type="submit" name="delete_report" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$hasSavedReports): ?>
                        <div class="alert alert-info border-0 mt-3 mb-0">
                            Belum ada arsip laporan.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#reportsTable tbody tr[data-report-row="1"]').length > 0) {
                $('#reportsTable').DataTable({
                    pageLength: 10,
                    order: [[6, 'desc']]
                });
            }
        });
    </script>
</body>
</html>
