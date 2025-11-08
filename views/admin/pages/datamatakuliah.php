<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../../../include/config.php';

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add mata kuliah process
if (isset($_POST['tambah_matkul'])) {
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $nama_matkul = clean_input($_POST['nama_matkul']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode_matkul) || empty($nama_matkul) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Semua field harus diisi";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Additional validation
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: datamatakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Check if kode_matkul already exists
    $check_sql = "SELECT kode_matkul FROM mata_kuliah WHERE kode_matkul = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $kode_matkul);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "Kode mata kuliah sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_stmt->close();

    // Verify if prodi exists in program_studi table
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "INSERT INTO mata_kuliah (kode_matkul, nama_matkul, sks, semester, prodi) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiis", $kode_matkul, $nama_matkul, $sks, $semester, $prodi);
        
        if ($stmt->execute()) {
            // Log the action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'ADD', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Added new mata kuliah: {$nama_matkul}";
                $log_stmt->bind_param("iss", $user_id, $kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Mata kuliah baru berhasil ditambahkan";
            $conn->commit();
        } else {
            throw new Exception("Gagal menambahkan mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}

// Edit mata kuliah process
if (isset($_POST['edit_matkul'])) {
    $old_kode_matkul = clean_input($_POST['old_kode_matkul']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $nama_matkul = clean_input($_POST['nama_matkul']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode_matkul) || empty($nama_matkul) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Semua field harus diisi";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Additional validation for input values
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: datamatakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Check if kode_matkul is being changed to an existing one
    if ($old_kode_matkul != $kode_matkul) {
        $check_sql = "SELECT kode_matkul FROM mata_kuliah WHERE kode_matkul = ? AND kode_matkul != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $kode_matkul, $old_kode_matkul);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "Kode mata kuliah sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datamatakuliah.php");
            exit();
        }
        $check_stmt->close();
    }

    // Verify if prodi exists in program_studi table
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction for data integrity
        $conn->begin_transaction();
        
        // Check if this course has associated data in other tables
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE kode_matkul = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("s", $old_kode_matkul);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        $check_nilai_sql = "SELECT COUNT(*) FROM data_nilai WHERE kode_matkul = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        $check_nilai_stmt->bind_param("s", $old_kode_matkul);  // Fixed: changed from $kode_matkul to $old_kode_matkul
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($jadwal_count > 0 || $nilai_count > 0) {
            // If there are associated records, we cannot change the primary key
            if ($old_kode_matkul != $kode_matkul) {
                throw new Exception("Kode mata kuliah tidak dapat diubah karena sudah digunakan dalam jadwal atau nilai");
            }
            
            // But we can update other fields
            $sql = "UPDATE mata_kuliah SET 
                    nama_matkul = ?, 
                    sks = ?, 
                    semester = ?, 
                    prodi = ? 
                    WHERE kode_matkul = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiss", $nama_matkul, $sks, $semester, $prodi, $old_kode_matkul);
        } else {
            // If no associated records, can change everything including primary key
            $sql = "UPDATE mata_kuliah SET 
                    kode_matkul = ?,
                    nama_matkul = ?, 
                    sks = ?, 
                    semester = ?, 
                    prodi = ? 
                    WHERE kode_matkul = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiss", $kode_matkul, $nama_matkul, $sks, $semester, $prodi, $old_kode_matkul);
        }
        
        if ($stmt->execute()) {
            // Log the edit action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'EDIT', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Updated mata kuliah from {$old_kode_matkul} to {$kode_matkul}";
                $log_stmt->bind_param("iss", $user_id, $old_kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Data mata kuliah berhasil diperbarui";
            $conn->commit();
        } else {
            throw new Exception("Gagal memperbarui data mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}

// Delete mata kuliah process
if (isset($_GET['delete_kode'])) {
    $kode_matkul = clean_input($_GET['delete_kode']);
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if mata kuliah has any related records
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE kode_matkul = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("s", $kode_matkul);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        $check_nilai_sql = "SELECT COUNT(*) FROM data_nilai WHERE kode_matkul = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        $check_nilai_stmt->bind_param("s", $kode_matkul);
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($jadwal_count > 0) {
            throw new Exception("Mata kuliah tidak dapat dihapus karena digunakan dalam jadwal kuliah");
        }
        
        if ($nilai_count > 0) {
            throw new Exception("Mata kuliah tidak dapat dihapus karena memiliki data nilai");
        }
        
        // Get mata kuliah details for logging before deletion
        $get_matkul_sql = "SELECT nama_matkul FROM mata_kuliah WHERE kode_matkul = ?";
        $get_matkul_stmt = $conn->prepare($get_matkul_sql);
        $get_matkul_stmt->bind_param("s", $kode_matkul);
        $get_matkul_stmt->execute();
        $get_matkul_stmt->bind_result($nama_matkul);
        $get_matkul_stmt->fetch();
        $get_matkul_stmt->close();

        $sql = "DELETE FROM mata_kuliah WHERE kode_matkul = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kode_matkul);
        
        if ($stmt->execute()) {
            // Log the delete action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'DELETE', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Deleted mata kuliah '{$nama_matkul}' with code {$kode_matkul}";
                $log_stmt->bind_param("iss", $user_id, $kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Mata kuliah berhasil dihapus";
            $conn->commit();
        } else {
            throw new Exception("Gagal menghapus mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Get mata kuliah data with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) FROM mata_kuliah";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE kode_matkul LIKE ? OR nama_matkul LIKE ? ";
    $search_param = "%$search%";
}

// Get paginated data with program studi information
if (empty($search)) {
    $sql = "SELECT mk.*, ps.nama_prodi 
            FROM mata_kuliah mk 
            LEFT JOIN program_studi ps ON mk.prodi = ps.kode_prodi 
            ORDER BY mk.nama_matkul ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $offset, $per_page);
} else {
    $sql = "SELECT mk.*, ps.nama_prodi 
            FROM mata_kuliah mk 
            LEFT JOIN program_studi ps ON mk.prodi = ps.kode_prodi 
            WHERE mk.kode_matkul LIKE ? OR mk.nama_matkul LIKE ?
            ORDER BY mk.nama_matkul ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $search_param, $search_param, $offset, $per_page);
    
    // Also update the count for pagination
    $count_sql = "SELECT COUNT(*) FROM mata_kuliah WHERE kode_matkul LIKE ? OR nama_matkul LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("ss", $search_param, $search_param);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!-- HTML  -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mata Kuliah - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
   <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/admin.css">
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
                <h2 class="mb-0 text-primary">Data Mata Kuliah</h2>
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
            
            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Cari kode atau nama mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-1"></i> Cari
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                        <div class="col-auto">
                            <a href="datamatakuliah.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Reset
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        
            <!-- Data Table -->
            <div class="card fade-in">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Matkul</th>
                                    <th>Nama Matkul</th>
                                    <th>SKS</th>
                                    <th>Semester</th>
                                    <th>Program Studi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['kode_matkul']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_matkul']); ?></td>
                                        <td><?php echo htmlspecialchars($row['sks']); ?></td>
                                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                                                                <td><?php echo htmlspecialchars($row['nama_prodi'] ?: '-'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal" 
                                                    onclick="setEditForm(
                                                        '<?php echo $row['kode_matkul']; ?>',
                                                        '<?php echo addslashes($row['nama_matkul']); ?>',
                                                        '<?php echo $row['sks']; ?>',
                                                        '<?php echo $row['semester']; ?>',
                                                        '<?php echo $row['prodi']; ?>'
                                                    )">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete_kode=<?php echo $row['kode_matkul']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah ini?')">
                                                    <i class="fas fa-trash-alt"></i> Hapus
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-database me-2"></i>Tidak ada data mata kuliah
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
                            <small class="text-muted">Menampilkan <?php echo min($per_page, $result->num_rows); ?> dari <?php echo $total_rows; ?> data</small>
                        </div>
                        <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" aria-label="Next">
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
    
    <!-- Tambah Matkul Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tambahModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Mata Kuliah
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kode Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kode_matkul" required maxlength="10">
                                <small class="text-muted">Contoh: MK001</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_matkul" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">SKS <span class="text-danger">*</span></label>
                                <select class="form-select" name="sks" required>
                                    <option value="">Pilih SKS</option>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" required>
                                    <option value="">Pilih Semester</option>
                                    <?php for ($i = 1; $i <= 14; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Program Studi <span class="text-danger">*</span></label>
                                <select class="form-select" name="prodi" required>
                                    <option value="">Pilih Program Studi</option>
                                    <?php while($prodi_row = $prodi_result->fetch_assoc()): ?>
                                        <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                                            <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <?php $prodi_result->data_seek(0); ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" name="tambah_matkul">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Matkul Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Mata Kuliah
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="old_kode_matkul" id="edit_old_kode_matkul">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kode Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kode_matkul" id="edit_kode_matkul" required maxlength="10">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_matkul" id="edit_nama_matkul" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">SKS <span class="text-danger">*</span></label>
                                <select class="form-select" name="sks" id="edit_sks" required>
                                    <option value="">Pilih SKS</option>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" id="edit_semester" required>
                                    <option value="">Pilih Semester</option>
                                    <?php for ($i = 1; $i <= 14; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Program Studi <span class="text-danger">*</span></label>
                                <select class="form-select" name="prodi" id="edit_prodi" required>
                                    <option value="">Pilih Program Studi</option>
                                    <?php while($prodi_row = $prodi_result->fetch_assoc()): ?>
                                        <option value="<?php echo $prodi_row['kode_prodi']; ?>">
                                            <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                    <?php $prodi_result->data_seek(0); ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary" name="edit_matkul">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Function to set edit form values
        function setEditForm(kode_matkul, nama_matkul, sks, semester, prodi) {
            document.getElementById('edit_old_kode_matkul').value = kode_matkul;
            document.getElementById('edit_kode_matkul').value = kode_matkul;
            document.getElementById('edit_nama_matkul').value = nama_matkul;
            document.getElementById('edit_sks').value = sks;
            document.getElementById('edit_semester').value = semester;
            document.getElementById('edit_prodi').value = prodi;
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>