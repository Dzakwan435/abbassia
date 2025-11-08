<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../include/config.php';

// Verifikasi koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// PERBAIKAN: Ubah logika untuk mendapatkan NIP dosen
$current_user_id = $_SESSION['user_id'];
$current_dosen_nip = null;
$current_user_data = null;

// 1. Ambil data user terlebih dahulu
$user_sql = "SELECT id, username, role, nama FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);

if ($user_stmt !== false) {
    $user_stmt->bind_param("i", $current_user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $current_user_data = $user_result->fetch_assoc();
        
        // Verifikasi role dosen
        if ($current_user_data['role'] !== 'dosen') {
            $_SESSION['error_message'] = "Hanya dosen yang bisa mengakses halaman ini. Role Anda: " . $current_user_data['role'];
            header("Location: ../../login.php");
            exit();
        }
        
        // 2. PERBAIKAN: Cari data dosen berdasarkan user_id
        $dosen_sql = "SELECT nip, nama FROM dosen WHERE user_id = ?";
        $dosen_stmt = $conn->prepare($dosen_sql);
        
        if ($dosen_stmt !== false) {
            $dosen_stmt->bind_param("i", $current_user_id);
            $dosen_stmt->execute();
            $dosen_result = $dosen_stmt->get_result();
            
            if ($dosen_result->num_rows > 0) {
                $dosen_data = $dosen_result->fetch_assoc();
                $current_dosen_nip = $dosen_data['nip'];
            } else {
                // PERBAIKAN ALTERNATIF: Jika relasi user_id tidak ada, coba cari berdasarkan username = nip
                $dosen_alt_sql = "SELECT nip, nama FROM dosen WHERE nip = ?";
                $dosen_alt_stmt = $conn->prepare($dosen_alt_sql);
                
                if ($dosen_alt_stmt !== false) {
                    $dosen_alt_stmt->bind_param("s", $current_user_data['username']);
                    $dosen_alt_stmt->execute();
                    $dosen_alt_result = $dosen_alt_stmt->get_result();
                    
                    if ($dosen_alt_result->num_rows > 0) {
                        $dosen_alt_data = $dosen_alt_result->fetch_assoc();
                        $current_dosen_nip = $dosen_alt_data['nip'];
                        
                        // Update user_id di tabel dosen untuk konsistensi
                        $update_sql = "UPDATE dosen SET user_id = ? WHERE nip = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        if ($update_stmt !== false) {
                            $update_stmt->bind_param("is", $current_user_id, $current_dosen_nip);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                    $dosen_alt_stmt->close();
                }
            }
            $dosen_stmt->close();
        }
    } else {
        $_SESSION['error_message'] = "Data user tidak ditemukan";
        header("Location: ../../login.php");
        exit();
    }
    $user_stmt->close();
} else {
    $_SESSION['error_message'] = "Error dalam query database";
    header("Location: ../../login.php");
    exit();
}

// Jika masih tidak ditemukan NIP dosen
if (empty($current_dosen_nip)) {
    $_SESSION['error_message'] = "Data dosen tidak ditemukan untuk user ID: " . $current_user_id . 
                                ". Username: " . ($current_user_data['username'] ?? 'Unknown') . 
                                ". Silakan hubungi administrator.";
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Inisialisasi variabel untuk mencegah error
$result = null;
$total_pages = 0;
$total_rows = 0;
$mata_kuliah_result = null;

// Hanya ambil data mahasiswa jika NIP ditemukan
if (!empty($current_dosen_nip)) {
    // Ambil mata kuliah yang diampu dosen untuk filter
    $mk_sql = "SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul, j.hari, j.waktu_mulai, j.waktu_selesai 
               FROM jadwal_kuliah j
               JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
               WHERE j.dosen_nip = ?
               ORDER BY mk.nama_matkul ASC";
    $mk_stmt = $conn->prepare($mk_sql);
    $mk_stmt->bind_param("s", $current_dosen_nip);
    $mk_stmt->execute();
    $mata_kuliah_result = $mk_stmt->get_result();
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 15;
    $offset = ($page - 1) * $per_page;

    // Filter berdasarkan mata kuliah (opsional)
    $filter_jadwal = isset($_GET['jadwal_id']) ? intval($_GET['jadwal_id']) : '';
    $filter_condition = '';
    $filter_param = '';

    if (!empty($filter_jadwal)) {
        // Verifikasi apakah jadwal ini milik dosen yang login
        $verify_sql = "SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("is", $filter_jadwal, $current_dosen_nip);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $filter_condition = " AND krs.jadwal_id = ? ";
            $filter_param = $filter_jadwal;
        }
        $verify_stmt->close();
    }

    // Fungsi pencarian
    $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
    $search_condition = '';
    $search_param = '';

    if (!empty($search)) {
        $search_condition = " AND (m.nim LIKE ? OR m.nama LIKE ? OR m.email LIKE ?) ";
        $search_param = "%$search%";
    }

    // Query untuk menghitung total mahasiswa yang mengambil mata kuliah dari dosen
    $count_conditions = "";
    $count_params = array();
    $count_types = "";

    if (!empty($filter_condition) && !empty($search_condition)) {
        $count_conditions = $filter_condition . $search_condition;
        $count_params = array($current_dosen_nip, $filter_param, $search_param, $search_param, $search_param);
        $count_types = "sisss";
    } elseif (!empty($filter_condition)) {
        $count_conditions = $filter_condition;
        $count_params = array($current_dosen_nip, $filter_param);
        $count_types = "si";
    } elseif (!empty($search_condition)) {
        $count_conditions = $search_condition;
        $count_params = array($current_dosen_nip, $search_param, $search_param, $search_param);
        $count_types = "ssss";
    } else {
        $count_params = array($current_dosen_nip);
        $count_types = "s";
    }

    // UPDATED QUERY: Menggunakan tabel KRS untuk mencocokkan mahasiswa yang mengambil mata kuliah
    $count_sql = "SELECT COUNT(DISTINCT m.id) 
                  FROM mahasiswa m
                  JOIN krs ON m.id = krs.mahasiswa_id
                  JOIN jadwal_kuliah j ON krs.jadwal_id = j.id
                  WHERE j.dosen_nip = ? AND krs.status = 'aktif'" . $count_conditions;
    
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt !== false) {
        $count_stmt->bind_param($count_types, ...$count_params);
        $count_stmt->execute();
        $count_stmt->bind_result($total_rows);
        $count_stmt->fetch();
        $count_stmt->close();
        $total_pages = ceil($total_rows / $per_page);
    }

    // Query utama dengan join KRS untuk mendapatkan mahasiswa yang mengambil mata kuliah dari dosen
    $main_conditions = "";
    $main_params = array();
    $main_types = "";

    if (!empty($filter_condition) && !empty($search_condition)) {
        $main_conditions = $filter_condition . $search_condition;
        $main_params = array($current_dosen_nip, $filter_param, $search_param, $search_param, $search_param, $offset, $per_page);
        $main_types = "sisssii";
    } elseif (!empty($filter_condition)) {
        $main_conditions = $filter_condition;
        $main_params = array($current_dosen_nip, $filter_param, $offset, $per_page);
        $main_types = "siii";
    } elseif (!empty($search_condition)) {
        $main_conditions = $search_condition;
        $main_params = array($current_dosen_nip, $search_param, $search_param, $search_param, $offset, $per_page);
        $main_types = "ssssii";
    } else {
        $main_params = array($current_dosen_nip, $offset, $per_page);
        $main_types = "sii";
    }

    // UPDATED QUERY: Menggunakan tabel KRS sebagai penghubung
    $sql = "SELECT DISTINCT m.id, m.nim, m.nama, m.email, m.nohp as telepon, m.alamat, 
                   '' as tanggal_lahir, -- Tidak ada kolom tanggal_lahir di tabel mahasiswa
                   GROUP_CONCAT(DISTINCT mk.nama_matkul SEPARATOR ', ') as mata_kuliah_diambil,
                   COUNT(DISTINCT krs.jadwal_id) as jumlah_matkul
            FROM mahasiswa m
            JOIN krs ON m.id = krs.mahasiswa_id
            JOIN jadwal_kuliah j ON krs.jadwal_id = j.id
            JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
            WHERE j.dosen_nip = ? AND krs.status = 'aktif'" . $main_conditions . "
            GROUP BY m.id, m.nim, m.nama, m.email, m.nohp, m.alamat
            ORDER BY m.nama ASC 
            LIMIT ?, ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt !== false) {
        $stmt->bind_param($main_types, ...$main_params);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Mahasiswa - Portal Akademik</title>
    
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
                <div>
                    <h2 class="mb-0 text-primary">Daftar Mahasiswa</h2>
                    <p class="text-muted mb-0">Mahasiswa yang mengambil mata kuliah yang Anda ampu</p>
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

            <?php if (empty($current_dosen_nip)): ?>
                <div class="error-notice fade-in">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i> Akses Ditolak</h4>
                    <p class="mb-0">Anda tidak memiliki izin untuk mengakses halaman ini. Silakan hubungi administrator.</p>
                </div>
            <?php else: ?>

                <!-- Statistics Cards -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-graduate fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo $total_rows; ?></h4>
                                <small>Total Mahasiswa</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-2x mb-2"></i>
                                <h4 class="mb-0"><?php echo ($mata_kuliah_result) ? $mata_kuliah_result->num_rows : 0; ?></h4>
                                <small>Mata Kuliah Diampu</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter and Search -->
                <div class="card mb-4 fade-in">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter Mata Kuliah</label>
                                <select class="form-select" name="jadwal_id">
                                    <option value="">Semua Mata Kuliah</option>
                                    <?php if ($mata_kuliah_result): ?>
                                        <?php 
                                        // Reset pointer untuk iterasi kedua
                                        $mata_kuliah_result->data_seek(0);
                                        ?>
                                        <?php while ($mk = $mata_kuliah_result->fetch_assoc()): ?>
                                            <option value="<?php echo $mk['jadwal_id']; ?>" <?php echo (isset($_GET['jadwal_id']) && $_GET['jadwal_id'] == $mk['jadwal_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($mk['nama_matkul'] . ' - ' . $mk['hari'] . ' ' . date('H:i', strtotime($mk['waktu_mulai'])) . '-' . date('H:i', strtotime($mk['waktu_selesai']))); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cari Mahasiswa</label>
                                <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan NIM, nama, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Students List -->
                <div class="card fade-in">
                    <div class="card-body">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <div class="row">
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <div class="col-lg-6 col-xl-4 mb-4">
                                        <div class="card student-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                        <i class="fas fa-user-graduate"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($row['nama']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($row['nim']); ?></small>
                                                    </div>
                                                </div>
                                                
                                                <div class="student-info p-3 rounded mb-3">
                                                    <div class="row text-sm">
                                                        <div class="col-12 mb-2">
                                                            <i class="fas fa-envelope text-muted me-2"></i>
                                                            <small><?php echo htmlspecialchars($row['email'] ?? 'Tidak tersedia'); ?></small>
                                                        </div>
                                                        <div class="col-12 mb-2">
                                                            <i class="fas fa-phone text-muted me-2"></i>
                                                            <small><?php echo htmlspecialchars($row['telepon'] ?? 'Tidak tersedia'); ?></small>
                                                        </div>
                                                        <div class="col-12">
                                                            <i class="fas fa-map-marker-alt text-muted me-2"></i>
                                                            <small><?php echo htmlspecialchars(substr($row['alamat'] ?? 'Tidak tersedia', 0, 50)); ?><?php echo strlen($row['alamat'] ?? '') > 50 ? '...' : ''; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <small class="text-muted fw-bold">Mata Kuliah Diambil:</small>
                                                        <span class="badge bg-primary"><?php echo $row['jumlah_matkul']; ?> MK</span>
                                                    </div>
                                                    <div class="text-sm">
                                                        <?php
                                                        $mata_kuliah_list = explode(', ', $row['mata_kuliah_diambil']);
                                                        foreach ($mata_kuliah_list as $mk) {
                                                            echo '<span class="badge subject-badge me-1 mb-1">' . htmlspecialchars($mk) . '</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye me-1"></i>Detail
                                                    </button>
                                                    <a href="input-nilai.php?search=<?php echo urlencode($row['nim']);?>" class="btn btn-sm btn-primary flex-fill">
                                                        <i class="fas fa edit me-1"></i>input nilai
                                                    </a>
                                                    </div>
                                                    </div>
                                                    </div>
                                                    </div>
                                                                                    <!-- Detail Modal for each student -->
                                <div class="modal fade" id="detailModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Detail Mahasiswa</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-3">
                                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                                            <i class="fas fa-user-graduate fa-3x"></i>
                                                        </div>
                                                        <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($row['nama']); ?></h5>
                                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($row['nim']); ?></p>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Email</label>
                                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['email']); ?>" readonly>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Telepon</label>
                                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['telepon']); ?>" readonly>
                                                            </div>
                                                            <div class="col-12 mb-3">
                                                                <label class="form-label">Alamat</label>
                                                                <textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars($row['alamat']); ?></textarea>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label">Mata Kuliah yang Diambil</label>
                                                                <div class="p-2 bg-light rounded">
                                                                    <?php
                                                                    $mata_kuliah_list = explode(', ', $row['mata_kuliah_diambil']);
                                                                    foreach ($mata_kuliah_list as $mk) {
                                                                        echo '<span class="badge bg-primary me-1 mb-1">' . htmlspecialchars($mk) . '</span>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                <a href="input-nilai.php?search=<?php echo urlencode($row['nim']); ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit me-1"></i>Input Nilai
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&jadwal_id=<?php echo $filter_jadwal; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-graduate fa-4x text-muted mb-4"></i>
                            <h5>Tidak ada data mahasiswa</h5>
                            <p class="text-muted">Tidak ada mahasiswa yang mengambil mata kuliah yang Anda ampu</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom Script -->
<script>
    $(document).ready(function() {
        // Auto close alert after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
        
        // Tooltip initialization
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Student card hover effect
        $('.student-card').hover(
            function() {
                $(this).addClass('shadow-sm');
            },
            function() {
                $(this).removeClass('shadow-sm');
            }
        );
    });
</script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>