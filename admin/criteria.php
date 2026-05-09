<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_criteria'])) {
            $nama = trim($_POST['nama'] ?? '');
            $bobot = (float) ($_POST['bobot'] ?? 0);
            $jenis = $_POST['jenis'] ?? 'benefit';
            $jenis_kredit = $_POST['jenis_kredit'] ?? 'BOTH';

            $stmt = $conn->prepare('INSERT INTO kriteria (nama, bobot, jenis, jenis_kredit) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('sdss', $nama, $bobot, $jenis, $jenis_kredit);
            $stmt->execute();
        } elseif (isset($_POST['edit_criteria'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $bobot = (float) ($_POST['bobot'] ?? 0);
            $jenis = $_POST['jenis'] ?? 'benefit';
            $jenis_kredit = $_POST['jenis_kredit'] ?? 'BOTH';

            $stmt = $conn->prepare('UPDATE kriteria SET nama = ?, bobot = ?, jenis = ?, jenis_kredit = ? WHERE id = ?');
            $stmt->bind_param('sdssi', $nama, $bobot, $jenis, $jenis_kredit, $id);
            $stmt->execute();
        } elseif (isset($_POST['delete_criteria'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM kriteria WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }

        header('Location: criteria.php');
        exit();
    } catch (Throwable $e) {
        header('Location: criteria.php?error=1');
        exit();
    }
}

$result = $conn->query('SELECT * FROM kriteria ORDER BY jenis_kredit, id');
$criteria = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kriteria - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dasbor Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../proses/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Kelola Kriteria</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCriteriaModal">
                <i class="fas fa-plus"></i> Tambah Kriteria
            </button>
        </div>

        <table id="criteriaTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Target</th>
                    <th>Bobot</th>
                    <th>Jenis</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($criteria as $c): ?>
                <tr>
                    <td><?php echo (int) $c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['nama']); ?></td>
                    <td><?php echo htmlspecialchars($c['jenis_kredit']); ?></td>
                    <td><?php echo number_format((float) $c['bobot'], 2); ?></td>
                    <td><?php echo $c['jenis'] === 'benefit' ? 'Keuntungan' : 'Biaya'; ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning"
                            onclick="editCriteria(<?php echo (int) $c['id']; ?>, <?php echo json_encode($c['nama']); ?>, <?php echo number_format((float) $c['bobot'], 2, '.', ''); ?>, <?php echo json_encode($c['jenis']); ?>, <?php echo json_encode($c['jenis_kredit']); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus kriteria ini?')">
                            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                            <button type="submit" name="delete_criteria" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="addCriteriaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kriteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Target Pinjaman</label>
                            <select name="jenis_kredit" class="form-control" required>
                                <option value="KTA">KTA</option>
                                <option value="KUR">KUR</option>
                                <option value="BOTH">Keduanya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Bobot (0,00 - 1,00)</label>
                            <input type="number" name="bobot" class="form-control" step="0.01" min="0" max="1" required>
                        </div>
                        <div class="mb-3">
                            <label>Jenis</label>
                            <select name="jenis" class="form-control" required>
                                <option value="benefit">Keuntungan</option>
                                <option value="cost">Biaya</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_criteria" class="btn btn-primary">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCriteriaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Kriteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label>Nama</label>
                            <input type="text" name="nama" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Target Pinjaman</label>
                            <select name="jenis_kredit" id="edit_jenis_kredit" class="form-control" required>
                                <option value="KTA">KTA</option>
                                <option value="KUR">KUR</option>
                                <option value="BOTH">Keduanya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Bobot (0,00 - 1,00)</label>
                            <input type="number" name="bobot" id="edit_bobot" class="form-control" step="0.01" min="0" max="1" required>
                        </div>
                        <div class="mb-3">
                            <label>Jenis</label>
                            <select name="jenis" id="edit_jenis" class="form-control" required>
                                <option value="benefit">Keuntungan</option>
                                <option value="cost">Biaya</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_criteria" class="btn btn-primary">Simpan</button>
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
            $('#criteriaTable').DataTable();
        });

        function editCriteria(id, nama, bobot, jenis, jenisKredit) {
            $('#edit_id').val(id);
            $('#edit_nama').val(nama);
            $('#edit_bobot').val(bobot);
            $('#edit_jenis').val(jenis);
            $('#edit_jenis_kredit').val(jenisKredit);
            const modal = new bootstrap.Modal(document.getElementById('editCriteriaModal'));
            modal.show();
        }
    </script>
</body>
</html>
