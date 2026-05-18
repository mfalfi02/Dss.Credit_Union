<?php
// Halaman petugas untuk memeriksa dokumen, mengubah status, dan memberi keputusan awal.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('petugas');
require_once '../config/database.php';
require_once '../function/saw.php';

$conn = getDBConnection();
$saw = new SAWCalculator($conn);

$activeTypes = $conn->query(
    "SELECT DISTINCT jenis_kredit
     FROM pengajuan
     WHERE status IN ('verified', 'accepted')"
);
if ($activeTypes) {
    // Pastikan ranking SAW tetap sinkron untuk pengajuan yang valid.
    while ($row = $activeTypes->fetch_assoc()) {
        if (!empty($row['jenis_kredit'])) {
            $saw->calculateRanking($row['jenis_kredit']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proses perubahan status verifikasi atau penolakan.
    $pengajuan_id = (int) ($_POST['pengajuan_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    if ($pengajuan_id > 0 && in_array($status, ['verified', 'accepted', 'document_rejected', 'rejected'], true)) {
        $stmt = $conn->prepare('SELECT jenis_kredit FROM pengajuan WHERE id = ?');
        $stmt->bind_param('i', $pengajuan_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();

        if (!$application) {
            header('Location: verify_documents.php?error=2');
            exit();
        }

        $conn->begin_transaction();

        try {
            // Update status pengajuan dan simpan riwayat aksi petugas.
            $stmt = $conn->prepare("UPDATE pengajuan SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $pengajuan_id);
            $stmt->execute();

            $aksi = match ($status) {
                'verified' => 'Dokumen diverifikasi',
                'accepted' => 'Validasi akhir disetujui CU',
                'document_rejected' => 'Dokumen ditolak',
                'rejected' => 'Validasi akhir ditolak CU',
                default => 'Perubahan status pengajuan',
            };

            if ($catatan !== '') {
                $aksi .= ' - ' . $catatan;
            }

            $user_id = (int) $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO riwayat_pengajuan (pengajuan_id, aksi, dilakukan_oleh) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $pengajuan_id, $aksi, $user_id);
            $stmt->execute();

            $conn->commit();

            $saw->calculateRanking($application['jenis_kredit']);

            header('Location: verify_documents.php?success=1');
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            header('Location: verify_documents.php?error=1');
            exit();
        }
    }

    header('Location: verify_documents.php?error=2');
    exit();
}

$applications = [];
$result = $conn->query(
    "SELECT p.id, p.jenis_kredit, p.status, p.jumlah_pinjaman, p.detail_pinjaman, p.created_at,
            a.nama, u.username,
            h.skor_terbobot, h.persentase_saw, h.kelayakan,
            COUNT(d.id) AS dokumen_count,
            SUM(CASE WHEN d.jenis = 'ktp' THEN 1 ELSE 0 END) AS ktp_count,
            SUM(CASE WHEN d.jenis = 'slip_gaji' THEN 1 ELSE 0 END) AS slip_gaji_count,
            SUM(CASE WHEN d.jenis = 'surat_kerja' THEN 1 ELSE 0 END) AS surat_kerja_count,
            SUM(CASE WHEN d.jenis = 'kartu_pelajar' THEN 1 ELSE 0 END) AS kartu_pelajar_count,
            SUM(CASE WHEN d.jenis = 'kartu_keluarga' THEN 1 ELSE 0 END) AS kartu_keluarga_count,
            SUM(CASE WHEN d.jenis = 'jaminan' THEN 1 ELSE 0 END) AS jaminan_count,
            SUM(CASE WHEN d.jenis = 'foto_usaha' THEN 1 ELSE 0 END) AS foto_usaha_count,
            SUM(CASE WHEN d.jenis = 'izin_usaha' THEN 1 ELSE 0 END) AS izin_usaha_count,
            SUM(CASE WHEN d.jenis = 'laporan_usaha' THEN 1 ELSE 0 END) AS laporan_usaha_count
     FROM pengajuan p
     JOIN anggota a ON p.anggota_id = a.id
     JOIN users u ON a.user_id = u.id
     LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
     LEFT JOIN dokumen d ON d.pengajuan_id = p.id
     GROUP BY p.id, h.skor_terbobot, h.persentase_saw, h.kelayakan
     ORDER BY p.created_at DESC"
);
if ($result) {
    $applications = $result->fetch_all(MYSQLI_ASSOC);
}

$documentRows = [];
$appIds = array_map(static fn($app) => (int) $app['id'], $applications);
if (!empty($appIds)) {
    $idList = implode(',', array_map('intval', $appIds));
    $resultDocs = $conn->query(
        "SELECT id, pengajuan_id, nama_file, path_file, jenis, uploaded_at
         FROM dokumen
         WHERE pengajuan_id IN ($idList)
         ORDER BY uploaded_at ASC, id ASC"
    );
    if ($resultDocs) {
        $documentRows = $resultDocs->fetch_all(MYSQLI_ASSOC);
    }
}

$documentsByApplication = [];
foreach ($documentRows as $doc) {
    $pengajuanId = (int) $doc['pengajuan_id'];
    $documentsByApplication[$pengajuanId][] = $doc;
}

foreach ($applications as &$app) {
    $appId = (int) $app['id'];
    $app['documents'] = $documentsByApplication[$appId] ?? [];
}
unset($app);

function statusBadgeClass($status)
{
    // Badge status untuk tabel verifikasi.
    return match ($status) {
        'pending' => 'warning',
        'verified' => 'info',
        'document_rejected' => 'danger',
        'accepted' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
}

function sawBadgeClass($kelayakan)
{
    // Badge rekomendasi sistem berdasarkan kelayakan SAW.
    if ($kelayakan === 'layak') {
        return 'success';
    }
    if ($kelayakan === 'tidak_layak') {
        return 'danger';
    }
    return 'secondary';
}

function eligibilityLabel($value)
{
    // Label deskriptif untuk hasil kelayakan.
    return $value === 'layak'
        ? 'Memenuhi Batas Minimum'
        : 'Belum Memenuhi Batas Minimum';
}

function isStudentApplicant($detailJson)
{
    // Cek apakah pemohon KTA termasuk kategori pelajar/mahasiswa.
    $detail = json_decode($detailJson ?? '', true);
    if (!is_array($detail)) {
        return false;
    }

    return ($detail['status_pekerjaan_asli'] ?? '') === 'Pelajar/Mahasiswa';
}

function buildDetailSummary($detailJson, $jenisKredit)
{
    // Ringkas detail pengajuan agar mudah dibaca di tabel.
    $detail = json_decode($detailJson ?? '', true);
    if (!is_array($detail)) {
        return '-';
    }

    $items = [];
    if ($jenisKredit === 'KTA') {
        if (!empty($detail['tujuan_pinjaman'])) {
            $items[] = 'Tujuan: ' . htmlspecialchars($detail['tujuan_pinjaman']);
        }
        if (!empty($detail['status_pekerjaan_asli'])) {
            $items[] = 'Status Pekerjaan: ' . htmlspecialchars($detail['status_pekerjaan_asli']);
        }
        if (!empty($detail['status_pekerjaan_custom'])) {
            $items[] = 'Keterangan: ' . htmlspecialchars($detail['status_pekerjaan_custom']);
        }
        if (($detail['status_pekerjaan_asli'] ?? '') === 'Pelajar/Mahasiswa') {
            $items[] = 'Tanggungan: ' . (int) ($detail['jumlah_tanggungan'] ?? 0);
        } else {
            if (!empty($detail['nama_tempat_kerja'])) {
                $items[] = 'Tempat Kerja: ' . htmlspecialchars($detail['nama_tempat_kerja']);
            }
            if (!empty($detail['lama_bekerja_bulan'])) {
                $items[] = 'Lama Bekerja: ' . (int) $detail['lama_bekerja_bulan'] . ' bulan';
            }
        }
        if (!empty($detail['penghasilan_bulanan'])) {
            $items[] = 'Penghasilan: Rp ' . number_format((float) $detail['penghasilan_bulanan'], 0, ',', '.');
        }
    } else {
        if (!empty($detail['nama_usaha'])) {
            $items[] = 'Usaha: ' . htmlspecialchars($detail['nama_usaha']);
        }
        if (!empty($detail['bidang_usaha'])) {
            $items[] = 'Bidang: ' . htmlspecialchars($detail['bidang_usaha']);
        }
        if (!empty($detail['omzet_bulanan'])) {
            $items[] = 'Omzet: Rp ' . number_format((float) $detail['omzet_bulanan'], 0, ',', '.');
        }
    }

    return $items ? implode('<br>', $items) : '-';
}

function statusLabel($status)
{
    // Label status pengajuan untuk petugas.
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Siap Validasi Final',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst((string) $status),
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Dokumen - Petugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background: linear-gradient(180deg, #f7fbff 0%, #eef8f1 100%);
        }
        .page-shell {
            max-width: 1400px;
        }
        .hero-card {
            background: linear-gradient(135deg, #0f766e 0%, #2563eb 100%);
            color: #fff;
        }

        .section-card {
            border: 0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }

        .doc-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .6rem;
            margin: .15rem .25rem .15rem 0;
            border-radius: 999px;
            background: #f1f5f9;
            font-size: .85rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container page-shell">
            <a class="navbar-brand" href="dashboard.php">Dasbor Petugas</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container page-shell py-4">
        <div class="card hero-card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <h2 class="mb-2">Verifikasi Dokumen</h2>
                        <p class="mb-0 text-white-50">
                            Periksa kelengkapan dokumen, lihat ringkasan pengajuan KTA/KUR, lalu tandai dokumen sebagai diverifikasi atau ditolak.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="dashboard.php" class="btn btn-light">
                            <i class="fas fa-house me-2"></i>Kembali ke Dashboard
                        </a>
                        <a href="rankings.php" class="btn btn-outline-light">
                            <i class="fas fa-ranking-star me-2"></i>Lihat Peringkat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card">
            <div class="card-body">
                <table id="documentsTable" class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Ringkasan</th>
                            <th>Dokumen</th>
                            <th>SAW</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?php echo (int) $app['id']; ?></td>
                            <td><?php echo htmlspecialchars($app['nama']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>">
                                    <?php echo htmlspecialchars($app['jenis_kredit']); ?>
                                </span>
                            </td>
                            <td><?php echo buildDetailSummary($app['detail_pinjaman'], $app['jenis_kredit']); ?></td>
                            <td>
                                <div class="mb-1">
                                    <span class="doc-pill">KTP: <?php echo (int) $app['ktp_count']; ?></span>
                                    <?php if ($app['jenis_kredit'] === 'KTA' && isStudentApplicant($app['detail_pinjaman'])): ?>
                                        <span class="doc-pill">Kartu Pelajar: <?php echo (int) $app['kartu_pelajar_count']; ?></span>
                                        <span class="doc-pill">Kartu Keluarga: <?php echo (int) $app['kartu_keluarga_count']; ?></span>
                                    <?php elseif ($app['jenis_kredit'] === 'KTA'): ?>
                                        <span class="doc-pill">Slip Gaji: <?php echo (int) $app['slip_gaji_count']; ?></span>
                                        <span class="doc-pill">Surat Kerja: <?php echo (int) $app['surat_kerja_count']; ?></span>
                                    <?php else: ?>
                                        <span class="doc-pill">Foto Usaha: <?php echo (int) $app['foto_usaha_count']; ?></span>
                                        <span class="doc-pill">Izin Usaha: <?php echo (int) $app['izin_usaha_count']; ?></span>
                                        <span class="doc-pill">Laporan Usaha: <?php echo (int) $app['laporan_usaha_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">Total berkas: <?php echo (int) $app['dokumen_count']; ?></small>
                            </td>
                            <td>
                                <?php if (!empty($app['kelayakan'])): ?>
                                    <span class="badge bg-<?php echo sawBadgeClass($app['kelayakan']); ?> text-wrap" style="white-space: normal;">
                                    <?php echo eligibilityLabel($app['kelayakan']); ?>
                                    </span>
                                    <div class="small text-muted mt-1">
                                        <?php echo number_format((float) $app['persentase_saw'], 2); ?>%
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Belum ada rekomendasi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo statusBadgeClass($app['status']); ?>">
                                    <?php echo statusLabel($app['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick="openDocModal(<?php echo (int) $app['id']; ?>, <?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)">
                                    Detail
                                </button>
                                <?php if ($app['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo (int) $app['id']; ?>">
                                        <input type="hidden" name="status" value="verified">
                                        <input type="hidden" name="catatan" value="Dokumen lengkap dan siap validasi akhir">
                                        <button type="submit" class="btn btn-sm btn-success mb-1">
                                            Verifikasi Dokumen
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo (int) $app['id']; ?>">
                                        <input type="hidden" name="status" value="document_rejected">
                                        <input type="hidden" name="catatan" value="Dokumen tidak lengkap atau tidak sesuai">
                                        <button type="submit" class="btn btn-sm btn-danger mb-1">
                                            Tolak Dokumen
                                        </button>
                                    </form>
                                <?php elseif ($app['status'] === 'verified'): ?>
                                    <form method="POST" action="../proses/recommend.php" class="d-inline">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo (int) $app['id']; ?>">
                                        <input type="hidden" name="decision" value="accepted">
                                        <input type="hidden" name="notes" value="Validasi akhir disetujui CU">
                                        <button type="submit" class="btn btn-sm btn-success mb-1">
                                            Validasi Akhir
                                        </button>
                                    </form>
                                    <form method="POST" action="../proses/recommend.php" class="d-inline">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo (int) $app['id']; ?>">
                                        <input type="hidden" name="decision" value="rejected">
                                        <input type="hidden" name="notes" value="Validasi akhir ditolak CU">
                                        <button type="submit" class="btn btn-sm btn-danger mb-1">
                                            Tolak CU
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">Riwayat final</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="docModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Detail Dokumen</h5>
                        <small class="text-muted" id="docModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="docModalContent" class="row g-3"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalTitle">Pratinjau Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewModalBody"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#documentsTable').DataTable({
                pageLength: 10,
                order: [[6, 'desc']]
            });
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function isImageFile(path) {
            return /\.(jpe?g|png|gif|webp)$/i.test(String(path || ''));
        }

        function isRequiredDoc(docType, isStudent, jenisKredit) {
            if (jenisKredit === 'KUR') {
                return ['ktp', 'foto_usaha', 'izin_usaha', 'laporan_usaha'].includes(docType);
            }

            if (isStudent) {
                return ['ktp', 'kartu_pelajar', 'kartu_keluarga'].includes(docType);
            }

            return ['ktp', 'slip_gaji', 'surat_kerja'].includes(docType);
        }

        function openPreview(title, fileUrl, isImage) {
            $('#previewModalTitle').text(title);
            $('#previewModalBody').html(
                isImage
                    ? `<img src="${fileUrl}" alt="${escapeHtml(title)}" class="img-fluid rounded border w-100">`
                    : `<iframe src="${fileUrl}" title="${escapeHtml(title)}" style="width:100%;height:80vh;border:0;" class="rounded border"></iframe>`
            );
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }

        function openDocModal(id, app) {
            let detail = {};
            try {
                detail = typeof app.detail_pinjaman === 'string' ? JSON.parse(app.detail_pinjaman || '{}') : (app.detail_pinjaman || {});
            } catch (e) {
                detail = {};
            }

            $('#docModalSubtitle').text(`#${id} - ${app.nama} (${app.jenis_kredit})`);

            const isStudent = detail.status_pekerjaan_asli === 'Pelajar/Mahasiswa';
            const statusMap = {
                pending: 'Menunggu',
                verified: 'Siap Validasi Final',
                document_rejected: 'Ditolak Dokumen',
                accepted: 'Diterima CU',
                rejected: 'Ditolak CU',
            };
            const detailRows = [
                ['Nama', app.nama],
                ['Nama Pengguna', app.username],
                ['Status', statusMap[app.status] || app.status],
                ['Jenis Kredit', app.jenis_kredit],
                ['Jumlah Pinjaman', 'Rp ' + Number(app.jumlah_pinjaman).toLocaleString('id-ID')],
            ];

            if (app.jenis_kredit === 'KTA') {
                detailRows.push(['Tujuan', detail.tujuan_pinjaman || '-']);
                detailRows.push(['Status Pekerjaan', detail.status_pekerjaan_asli || '-']);
                if (detail.status_pekerjaan_custom) {
                    detailRows.push(['Keterangan', detail.status_pekerjaan_custom]);
                }
                if (isStudent) {
                    detailRows.push(['Jumlah Tanggungan', detail.jumlah_tanggungan ?? '-']);
                } else {
                    detailRows.push(['Pekerjaan', detail.status_pekerjaan || '-']);
                    detailRows.push(['Tempat Kerja', detail.nama_tempat_kerja || '-']);
                    detailRows.push(['Lama Bekerja', detail.lama_bekerja_bulan ? detail.lama_bekerja_bulan + ' bulan' : '-']);
                }
                detailRows.push(['Penghasilan', detail.penghasilan_bulanan ? 'Rp ' + Number(detail.penghasilan_bulanan).toLocaleString('id-ID') : '-']);
            } else {
                detailRows.push(['Nama Usaha', detail.nama_usaha || '-']);
                detailRows.push(['Bidang Usaha', detail.bidang_usaha || '-']);
                detailRows.push(['Alamat Usaha', detail.alamat_usaha || '-']);
                detailRows.push(['Omzet', detail.omzet_bulanan ? 'Rp ' + Number(detail.omzet_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Laba', detail.laba_bersih_bulanan ? 'Rp ' + Number(detail.laba_bersih_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Legalitas', detail.legalitas_usaha || '-']);
            }

            const detailHtml = detailRows.map(function(row) {
                return `
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <div class="text-muted small">${escapeHtml(row[0])}</div>
                            <div class="fw-semibold">${escapeHtml(row[1])}</div>
                        </div>
                    </div>
                `;
            }).join('');

            const docs = Array.isArray(app.documents) ? app.documents : [];
            const docHtml = docs.length ? docs.map(function(doc) {
                const fileUrl = `../proses/view_document.php?id=${encodeURIComponent(doc.id)}`;
                const downloadUrl = `${fileUrl}&download=1`;
                const required = isRequiredDoc(doc.jenis, isStudent, app.jenis_kredit);
                const preview = isImageFile(doc.path_file)
                    ? `<button type="button" class="btn p-0 border-0 bg-transparent d-block mb-2 w-100"
                            onclick="openPreview('${escapeHtml(doc.nama_file)}', '${fileUrl}', true)">
                            <img src="${fileUrl}" alt="${escapeHtml(doc.nama_file)}" class="img-fluid rounded border w-100" style="max-height: 260px; object-fit: cover;">
                       </button>`
                    : `<a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-2">Buka File</a>`;

                return `
                    <div class="col-md-6 doc-card" data-doc-type="${escapeHtml(doc.jenis)}">
                        <div class="border rounded p-3 h-100 bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="fw-semibold">${escapeHtml(doc.jenis)}</div>
                                <span class="badge bg-${required ? 'success' : 'secondary'}">${required ? 'Wajib' : 'Opsional'}</span>
                            </div>
                            <div class="text-muted small mb-2">${escapeHtml(doc.nama_file)}</div>
                            ${preview}
                            <div class="d-flex gap-2">
                                <a href="${fileUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Pratinjau</a>
                                <a href="${downloadUrl}" class="btn btn-sm btn-outline-secondary">Unduh</a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('') : '<div class="col-12 text-muted">Belum ada dokumen.</div>';

            $('#docModalContent').html(`
                <div class="col-12">
                    <div class="alert alert-info mb-0">
                        Periksa kelengkapan dokumen dan cocokkan dengan detail pengajuan sebelum memverifikasi.
                    </div>
                </div>
                <div class="col-12">
                    <h6 class="mb-3">Ringkasan Pengajuan</h6>
                </div>
                ${detailHtml}
                <div class="col-12 mt-2">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <h6 class="mb-0">Kelengkapan Dokumen</h6>
                        <select id="docFilter" class="form-select form-select-sm" style="max-width: 220px;">
                            <option value="all">Semua Dokumen</option>
                            <option value="ktp">KTP</option>
                            <option value="slip_gaji">Slip Gaji</option>
                            <option value="surat_kerja">Surat Kerja</option>
                            <option value="kartu_pelajar">Kartu Pelajar</option>
                            <option value="kartu_keluarga">Kartu Keluarga</option>
                            <option value="foto_usaha">Foto Usaha</option>
                            <option value="izin_usaha">Izin Usaha</option>
                            <option value="laporan_usaha">Laporan Usaha</option>
                            <option value="jaminan">Jaminan</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 mt-2">
                    <div class="row g-3" id="docGrid">
                        ${docHtml}
                    </div>
                </div>
            `);

            $('#docFilter').off('change').on('change', function() {
                const filter = $(this).val();
                if (filter === 'all') {
                    $('#docGrid .doc-card').show();
                } else {
                    $('#docGrid .doc-card').hide();
                    $('#docGrid .doc-card[data-doc-type="' + filter + '"]').show();
                }
            });

            const modal = new bootstrap.Modal(document.getElementById('docModal'));
            modal.show();
        }
    </script>
</body>
</html>
