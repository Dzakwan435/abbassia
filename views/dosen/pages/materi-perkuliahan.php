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

// Get current user data
$user_id = $_SESSION['user_id'];

// Verify the user is a lecturer and get their NIP
$verify_dosen_sql = "SELECT d.nip FROM dosen d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE u.id = ? AND u.role = 'dosen'";
$verify_dosen_stmt = $conn->prepare($verify_dosen_sql);
$verify_dosen_stmt->bind_param("i", $user_id);
$verify_dosen_stmt->execute();
$verify_dosen_result = $verify_dosen_stmt->get_result();
$dosen_data = $verify_dosen_result->fetch_assoc();
$verify_dosen_stmt->close();

if (!$dosen_data) {
    $_SESSION['error_message'] = "Anda tidak memiliki akses sebagai dosen";
    header("Location: dashboard-dosen.php");
    exit();
}

$dosen_nip = $dosen_data['nip'];

// File upload directory
$upload_dir = '../../../uploads/materi/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Add materi process
if (isset($_POST['tambah_materi'])) {
    $judul = clean_input($_POST['judul']);
    $deskripsi = clean_input($_POST['deskripsi']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $pertemuan_ke = (int)clean_input($_POST['pertemuan_ke']);
    $jenis_materi = clean_input($_POST['jenis_materi']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validate input
    if (empty($judul) || empty($kode_matkul) || empty($pertemuan_ke) || 
        empty($jenis_materi) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    // Verify kode_matkul exists and belongs to this dosen
    $verify_matkul_sql = "SELECT COUNT(*) FROM jadwal_kuliah jk
                         JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
                         WHERE jk.dosen_nip = ? AND jk.kode_matkul = ?";
    $verify_matkul_stmt = $conn->prepare($verify_matkul_sql);
    $verify_matkul_stmt->bind_param("ss", $dosen_nip, $kode_matkul);
    $verify_matkul_stmt->execute();
    $verify_matkul_stmt->bind_result($matkul_exists);
    $verify_matkul_stmt->fetch();
    $verify_matkul_stmt->close();

    if (!$matkul_exists) {
        $_SESSION['error_message'] = "Anda tidak mengajar mata kuliah yang dipilih";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    $file_path = null;
    $file_name = null;
    $file_size = 0;

    // Handle file upload
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_info = pathinfo($_FILES['file_materi']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['error_message'] = "Tipe file tidak diizinkan. Gunakan: " . implode(', ', $allowed_types);
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        if ($_FILES['file_materi']['size'] > $max_size) {
            $_SESSION['error_message'] = "Ukuran file terlalu besar. Maksimal 10MB";
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        $file_name = $dosen_nip . '_' . $kode_matkul . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        $file_size = $_FILES['file_materi']['size'];
        
        if (!move_uploaded_file($_FILES['file_materi']['tmp_name'], $file_path)) {
            $_SESSION['error_message'] = "Gagal mengupload file";
            header("Location: materi-perkuliahan.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert into materi_perkuliahan table
        $sql = "INSERT INTO materi_perkuliahan (judul, deskripsi, kode_matkul, dosen_nip, pertemuan_ke, jenis_materi, file_path, file_name, file_size, semester, tahun_ajaran, tanggal_upload) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssississs", $judul, $deskripsi, $kode_matkul, $dosen_nip, $pertemuan_ke, $jenis_materi, $file_path, $file_name, $file_size, $semester, $tahun_ajaran);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Materi perkuliahan berhasil ditambahkan";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        $_SESSION['error_message'] = "Gagal menambahkan materi: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Edit materi process
if (isset($_POST['edit_materi'])) {
    $id = (int)clean_input($_POST['id']);
    $judul = clean_input($_POST['judul']);
    $deskripsi = clean_input($_POST['deskripsi']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $pertemuan_ke = (int)clean_input($_POST['pertemuan_ke']);
    $jenis_materi = clean_input($_POST['jenis_materi']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validate input
    if (empty($judul) || empty($kode_matkul) || empty($pertemuan_ke) || 
        empty($jenis_materi) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    // Verify ownership
    $check_ownership_sql = "SELECT file_path FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $ownership_result = $check_ownership_stmt->get_result();
    $old_materi = $ownership_result->fetch_assoc();
    $check_ownership_stmt->close();
    
    if (!$old_materi) {
        $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengedit materi ini";
        header("Location: materi-perkuliahan.php");
        exit();
    }

    $file_path = $old_materi['file_path'];
    $file_name = null;
    $file_size = 0;

    // Handle new file upload if provided
    if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        $file_info = pathinfo($_FILES['file_materi']['name']);
        $file_ext = strtolower($file_info['extension']);
        
        if (!in_array($file_ext, $allowed_types)) {
            $_SESSION['error_message'] = "Tipe file tidak diizinkan. Gunakan: " . implode(', ', $allowed_types);
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        if ($_FILES['file_materi']['size'] > $max_size) {
            $_SESSION['error_message'] = "Ukuran file terlalu besar. Maksimal 10MB";
            header("Location: materi-perkuliahan.php");
            exit();
        }
        
        $new_file_name = $dosen_nip . '_' . $kode_matkul . '_' . time() . '.' . $file_ext;
        $new_file_path = $upload_dir . $new_file_name;
        $file_size = $_FILES['file_materi']['size'];
        
        if (move_uploaded_file($_FILES['file_materi']['tmp_name'], $new_file_path)) {
            // Delete old file if exists
            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }
            $file_path = $new_file_path;
            $file_name = $new_file_name;
        } else {
            $_SESSION['error_message'] = "Gagal mengupload file baru";
            header("Location: materi-perkuliahan.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "UPDATE materi_perkuliahan SET 
                judul = ?, 
                deskripsi = ?, 
                kode_matkul = ?, 
                pertemuan_ke = ?, 
                jenis_materi = ?, 
                semester = ?, 
                tahun_ajaran = ?";
        
        $params = [$judul, $deskripsi, $kode_matkul, $pertemuan_ke, $jenis_materi, $semester, $tahun_ajaran];
        $param_types = "sssssss";
        
        if ($file_name) {
            $sql .= ", file_path = ?, file_name = ?, file_size = ?";
            $params[] = $file_path;
            $params[] = $file_name;
            $params[] = $file_size;
            $param_types .= "ssi";
        }
        
        $sql .= " WHERE id = ? AND dosen_nip = ?";
        $params[] = $id;
        $params[] = $dosen_nip;
        $param_types .= "is";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param($param_types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Materi perkuliahan berhasil diperbarui";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal memperbarui materi: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Delete materi process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    // Verify ownership and get file path
    $check_ownership_sql = "SELECT file_path FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $ownership_result = $check_ownership_stmt->get_result();
    $materi_data = $ownership_result->fetch_assoc();
    $check_ownership_stmt->close();
    
    if (!$materi_data) {
        $_SESSION['error_message'] = "Anda tidak memiliki izin untuk menghapus materi ini";
        header("Location: materi-perkuliahan.php");
        exit();
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "DELETE FROM materi_perkuliahan WHERE id = ? AND dosen_nip = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("is", $id, $dosen_nip);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Delete file if exists
        if ($materi_data['file_path'] && file_exists($materi_data['file_path'])) {
            unlink($materi_data['file_path']);
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Materi perkuliahan berhasil dihapus";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal menghapus materi: " . $e->getMessage();
    }
    header("Location: materi-perkuliahan.php");
    exit();
}

// Get mata kuliah data for current dosen
$matkul_sql = "SELECT DISTINCT jk.kode_matkul, mk.nama_matkul 
               FROM jadwal_kuliah jk
               JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
               WHERE jk.dosen_nip = ?
               ORDER BY mk.nama_matkul ASC";
$matkul_stmt = $conn->prepare($matkul_sql);
$matkul_stmt->bind_param("s", $dosen_nip);
$matkul_stmt->execute();
$matkul_result = $matkul_stmt->get_result();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_matkul = isset($_GET['matkul']) ? clean_input($_GET['matkul']) : '';
$filter_jenis = isset($_GET['jenis']) ? clean_input($_GET['jenis']) : '';
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = ["mp.dosen_nip = ?"]; // Always filter by current dosen
$params = [$dosen_nip];
$param_types = 's';

if (!empty($search)) {
    $where_clauses[] = "(mp.judul LIKE ? OR mp.deskripsi LIKE ? OR mk.nama_matkul LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_matkul)) {
    $where_clauses[] = "mp.kode_matkul = ?";
    $params[] = $filter_matkul;
    $param_types .= 's';
}

if (!empty($filter_jenis)) {
    $where_clauses[] = "mp.jenis_materi = ?";
    $params[] = $filter_jenis;
    $param_types .= 's';
}

if (!empty($filter_semester)) {
    $where_clauses[] = "mp.semester = ?";
    $params[] = $filter_semester;
    $param_types .= 's';
}

if (!empty($filter_tahun)) {
    $where_clauses[] = "mp.tahun_ajaran = ?";
    $params[] = $filter_tahun;
    $param_types .= 's';
}

$where_clause = "WHERE " . implode(' AND ', $where_clauses);

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT tahun_ajaran FROM materi_perkuliahan WHERE dosen_nip = ? ORDER BY tahun_ajaran DESC";
$tahun_stmt = $conn->prepare($tahun_sql);
$tahun_stmt->bind_param("s", $dosen_nip);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();

// Count total records for pagination
$count_sql = "SELECT COUNT(*) 
              FROM materi_perkuliahan mp
              LEFT JOIN mata_kuliah mk ON mp.kode_matkul = mk.kode_matkul
              $where_clause";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Get data with joins for detailed information
$sql = "SELECT mp.*, mk.nama_matkul
        FROM materi_perkuliahan mp
        LEFT JOIN mata_kuliah mk ON mp.kode_matkul = mk.kode_matkul
        $where_clause
        ORDER BY mp.tanggal_upload DESC, mp.pertemuan_ke ASC
        LIMIT ?, ?";

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
// 1. Total materi uploaded
$stats_sql = "SELECT COUNT(*) as total_materi FROM materi_perkuliahan WHERE dosen_nip = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $dosen_nip);
$stats_stmt->execute();
$stats_stmt->bind_result($total_materi);
$stats_stmt->fetch();
$stats_stmt->close();

// 2. Total mata kuliah with materials
$matkul_with_materi_sql = "SELECT COUNT(DISTINCT kode_matkul) as total_matkul FROM materi_perkuliahan WHERE dosen_nip = ?";
$matkul_with_materi_stmt = $conn->prepare($matkul_with_materi_sql);
$matkul_with_materi_stmt->bind_param("s", $dosen_nip);
$matkul_with_materi_stmt->execute();
$matkul_with_materi_stmt->bind_result($total_matkul_with_materi);
$matkul_with_materi_stmt->fetch();
$matkul_with_materi_stmt->close();

// 3. Most recent upload
$recent_upload_sql = "SELECT judul, tanggal_upload FROM materi_perkuliahan WHERE dosen_nip = ? ORDER BY tanggal_upload DESC LIMIT 1";
$recent_upload_stmt = $conn->prepare($recent_upload_sql);
$recent_upload_stmt->bind_param("s", $dosen_nip);
$recent_upload_stmt->execute();
$recent_result = $recent_upload_stmt->get_result();
$recent_upload = $recent_result->fetch_assoc();
$recent_upload_stmt->close();

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Function to get file icon
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':
            return 'fas fa-file-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word text-primary';
        case 'ppt':
        case 'pptx':
            return 'fas fa-file-powerpoint text-warning';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel text-success';
        default:
            return 'fas fa-file text-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Perkuliahan - Portal Dosen</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
</head>
<body>
  <!-- includes/dosen-sidebar.php -->
<div class="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
            <span class="fw-bold">U</span>
        </div>
        <span class="sidebar-brand">PORTALSIA</span>
    </div>
    
    <div class="menu-category">MENU DOSEN</div>
    <div class="menu">
        <a href="dashboard-dosen.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard-dosen.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="profil-dosen.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profil-dosen.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profil Dosen</span>
        </a>
        <a href="jadwal-mengajar.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'jadwal-mengajar.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Jadwal Mengajar</span>
        </a>
        <a href="input-nilai.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'input-nilai.php' ? 'active' : ''; ?>">
            <i class="fas fa-edit"></i>
            <span>Input Nilai</span>
        </a>
        <a href="daftar-mahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'daftar-mahasiswa.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Daftar Mahasiswa</span>
        </a>
        <a href="materi-perkuliahan.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materi-perkuliahan.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Materi Perkuliahan</span>
        </a>
    </div>
    
    <div class="menu-category">PENGATURAN</div>
    <div class="menu">
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
        <!-- Main Content -->
        <main class="main-content">
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show fade-in">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show fade-in">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Materi Perkuliahan</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahMateriModal">
                    <i class="fas fa-plus me-2"></i>Tambah Materi
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card bg-white fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Total Materi</h6>
                                    <h3 class="mb-0"><?php echo $total_materi; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-white fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Mata Kuliah</h6>
                                    <h3 class="mb-0"><?php echo $total_matkul_with_materi; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-white fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-info bg-opacity-10 text-info me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Terakhir Diunggah</h6>
                                    <h5 class="mb-0">
                                        <?php echo $recent_upload ? date('d M Y', strtotime($recent_upload['tanggal_upload'])) : '-'; ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card mb-4 fade-in">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari Materi</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul/Deskripsi">
                            </div>
                            <div class="col-md-2">
                                <label for="matkul" class="form-label">Mata Kuliah</label>
                                <select class="form-select" id="matkul" name="matkul">
                                    <option value="">Semua</option>
                                    <?php while ($matkul = $matkul_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>" 
                                        <?php echo ($filter_matkul == $matkul['kode_matkul']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="jenis" class="form-label">Jenis Materi</label>
                                <select class="form-select" id="jenis" name="jenis">
                                    <option value="">Semua</option>
                                    <option value="Slide" <?php echo ($filter_jenis == 'Slide') ? 'selected' : ''; ?>>Slide</option>
                                    <option value="Dokumen" <?php echo ($filter_jenis == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                                    <option value="Video" <?php echo ($filter_jenis == 'Video') ? 'selected' : ''; ?>>Video</option>
                                    <option value="Lainnya" <?php echo ($filter_jenis == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester">
                                    <option value="">Semua</option>
                                    <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                    <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                <select class="form-select" id="tahun_ajaran" name="tahun_ajaran">
                                    <option value="">Semua</option>
                                    <?php while ($tahun = $tahun_result->fetch_assoc()): ?>
                                    <option value="<?php echo $tahun['tahun_ajaran']; ?>"
                                        <?php echo ($filter_tahun == $tahun['tahun_ajaran']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Materi Table -->
            <div class="card fade-in">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="25%">Judul Materi</th>
                                    <th width="15%">Mata Kuliah</th>
                                    <th width="10%">Pertemuan</th>
                                    <th width="10%">Jenis</th>
                                    <th width="15%">File</th>
                                    <th width="10%">Tanggal</th>
                                    <th width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['judul']); ?></strong>
                                            <div class="materi-detail">
                                                <small class="text-muted"><?php echo htmlspecialchars($row['deskripsi']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['nama_matkul']); ?>
                                            <div class="materi-detail">
                                                <small><i class="fas fa-calendar-alt"></i> <?php echo $row['semester']; ?> <?php echo $row['tahun_ajaran']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary rounded-pill">Pertemuan <?php echo $row['pertemuan_ke']; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            switch($row['jenis_materi']) {
                                                case 'Slide': $badge_class = 'bg-warning'; break;
                                                case 'Dokumen': $badge_class = 'bg-info'; break;
                                                case 'Video': $badge_class = 'bg-danger'; break;
                                                default: $badge_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?> rounded-pill"><?php echo $row['jenis_materi']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($row['file_path']): ?>
                                            <div class="file-info">
                                                <i class="<?php echo getFileIcon($row['file_name']); ?> file-icon"></i>
                                                <div>
                                                    <small class="d-block"><?php echo htmlspecialchars($row['file_name']); ?></small>
                                                    <small class="text-muted"><?php echo formatFileSize($row['file_size']); ?></small>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">Tidak ada file</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d M Y', strtotime($row['tanggal_upload'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary edit-materi" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-judul="<?php echo htmlspecialchars($row['judul']); ?>"
                                                        data-deskripsi="<?php echo htmlspecialchars($row['deskripsi']); ?>"
                                                        data-kode_matkul="<?php echo $row['kode_matkul']; ?>"
                                                        data-pertemuan_ke="<?php echo $row['pertemuan_ke']; ?>"
                                                        data-jenis_materi="<?php echo $row['jenis_materi']; ?>"
                                                        data-semester="<?php echo $row['semester']; ?>"
                                                        data-tahun_ajaran="<?php echo $row['tahun_ajaran']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete_id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="fas fa-book-open fa-2x text-muted mb-2"></i>
                                                <p class="text-muted">Tidak ada materi perkuliahan ditemukan</p>
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tambahMateriModal">
                                                    <i class="fas fa-plus me-1"></i> Tambah Materi
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Tambah Materi Modal -->
    <div class="modal fade" id="tambahMateriModal" tabindex="-1" aria-labelledby="tambahMateriModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tambahMateriModalLabel">Tambah Materi Perkuliahan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="judul" name="judul" required>
                            </div>
                            <div class="col-md-12">
                                <label for="deskripsi" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="kode_matkul" class="form-label">Mata Kuliah <span class="text-danger">*</span></label>
                                <select class="form-select" id="kode_matkul" name="kode_matkul" required>
                                    <option value="">Pilih Mata Kuliah</option>
                                    <?php 
                                    $matkul_result->data_seek(0); // Reset pointer to beginning
                                    while ($matkul = $matkul_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>">
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="pertemuan_ke" class="form-label">Pertemuan Ke <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="pertemuan_ke" name="pertemuan_ke" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label for="jenis_materi" class="form-label">Jenis Materi <span class="text-danger">*</span></label>
                                <select class="form-select" id="jenis_materi" name="jenis_materi" required>
                                    <option value="">Pilih Jenis</option>
                                    <option value="Slide">Slide</option>
                                    <option value="Dokumen">Dokumen</option>
                                    <option value="Video">Video</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">Pilih Semester</option>
                                    <option value="Ganjil">Ganjil</option>
                                    <option value="Genap">Genap</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="tahun_ajaran" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                            </div>
                            <div class="col-md-12">
                                <label for="file_materi" class="form-label">File Materi</label>
                                <input type="file" class="form-control" id="file_materi" name="file_materi">
                                <div class="form-text">Format yang didukung: PDF, DOC, PPT, XLS, TXT (Maks. 10MB)</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="tambah_materi">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Materi Modal -->
    <div class="modal fade" id="editMateriModal" tabindex="-1" aria-labelledby="editMateriModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMateriModalLabel">Edit Materi Perkuliahan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="edit_judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_judul" name="judul" required>
                            </div>
                            <div class="col-md-12">
                                <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="edit_deskripsi" name="deskripsi" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_kode_matkul" class="form-label">Mata Kuliah <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_kode_matkul" name="kode_matkul" required>
                                    <option value="">Pilih Mata Kuliah</option>
                                    <?php 
                                    $matkul_result->data_seek(0); // Reset pointer to beginning
                                    while ($matkul = $matkul_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>">
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_pertemuan_ke" class="form-label">Pertemuan Ke <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_pertemuan_ke" name="pertemuan_ke" min="1" required>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_jenis_materi" class="form-label">Jenis Materi <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_jenis_materi" name="jenis_materi" required>
                                    <option value="">Pilih Jenis</option>
                                    <option value="Slide">Slide</option>
                                    <option value="Dokumen">Dokumen</option>
                                    <option value="Video">Video</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_semester" class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_semester" name="semester" required>
                                    <option value="">Pilih Semester</option>
                                    <option value="Ganjil">Ganjil</option>
                                    <option value="Genap">Genap</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_tahun_ajaran" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                            </div>
                            <div class="col-md-12">
                                <label for="edit_file_materi" class="form-label">File Materi Baru (Opsional)</label>
                                <input type="file" class="form-control" id="edit_file_materi" name="file_materi">
                                <div class="form-text">Format yang didukung: PDF, DOC, PPT, XLS, TXT (Maks. 10MB)</div>
                                <div id="current-file-info" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="edit_materi">Simpan Perubahan</button>
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
        $(document).ready(function() {
            // Handle edit button click
            $('.edit-materi').click(function() {
                var id = $(this).data('id');
                var judul = $(this).data('judul');
                var deskripsi = $(this).data('deskripsi');
                var kode_matkul = $(this).data('kode_matkul');
                var pertemuan_ke = $(this).data('pertemuan_ke');
                var jenis_materi = $(this).data('jenis_materi');
                var semester = $(this).data('semester');
                var tahun_ajaran = $(this).data('tahun_ajaran');
                
                $('#edit_id').val(id);
                $('#edit_judul').val(judul);
                $('#edit_deskripsi').val(deskripsi);
                $('#edit_kode_matkul').val(kode_matkul);
                $('#edit_pertemuan_ke').val(pertemuan_ke);
                $('#edit_jenis_materi').val(jenis_materi);
                $('#edit_semester').val(semester);
                $('#edit_tahun_ajaran').val(tahun_ajaran);
                
                // Get current file info via AJAX
                $.ajax({
                    url: 'get_file_info.php',
                    type: 'GET',
                    data: { id: id },
                    success: function(response) {
                        $('#current-file-info').html(response);
                    }
                });
                
                $('#editMateriModal').modal('show');
            });
            
            // Auto format tahun ajaran
            $('#tahun_ajaran, #edit_tahun_ajaran').on('blur', function() {
                var value = $(this).val();
                if (value.length === 4 && /^\d+$/.test(value)) {
                    var nextYear = parseInt(value) + 1;
                    $(this).val(value + '/' + nextYear);
                }
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>