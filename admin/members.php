<?php
session_start();
require_once '../function/auth.php';
checkLogin();
checkRole('admin');
require_once '../config/database.php';
require_once 'ui.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_member'])) {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

            if ($username !== '' && $password !== '' && $nama !== '') {
                $conn->begin_transaction();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, 3)');
                $stmt->bind_param('ss', $username, $hashed_password);
                $stmt->execute();
                $user_id = $conn->insert_id;

                $stmt = $conn->prepare(
                    'INSERT INTO anggota (user_id, nama, alamat, no_hp, email, tanggal_lahir)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isssss', $user_id, $nama, $alamat, $no_hp, $email, $tanggal_lahir);
                $stmt->execute();

                $conn->commit();
                header('Location: members.php?success=1');
                exit();
            }
        } elseif (isset($_POST['edit_member'])) {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $anggota_id = (int) ($_POST['anggota_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $nama = trim($_POST['nama'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $no_hp = trim($_POST['no_hp'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;

            if ($user_id > 0 && $anggota_id > 0) {
                $conn->begin_transaction();

                $stmt = $conn->prepare('UPDATE users SET username = ?, role_id = 3 WHERE id = ?');
                $stmt->bind_param('si', $username, $user_id);
                $stmt->execute();

                if ($password !== '') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->bind_param('si', $hashed_password, $user_id);
                    $stmt->execute();
                }

                $stmt = $conn->prepare(
                    'UPDATE anggota
                     SET nama = ?, alamat = ?, no_hp = ?, email = ?, tanggal_lahir = ?
                     WHERE id = ?'
                );
                $stmt->bind_param('sssssi', $nama, $alamat, $no_hp, $email, $tanggal_lahir, $anggota_id);
                $stmt->execute();

                $conn->commit();
                header('Location: members.php?success=2');
                exit();
            }
        } elseif (isset($_POST['delete_member'])) {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
            }
            header('Location: members.php?success=3');
            exit();
        }

        header('Location: members.php');
        exit();
    } catch (Throwable $e) {
        if ($conn->errno === 0) {
            $conn->rollback();
        }
        header('Location: members.php?error=1');
        exit();
    }
}

$result = $conn->query(
    'SELECT a.id AS anggota_id, u.id AS user_id, u.username, a.nama, a.alamat, a.no_hp, a.email, a.tanggal_lahir, a.created_at
     FROM anggota a
     JOIN users u ON a.user_id = u.id
     ORDER BY a.id'
);
$members = $result->fetch_all(MYSQLI_ASSOC);

$successMessage = '';
if (isset($_GET['success'])) {
    $successMessage = match ((string) $_GET['success']) {
        '1' => 'Anggota berhasil ditambahkan.',
        '2' => 'Anggota berhasil diperbarui.',
        '3' => 'Anggota berhasil dihapus.',
        default => '',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Anggota - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <?php echo adminPageStyles(); ?>
</head>
<body>
    <?php echo renderAdminHeader('members', 'Kelola Anggota', 'Data khusus anggota atau peminjam di sistem.'); ?>
    <div class="container admin-shell">
        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success border-0 shadow-sm mb-3"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-1">Kelola Anggota</h2>
                <p class="text-muted mb-0">Data akun dan biodata anggota yang mengajukan kredit.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                <i class="fas fa-plus"></i> Tambah Anggota
            </button>
        </div>

        <div class="card admin-card">
            <div class="card-body">
        <table id="membersTable" class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Pengguna</th>
                    <th>Nama</th>
                    <th>HP</th>
                    <th>Email</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td><?php echo (int) $m['anggota_id']; ?></td>
                    <td><?php echo htmlspecialchars($m['username']); ?></td>
                    <td><?php echo htmlspecialchars($m['nama']); ?></td>
                    <td><?php echo htmlspecialchars($m['no_hp']); ?></td>
                    <td><?php echo htmlspecialchars($m['email']); ?></td>
                    <td><?php echo htmlspecialchars($m['created_at']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning"
                            type="button"
                            data-user-id="<?php echo (int) $m['user_id']; ?>"
                            data-anggota-id="<?php echo (int) $m['anggota_id']; ?>"
                            data-username="<?php echo htmlspecialchars($m['username'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-nama="<?php echo htmlspecialchars($m['nama'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-alamat="<?php echo htmlspecialchars($m['alamat'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-no-hp="<?php echo htmlspecialchars($m['no_hp'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-email="<?php echo htmlspecialchars($m['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-tanggal-lahir="<?php echo htmlspecialchars((string) ($m['tanggal_lahir'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-pen-to-square me-1"></i>Edit
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Hapus anggota dan pengguna terkait?')">
                            <input type="hidden" name="user_id" value="<?php echo (int) $m['user_id']; ?>">
                            <button type="submit" name="delete_member" class="btn btn-sm btn-outline-danger">
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

    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Nama Pengguna</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Kata Sandi</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Alamat</label>
                            <textarea name="alamat" class="form-control"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>No HP</label>
                                <input type="text" name="no_hp" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_member" class="btn btn-primary">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editMemberModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubah Anggota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="anggota_id" id="edit_anggota_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Nama Pengguna</label>
                                <input type="text" name="username" id="edit_username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Kata Sandi Baru</label>
                                <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diganti">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Alamat</label>
                            <textarea name="alamat" id="edit_alamat" class="form-control"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>No HP</label>
                                <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="edit_member" class="btn btn-primary">Simpan</button>
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
            $('#membersTable').DataTable();

            $('button[data-anggota-id]').on('click', function() {
                editMember(
                    $(this).data('user-id'),
                    $(this).data('anggota-id'),
                    $(this).data('username'),
                    $(this).data('nama'),
                    $(this).data('alamat'),
                    $(this).data('no-hp'),
                    $(this).data('email'),
                    $(this).data('tanggal-lahir')
                );
            });
        });

        function editMember(userId, anggotaId, username, nama, alamat, noHp, email, tanggalLahir) {
            $('#edit_user_id').val(userId);
            $('#edit_anggota_id').val(anggotaId);
            $('#edit_username').val(username);
            $('#edit_nama').val(nama);
            $('#edit_alamat').val(alamat);
            $('#edit_no_hp').val(noHp);
            $('#edit_email').val(email);
            $('#edit_tanggal_lahir').val(tanggalLahir);
            const modal = new bootstrap.Modal(document.getElementById('editMemberModal'));
            modal.show();
        }
    </script>
</body>
</html>
local
