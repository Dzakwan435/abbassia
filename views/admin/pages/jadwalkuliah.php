<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/../../../include/config.php';
require_once __DIR__.'/../../../include/jadwalkuliah_functions.php';

// Get mata kuliah data
$matkul_sql = "SELECT kode_matkul, nama_matkul FROM mata_kuliah ORDER BY nama_matkul ASC";
$matkul_result = $conn->query($matkul_sql);

// Get dosen data
$dosen_sql = "SELECT nip, nama FROM dosen ORDER BY nama ASC";
$dosen_result = $conn->query($dosen_sql);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM jadwal_kuliah";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Search functionality
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

// Filter by semester and tahun_ajaran
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_clauses[] = "(mk.nama_matkul LIKE ? OR d.nama LIKE ? OR jk.ruangan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
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

$where_clause = '';
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(' AND ', $where_clauses);
}

// Get unique tahun ajaran for filter dropdown
$tahun_sql = "SELECT DISTINCT tahun_ajaran FROM jadwal_kuliah ORDER BY tahun_ajaran DESC";
$tahun_result = $conn->query($tahun_sql);

// Get data with joins for detailed information
$sql = "SELECT jk.*, mk.nama_matkul, d.nama as nama_dosen
        FROM jadwal_kuliah jk
        LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
        LEFT JOIN dosen d ON jk.dosen_nip = d.nip
        $where_clause
        ORDER BY jk.hari ASC, jk.waktu_mulai ASC
        LIMIT ?, ?";

// Prepare statement with dynamic parameters
$stmt = $conn->prepare($sql);

// Add parameters for pagination
$params[] = $offset;
$params[] = $per_page;
$param_types .= 'ii';

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Also update the count for pagination if filters are applied
if (!empty($where_clause)) {
    $count_sql = "SELECT COUNT(*) 
                  FROM jadwal_kuliah jk
                  LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
                  LEFT JOIN dosen d ON jk.dosen_nip = d.nip
                  $where_clause";
    
    $count_stmt = $conn->prepare($count_sql);
    
    // Reset param types to exclude pagination parameters
    $count_param_types = substr($param_types, 0, -2);
    
    // Remove pagination parameters
    array_pop($params);
    array_pop($params);
    
    if (!empty($params)) {
        $count_stmt->bind_param($count_param_types, ...$params);
    }
    
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Kuliah - Portal Akademik</title>
    
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
                <h2 class="mb-0 text-primary">Jadwal Kuliah</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Jadwal
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
            
            <!-- Search and Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Cari mata kuliah, dosen, ruangan..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-1"></i> Cari
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="semester">
                                <option value="">-- Pilih Semester --</option>
                                <option value="Ganjil" <?php if($filter_semester == 'Ganjil') echo 'selected'; ?>>Ganjil</option>
                                <option value="Genap" <?php if($filter_semester == 'Genap') echo 'selected'; ?>>Genap</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="tahun_ajaran">
                                <option value="">-- Pilih Tahun Ajaran --</option>
                                <?php while($tahun = $tahun_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>" <?php if($filter_tahun == $tahun['tahun_ajaran']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search) || !empty($filter_semester) || !empty($filter_tahun)): ?>
                        <div class="col-12 mt-2">
                            <a href="jadwalkuliah.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times me-1"></i> Reset Filter
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Data Card -->
            <div class="card mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Hari</th>
                                    <th>Waktu</th>
                                    <th>Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th>Ruangan</th>
                                    <th>Semester/TA</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['hari']); ?></td>
                                        <td>
                                            <?php 
                                                $waktu_mulai = date("H:i", strtotime($row['waktu_mulai']));
                                                $waktu_selesai = date("H:i", strtotime($row['waktu_selesai']));
                                                echo $waktu_mulai . ' - ' . $waktu_selesai; 
                                            ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['kode_matkul']); ?></div>
                                            <?php echo htmlspecialchars($row['nama_matkul']); ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($row['dosen_nip']); ?></div>
                                            <?php echo htmlspecialchars($row['nama_dosen']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['ruangan']); ?></td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary badge-custom">
                                                <?php echo htmlspecialchars($row['semester']); ?>
                                            </span>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary badge-custom">
                                                <?php echo htmlspecialchars($row['tahun_ajaran']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex">
                                                <button class="btn btn-sm btn-outline-primary me-2 edit-btn" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-hari="<?php echo htmlspecialchars($row['hari']); ?>"
                                                        data-waktu-mulai="<?php echo htmlspecialchars($row['waktu_mulai']); ?>"
                                                        data-waktu-selesai="<?php echo htmlspecialchars($row['waktu_selesai']); ?>"
                                                        data-kode-matkul="<?php echo htmlspecialchars($row['kode_matkul']); ?>"
                                                        data-ruangan="<?php echo htmlspecialchars($row['ruangan']); ?>"
                                                        data-dosen-nip="<?php echo htmlspecialchars($row['dosen_nip']); ?>"
                                                        data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                        data-tahun-ajaran="<?php echo htmlspecialchars($row['tahun_ajaran']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete_id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-calendar-times fa-2x mb-3"></i>
                                                <p class="mb-0">Tidak ada data jadwal kuliah ditemukan</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_semester) ? '&semester='.urlencode($filter_semester) : ''; ?><?php echo !empty($filter_tahun) ? '&tahun_ajaran='.urlencode($filter_tahun) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Tambah Jadwal Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="tambahModalLabel">Tambah Jadwal Kuliah</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="hari" class="form-label">Hari</label>
                                <select class="form-select" id="hari" name="hari" required>
                                    <option value="">-- Pilih Hari --</option>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="waktu_mulai" class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
                            </div>
                            <div class="col-md-3">
                                <label for="waktu_selesai" class="form-label">Waktu Selesai</label>
                                <input type="time" class="form-control" id="waktu_selesai" name="waktu_selesai" required>
                            </div>
                            <div class="col-md-6">
                                <label for="kode_matkul" class="form-label">Mata Kuliah</label>
                                <select class="form-select" id="kode_matkul" name="kode_matkul" required>
                                    <option value="">-- Pilih Mata Kuliah --</option>
                                    <?php while($matkul = $matkul_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($matkul['kode_matkul']); ?>">
                                            <?php echo htmlspecialchars($matkul['kode_matkul']); ?> - <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="dosen_nip" class="form-label">Dosen Pengajar</label>
                                <select class="form-select" id="dosen_nip" name="dosen_nip" required>
                                    <option value="">-- Pilih Dosen --</option>
                                    <?php 
                                    $dosen_result->data_seek(0); // Reset pointer to beginning
                                    while($dosen = $dosen_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($dosen['nip']); ?>">
                                            <?php echo htmlspecialchars($dosen['nip']); ?> - <?php echo htmlspecialchars($dosen['nama']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="ruangan" class="form-label">Ruangan</label>
                                <input type="text" class="form-control" id="ruangan" name="ruangan">
                            </div>
                            <div class="col-md-4">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="">-- Pilih Semester --</option>
                                    <option value="Ganjil">Ganjil</option>
                                    <option value="Genap">Genap</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="tambah_jadwal">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Jadwal Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="editModalLabel">Edit Jadwal Kuliah</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_hari" class="form-label">Hari</label>
                                <select class="form-select" id="edit_hari" name="hari" required>
                                    <option value="">-- Pilih Hari --</option>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_waktu_mulai" class="form-label">Waktu Mulai</label>
                                <input type="time" class="form-control" id="edit_waktu_mulai" name="waktu_mulai" required>
                            </div>
                            <div class="col-md-3">
                                <label for="edit_waktu_selesai" class="form-label">Waktu Selesai</label>
                                <input type="time" class="form-control" id="edit_waktu_selesai" name="waktu_selesai" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_kode_matkul" class="form-label">Mata Kuliah</label>
                                <select class="form-select" id="edit_kode_matkul" name="kode_matkul" required>
                                    <option value="">-- Pilih Mata Kuliah --</option>
                                    <?php 
                                    $matkul_result->data_seek(0); // Reset pointer to beginning
                                    while($matkul = $matkul_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($matkul['kode_matkul']); ?>">
                                            <?php echo htmlspecialchars($matkul['kode_matkul']); ?> - <?php echo htmlspecialchars($matkul['nama_matkul']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_dosen_nip" class="form-label">Dosen Pengajar</label>
                                <select class="form-select" id="edit_dosen_nip" name="dosen_nip" required>
                                    <option value="">-- Pilih Dosen --</option>
                                    <?php 
                                    $dosen_result->data_seek(0); // Reset pointer to beginning
                                    while($dosen = $dosen_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($dosen['nip']); ?>">
                                            <?php echo htmlspecialchars($dosen['nip']); ?> - <?php echo htmlspecialchars($dosen['nama']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_ruangan" class="form-label">Ruangan</label>
                                <input type="text" class="form-control" id="edit_ruangan" name="ruangan">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_semester" class="form-label">Semester</label>
                                <select class="form-select" id="edit_semester" name="semester" required>
                                    <option value="">-- Pilih Semester --</option>
                                    <option value="Ganjil">Ganjil</option>
                                    <option value="Genap">Genap</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                <input type="text" class="form-control" id="edit_tahun_ajaran" name="tahun_ajaran" placeholder="Contoh: 2023/2024" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" name="edit_jadwal">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../js/script.js"></script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>