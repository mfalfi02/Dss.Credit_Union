<?php
// Halaman petugas untuk mengisi nilai kriteria pada pengajuan yang sudah diverifikasi.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('petugas');
require_once '../config/database.php';
require_once '../function/saw.php';

$conn = getDBConnection();
$saw = new SAWCalculator($conn);

$sql = "SELECT p.id, p.jenis_kredit, p.jumlah_pinjaman, p.created_at, a.nama
        , p.detail_pinjaman,
        (SELECT COUNT(*) FROM dokumen d WHERE d.pengajuan_id = p.id) AS dokumen_count
        FROM pengajuan p
        JOIN anggota a ON p.anggota_id = a.id
        WHERE p.status = 'verified'
        ORDER BY p.created_at DESC";
// Ambil daftar pengajuan yang siap dinilai.
$result = $conn->query($sql);
$applications = $result->fetch_all(MYSQLI_ASSOC);

$criteriaByType = [
    'KTA' => $saw->getCriteria('KTA'),
    'KUR' => $saw->getCriteria('KUR'),
];

function summarizeDetail($detailJson, $jenisKredit) {
    // Ringkas detail pengajuan untuk membantu petugas melihat konteks data.
    $detail = json_decode($detailJson ?? '', true);
    if (!is_array($detail)) {
        return '-';
    }

    if ($jenisKredit === 'KTA') {
        $parts = [];
        if (!empty($detail['tujuan_pinjaman'])) {
            $parts[] = 'Tujuan: ' . htmlspecialchars($detail['tujuan_pinjaman']);
        }
        if (!empty($detail['status_pekerjaan'])) {
            $parts[] = 'Pekerjaan: ' . htmlspecialchars($detail['status_pekerjaan']);
        }
        if (!empty($detail['penghasilan_bulanan'])) {
            $parts[] = 'Penghasilan: Rp ' . number_format((float) $detail['penghasilan_bulanan'], 0, ',', '.');
        }
        if (!empty($detail['beban_cicilan_bulanan'])) {
            $parts[] = 'Cicilan: Rp ' . number_format((float) $detail['beban_cicilan_bulanan'], 0, ',', '.');
        }

        return $parts ? implode('<br>', $parts) : '-';
    }

    $parts = [];
    if (!empty($detail['nama_usaha'])) {
        $parts[] = 'Usaha: ' . htmlspecialchars($detail['nama_usaha']);
    }
    if (!empty($detail['bidang_usaha'])) {
        $parts[] = 'Bidang: ' . htmlspecialchars($detail['bidang_usaha']);
    }
    if (!empty($detail['omzet_bulanan'])) {
        $parts[] = 'Omzet: Rp ' . number_format((float) $detail['omzet_bulanan'], 0, ',', '.');
    }
    if (!empty($detail['laba_bersih_bulanan'])) {
        $parts[] = 'Laba: Rp ' . number_format((float) $detail['laba_bersih_bulanan'], 0, ',', '.');
    }

    return $parts ? implode('<br>', $parts) : '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi Nilai Kriteria - Petugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Petugas</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2>Isi Nilai Kriteria untuk Perhitungan SAW</h2>
        <p class="text-muted">Kriteria dan detail yang tampil akan menyesuaikan jenis pinjaman KTA atau KUR.</p>

        <table id="applicationsTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID Pengajuan</th>
                    <th>Nama Anggota</th>
                    <th>Jenis Kredit</th>
                    <th>Jumlah</th>
                    <th>Ringkasan</th>
                    <th>Dokumen</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?php echo (int) $app['id']; ?></td>
                    <td><?php echo htmlspecialchars($app['nama']); ?></td>
                    <td><span class="badge bg-<?php echo $app['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>"><?php echo htmlspecialchars($app['jenis_kredit']); ?></span></td>
                    <td>Rp <?php echo number_format((float) $app['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                    <td><?php echo summarizeDetail($app['detail_pinjaman'], $app['jenis_kredit']); ?></td>
                    <td><?php echo (int) $app['dokumen_count']; ?> berkas</td>
                    <td><?php echo date('d/m/Y', strtotime($app['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary"
                            onclick="inputValues(<?php echo (int) $app['id']; ?>, <?php echo json_encode($app['nama']); ?>, <?php echo json_encode($app['jenis_kredit']); ?>, <?php echo htmlspecialchars(json_encode($app['detail_pinjaman']), ENT_QUOTES, 'UTF-8'); ?>)">
                            <i class="fas fa-edit"></i> Isi Nilai
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="inputModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Isi Nilai Kriteria untuk <span id="memberName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../proses/input_criteria.php">
                    <div class="modal-body">
                        <input type="hidden" name="pengajuan_id" id="pengajuan_id">
                        <input type="hidden" name="jenis_kredit" id="jenis_kredit">
                        <div class="alert alert-light border" id="detailSummary"></div>
                        <div id="criteriaContainer"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Nilai & Hitung</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const criteriaByType = <?php echo json_encode($criteriaByType, JSON_UNESCAPED_UNICODE); ?>;

        $(document).ready(function() {
            $('#applicationsTable').DataTable();
        });

        function renderCriteriaFields(type) {
            const container = $('#criteriaContainer');
            container.empty();

            const criteria = criteriaByType[type] || [];
            criteria.forEach(function(c) {
                container.append(`
                    <div class="mb-3 card border-0 shadow-sm">
                        <div class="card-body">
                            <label class="form-label fw-semibold">${c.nama}</label>
                            <div class="text-muted small mb-2">
                                Bobot: ${c.bobot} | Jenis: ${c.jenis.toUpperCase()} | Target: ${c.jenis_kredit}
                            </div>
                            <input type="number" name="criteria[${c.id}]" class="form-control" step="0.01" required>
                        </div>
                    </div>
                `);
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function inputValues(id, name, type, detailJson) {
            $('#pengajuan_id').val(id);
            $('#jenis_kredit').val(type);
            $('#memberName').text(name + ' - ' + type);
            let detailContent = '-';
            try {
                const parsed = typeof detailJson === 'string' ? JSON.parse(detailJson || '{}') : (detailJson || {});
                if (type === 'KTA') {
                    detailContent = [
                        parsed.tujuan_pinjaman ? `Tujuan: ${escapeHtml(parsed.tujuan_pinjaman)}` : null,
                        parsed.status_pekerjaan ? `Status pekerjaan: ${escapeHtml(parsed.status_pekerjaan)}` : null,
                        parsed.nama_tempat_kerja ? `Tempat kerja: ${escapeHtml(parsed.nama_tempat_kerja)}` : null,
                        parsed.penghasilan_bulanan ? `Penghasilan: Rp ${Number(parsed.penghasilan_bulanan).toLocaleString('id-ID')}` : null,
                        parsed.beban_cicilan_bulanan ? `Cicilan: Rp ${Number(parsed.beban_cicilan_bulanan).toLocaleString('id-ID')}` : null,
                    ].filter(Boolean).join('<br>') || '-';
                } else {
                    detailContent = [
                        parsed.nama_usaha ? `Usaha: ${escapeHtml(parsed.nama_usaha)}` : null,
                        parsed.bidang_usaha ? `Bidang: ${escapeHtml(parsed.bidang_usaha)}` : null,
                        parsed.alamat_usaha ? `Alamat: ${escapeHtml(parsed.alamat_usaha)}` : null,
                        parsed.omzet_bulanan ? `Omzet: Rp ${Number(parsed.omzet_bulanan).toLocaleString('id-ID')}` : null,
                        parsed.laba_bersih_bulanan ? `Laba: Rp ${Number(parsed.laba_bersih_bulanan).toLocaleString('id-ID')}` : null,
                    ].filter(Boolean).join('<br>') || '-';
                }
            } catch (e) {
                detailContent = detailJson || '-';
            }
            $('#detailSummary').html(detailContent);
            renderCriteriaFields(type);
            const modal = new bootstrap.Modal(document.getElementById('inputModal'));
            modal.show();
        }
    </script>
</body>
</html>
