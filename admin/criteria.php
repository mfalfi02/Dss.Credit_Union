<?php
// Halaman admin untuk mengatur kriteria SAW, bobot, dan target pinjaman.
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once 'ui.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proses tambah, ubah, atau hapus kriteria.
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

function criterionKindLabel(string $jenis): string
{
    // Label jenis kriteria yang lebih mudah dibaca.
    return $jenis === 'benefit' ? 'Keuntungan' : 'Biaya';
}

function criterionKindClass(string $jenis): string
{
    return $jenis === 'benefit' ? 'success' : 'danger';
}

function criterionTargetClass(string $target): string
{
    // Warna badge berdasarkan target kredit.
    return match ($target) {
        'KTA' => 'primary',
        'KUR' => 'success',
        default => 'secondary',
    };
}
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
    <?php echo adminPageStyles(); ?>
    <style>
        .criteria-card {
            border: 0;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
            border-radius: 1rem;
        }

        .criteria-card .card-body {
            padding: clamp(1rem, 2vw, 1.5rem);
        }

        .criteria-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: .875rem;
        }

        .criteria-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .7rem;
            border-radius: 999px;
            font-size: .82rem;
            font-weight: 600;
        }

        .criteria-empty {
            padding: 2rem;
            border: 1px dashed #cbd5e1;
            border-radius: 1rem;
            background: #f8fafc;
        }

        @media (max-width: 767.98px) {
            .criteria-page-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .criteria-page-header .btn {
                width: 100%;
            }

            .criteria-actions {
                width: 100%;
            }

            .criteria-actions .btn,
            .criteria-actions form {
                width: 100%;
            }

            .criteria-actions form .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php echo renderAdminHeader('criteria', 'Kelola Kriteria', 'Atur bobot dan jenis kriteria untuk perhitungan SAW.'); ?>
    <div class="container admin-shell">
        <!-- Tombol tambah kriteria dan judul halaman -->
        <div class="d-flex justify-content-between align-items-center mb-3 criteria-page-header">
            <div>
                <h2 class="mb-1">Kelola Kriteria</h2>
                <p class="text-muted mb-0">Kelola kriteria benefit dan cost untuk KTA, KUR, atau keduanya.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCriteriaModal">
                <i class="fas fa-plus"></i> Tambah Kriteria
            </button>
        </div>

        <div class="card criteria-card">
            <div class="card-body">
                <!-- Tabel daftar kriteria -->
                <div class="table-responsive">
                    <table id="criteriaTable" class="table table-hover align-middle mb-0 criteria-table">
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
                                <td class="text-muted">#<?php echo (int) $c['id']; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($c['nama']); ?></td>
                                <td>
                                    <span class="criteria-pill text-bg-<?php echo criterionTargetClass((string) $c['jenis_kredit']); ?>">
                                        <?php echo htmlspecialchars((string) $c['jenis_kredit']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format((float) $c['bobot'], 2); ?></td>
                                <td>
                                    <span class="criteria-pill text-bg-<?php echo criterionKindClass((string) $c['jenis']); ?>">
                                        <?php echo criterionKindLabel((string) $c['jenis']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2 criteria-actions">
                                        <button class="btn btn-sm btn-outline-warning"
                                            data-id="<?php echo (int) $c['id']; ?>"
                                            data-nama="<?php echo htmlspecialchars($c['nama'], ENT_QUOTES); ?>"
                                            data-bobot="<?php echo htmlspecialchars(number_format((float) $c['bobot'], 2, '.', ''), ENT_QUOTES); ?>"
                                            data-jenis="<?php echo htmlspecialchars($c['jenis'], ENT_QUOTES); ?>"
                                            data-jenis-kredit="<?php echo htmlspecialchars($c['jenis_kredit'], ENT_QUOTES); ?>"
                                            onclick="editCriteria(this)">
                                            <i class="fas fa-pen-to-square me-1"></i>Edit
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus kriteria ini?')">
                                            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
                                            <button type="submit" name="delete_criteria" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash me-1"></i>Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($criteria)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="criteria-empty text-center text-muted">
                                        Belum ada data kriteria. Silakan tambahkan kriteria pertama.
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCriteriaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Form tambah kriteria -->
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kriteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            Tambahkan kriteria baru dan tentukan apakah termasuk <strong>Keuntungan</strong> atau <strong>Biaya</strong>.
                        </div>
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
                        <div class="alert alert-light border">
                            Perubahan akan dipakai pada perhitungan SAW berikutnya.
                        </div>
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

        function editCriteria(button) {
            $('#edit_id').val($(button).data('id'));
            $('#edit_nama').val($(button).data('nama'));
            $('#edit_bobot').val($(button).data('bobot'));
            $('#edit_jenis').val($(button).data('jenis'));
            $('#edit_jenis_kredit').val($(button).data('jenis-kredit'));
            const modal = new bootstrap.Modal(document.getElementById('editCriteriaModal'));
            modal.show();
        }
    </script>
</body>
</html>
