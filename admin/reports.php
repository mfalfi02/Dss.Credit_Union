<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['generate_report'])) {
            $jenis_laporan = trim($_POST['jenis_laporan'] ?? 'summary');

            $summary = [
                'users' => (int) $conn->query('SELECT COUNT(*) AS c FROM users')->fetch_assoc()['c'],
                'members' => (int) $conn->query('SELECT COUNT(*) AS c FROM anggota')->fetch_assoc()['c'],
                'applications' => (int) $conn->query('SELECT COUNT(*) AS c FROM pengajuan')->fetch_assoc()['c'],
                'verified_applications' => (int) $conn->query("SELECT COUNT(*) AS c FROM pengajuan WHERE status = 'verified'")->fetch_assoc()['c'],
                'rejected_applications' => (int) $conn->query("SELECT COUNT(*) AS c FROM pengajuan WHERE status = 'rejected'")->fetch_assoc()['c'],
                'results' => (int) $conn->query('SELECT COUNT(*) AS c FROM hasil_saw')->fetch_assoc()['c'],
            ];

            $json = json_encode($summary);
            $stmt = $conn->prepare('INSERT INTO laporan (generated_by, jenis_laporan, data) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $_SESSION['user_id'], $jenis_laporan, $json);
            $stmt->execute();
        } elseif (isset($_POST['delete_report'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare('DELETE FROM laporan WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
            }
        }

        header('Location: reports.php');
        exit();
    } catch (Throwable $e) {
        header('Location: reports.php?error=1');
        exit();
    }
}

$result = $conn->query(
    'SELECT l.id, l.jenis_laporan, l.data, l.created_at, u.username
     FROM laporan l
     JOIN users u ON l.generated_by = u.id
     ORDER BY l.created_at DESC'
);
$reports = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin</title>
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
            <h2 class="mb-0">Laporan</h2>
            <form method="POST" class="d-flex gap-2">
                <select name="jenis_laporan" class="form-control">
                    <option value="summary">Ringkasan</option>
                    <option value="monthly">Bulanan</option>
                    <option value="audit">Audit</option>
                </select>
                <button type="submit" name="generate_report" class="btn btn-primary">
                    <i class="fas fa-file-alt"></i> Buat
                </button>
            </form>
        </div>

        <table id="reportsTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jenis</th>
                    <th>Dibuat Oleh</th>
                    <th>Data</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo (int) $report['id']; ?></td>
                    <td><?php echo htmlspecialchars($report['jenis_laporan']); ?></td>
                    <td><?php echo htmlspecialchars($report['username']); ?></td>
                    <td><code><?php echo htmlspecialchars($report['data']); ?></code></td>
                    <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus laporan ini?')">
                            <input type="hidden" name="id" value="<?php echo (int) $report['id']; ?>">
                            <button type="submit" name="delete_report" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#reportsTable').DataTable();
        });
    </script>
</body>
</html>
