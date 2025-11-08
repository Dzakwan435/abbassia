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

// Get logged-in student data
$user_id = $_SESSION['user_id'];
$mahasiswa_sql = "SELECT m.id, m.nim, m.nama FROM mahasiswa m WHERE m.user_id = ?";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);

if (!$mahasiswa_stmt) {
    die("Error prepare statement: " . $conn->error);
}

$mahasiswa_stmt->bind_param("i", $user_id);
$mahasiswa_stmt->execute();
$mahasiswa_result = $mahasiswa_stmt->get_result();

if ($mahasiswa_result->num_rows === 0) {
    die("Data mahasiswa tidak ditemukan untuk user ini");
}

$mahasiswa_data = $mahasiswa_result->fetch_assoc();
$mahasiswa_id = $mahasiswa_data['id'];
$mahasiswa_nim = $mahasiswa_data['nim'];
$mahasiswa_nama = $mahasiswa_data['nama'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
$search_param = '';

// Filter by semester and tahun_ajaran
$filter_semester = isset($_GET['semester']) ? $conn->real_escape_string($_GET['semester']) : '';
$filter_tahun = isset($_GET['tahun_ajaran']) ? $conn->real_escape_string($_GET['tahun_ajaran']) : '';

// Build where clause for filters
$where_clauses = [];
$params = [];
$param_types = '';

// Only show jadwal kuliah for courses the student is registered in
$where_clauses[] = "jk.id IN (SELECT k.jadwal_id FROM krs k WHERE k.mahasiswa_id = ? AND k.status = 'aktif')";
$params[] = $mahasiswa_id;
$param_types .= 'i';

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
$tahun_sql = "SELECT DISTINCT jk.tahun_ajaran 
              FROM jadwal_kuliah jk
              JOIN krs k ON jk.id = k.jadwal_id
              WHERE k.mahasiswa_id = ?
              ORDER BY jk.tahun_ajaran DESC";
$tahun_stmt = $conn->prepare($tahun_sql);
$tahun_stmt->bind_param("i", $mahasiswa_id);
$tahun_stmt->execute();
$tahun_result = $tahun_stmt->get_result();

// Get data with joins for detailed information
$sql = "SELECT jk.*, mk.nama_matkul, d.nama as nama_dosen
        FROM jadwal_kuliah jk
        LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
        LEFT JOIN dosen d ON jk.dosen_nip = d.nip
        $where_clause
        ORDER BY 
            CASE jk.hari 
                WHEN 'Senin' THEN 1
                WHEN 'Selasa' THEN 2
                WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4
                WHEN 'Jumat' THEN 5
                WHEN 'Sabtu' THEN 6
                ELSE 7
            END, 
            jk.waktu_mulai ASC
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
    <link rel="stylesheet" href="../../../css/styles.css">
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0 text-primary">Jadwal Kuliah</h2>
                <div class="text-muted">
                </div>
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
                            <a href="jadwal-kuliah.php" class="btn btn-sm btn-secondary">
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
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
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
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>