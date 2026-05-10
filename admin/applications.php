<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once 'ui.php';
require_once '../function/saw.php';

$conn = getDBConnection();
$saw = new SAWCalculator($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false;
    try {
        if (isset($_POST['update_application'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $jumlah_pinjaman = (float) ($_POST['jumlah_pinjaman'] ?? 0);

            if ($id > 0 && $jumlah_pinjaman > 0 && in_array($status, ['pending', 'verified', 'document_rejected', 'accepted', 'rejected'], true)) {
                $stmt = $conn->prepare('SELECT jenis_kredit, detail_pinjaman FROM pengajuan WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $application = $stmt->get_result()->fetch_assoc();

                if ($application) {
                    $detail = json_decode((string) ($application['detail_pinjaman'] ?? '{}'), true);
                    if (!is_array($detail)) {
                        $detail = [];
                    }
                    $detail['jumlah_pinjaman'] = $jumlah_pinjaman;
                    $detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);

                    $conn->begin_transaction();
                    $transactionStarted = true;
                    $stmt = $conn->prepare('UPDATE pengajuan SET status = ?, jumlah_pinjaman = ?, detail_pinjaman = ? WHERE id = ?');
                    $stmt->bind_param('sdsi', $status, $jumlah_pinjaman, $detailJson, $id);
                    $stmt->execute();

                    $aksi = sprintf(
                        'Edit pengajuan oleh admin: status=%s, jumlah=%.2f',
                        $status,
                        $jumlah_pinjaman
                    );
                    $adminId = (int) ($_SESSION['user_id'] ?? 0);
                    $stmt = $conn->prepare('INSERT INTO riwayat_pengajuan (pengajuan_id, aksi, dilakukan_oleh) VALUES (?, ?, ?)');
                    $stmt->bind_param('isi', $id, $aksi, $adminId);
                    $stmt->execute();

                    $conn->commit();
                    $transactionStarted = false;

                    $saw->calculateRanking($application['jenis_kredit']);
                    header('Location: applications.php?success=1');
                    exit();
                }
            }
        } elseif (isset($_POST['delete_application'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare('DELETE FROM pengajuan WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
            }
            header('Location: applications.php?success=2');
            exit();
        }

        header('Location: applications.php');
        exit();
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        header('Location: applications.php?error=1');
        exit();
    }
}

$result = $conn->query(
            'SELECT p.id, p.jenis_kredit, p.jumlah_pinjaman, p.detail_pinjaman, p.status, p.created_at,
            a.nama, u.username,
            (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) AS dokumen_count,
            (SELECT COUNT(*) FROM penilaian n WHERE n.pengajuan_id = p.id) AS penilaian_count,
            h.skor_terbobot, h.ranking, h.persentase_saw, h.kelayakan
     FROM pengajuan p
     JOIN anggota a ON p.anggota_id = a.id
     JOIN users u ON a.user_id = u.id
     LEFT JOIN hasil_saw h ON h.pengajuan_id = p.id
     ORDER BY p.created_at DESC'
);
$applications = $result->fetch_all(MYSQLI_ASSOC);

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

function statusLabel($status)
{
    return match ($status) {
        'pending' => 'Menunggu',
        'verified' => 'Siap Validasi Final',
        'document_rejected' => 'Ditolak Dokumen',
        'accepted' => 'Diterima CU',
        'rejected' => 'Ditolak CU',
        default => ucfirst((string) $status),
    };
}

function statusBadgeClass($status)
{
    return match ($status) {
        'pending' => 'warning',
        'verified' => 'info',
        'document_rejected' => 'danger',
        'accepted' => 'success',
        'rejected' => 'danger',
        default => 'secondary',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <?php echo adminPageStyles(); ?>
</head>
<body>
    <?php echo renderAdminHeader('applications', 'Kelola Pengajuan', 'Pantau status pinjaman, dokumen, dan hasil SAW dengan lebih cepat.'); ?>
    <div class="container admin-shell">
        <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
            <div class="alert alert-success">Pengajuan berhasil diperbarui.</div>
        <?php elseif (isset($_GET['success']) && $_GET['success'] === '2'): ?>
            <div class="alert alert-success">Pengajuan berhasil dihapus.</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger">Aksi gagal diproses. Silakan cek data input dan coba lagi.</div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-1">Pengajuan</h2>
                <p class="text-muted mb-0">Lihat status, dokumen, dan hasil SAW untuk setiap permohonan.</p>
            </div>
        </div>
        <div class="card admin-card">
            <div class="card-body">
        <table id="applicationsTable" class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Anggota</th>
                    <th>Nama Pengguna</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>Dokumen</th>
                    <th>Penilaian</th>
                    <th>Hasil SAW</th>
                    <th>Detail</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?php echo (int) $app['id']; ?></td>
                    <td><?php echo htmlspecialchars($app['nama']); ?></td>
                    <td><?php echo htmlspecialchars($app['username']); ?></td>
                    <td><?php echo htmlspecialchars($app['jenis_kredit']); ?></td>
                    <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                    <td>
                        <span class="badge bg-<?php echo statusBadgeClass($app['status']); ?>">
                            <?php echo statusLabel($app['status']); ?>
                        </span>
                    </td>
                    <td><?php echo (int) $app['dokumen_count']; ?></td>
                    <td><?php echo (int) $app['penilaian_count']; ?></td>
                    <td>
                        <?php if ($app['skor_terbobot'] !== null): ?>
                            <div><?php echo number_format((float) $app['skor_terbobot'], 4); ?> (#<?php echo (int) $app['ranking']; ?>)</div>
                            <small class="text-muted d-block" style="white-space: normal;">
                                <?php echo number_format((float) $app['persentase_saw'], 2); ?>% -
                                <?php echo $app['kelayakan'] === 'layak' ? 'Layak Direkomendasikan' : ($app['kelayakan'] === 'tidak_layak' ? 'Belum Layak Direkomendasikan' : 'Belum Ada Rekomendasi'); ?>
                            </small>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                    <button class="btn btn-sm btn-outline-info"
                            onclick="showDetail(<?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)">
                            <i class="fas fa-eye me-1"></i>Lihat
                        </button>
                        <button class="btn btn-sm btn-outline-warning ms-1"
                            onclick="editApplication(<?php echo htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8'); ?>)">
                            <i class="fas fa-pen-to-square me-1"></i>Edit
                        </button>
                    </td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pengajuan ini?')">
                            <input type="hidden" name="id" value="<?php echo (int) $app['id']; ?>">
                            <button type="submit" name="delete_application" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash me-1"></i>Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Pengajuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">ID Pengajuan</label>
                                <input type="text" class="form-control" id="edit_display_id" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kredit</label>
                                <input type="text" class="form-control" id="edit_jenis_kredit" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nama Anggota</label>
                                <input type="text" class="form-control" id="edit_nama" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="pending">Pending</option>
                                    <option value="verified">Verified</option>
                                    <option value="document_rejected">Document Rejected</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jumlah Pinjaman</label>
                                <input type="number" name="jumlah_pinjaman" id="edit_jumlah_pinjaman" class="form-control" min="1000" step="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_application" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
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
            $('#applicationsTable').DataTable();
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function editApplication(app) {
            $('#edit_id').val(app.id);
            $('#edit_display_id').val('#' + app.id);
            $('#edit_jenis_kredit').val(app.jenis_kredit);
            $('#edit_nama').val(app.nama + ' (' + app.username + ')');
            $('#edit_status').val(app.status);
            $('#edit_jumlah_pinjaman').val(Number(app.jumlah_pinjaman || 0));

            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
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

        function showDetail(app) {
            let detail = {};
            try {
                detail = typeof app.detail_pinjaman === 'string' ? JSON.parse(app.detail_pinjaman || '{}') : (app.detail_pinjaman || {});
            } catch (e) {
                detail = {};
            }

            const isStudent = detail.status_pekerjaan_asli === 'Pelajar/Mahasiswa';

            const statusMap = {
                pending: 'Menunggu',
                verified: 'Siap Validasi Final',
                document_rejected: 'Ditolak Dokumen',
                accepted: 'Diterima CU',
                rejected: 'Ditolak CU',
            };

            const detailRows = [
                ['ID', app.id],
                ['Anggota', app.nama],
                ['Nama Pengguna', app.username],
                ['Jenis', app.jenis_kredit],
                ['Jumlah', 'Rp ' + Number(app.jumlah_pinjaman).toLocaleString('id-ID')],
                ['Status', statusMap[app.status] || app.status],
                ['Rekomendasi Sistem', app.skor_terbobot !== null ? Number(app.persentase_saw).toFixed(2) + '% - ' + (app.kelayakan === 'layak' ? 'Layak Direkomendasikan' : 'Belum Layak Direkomendasikan') : '-'],
            ];

            if (app.jenis_kredit === 'KTA') {
                detailRows.push(['Tujuan', detail.tujuan_pinjaman || '-']);
                detailRows.push(['Status Pekerjaan', detail.status_pekerjaan_asli || '-']);
                if (detail.status_pekerjaan_custom) {
                    detailRows.push(['Keterangan', detail.status_pekerjaan_custom]);
                }
                if (detail.status_pekerjaan_asli === 'Pelajar/Mahasiswa') {
                    detailRows.push(['Jumlah Tanggungan', detail.jumlah_tanggungan ?? '-']);
                } else {
                    detailRows.push(['Pekerjaan', detail.status_pekerjaan || '-']);
                    detailRows.push(['Tempat Kerja', detail.nama_tempat_kerja || '-']);
                    detailRows.push(['Lama Bekerja', detail.lama_bekerja_bulan ? detail.lama_bekerja_bulan + ' bulan' : '-']);
                }
                detailRows.push(['Penghasilan', detail.penghasilan_bulanan ? 'Rp ' + Number(detail.penghasilan_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Pengeluaran', detail.pengeluaran_bulanan ? 'Rp ' + Number(detail.pengeluaran_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Cicilan', detail.beban_cicilan_bulanan ? 'Rp ' + Number(detail.beban_cicilan_bulanan).toLocaleString('id-ID') : '-']);
            } else {
                detailRows.push(['Nama Usaha', detail.nama_usaha || '-']);
                detailRows.push(['Bidang Usaha', detail.bidang_usaha || '-']);
                detailRows.push(['Alamat Usaha', detail.alamat_usaha || '-']);
                detailRows.push(['Lama Usaha', detail.lama_usaha_bulan ? detail.lama_usaha_bulan + ' bulan' : '-']);
                detailRows.push(['Omzet', detail.omzet_bulanan ? 'Rp ' + Number(detail.omzet_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Laba', detail.laba_bersih_bulanan ? 'Rp ' + Number(detail.laba_bersih_bulanan).toLocaleString('id-ID') : '-']);
                detailRows.push(['Legalitas', detail.legalitas_usaha || '-']);
                detailRows.push(['Tujuan Dana', detail.tujuan_dana || '-']);
            }

            const docs = Array.isArray(app.documents) ? app.documents : [];
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

            const docHtml = docs.length ? docs.map(function(doc) {
                const viewUrl = `../proses/view_document.php?id=${encodeURIComponent(doc.id)}`;
                const downloadUrl = `${viewUrl}&download=1`;
                const required = isRequiredDoc(doc.jenis, isStudent, app.jenis_kredit);
                const preview = isImageFile(doc.path_file)
                    ? `<button type="button" class="btn p-0 border-0 bg-transparent d-block mb-2 w-100"
                            onclick="openPreview('${escapeHtml(doc.nama_file)}', '${viewUrl}', true)">
                            <img src="${viewUrl}" alt="${escapeHtml(doc.nama_file)}" class="img-fluid rounded border w-100" style="max-height: 260px; object-fit: cover;">
                       </button>`
                    : `<a href="${viewUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-2">Buka File</a>`;

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
                                <a href="${viewUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Pratinjau</a>
                                <a href="${downloadUrl}" class="btn btn-sm btn-outline-secondary">Unduh</a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('') : '<div class="col-12 text-muted">Belum ada dokumen.</div>';

            $('#detailContent').html(`
                ${detailHtml}
                <div class="col-12 mt-2">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <h6 class="mb-0">Dokumen</h6>
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
                <div class="col-12">
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
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    </script>
</body>
</html>
