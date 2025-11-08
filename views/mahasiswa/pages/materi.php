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

// Verify the user is a student and get their NIM
$verify_mahasiswa_sql = "SELECT m.id, m.nim FROM mahasiswa m 
                        JOIN users u ON m.user_id = u.id 
                        WHERE u.id = ? AND u.role = 'mahasiswa'";
$verify_mahasiswa_stmt = $conn->prepare($verify_mahasiswa_sql);
$verify_mahasiswa_stmt->bind_param("i", $user_id);
$verify_mahasiswa_stmt->execute();
$verify_mahasiswa_result = $verify_mahasiswa_stmt->get_result();
$mahasiswa_data = $verify_mahasiswa_result->fetch_assoc();
$verify_mahasiswa_stmt->close();

if (!$mahasiswa_data) {
    $_SESSION['error_message'] = "Anda tidak memiliki akses sebagai mahasiswa";
    header("Location: materi.php");
    exit();
}

$mahasiswa_id = $mahasiswa_data['id'];
$mahasiswa_nim = $mahasiswa_data['nim'];

$matkul_mahasiswa_sql = "SELECT DISTINCT jk.kode_matkul, mk.nama_matkul, mk.sks, d.nama as nama_dosen
                        FROM krs k
                        JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id
                        JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
                        JOIN dosen d ON jk.dosen_nip = d.nip
                        JOIN mahasiswa m ON k.mahasiswa_id = m.id
                        WHERE m.nim = ? AND k.status = 'aktif'
                        ORDER BY mk.nama_matkul ASC";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filter functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_matkul = isset($_GET['matkul']) ? clean_input($_GET['matkul']) : '';
$filter_jenis = isset($_GET['jenis']) ? clean_input($_GET['jenis']) : '';
$filter_pertemuan = isset($_GET['pertemuan']) ? clean_input($_GET['pertemuan']) : '';

// Build where clause for filters - only show materials from courses the student is enrolled in
$where_clauses = ["mp.kode_matkul IN (
    SELECT DISTINCT jk.kode_matkul 
    FROM krs k 
    JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
    WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
)"];
$params = [$mahasiswa_id];
$param_types = 'i';

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

if (!empty($filter_pertemuan)) {
    $where_clauses[] = "mp.pertemuan_ke = ?";
    $params[] = (int)$filter_pertemuan;
    $param_types .= 'i';
}

$where_clause = "WHERE " . implode(' AND ', $where_clauses);

// Count total records for pagination
$count_sql = "SELECT COUNT(*) 
              FROM materi_perkuliahan mp
              LEFT JOIN mata_kuliah mk ON mp.kode_matkul = mk.kode_matkul
              $where_clause";

if ($count_stmt = $conn->prepare($count_sql)) {
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    
    if ($count_stmt->execute()) {
        $count_stmt->bind_result($total_rows);
        $count_stmt->fetch();
        $total_pages = ceil($total_rows / $per_page);
    } else {
        // Handle error
        error_log("Error executing count query: " . $count_stmt->error);
        $total_pages = 1; // Default value
    }
    $count_stmt->close();
} else {
    // Handle prepare error
    error_log("Error preparing count query: " . $conn->error);
    $total_pages = 1; // Default value
}

// Get statistics for mahasiswa
// 1. Total materi available
$stats_total_sql = "SELECT COUNT(*) as total_materi 
                   FROM materi_perkuliahan mp
                   WHERE mp.kode_matkul IN (
                       SELECT DISTINCT jk.kode_matkul 
                       FROM krs k 
                       JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                       WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                   )";
$stats_total_stmt = $conn->prepare($stats_total_sql);
$stats_total_stmt->bind_param("i", $mahasiswa_id);
$stats_total_stmt->execute();
$stats_total_stmt->bind_result($total_materi_available);
$stats_total_stmt->fetch();
$stats_total_stmt->close();

// 2. Total mata kuliah with materials (versi diperbaiki)
$matkul_with_materi_sql = "SELECT COUNT(DISTINCT mp.kode_matkul) as total_matkul 
                          FROM materi_perkuliahan mp
                          WHERE mp.kode_matkul IN (
                              SELECT DISTINCT jk.kode_matkul 
                              FROM krs k 
                              JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                              WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                          )";
$matkul_with_materi_stmt = $conn->prepare($matkul_with_materi_sql);
$matkul_with_materi_stmt->bind_param("i", $mahasiswa_id);
$matkul_with_materi_stmt->execute();
$matkul_with_materi_stmt->bind_result($total_matkul_with_materi);
$matkul_with_materi_stmt->fetch();
$matkul_with_materi_stmt->close();

// 3. Most recent upload
$recent_upload_sql = "SELECT mp.judul, mp.tanggal_upload, mk.nama_matkul 
                     FROM materi_perkuliahan mp
                     LEFT JOIN mata_kuliah mk ON mp.kode_matkul = mk.kode_matkul
                     WHERE mp.kode_matkul IN (
                         SELECT DISTINCT jk.kode_matkul 
                         FROM krs k 
                         JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                         WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                     )
                     ORDER BY mp.tanggal_upload DESC LIMIT 1";
$recent_upload_stmt = $conn->prepare($recent_upload_sql);
$recent_upload_stmt->bind_param("i", $mahasiswa_id);
$recent_upload_stmt->execute();
$recent_result = $recent_upload_stmt->get_result();
$recent_upload = $recent_result->fetch_assoc();
$recent_upload_stmt->close();

// Query utama untuk menampilkan materi
$materi_sql = "SELECT mp.*, mk.nama_matkul, d.nama as nama_dosen 
              FROM materi_perkuliahan mp
              LEFT JOIN mata_kuliah mk ON mp.kode_matkul = mk.kode_matkul
              LEFT JOIN dosen d ON mp.dosen_nip = d.nip
              $where_clause
              ORDER BY mp.tanggal_upload DESC
              LIMIT $offset, $per_page";

$stmt = $conn->prepare($materi_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Query untuk mata kuliah filter (harus dieksekusi sebelum form filter)
$matkul_mahasiswa_stmt = $conn->prepare($matkul_mahasiswa_sql);
$matkul_mahasiswa_stmt->bind_param("s", $mahasiswa_nim);
$matkul_mahasiswa_stmt->execute();
$matkul_mahasiswa_result = $matkul_mahasiswa_stmt->get_result();

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

// Download handler
if (isset($_GET['download']) && isset($_GET['id'])) {
    $materi_id = (int)clean_input($_GET['id']);
    
    // Verify student has access to this material
    $download_sql = "SELECT mp.file_path, mp.file_name 
                    FROM materi_perkuliahan mp
                    WHERE mp.id = ? AND mp.kode_matkul IN (
                        SELECT DISTINCT jk.kode_matkul 
                        FROM krs k 
                        JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id 
                        WHERE k.mahasiswa_id = ? AND k.status = 'aktif'
                    )";
    $download_stmt = $conn->prepare($download_sql);
    $download_stmt->bind_param("ii", $materi_id, $mahasiswa_id);
    $download_stmt->execute();
    $download_result = $download_stmt->get_result();
    $download_data = $download_result->fetch_assoc();
    $download_stmt->close();
    
    if ($download_data && file_exists($download_data['file_path'])) {
        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($download_data['file_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($download_data['file_path']));
        
        // Output file
        readfile($download_data['file_path']);
        exit();
    } else {
        $_SESSION['error_message'] = "File tidak ditemukan atau Anda tidak memiliki akses";
        header("Location: materi.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Perkuliahan - Portal Mahasiswa</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
     <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
            /* Educational Theme Colors */
:root {
    --edu-primary: #2c5282;
    --edu-secondary: #4299e1;
    --edu-accent: #ebf8ff;
    --edu-dark: #2d3748;
    --edu-gray: #718096;
    --edu-light: #f7fafc;
}

/* Card Animations */
.fade-up {
    animation: fadeUp 0.6s ease-out;
    opacity: 0;
    animation-fill-mode: forwards;
}

@keyframes fadeUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Enhanced Stats Cards */
.stats-card {
    background: linear-gradient(135deg, #fff, var(--edu-light));
    border: none;
    border-radius: 16px;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stats-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: var(--edu-accent);
    color: var(--edu-primary);
}

/* Material Cards */
.materi-card {
    border: none;
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
    background: white;
}

.materi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.materi-header {
    background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
    color: white;
    padding: 1.5rem;
    border: none;
}

.materi-meta {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
    color: var(--edu-gray);
}

.materi-meta i {
    width: 24px;
    margin-right: 8px;
}

/* File Download Section */
.file-download {
    background: var(--edu-light);
    border-radius: 12px;
    padding: 1rem;
    margin-top: 1rem;
}

.file-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 8px;
    margin-right: 1rem;
}

/* Search and Filter Section */
.search-card {
    background: white;
    border-radius: 16px;
    border: none;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 0.75rem 1rem;
}

.form-control:focus, .form-select:focus {
    border-color: var(--edu-secondary);
    box-shadow: 0 0 0 3px rgba(1, 74, 29, 0.15);
}

/* Pagination */
.pagination .page-link {
    border: none;
    margin: 0 4px;
    border-radius: 8px;
    color: var(--edu-dark);
    padding: 0.75rem 1rem;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-state img {
    max-width: 200px;
    margin-bottom: 2rem;
}
    </style>
</head>
<body>
  <!-- includes/mahasiswa-sidebar.php -->
<div class="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
            <span class="fw-bold">U</span>
        </div>
        <span class="sidebar-brand">PORTALSIA</span>
    </div>
    
    <div class="menu-category">MENU MAHASISWA</div>
    <div class="menu">
        <a href="dashboard-mahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard-mahasiswa.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="profil-mahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profil-mahasiswa.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profil Mahasiswa</span>
        </a>
        <a href="jadwal-kuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'jadwal-kuliah.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Jadwal Kuliah</span>
        </a>
        <a href="krs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'krs.php' ? 'active' : ''; ?>">
            <i class="fas fa-edit"></i>
            <span>KRS</span>
        </a>
        <a href="nilai.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'nilai.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>KHS</span>
        </a>
        <a href="materi.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materi.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Materi Kuliah</span>
        </a>
        <a href="ukt.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'ukt.php' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Pembayaran UKT</span>
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
                <div>
                    <h2 class="mb-1">Materi Perkuliahan</h2>
                    <p class="text-muted">Akses materi kuliah dari mata kuliah yang Anda ambil</p>
                </div>
                <div class="text-end">
                    <small class="text-muted">Total: <?php echo $total_materi_available; ?> materi</small>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card fade-in">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="fas fa-book-open"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Total Materi</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_materi_available; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card fade-in" style="animation-delay: 0.1s">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Mata Kuliah</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo $total_matkul_with_materi; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card fade-in" style="animation-delay: 0.2s">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-info bg-opacity-10 text-info me-3">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 text-muted">Terbaru</h6>
                                    <p class="mb-0 fw-bold">
                                        <?php echo $recent_upload ? date('d M Y', strtotime($recent_upload['tanggal_upload'])) : 'Belum ada'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card search-card mb-4 fade-in" style="animation-delay: 0.3s">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label fw-semibold">Cari Materi</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="search" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" placeholder="Judul materi atau deskripsi">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label for="matkul" class="form-label fw-semibold">Mata Kuliah</label>
                                <select class="form-select" id="matkul" name="matkul">
                                    <option value="">Semua Mata Kuliah</option>
                                    <?php while ($matkul = $matkul_mahasiswa_result->fetch_assoc()): ?>
                                    <option value="<?php echo $matkul['kode_matkul']; ?>" 
                                        <?php echo ($filter_matkul == $matkul['kode_matkul']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="jenis" class="form-label fw-semibold">Jenis Materi</label>
                                <select class="form-select" id="jenis" name="jenis">
                                    <option value="">Semua Jenis</option>
                                    <option value="Slide" <?php echo ($filter_jenis == 'Slide') ? 'selected' : ''; ?>>Slide</option>
                                    <option value="Dokumen" <?php echo ($filter_jenis == 'Dokumen') ? 'selected' : ''; ?>>Dokumen</option>
                                    <option value="Video" <?php echo ($filter_jenis == 'Video') ? 'selected' : ''; ?>>Video</option>
                                    <option value="Lainnya" <?php echo ($filter_jenis == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="pertemuan" class="form-label fw-semibold">Pertemuan</label>
                                <select class="form-select" id="pertemuan" name="pertemuan">
                                    <option value="">Semua</option>
                                    <?php for($i = 1; $i <= 16; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($filter_pertemuan == $i) ? 'selected' : ''; ?>>
                                        Pertemuan <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Materials List -->
            <div class="row fade-in" style="animation-delay: 0.4s">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($materi = $result->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card materi-card h-100">
                            <div class="card-header materi-header py-3">
                                <h5 class="mb-0 text-truncate"><?php echo htmlspecialchars($materi['judul']); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="materi-meta">
                                    <i class="fas fa-book text-primary"></i>
                                    <span><?php echo htmlspecialchars($materi['nama_matkul']); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-user-tie text-secondary"></i>
                                    <span><?php echo htmlspecialchars($materi['nama_dosen']); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-calendar-alt text-info"></i>
                                    <span><?php echo date('d M Y', strtotime($materi['tanggal_upload'])); ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-layer-group text-warning"></i>
                                    <span>Pertemuan <?php echo $materi['pertemuan_ke']; ?></span>
                                </div>
                                <div class="materi-meta">
                                    <i class="fas fa-tag text-success"></i>
                                    <span class="badge bg-light text-dark"><?php echo $materi['jenis_materi']; ?></span>
                                </div>
                                
                                <p class="mt-3 mb-4"><?php echo nl2br(htmlspecialchars($materi['deskripsi'])); ?></p>
                                
                                <?php if (!empty($materi['file_path'])): ?>
                                <div class="file-download d-flex align-items-center">
                                    <div class="file-icon">
                                        <i class="<?php echo getFileIcon($materi['file_name']); ?>"></i>
                                    </div>
                                    <div class="file-info flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($materi['file_name']); ?></h6>
                                        <small class="text-muted"><?php echo formatFileSize($materi['file_size']); ?></small>
                                    </div>
                                    <div class="file-action">
                                        <a href="materi.php?download=1&id=<?php echo $materi['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <img src="../../../assets/img/empty-state.svg" alt="No data" style="height: 150px;" class="mb-4">
                                <h4 class="text-muted">Tidak ada materi ditemukan</h4>
                                <p class="text-muted">Coba gunakan kata kunci atau filter yang berbeda</p>
                                <a href="materi.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-sync-alt me-2"></i>Reset Pencarian
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4 fade-in" style="animation-delay: 0.5s">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&matkul=<?php echo urlencode($filter_matkul); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&pertemuan=<?php echo urlencode($filter_pertemuan); ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Animate cards on scroll
            function animateCards() {
                $('.fade-up').each(function(i) {
                    var bottom_of_object = $(this).offset().top + $(this).outerHeight();
                    var bottom_of_window = $(window).scrollTop() + $(window).height();
                    
                    if (bottom_of_window > bottom_of_object) {
                        $(this).css('animation-delay', (i * 0.1) + 's');
                        $(this).addClass('show');
                    }
                });
            }
            
            // Initial animation
            animateCards();
            
            // Animate on scroll
            $(window).scroll(function() {
                animateCards();
            });

            // Auto hide alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>
<?php
// Close database connection
$stmt->close();
$conn->close();
?>