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
    header("Location: jadwal-mengajar.php");
    exit();
}

$dosen_nip = $dosen_data['nip'];

// Get lecturer details
$dosen_sql = "SELECT nip, nama FROM dosen WHERE nip = ?";
$dosen_stmt = $conn->prepare($dosen_sql);
$dosen_stmt->bind_param("s", $dosen_nip);
$dosen_stmt->execute();
$dosen_result = $dosen_stmt->get_result();
$dosen_data = $dosen_result->fetch_assoc();
$dosen_stmt->close();

// Add jadwal mengajar process
if (isset($_POST['tambah_jadwal'])) {
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $ruangan = clean_input($_POST['ruangan']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($kode_matkul) || 
        empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Verify kode_matkul exists
    $verify_matkul_sql = "SELECT COUNT(*) FROM mata_kuliah WHERE kode_matkul = ?";
    $verify_matkul_stmt = $conn->prepare($verify_matkul_sql);
    $verify_matkul_stmt->bind_param("s", $kode_matkul);
    $verify_matkul_stmt->execute();
    $verify_matkul_stmt->bind_result($matkul_exists);
    $verify_matkul_stmt->fetch();
    $verify_matkul_stmt->close();

    if (!$matkul_exists) {
        $_SESSION['error_message'] = "Mata kuliah yang dipilih tidak valid";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                           (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                           (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssssssss", $dosen_nip, $hari, $semester, $tahun_ajaran, 
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Anda sudah memiliki jadwal mengajar pada hari dan waktu yang sama";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssssssss", $ruangan, $hari, $semester, $tahun_ajaran, 
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-mengajar.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert into jadwal_kuliah table
        $sql = "INSERT INTO jadwal_kuliah (hari, waktu_mulai, waktu_selesai, kode_matkul, ruangan, dosen_nip, semester, tahun_ajaran) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssssssss", $hari, $waktu_mulai, $waktu_selesai, $kode_matkul, $ruangan, $dosen_nip, $semester, $tahun_ajaran);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Jadwal mengajar berhasil ditambahkan";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-mengajar.php");
    exit();
}

// Edit jadwal mengajar process
if (isset($_POST['edit_jadwal'])) {
    $id = (int)clean_input($_POST['id']);
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $ruangan = clean_input($_POST['ruangan']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($kode_matkul) || 
        empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Verify kode_matkul exists
    $verify_matkul_sql = "SELECT COUNT(*) FROM mata_kuliah WHERE kode_matkul = ?";
    $verify_matkul_stmt = $conn->prepare($verify_matkul_sql);
    $verify_matkul_stmt->bind_param("s", $kode_matkul);
    $verify_matkul_stmt->execute();
    $verify_matkul_stmt->bind_result($matkul_exists);
    $verify_matkul_stmt->fetch();
    $verify_matkul_stmt->close();

    if (!$matkul_exists) {
        $_SESSION['error_message'] = "Mata kuliah yang dipilih tidak valid";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Verify ownership of this jadwal
    $check_ownership_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $check_ownership_stmt->bind_result($is_owner);
    $check_ownership_stmt->fetch();
    $check_ownership_stmt->close();
    
    if ($is_owner == 0) {
        $_SESSION['error_message'] = "Anda tidak memiliki izin untuk mengedit jadwal ini";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                    (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                    (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssissssss", $dosen_nip, $hari, $semester, $tahun_ajaran, $id,
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Anda sudah memiliki jadwal mengajar pada hari dan waktu yang sama";
        header("Location: jadwal-mengajar.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                         (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                         (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssissssss", $ruangan, $hari, $semester, $tahun_ajaran, $id,
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwal-mengajar.php");
            exit();
        }
    }

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "UPDATE jadwal_kuliah SET 
                hari = ?, 
                waktu_mulai = ?, 
                waktu_selesai = ?, 
                kode_matkul = ?, 
                ruangan = ?, 
                semester = ?, 
                tahun_ajaran = ? 
                WHERE id = ? AND dosen_nip = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssss", $hari, $waktu_mulai, $waktu_selesai, $kode_matkul, $ruangan, $semester, $tahun_ajaran, $id, $dosen_nip);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Jadwal mengajar berhasil diperbarui";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-mengajar.php");
    exit();
}

// Delete jadwal mengajar process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    // Verify ownership of this jadwal
    $check_ownership_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $check_ownership_stmt = $conn->prepare($check_ownership_sql);
    $check_ownership_stmt->bind_param("is", $id, $dosen_nip);
    $check_ownership_stmt->execute();
    $check_ownership_stmt->bind_result($is_owner);
    $check_ownership_stmt->fetch();
    $check_ownership_stmt->close();
    
    if ($is_owner == 0) {
        $_SESSION['error_message'] = "Anda tidak memiliki izin untuk menghapus jadwal ini";
        header("Location: jadwal-mengajar.php");
        exit();
    }
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "DELETE FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("is", $id, $dosen_nip);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Jadwal mengajar berhasil dihapus";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwal-mengajar.php");
    exit();
}

// Get mata kuliah data
$matkul_sql = "SELECT kode_matkul, nama_matkul FROM mata_kuliah ORDER BY nama_matkul ASC";
$matkul_result = $conn->query($matkul_sql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records for pagination for current dosen only
$count_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE dosen_nip = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("s", $dosen_nip);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Filter by semester and tahun_ajaran
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = ["jk.dosen_nip = ?"]; // Always filter by current dosen
$params = [$dosen_nip];
$param_types = 's';

if (!empty($search)) {
    $where_clauses[] = "(mk.nama_matkul LIKE ? OR jk.ruangan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($filter_semester)) {
    $where_clauses[] = "jk.semester = ?";
    $params[] = $filter_semester;
    $param_types .= 's';
}

if (!empty($filter_tahun)) {
    $where_clauses[] = "jk.tahun_ajaran = ?";
    $params[] = $filter_tahun;
    $param_types .= 's';
}

$where_clause = "WHERE " . implode(' AND ', $where_clauses);

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT tahun_ajaran FROM jadwal_kuliah WHERE dosen_nip = ? ORDER BY tahun_ajaran DESC";
$tahun_stmt = $conn->prepare($tahun_sql);
$tahun_stmt->bind_param("s", $dosen_nip);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();
$tahun_stmt->close();

// Get data with joins for detailed information
$sql = "SELECT jk.*, mk.nama_matkul
        FROM jadwal_kuliah jk
        LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
        $where_clause
        ORDER BY jk.hari ASC, jk.waktu_mulai ASC
        LIMIT ?, ?";

// Prepare statement with dynamic parameters
$stmt = $conn->prepare($sql);

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

// Bind parameters
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Also update the count for pagination if filters are applied
if (count($where_clauses) > 1) {
    $count_sql = "SELECT COUNT(*) 
                  FROM jadwal_kuliah jk
                  LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
                  $where_clause";
    
    $count_stmt = $conn->prepare($count_sql);
    
    // Reset param types to exclude pagination parameters
    $count_param_types = substr($param_types, 0, -2);
    
    // Remove pagination parameters
    array_pop($params);
    array_pop($params);
    
    $count_stmt->bind_param($count_param_types, ...$params);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}

// Get statistics
// 1. Total jam mengajar per minggu
$stats_sql = "SELECT 
                SUM(TIME_TO_SEC(TIMEDIFF(waktu_selesai, waktu_mulai))/3600) as total_jam
              FROM jadwal_kuliah 
              WHERE dosen_nip = ? AND semester = ? AND tahun_ajaran = ?";

$active_semester = !empty($filter_semester) ? $filter_semester : 'Ganjil'; // Default to current semester
$active_tahun = !empty($filter_tahun) ? $filter_tahun : date('Y') . '/' . (date('Y')+1); // Default to current year

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("sss", $dosen_nip, $active_semester, $active_tahun);
$stats_stmt->execute();
$stats_stmt->bind_result($total_jam);
$stats_stmt->fetch();
$stats_stmt->close();

// 2. Total mata kuliah yang diajar
$matkul_count_sql = "SELECT COUNT(DISTINCT kode_matkul) as total_matkul
                      FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND semester = ? AND tahun_ajaran = ?";
$matkul_count_stmt = $conn->prepare($matkul_count_sql);
$matkul_count_stmt->bind_param("sss", $dosen_nip, $active_semester, $active_tahun);
$matkul_count_stmt->execute();
$matkul_count_stmt->bind_result($total_matkul);
$matkul_count_stmt->fetch();
$matkul_count_stmt->close();

// 3. Hari tersibuk (with most classes)
$busy_day_sql = "SELECT 
                  hari, 
                  COUNT(*) as total_kelas
                FROM jadwal_kuliah 
                WHERE dosen_nip = ? AND semester = ? AND tahun_ajaran = ?
                GROUP BY hari
                ORDER BY total_kelas DESC
                LIMIT 1";
$busy_day_stmt = $conn->prepare($busy_day_sql);
$busy_day_stmt->bind_param("sss", $dosen_nip, $active_semester, $active_tahun);
$busy_day_stmt->execute();
$busy_day_result = $busy_day_stmt->get_result();
$busy_day = $busy_day_result->fetch_assoc();
$busy_day_stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar - Portal Dosen</title>
    
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0 text-primary">Jadwal Mengajar</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Jadwal
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4 fade-in">
                <div class="col-md-4">
                    <div class="card stats-card bg-white mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Jam Mengajar</h6>
                                    <h3 class="mb-0"><?php echo number_format($total_jam ?? 0, 1);?> jam/minggu</h3>
                                </div>
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-white mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Mata Kuliah Diajar</h6>
                                    <h3 class="mb-0"><?php echo $total_matkul ?? 0; ?> matkul</h3>
                                </div>
                                <div class="stats-icon bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-book-open"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card bg-white mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Hari Tersibuk</h6>
                                    <h3 class="mb-0"><?php echo isset($busy_day['hari']) ? $busy_day['hari'] : '-'; ?></h3>
                                </div>
                                <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
            
        
        <!-- Filter Form -->
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cari</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Mata kuliah atau ruangan" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="semester" class="form-label">Semester</label>
                        <select class="form-select" id="semester" name="semester">
                            <option value="">Semua</option>
                            <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                            <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                        <select class="form-select" id="tahun_ajaran" name="tahun_ajaran">
                            <option value="">Semua</option>
                            <?php while ($tahun = $tahun_result->fetch_assoc()): ?>
                                <option value="<?php echo $tahun['tahun_ajaran']; ?>" <?php echo ($filter_tahun == $tahun['tahun_ajaran']) ? 'selected' : ''; ?>>
                                    <?php echo $tahun['tahun_ajaran']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Jadwal Table -->
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Kuliah</th>
                                <th>Jadwal</th>
                                <th>Ruangan</th>
                                <th>Semester</th>
                                <th>Tahun Ajaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php $no = $offset + 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="fade-in">
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['kode_matkul']); ?></small>
                                        </td>
                                        <td>
                                            <div class="jadwal-detail">
                                                <i class="fas fa-calendar-day"></i>
                                                <span><?php echo htmlspecialchars($row['hari']); ?></span>
                                            </div>
                                            <div class="jadwal-detail">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo htmlspecialchars($row['waktu_mulai'] . ' - ' . $row['waktu_selesai']); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo !empty($row['ruangan']) ? htmlspecialchars($row['ruangan']) : '-'; ?></td>
                                        <td>
                                            <span class="badge bg-primary badge-custom"><?php echo htmlspecialchars($row['semester']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['tahun_ajaran']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal for each row -->
                                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit Jadwal Mengajar</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label for="hari" class="form-label">Hari</label>
                                                                <select class="form-select" id="hari" name="hari" required>
                                                                    <option value="Senin" <?php echo ($row['hari'] == 'Senin') ? 'selected' : ''; ?>>Senin</option>
                                                                    <option value="Selasa" <?php echo ($row['hari'] == 'Selasa') ? 'selected' : ''; ?>>Selasa</option>
                                                                    <option value="Rabu" <?php echo ($row['hari'] == 'Rabu') ? 'selected' : ''; ?>>Rabu</option>
                                                                    <option value="Kamis" <?php echo ($row['hari'] == 'Kamis') ? 'selected' : ''; ?>>Kamis</option>
                                                                    <option value="Jumat" <?php echo ($row['hari'] == 'Jumat') ? 'selected' : ''; ?>>Jumat</option>
                                                                    <option value="Sabtu" <?php echo ($row['hari'] == 'Sabtu') ? 'selected' : ''; ?>>Sabtu</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="kode_matkul" class="form-label">Mata Kuliah</label>
                                                                <select class="form-select" id="kode_matkul" name="kode_matkul" required>
                                                                    <?php $matkul_result->data_seek(0); // Reset pointer ?>
                                                                    <?php while ($matkul = $matkul_result->fetch_assoc()): ?>
                                                                        <option value="<?php echo $matkul['kode_matkul']; ?>" <?php echo ($row['kode_matkul'] == $matkul['kode_matkul']) ? 'selected' : ''; ?>>
                                                                            <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                                                        </option>
                                                                    <?php endwhile; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label for="waktu_mulai" class="form-label">Waktu Mulai</label>
                                                                <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" value="<?php echo htmlspecialchars($row['waktu_mulai']); ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="waktu_selesai" class="form-label">Waktu Selesai</label>
                                                                <input type="time" class="form-control" id="waktu_selesai" name="waktu_selesai" value="<?php echo htmlspecialchars($row['waktu_selesai']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label for="ruangan" class="form-label">Ruangan</label>
                                                                <input type="text" class="form-control" id="ruangan" name="ruangan" value="<?php echo htmlspecialchars($row['ruangan']); ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="semester" class="form-label">Semester</label>
                                                                <select class="form-select" id="semester" name="semester" required>
                                                                    <option value="Ganjil" <?php echo ($row['semester'] == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                                                    <option value="Genap" <?php echo ($row['semester'] == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                                                <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" value="<?php echo htmlspecialchars($row['tahun_ajaran']); ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_jadwal" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr class="fade-in">
                                    <td colspan="7" class="text-center text-muted py-4">Tidak ada jadwal mengajar yang ditemukan</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&semester=<?php echo urlencode($filter_semester); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Tambah Jadwal Modal -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahModalLabel">Tambah Jadwal Mengajar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hari" class="form-label">Hari</label>
                            <select class="form-select" id="hari" name="hari" required>
                                <option value="">Pilih Hari</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                                <option value="Sabtu">Sabtu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="kode_matkul" class="form-label">Mata Kuliah</label>
                            <select class="form-select" id="kode_matkul" name="kode_matkul" required>
                                <option value="">Pilih Mata Kuliah</option>
                                <?php $matkul_result->data_seek(0); // Reset pointer ?>
                                <?php while ($matkul = $matkul_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>">
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="waktu_mulai" class="form-label">Waktu Mulai</label>
                            <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
                        </div>
                        <div class="col-md-6">
                            <label for="waktu_selesai" class="form-label">Waktu Selesai</label>
                            <input type="time" class="form-control" id="waktu_selesai" name="waktu_selesai" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ruangan" class="form-label">Ruangan</label>
                            <input type="text" class="form-control" id="ruangan" name="ruangan">
                        </div>
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester" required>
                                <option value="Ganjil">Ganjil</option>
                                <option value="Genap">Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                            <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" value="<?php echo date('Y') . '/' . (date('Y')+1); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_jadwal" class="btn btn-primary">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom Script -->
<script>
    // Auto close alert after 5 seconds
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Reset modal when closed
        $('#tambahModal').on('hidden.bs.modal', function () {
            $(this).find('form').trigger('reset');
        });
        
        // Time validation
        $('form').on('submit', function() {
            const start = $('#waktu_mulai').val();
            const end = $('#waktu_selesai').val();
            
            if (start && end && start >= end) {
                alert('Waktu mulai harus lebih awal dari waktu selesai');
                return false;
            }
            return true;
        });

        // Add animation to table rows
        $('tr.fade-in').each(function(i) {
            $(this).delay(50 * i).animate({
                opacity: 1
            }, 200);
        });
    });
</script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>