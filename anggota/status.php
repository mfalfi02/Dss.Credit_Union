<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('anggota');
require_once '../config/database.php';
$conn = getDBConnection();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare(
    "SELECT a.id AS anggota_id, a.nama
     FROM anggota a
     WHERE a.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

$applications = [];
if ($member) {
    $sql = "SELECT p.*,
            h.skor_terbobot, h.persentase_saw, h.kelayakan, h.ranking,
            (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) as dokumen_count
            FROM pengajuan p
            JOIN anggota a ON p.anggota_id = a.id
            LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
            WHERE a.user_id = ?
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function summarize_status_detail($detailJson, $jenisKredit) {
    $detail = json_decode($detailJson ?? '', true);
    if (!is_array($detail)) {
        return '-';
    }

    $parts = [];
    $addPart = static function (array &$parts, $value): void {
        if ($value === null || $value === '' || $value === '-') {
            return;
        }
        $parts[] = htmlspecialchars((string) $value);
    };

    if ($jenisKredit === 'KTA') {
        $addPart($parts, $detail['tujuan_pinjaman'] ?? '');
        $addPart($parts, $detail['status_pekerjaan_asli'] ?? '');
        if (($detail['status_pekerjaan_asli'] ?? '') === 'Pelajar/Mahasiswa') {
            $addPart($parts, 'Tanggungan ' . (int) ($detail['jumlah_tanggungan'] ?? 0));
        } else {
            $addPart($parts, $detail['nama_tempat_kerja'] ?? '');
            $addPart($parts, !empty($detail['lama_bekerja_bulan']) ? (int) $detail['lama_bekerja_bulan'] . ' bulan kerja' : '');
        }
        $addPart($parts, !empty($detail['penghasilan_bulanan']) ? 'Rp ' . number_format((float) $detail['penghasilan_bulanan'], 0, ',', '.') : '');
        return $parts ? implode(' • ', array_slice($parts, 0, 3)) : '-';
    }

    $addPart($parts, $detail['nama_usaha'] ?? '');
    $addPart($parts, $detail['bidang_usaha'] ?? '');
    $addPart($parts, !empty($detail['omzet_bulanan']) ? 'Omzet Rp ' . number_format((float) $detail['omzet_bulanan'], 0, ',', '.') : '');
    $addPart($parts, !empty($detail['laba_bersih_bulanan']) ? 'Laba Rp ' . number_format((float) $detail['laba_bersih_bulanan'], 0, ',', '.') : '');

    return $parts ? implode(' • ', array_slice($parts, 0, 3)) : '-';
}

function eligibilityLabel($value)
{
    return $value === 'layak'
        ? 'Memenuhi Batas Minimum'
        : 'Belum Memenuhi Batas Minimum';
}

function statusLabel($status)
{
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Terverifikasi',
        'accepted' => 'Disetujui',
        'rejected' => 'Ditolak',
        default => ucfirst((string) $status),
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pengajuan - SPK Kredit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Anggota</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
            <div>
                <h2 class="mb-1">Status Pengajuan</h2>
                <p class="text-muted mb-0">Pantau status pengajuan KTA dan KUR milikmu.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="dashboard.php" class="btn btn-outline-secondary">Kembali ke Dashboard</a>
                <a href="submit_application.php" class="btn btn-info">Ajukan Baru</a>
            </div>
        </div>

        <?php if (!$member): ?>
            <div class="alert alert-warning">
                Akun ini belum terhubung ke data anggota. Silakan lengkapi profil anggota terlebih dahulu.
            </div>
        <?php else: ?>
            <?php if (empty($applications)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="text-muted mb-2">Belum ada pengajuan.</div>
                        <p class="mb-3">Silakan ajukan kredit terlebih dahulu untuk melihat statusnya di sini.</p>
                        <a href="submit_application.php" class="btn btn-info">Ajukan Sekarang</a>
                    </div>
                </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <table id="applicationsTable" class="table table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th>No</th>
                            <th>Jenis Kredit</th>
                            <th>Jumlah Pinjaman</th>
                            <th>Detail Singkat</th>
                            <th>Status</th>
                            <th>Persentase</th>
                            <th>Kelayakan</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Dokumen</th>
                            <th>Detail</th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($applications as $app): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>">
                                        <?php echo htmlspecialchars($app['jenis_kredit']); ?>
                                    </span>
                                </td>
                                <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                <td class="small text-muted" style="max-width: 320px;">
                                    <span class="d-inline-block text-truncate w-100" title="<?php echo htmlspecialchars(summarize_status_detail($app['detail_pinjaman'], $app['jenis_kredit'])); ?>">
                                        <?php echo summarize_status_detail($app['detail_pinjaman'], $app['jenis_kredit']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?php
                                        echo $app['status'] === 'pending' ? 'warning' :
                                            ($app['status'] === 'verified' ? 'info' :
                                                ($app['status'] === 'accepted' ? 'success' : 'danger'));
                                    ?>">
                                        <?php echo statusLabel($app['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $app['persentase_saw'] !== null ? number_format((float) $app['persentase_saw'], 2) . '%' : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($app['kelayakan']): ?>
                                        <span class="badge text-bg-<?php echo $app['kelayakan'] === 'layak' ? 'success' : 'danger'; ?> text-wrap" style="white-space: normal;">
                                            <?php echo eligibilityLabel($app['kelayakan']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                                <td><?php echo (int) $app['dokumen_count']; ?> berkas</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info"
                                        onclick="showDetail(<?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)">
                                        Lihat
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent" class="row g-3"></div>
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
            if ($('#applicationsTable').length) {
                $('#applicationsTable').DataTable();
            }
        });

        function showDetail(app) {
            let detail = {};
            try {
                detail = typeof app.detail_pinjaman === 'string' ? JSON.parse(app.detail_pinjaman || '{}') : (app.detail_pinjaman || {});
            } catch (e) {
                detail = {};
            }

            const statusMap = {
                pending: 'Menunggu',
                verified: 'Terverifikasi',
                accepted: 'Disetujui',
                rejected: 'Ditolak',
            };

            const items = [
                ['ID', app.id],
                ['Jenis Kredit', app.jenis_kredit],
                ['Jumlah Pinjaman', 'Rp ' + Number(app.jumlah_pinjaman).toLocaleString('id-ID')],
                ['Status', statusMap[app.status] || app.status],
                ['Persentase', app.persentase_saw ? Number(app.persentase_saw).toFixed(2) + '%' : '-'],
                ['Kelayakan', app.kelayakan ? (app.kelayakan === 'layak' ? 'Memenuhi Batas Minimum' : 'Belum Memenuhi Batas Minimum') : '-'],
                ['Tanggal', new Date(app.created_at).toLocaleDateString('id-ID')],
                ['Dokumen', app.dokumen_count + ' file'],
            ];

            if (app.jenis_kredit === 'KTA') {
                items.push(['Tujuan', detail.tujuan_pinjaman || '-']);
                items.push(['Status Pekerjaan', detail.status_pekerjaan_asli || '-']);
                if (detail.status_pekerjaan_custom) {
                    items.push(['Keterangan', detail.status_pekerjaan_custom]);
                }
                if (detail.status_pekerjaan_asli === 'Pelajar/Mahasiswa') {
                    items.push(['Jumlah Tanggungan', detail.jumlah_tanggungan ?? '-']);
                } else {
                    items.push(['Pekerjaan', detail.status_pekerjaan || '-']);
                    items.push(['Tempat Kerja', detail.nama_tempat_kerja || '-']);
                    items.push(['Lama Bekerja', detail.lama_bekerja_bulan ? detail.lama_bekerja_bulan + ' bulan' : '-']);
                }
                items.push(['Penghasilan', detail.penghasilan_bulanan ? 'Rp ' + Number(detail.penghasilan_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Pengeluaran', detail.pengeluaran_bulanan ? 'Rp ' + Number(detail.pengeluaran_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Cicilan', detail.beban_cicilan_bulanan ? 'Rp ' + Number(detail.beban_cicilan_bulanan).toLocaleString('id-ID') : '-']);
            } else {
                items.push(['Nama Usaha', detail.nama_usaha || '-']);
                items.push(['Bidang Usaha', detail.bidang_usaha || '-']);
                items.push(['Alamat Usaha', detail.alamat_usaha || '-']);
                items.push(['Lama Usaha', detail.lama_usaha_bulan ? detail.lama_usaha_bulan + ' bulan' : '-']);
                items.push(['Omzet', detail.omzet_bulanan ? 'Rp ' + Number(detail.omzet_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Laba', detail.laba_bersih_bulanan ? 'Rp ' + Number(detail.laba_bersih_bulanan).toLocaleString('id-ID') : '-']);
                items.push(['Legalitas', detail.legalitas_usaha || '-']);
                items.push(['Tujuan Dana', detail.tujuan_dana || '-']);
            }

            const html = items.map(function(item) {
                return `
                    <div class="col-md-6">
                        <div class="p-3 border rounded h-100">
                            <div class="text-muted small">${item[0]}</div>
                            <div class="fw-semibold">${String(item[1]).replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>
                        </div>
                    </div>
                `;
            }).join('');

            $('#detailContent').html(html);
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    </script>
</body>
</html>
