<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../include/config.php';

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Input sanitization function
function clean_input($data) {
    if (empty($data)) return $data;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Add user process
if (isset($_POST['tambah_user'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: manajemenuser.php");
        exit();
    }

    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($username) || empty($password) || empty($nama) || empty($role)) {
        $_SESSION['error_message'] = "Username, Password, Nama dan Role harus diisi";
        header("Location: manajemenuser.php");
        exit();
    }

    // Validasi role
    if (!in_array($role, ['admin', 'dosen', 'mahasiswa'])) {
        $_SESSION['error_message'] = "Role tidak valid";
        header("Location: manajemenuser.php");
        exit();
    }

    try {
        // Check if username already exists
        $check_sql = "SELECT username FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "Username sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: manajemenuser.php");
            exit();
        }
        $check_stmt->close();

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, nama, role, prodi) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("sssss", $username, $hashed_password, $nama, $role, $prodi);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan user: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Add user error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: manajemenuser.php");
    exit();
}

// Edit user process
if (isset($_POST['edit_user'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: manajemenuser.php");
        exit();
    }

    $id = (int)clean_input($_POST['id']);
    $username = clean_input($_POST['username']);
    $old_username = clean_input($_POST['old_username']);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']);
    $prodi = clean_input($_POST['prodi']);
    $password = clean_input($_POST['password']);

    // Validasi input
    if (empty($username) || empty($nama) || empty($role)) {
        $_SESSION['error_message'] = "Username, Nama dan Role harus diisi";
        header("Location: manajemenuser.php");
        exit();
    }

    // Validasi role
    if (!in_array($role, ['admin', 'dosen', 'mahasiswa'])) {
        $_SESSION['error_message'] = "Role tidak valid";
        header("Location: manajemenuser.php");
        exit();
    }

    try {
        // Check if username is being changed to an existing one
        if ($old_username != $username) {
            $check_sql = "SELECT username FROM users WHERE username = ? AND username != ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_stmt->bind_param("ss", $username, $old_username);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "Username sudah terdaftar dalam sistem";
                $check_stmt->close();
                header("Location: manajemenuser.php");
                exit();
            }
            $check_stmt->close();
        }

        // Check if password is being updated
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username=?, password=?, nama=?, role=?, prodi=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("sssssi", $username, $hashed_password, $nama, $role, $prodi, $id);
        } else {
            $sql = "UPDATE users SET username=?, nama=?, role=?, prodi=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("ssssi", $username, $nama, $role, $prodi, $id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data user berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui data user: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Edit user error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: manajemenuser.php");
    exit();
}

// Delete user process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    // Mencegah penghapusan user yang sedang login
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Anda tidak dapat menghapus akun yang sedang digunakan";
        header("Location: manajemenuser.php");
        exit();
    }
    
    try {
        // Check if user is linked to any lecturer or student
        $check_dosen_sql = "SELECT COUNT(*) as count FROM dosen WHERE user_id = ?";
        $check_dosen_stmt = $conn->prepare($check_dosen_sql);
        if (!$check_dosen_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_dosen_stmt->bind_param("i", $id);
        $check_dosen_stmt->execute();
        $check_dosen_stmt->bind_result($dosen_count);
        $check_dosen_stmt->fetch();
        $check_dosen_stmt->close();
        
        $check_mahasiswa_sql = "SELECT COUNT(*) as count FROM mahasiswa WHERE user_id = ?";
        $check_mahasiswa_stmt = $conn->prepare($check_mahasiswa_sql);
        if (!$check_mahasiswa_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_mahasiswa_stmt->bind_param("i", $id);
        $check_mahasiswa_stmt->execute();
        $check_mahasiswa_stmt->bind_result($mahasiswa_count);
        $check_mahasiswa_stmt->fetch();
        $check_mahasiswa_stmt->close();
        
        if ($dosen_count > 0 || $mahasiswa_count > 0) {
            throw new Exception("User tidak dapat dihapus karena terhubung dengan data dosen atau mahasiswa");
        }

        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus user: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: manajemenuser.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get user data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM users";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $per_page);
} else {
    $total_rows = 0;
    $total_pages = 1;
}

// Get paginated data
$sql = "SELECT * FROM users ORDER BY role, nama ASC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $offset, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
    $_SESSION['error_message'] = "Error retrieving user data: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/admin.css">
    <style>
        .badge-admin {
            background-color: #dc3545;
            color: white;
        }
        .badge-dosen {
            background-color: #0d6efd;
            color: white;
        }
        .badge-mahasiswa {
            background-color: #198754;
            color: white;
        }
        .table-responsive {
            min-height: 400px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header d-flex align-items-center">
            <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                <span class="fw-bold">U</span>
            </div>
            <span class="sidebar-brand">PORTALSIA</span>
        </div>
        
        <div class="menu-category">MENU UTAMA</div>
        <div class="menu">
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="datadosen.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datadosen.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span>Data Dosen</span>
            </a>
            <a href="datamahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datamahasiswa.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Data Mahasiswa</span>
            </a>
            <a href="datamatakuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datamatakuliah.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Data Mata Kuliah</span>
            </a>
            <a href="jadwalkuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'jadwalkuliah.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Kuliah</span>
            </a>
            <a href="datanilai.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datanilai.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Data Nilai</span>
            </a>
        </div>
        
        <div class="menu-category">PENGATURAN</div>
        <div class="menu">
            <a href="manajemenuser.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'manajemenuser.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Manajemen User</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0 text-primary">Manajemen User</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah User
            </button>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <!-- User Table -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Program Studi</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                    <td>
                                        <?php 
                                            $badge_class = '';
                                            if ($row['role'] == 'admin') $badge_class = 'badge-admin';
                                            elseif ($row['role'] == 'dosen') $badge_class = 'badge-dosen';
                                            else $badge_class = 'badge-mahasiswa';
                                        ?>
                                        <span class="badge rounded-pill <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['prodi'] ?: '-'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                onclick="setEditForm(
                                                    '<?php echo $row['id']; ?>',
                                                    '<?php echo $row['username']; ?>',
                                                    '<?php echo addslashes($row['nama']); ?>',
                                                    '<?php echo $row['role']; ?>',
                                                    '<?php echo $row['prodi']; ?>'
                                                )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus user <?php echo addslashes($row['username']); ?>?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-database me-2"></i>Tidak ada data user
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">
                            Menampilkan <?php echo ($result && $result->num_rows > 0) ? min($per_page, $result->num_rows) : 0; ?> 
                            dari <?php echo $total_rows; ?> data
                        </small>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tambahModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Tambah User Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required minlength="3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="dosen">Dosen</option>
                                <option value="mahasiswa">Mahasiswa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Program Studi</label>
                            <select class="form-select" name="prodi">
                                <option value="">Pilih Program Studi</option>
                                <?php if ($prodi_result && $prodi_result->num_rows > 0): ?>
                                    <?php while($prodi = $prodi_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($prodi['kode_prodi']); ?>">
                                            <?php echo htmlspecialchars($prodi['nama_prodi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="tambah_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="fas fa-user-edit me-2"></i>Edit Data User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="old_username" id="edit_old_username">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" id="edit_username" required maxlength="50">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (Biarkan kosong jika tidak ingin mengubah)</label>
                            <input type="password" class="form-control" name="password" id="edit_password" minlength="3">
                            <div class="form-text">Minimal 3 karakter</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="dosen">Dosen</option>
                                <option value="mahasiswa">Mahasiswa</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Program Studi</label>
                            <select class="form-select" name="prodi" id="edit_prodi">
                                <option value="">Pilih Program Studi</option>
                                <?php 
                                if ($prodi_result) {
                                    $prodi_result->data_seek(0);
                                    while($prodi = $prodi_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($prodi['kode_prodi']); ?>">
                                        <?php echo htmlspecialchars($prodi['nama_prodi']); ?>
                                    </option>
                                <?php 
                                    endwhile; 
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="edit_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Function to set edit form values
        function setEditForm(id, username, nama, role, prodi) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_old_username').value = username;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_prodi').value = prodi || '';
            document.getElementById('edit_password').value = '';
        }
    </script>
</body>
</html>

<?php
// Clean up
if (isset($stmt)) $stmt->close();
if (isset($result)) $result->free();
$conn->close();
?>