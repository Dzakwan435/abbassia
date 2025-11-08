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

// Add student process
if (isset($_POST['tambah_mahasiswa'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: datamahasiswa.php");
        exit();
    }

    $nim = clean_input($_POST['nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: datamahasiswa.php");
        exit();
    }

    try {
        // Check if NIM already exists
        $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $nim);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_stmt->close();

        // Check if user_id already exists if provided
        if ($user_id !== null) {
            $check_user_sql = "SELECT user_id FROM mahasiswa WHERE user_id = ?";
            $check_user_stmt = $conn->prepare($check_user_sql);
            if (!$check_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_user_stmt->bind_param("i", $user_id);
            $check_user_stmt->execute();
            $check_user_stmt->store_result();
            
            if ($check_user_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "ID User sudah terhubung dengan mahasiswa lain";
                $check_user_stmt->close();
                header("Location: datamahasiswa.php");
                exit();
            }
            $check_user_stmt->close();
            
            // Verify if user_id exists in users table
            $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
            $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
            if (!$check_valid_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_valid_user_stmt->bind_param("i", $user_id);
            $check_valid_user_stmt->execute();
            $check_valid_user_stmt->store_result();
            
            if ($check_valid_user_stmt->num_rows == 0) {
                $_SESSION['error_message'] = "ID User tidak ditemukan";
                $check_valid_user_stmt->close();
                header("Location: datamahasiswa.php");
                exit();
            }
            $check_valid_user_stmt->close();
        }

        // Verify if prodi exists in program_studi table
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        if (!$check_prodi_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_prodi_stmt->close();

        // Insert data
        if ($user_id === null) {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("ssssss", $nim, $nama, $prodi, $alamat, $nohp, $email);
        } else {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("ssssssi", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Add student error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamahasiswa.php");
    exit();
}

// Edit student process
if (isset($_POST['edit_mahasiswa'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: datamahasiswa.php");
        exit();
    }

    $id = (int)clean_input($_POST['id']);
    $nim = clean_input($_POST['nim']);
    $old_nim = clean_input($_POST['old_nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: datamahasiswa.php");
        exit();
    }

    try {
        // Check if NIM is being changed to an existing one
        if ($old_nim != $nim) {
            $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ? AND nim != ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_stmt->bind_param("ss", $nim, $old_nim);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
                $check_stmt->close();
                header("Location: datamahasiswa.php");
                exit();
            }
            $check_stmt->close();
        }

        // Check if user_id is being changed to an existing one
        if ($user_id !== null) {
            $check_user_sql = "SELECT id FROM mahasiswa WHERE user_id = ? AND nim != ?";
            $check_user_stmt = $conn->prepare($check_user_sql);
            if (!$check_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_user_stmt->bind_param("is", $user_id, $old_nim);
            $check_user_stmt->execute();
            $check_user_stmt->store_result();
            
            if ($check_user_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "ID User sudah terhubung dengan mahasiswa lain";
                $check_user_stmt->close();
                header("Location: datamahasiswa.php");
                exit();
            }
            $check_user_stmt->close();
            
            // Verify if user_id exists in users table
            $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
            $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
            if (!$check_valid_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_valid_user_stmt->bind_param("i", $user_id);
            $check_valid_user_stmt->execute();
            $check_valid_user_stmt->store_result();
            
            if ($check_valid_user_stmt->num_rows == 0) {
                $_SESSION['error_message'] = "ID User tidak ditemukan";
                $check_valid_user_stmt->close();
                header("Location: datamahasiswa.php");
                exit();
            }
            $check_valid_user_stmt->close();
        }

        // Verify if prodi exists in program_studi table
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        if (!$check_prodi_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_prodi_stmt->close();

        // Update data
        $sql = "UPDATE mahasiswa SET 
                nim = ?, 
                nama = ?, 
                prodi = ?, 
                alamat = ?, 
                nohp = ?, 
                email = ?,
                user_id = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssii", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data mahasiswa berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui data mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Edit student error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamahasiswa.php");
    exit();
}

// Delete student process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    try {
        // Check if student has any course enrollments
        $check_sql = "SELECT COUNT(*) as count FROM krs WHERE mahasiswa_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            throw new Exception("Mahasiswa tidak dapat dihapus karena memiliki data KRS");
        }

        // Check if student has grades
        $check_nilai_sql = "SELECT COUNT(*) as count FROM data_nilai WHERE mahasiswa_id = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        if (!$check_nilai_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_nilai_stmt->bind_param("i", $id);
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($nilai_count > 0) {
            throw new Exception("Mahasiswa tidak dapat dihapus karena memiliki data nilai");
        }

        $sql = "DELETE FROM mahasiswa WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Delete student error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamahasiswa.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get users for dropdown (only users that aren't already linked and have role mahasiswa)
$users_sql = "SELECT id, username FROM users 
              WHERE role = 'mahasiswa' 
              AND id NOT IN (SELECT user_id FROM mahasiswa WHERE user_id IS NOT NULL) 
              ORDER BY username ASC";
$users_result = $conn->query($users_sql);

// Get student data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM mahasiswa";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $per_page);
} else {
    $total_rows = 0;
    $total_pages = 1;
}

// Get paginated data with program studi information
$sql = "SELECT m.*, ps.nama_prodi 
        FROM mahasiswa m 
        LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
        ORDER BY m.nama ASC LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $offset, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
    $_SESSION['error_message'] = "Error retrieving student data: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mahasiswa - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/admin.css">
    <style>
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
                <h2 class="mb-0 text-primary">Data Mahasiswa</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Data
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
            
            <!-- Data Card -->
            <div class="card mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>NIM</th>
                                    <th>Nama Mahasiswa</th>
                                    <th>Program Studi</th>
                                    <th>No. HP</th>
                                    <th>Email</th>
                                    <th>Semester</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['nim']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['nohp'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($row['semester_aktif'] ?: '1'); ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                    onclick="setEditForm(
                                                        '<?php echo $row['id']; ?>',
                                                        '<?php echo $row['nim']; ?>',
                                                        '<?php echo addslashes($row['nama']); ?>',
                                                        '<?php echo $row['prodi']; ?>',
                                                        `<?php echo addslashes($row['alamat']); ?>`,
                                                        '<?php echo addslashes($row['nohp']); ?>',
                                                        '<?php echo addslashes($row['email']); ?>',
                                                        '<?php echo $row['user_id']; ?>'
                                                    )">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus mahasiswa <?php echo addslashes($row['nama']); ?>?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-database me-2"></i>Tidak ada data mahasiswa
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
    </div>
    
    <!-- Tambah Mahasiswa Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tambahModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Data Mahasiswa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nim" required maxlength="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Mahasiswa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi <span class="text-danger">*</span></label>
                                <select class="form-select" name="prodi" required>
                                    <option value="">Pilih Program Studi</option>
                                    <?php if ($prodi_result && $prodi_result->num_rows > 0): ?>
                                        <?php while($prodi_row = $prodi_result->fetch_assoc()): ?>
                                            <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                                                <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User ID</label>
                                <select class="form-select" name="user_id">
                                    <option value="">Pilih User (Opsional)</option>
                                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                                        <?php while($user_row = $users_result->fetch_assoc()): ?>
                                            <option value="<?php echo $user_row['id']; ?>">
                                                <?php echo htmlspecialchars($user_row['username']) . ' (ID: ' . $user_row['id'] . ')'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="nohp" maxlength="15">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" maxlength="100">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" name="alamat" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" name="tambah_mahasiswa">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Mahasiswa Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Mahasiswa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="old_nim" id="edit_old_nim">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nim" id="edit_nim" required maxlength="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Mahasiswa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" id="edit_nama" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi <span class="text-danger">*</span></label>
                                <select class="form-select" name="prodi" id="edit_prodi" required>
                                    <option value="">Pilih Program Studi</option>
                                    <?php 
                                    if ($prodi_result) {
                                        $prodi_result->data_seek(0);
                                        while($prodi_row = $prodi_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                                            <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                        </option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User ID</label>
                                <select class="form-select" name="user_id" id="edit_user_id">
                                    <option value="">Pilih User (Opsional)</option>
                                    <?php 
                                    if ($users_result) {
                                        $users_result->data_seek(0);
                                        while($user_row = $users_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $user_row['id']; ?>">
                                            <?php echo htmlspecialchars($user_row['username']) . ' (ID: ' . $user_row['id'] . ')'; ?>
                                        </option>
                                    <?php 
                                        endwhile; 
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="nohp" id="edit_nohp" maxlength="15">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" maxlength="100">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" name="alamat" id="edit_alamat" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" name="edit_mahasiswa">
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
        function setEditForm(id, nim, nama, prodi, alamat, nohp, email, user_id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_old_nim').value = nim;
            document.getElementById('edit_nim').value = nim;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_prodi').value = prodi;
            document.getElementById('edit_alamat').value = alamat || '';
            document.getElementById('edit_nohp').value = nohp || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_user_id').value = user_id || '';
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