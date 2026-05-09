<?php
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
     WHERE status IN ('pending', 'verified', 'accepted')"
);
if ($activeTypes) {
    while ($row = $activeTypes->fetch_assoc()) {
        if (!empty($row['jenis_kredit'])) {
            $saw->calculateRanking($row['jenis_kredit']);
        }
    }
}

// Get rankings
$sql = "SELECT h.pengajuan_id, h.skor_terbobot, h.ranking, p.jenis_kredit, p.jumlah_pinjaman, a.nama, p.created_at
        , h.persentase_saw, h.kelayakan
        FROM hasil_saw h
        JOIN pengajuan p ON h.pengajuan_id = p.id
        JOIN anggota a ON p.anggota_id = a.id
        ORDER BY p.jenis_kredit ASC, h.ranking ASC";
$result = $conn->query($sql);
$rankings = $result->fetch_all(MYSQLI_ASSOC);

function eligibilityLabel($value)
{
    return $value === 'layak' ? 'Layak' : 'Tidak Layak';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringkat SAW - Petugas</title>
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
        <h2>Peringkat SAW</h2>
        <p>Pengajuan diurutkan berdasarkan skor SAW (semakin tinggi, semakin baik peringkatnya).</p>
        
        <table id="rankingsTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>ID Pengajuan</th>
                    <th>Nama Anggota</th>
                    <th>Jenis Kredit</th>
                    <th>Jumlah</th>
                    <th>Persentase</th>
                    <th>Kelayakan</th>
                    <th>Skor SAW</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankings as $rank): ?>
                <tr>
                    <td><span class="badge text-bg-primary">#<?php echo (int) $rank['ranking']; ?></span></td>
                    <td><?php echo $rank['pengajuan_id']; ?></td>
                    <td><?php echo htmlspecialchars($rank['nama']); ?></td>
                    <td><span class="badge text-bg-<?php echo $rank['jenis_kredit'] === 'KTA' ? 'primary' : 'success'; ?>"><?php echo $rank['jenis_kredit']; ?></span></td>
                    <td>Rp <?php echo number_format($rank['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format((float) $rank['persentase_saw'], 2); ?>%</td>
                    <td>
                        <span class="badge text-bg-<?php echo $rank['kelayakan'] === 'layak' ? 'success' : 'danger'; ?>">
                            <?php echo eligibilityLabel($rank['kelayakan'] ?? ''); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($rank['skor_terbobot'], 4); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($rank['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="giveRecommendation(<?php echo $rank['pengajuan_id']; ?>, '<?php echo addslashes($rank['nama']); ?>', '<?php echo $rank['kelayakan']; ?>')">
                            <i class="fas fa-check"></i> Rekomendasi Otomatis
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Rekomendasi -->
    <div class="modal fade" id="recommendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Beri Rekomendasi untuk <span id="recMemberName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="../proses/recommend.php">
                    <div class="modal-body">
                        <input type="hidden" name="pengajuan_id" id="rec_pengajuan_id">
                        <div class="alert alert-info d-flex justify-content-between align-items-center" role="alert">
                            <span>Rekomendasi akan mengikuti hasil SAW secara otomatis.</span>
                            <span id="recEligibility" class="badge text-bg-primary"></span>
                        </div>
                        <div class="mb-3">
                            <label>Catatan</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Kirim Rekomendasi</button>
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
        $(document).ready(function() {
            $('#rankingsTable').DataTable({
                "order": [[3, "asc"], [0, "asc"]]
            });
        });

        function giveRecommendation(id, name, eligibility) {
            $('#rec_pengajuan_id').val(id);
            $('#recMemberName').text(name);
            const isLayak = eligibility === 'layak';
            $('#recEligibility')
                .removeClass('text-bg-primary text-bg-success text-bg-danger')
                .addClass(isLayak ? 'text-bg-success' : 'text-bg-danger')
                .text(isLayak ? 'Layak -> Disetujui' : 'Tidak Layak -> Ditolak');
            const modal = new bootstrap.Modal(document.getElementById('recommendModal'));
            modal.show();
        }
    </script>
</body>
</html>
