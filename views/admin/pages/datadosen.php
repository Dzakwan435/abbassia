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

// Add lecturer process
if (isset($_POST['tambah_dosen'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: datadosen.php");
        exit();
    }

    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $bidang_keahlian = clean_input($_POST['bidang_keahlian']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: datadosen.php");
        exit();
    }

    try {
        // Check if NIP already exists
        $check_sql = "SELECT nip FROM dosen WHERE nip = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $nip);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_stmt->close();

        // Check if user_id already exists if provided
        if ($user_id !== null) {
            $check_user_sql = "SELECT user_id FROM dosen WHERE user_id = ?";
            $check_user_stmt = $conn->prepare($check_user_sql);
            if (!$check_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_user_stmt->bind_param("i", $user_id);
            $check_user_stmt->execute();
            $check_user_stmt->store_result();
            
            if ($check_user_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
                $check_user_stmt->close();
                header("Location: datadosen.php");
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
                header("Location: datadosen.php");
                exit();
            }
            $check_valid_user_stmt->close();
        }

        // Verify if prodi exists in program_studi table (jika diisi)
        if (!empty($prodi)) {
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
                header("Location: datadosen.php");
                exit();
            }
            $check_prodi_stmt->close();
        }

        // Insert data
        if ($user_id === null) {
            $sql = "INSERT INTO dosen (nip, nama, bidang_keahlian, pangkat, alamat, nohp, email, prodi) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("ssssssss", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi);
        } else {
            $sql = "INSERT INTO dosen (nip, nama, bidang_keahlian, pangkat, alamat, nohp, email, prodi, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            $stmt->bind_param("ssssssssi", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Add lecturer error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}

// Edit lecturer process
if (isset($_POST['edit_dosen'])) {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Token keamanan tidak valid";
        header("Location: datadosen.php");
        exit();
    }

    $old_nip = clean_input($_POST['old_nip']);
    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $bidang_keahlian = clean_input($_POST['bidang_keahlian']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: datadosen.php");
        exit();
    }

    try {
        // Check if NIP is being changed to an existing one
        if ($old_nip != $nip) {
            $check_sql = "SELECT nip FROM dosen WHERE nip = ? AND nip != ?";
            $check_stmt = $conn->prepare($check_sql);
            if (!$check_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_stmt->bind_param("ss", $nip, $old_nip);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
                $check_stmt->close();
                header("Location: datadosen.php");
                exit();
            }
            $check_stmt->close();
        }

        // Check if user_id is being changed to an existing one
        if ($user_id !== null) {
            $check_user_sql = "SELECT nip FROM dosen WHERE user_id = ? AND nip != ?";
            $check_user_stmt = $conn->prepare($check_user_sql);
            if (!$check_user_stmt) {
                throw new Exception("Error database: " . $conn->error);
            }
            
            $check_user_stmt->bind_param("is", $user_id, $old_nip);
            $check_user_stmt->execute();
            $check_user_stmt->store_result();
            
            if ($check_user_stmt->num_rows > 0) {
                $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
                $check_user_stmt->close();
                header("Location: datadosen.php");
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
                header("Location: datadosen.php");
                exit();
            }
            $check_valid_user_stmt->close();
        }

        // Verify if prodi exists in program_studi table (jika diisi)
        if (!empty($prodi)) {
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
                header("Location: datadosen.php");
                exit();
            }
            $check_prodi_stmt->close();
        }

        // Update data
        $sql = "UPDATE dosen SET 
                nip = ?, 
                nama = ?, 
                bidang_keahlian = ?, 
                pangkat = ?, 
                alamat = ?, 
                nohp = ?, 
                email = ?,
                prodi = ?,
                user_id = ?
                WHERE nip = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssssss", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi, $user_id, $old_nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data dosen berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui data dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Edit lecturer error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}

// Delete lecturer process
if (isset($_GET['delete_nip'])) {
    $nip = clean_input($_GET['delete_nip']);
    
    try {
        // Check if lecturer has any teaching assignments
        $check_sql = "SELECT COUNT(*) as count FROM jadwal_kuliah WHERE dosen_nip = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $check_stmt->bind_param("s", $nip);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            throw new Exception("Dosen tidak dapat dihapus karena memiliki jadwal mengajar");
        }

        $sql = "DELETE FROM dosen WHERE nip = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }
        
        $stmt->bind_param("s", $nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Delete lecturer error: " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get users for dropdown (only users that aren't already linked and have role dosen)
$users_sql = "SELECT id, username FROM users 
              WHERE role = 'dosen' 
              AND id NOT IN (SELECT user_id FROM dosen WHERE user_id IS NOT NULL) 
              ORDER BY username ASC";
$users_result = $conn->query($users_sql);

// Get lecturer data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM dosen";
$count_result = $conn->query($count_sql);
if ($count_result) {
    $total_rows = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $per_page);
} else {
    $total_rows = 0;
    $total_pages = 1;
}

// Get paginated data with program studi information
$sql = "SELECT d.*, ps.nama_prodi 
        FROM dosen d 
        LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
        ORDER BY d.nama ASC 
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $offset, $per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
    $_SESSION['error_message'] = "Error retrieving lecturer data: " . $conn->error;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Dosen - Portal Akademik</title>
    
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
                <h2 class="mb-0 text-primary">Data Dosen</h2>
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
                                    <th>NIP</th>
                                    <th>Nama Dosen</th>
                                    <th>Bidang Keahlian</th>
                                    <th>Program Studi</th>
                                    <th>Pangkat</th>
                                    <th>No. HP</th>
                                    <th>Email</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['nip']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($row['bidang_keahlian'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['pangkat'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['nohp'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['email'] ?: '-'); ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                    onclick="setEditForm(
                                                        '<?php echo $row['nip']; ?>',
                                                        '<?php echo addslashes($row['nama']); ?>',
                                                        '<?php echo addslashes($row['bidang_keahlian']); ?>',
                                                        '<?php echo addslashes($row['pangkat']); ?>',
                                                        `<?php echo addslashes($row['alamat']); ?>`,
                                                        '<?php echo addslashes($row['nohp']); ?>',
                                                        '<?php echo addslashes($row['email']); ?>',
                                                        '<?php echo $row['prodi']; ?>',
                                                        '<?php echo $row['user_id']; ?>'
                                                    )">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete_nip=<?php echo $row['nip']; ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus dosen <?php echo addslashes($row['nama']); ?>?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-database me-2"></i>Tidak ada data dosen
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
    
    <!-- Tambah Dosen Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tambahModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Data Dosen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nip" required maxlength="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Dosen <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bidang Keahlian</label>
                                <input type="text" class="form-control" name="bidang_keahlian" maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi</label>
                                <select class="form-select" name="prodi">
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
                                <label class="form-label">Pangkat / Jabatan</label>
                                <input type="text" class="form-control" name="pangkat" maxlength="50">
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
                                <textarea class="form-control" name="alamat" rows="3" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="tambah_dosen" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Dosen Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Data Dosen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="old_nip" id="edit_old_nip">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nip" name="nip" required maxlength="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Dosen <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nama" name="nama" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bidang Keahlian</label>
                                <input type="text" class="form-control" id="edit_bidang" name="bidang_keahlian" maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi</label>
                                <select class="form-select" id="edit_prodi" name="prodi">
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
                                <label class="form-label">Pangkat / Jabatan</label>
                                <input type="text" class="form-control" id="edit_pangkat" name="pangkat" maxlength="50">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">User ID</label>
                                <select class="form-select" id="edit_user_id" name="user_id">
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
                                <input type="text" class="form-control" id="edit_nohp" name="nohp" maxlength="15">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" maxlength="100">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" id="edit_alamat" name="alamat" rows="3" maxlength="500"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="edit_dosen" class="btn btn-primary">
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
        function setEditForm(nip, nama, bidang, pangkat, alamat, nohp, email, prodi, user_id) {
            document.getElementById('edit_old_nip').value = nip;
            document.getElementById('edit_nip').value = nip;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_bidang').value = bidang || '';
            document.getElementById('edit_pangkat').value = pangkat || '';
            document.getElementById('edit_alamat').value = alamat || '';
            document.getElementById('edit_nohp').value = nohp || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_prodi').value = prodi || '';
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