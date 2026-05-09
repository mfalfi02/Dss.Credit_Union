<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_user'])) {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role_id = (int) ($_POST['role_id'] ?? 0);

            if ($username !== '' && $password !== '' && $role_id > 0) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $username, $hashed_password, $role_id);
                $stmt->execute();
            }
        } elseif (isset($_POST['edit_user'])) {
            $id = (int) ($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $role_id = (int) ($_POST['role_id'] ?? 0);
            $password = trim($_POST['password'] ?? '');

            if ($id > 0 && $username !== '' && $role_id > 0) {
                $stmt = $conn->prepare('UPDATE users SET username = ?, role_id = ? WHERE id = ?');
                $stmt->bind_param('sii', $username, $role_id, $id);
                $stmt->execute();

                if ($password !== '') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->bind_param('si', $hashed_password, $id);
                    $stmt->execute();
                }
            }
        } elseif (isset($_POST['delete_user'])) {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int) $_SESSION['user_id']) {
                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
            }
        }

        header('Location: users.php');
        exit();
    } catch (Throwable $e) {
        header('Location: users.php?error=1');
        exit();
    }
}

$roles = $conn->query('SELECT * FROM roles ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$result = $conn->query(
    'SELECT u.id, u.username, u.role_id, r.name AS role_name, u.created_at
     FROM users u
     JOIN roles r ON u.role_id = r.id
     ORDER BY u.id'
);
$users = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin</title>
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
            <h2 class="mb-0">Kelola Pengguna</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Tambah Pengguna
            </button>
        </div>

        <table id="usersTable" class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Pengguna</th>
                    <th>Peran</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo (int) $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                    <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-warning"
                            onclick="editUser(<?php echo (int) $u['id']; ?>, <?php echo json_encode($u['username']); ?>, <?php echo (int) $u['role_id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?')">
                            <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Nama Pengguna</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Kata Sandi</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Peran</label>
                            <select name="role_id" class="form-control" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int) $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label>Nama Pengguna</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Kata Sandi Baru</label>
                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diganti">
                        </div>
                        <div class="mb-3">
                            <label>Peran</label>
                            <select name="role_id" id="edit_role_id" class="form-control" required>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int) $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_user" class="btn btn-primary">Simpan</button>
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
            $('#usersTable').DataTable();
        });

        function editUser(id, username, roleId) {
            $('#edit_id').val(id);
            $('#edit_username').val(username);
            $('#edit_role_id').val(roleId);
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
    </script>
</body>
</html>
