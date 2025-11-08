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

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
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

// Pagination data nilai
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fungsi pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?) ";
    $search_param = "%$search%";
}

// Hitung total record
$count_sql = "SELECT COUNT(*) 
              FROM data_nilai dn
              JOIN mahasiswa m ON dn.mahasiswa_id = m.id
              JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
              JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
              WHERE dn.mahasiswa_id = ?" . $search_condition;

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
    die("Error prepare count: " . $conn->error);
}

if (!empty($search)) {
    $count_stmt->bind_param("isss", $mahasiswa_id, $search_param, $search_param, $search_param);
} else {
    $count_stmt->bind_param("i", $mahasiswa_id);
}

$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total_rows / $per_page);

// Ambil data dengan join dan pagination
$sql = "SELECT dn.*, 
               m.nim, m.nama AS nama_mahasiswa, 
               mk.nama_matkul, mk.sks, 
               d.nama AS nama_dosen, 
               j.hari, j.waktu_mulai, j.waktu_selesai
        FROM data_nilai dn
        JOIN mahasiswa m ON dn.mahasiswa_id = m.id
        JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
        JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
        JOIN dosen d ON j.dosen_nip = d.nip
        WHERE dn.mahasiswa_id = ?" . $search_condition . "
        ORDER BY mk.nama_matkul ASC LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error prepare stmt: " . $conn->error);
}

if (!empty($search)) {
    $stmt->bind_param("isssii", $mahasiswa_id, $search_param, $search_param, $search_param, $offset, $per_page);
} else {
    $stmt->bind_param("iii", $mahasiswa_id, $offset, $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Nilai - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
:root {
    --edu-primary: #1a5632;
    --edu-secondary: #2e8b57;
    --edu-accent: #f8f9fa;
    --grade-a: linear-gradient(135deg, #28a745, #218838);
    --grade-b: linear-gradient(135deg, #17a2b8, #138496);
    --grade-c: linear-gradient(135deg, #ffc107, #d39e00);
    --grade-d: linear-gradient(135deg, #fd7e14, #dc6502);
    --grade-e: linear-gradient(135deg, #dc3545, #bd2130);
}

/* Enhanced Info Box */
.info-box {
    background: linear-gradient(to right, #ffffff, var(--edu-accent));
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.info-box h5 {
    color: var(--edu-primary);
    font-weight: 600;
    margin-bottom: 1rem;
}

.info-box i {
    background: var(--edu-primary);
    color: white;
    padding: 8px;
    border-radius: 8px;
    margin-right: 10px;
}

/* Table Styling */
.table {
    background: white;
    border-radius: 10px;
    overflow: hidden;
}

.table thead {
    background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
    color: white;
}

.table th {
    font-weight: 600;
    padding: 1rem;
    border: none;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

/* Grade Styling */
.grade-A { background: var(--grade-a); }
.grade-B { background: var(--grade-b); }
.grade-C { background: var(--grade-c); }
.grade-D { background: var(--grade-d); }
.grade-E { background: var(--grade-e); }

[class^="grade-"] {
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    display: inline-block;
    min-width: 45px;
    text-align: center;
}

/* Search Form Enhancement */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}

.input-group .form-control {
    border-radius: 10px 0 0 10px;
    border: 1px solid #ced4da;
}

.input-group .btn {
    border-radius: 0 10px 10px 0;
    padding: 0.5rem 1.5rem;
}

/* Pagination Enhancement */
.pagination .page-link {
    color: var(--edu-primary);
    border: none;
    padding: 0.5rem 1rem;
    margin: 0 3px;
    border-radius: 5px;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
    color: white;
}

/* Animations */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Enhancement */
@media (max-width: 768px) {
    .info-box {
        padding: 1rem;
    }
    
    .table-responsive {
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
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
                <h2 class="mb-0 text-primary">Kartu Hasil Studi (KHS)</h2>
                <div class="text-muted">
                    <i class="fas fa-calendar-alt"></i> 
                    <?php echo date('d M Y'); ?>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <h5><i class="fas fa-user-graduate me-2"></i>Mahasiswa</h5>
                        <p class="mb-0">Nama: <strong><?php echo htmlspecialchars($mahasiswa_nama); ?></strong><br>
                        NIM: <strong><?php echo htmlspecialchars($mahasiswa_nim); ?></strong></p>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fas fa-book me-2"></i>Total SKS</h5>
                        <p class="mb-0">
                            <?php 
                            $sks_sql = "SELECT SUM(mk.sks) as total_sks
                                       FROM data_nilai dn
                                       JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                                       JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                                       WHERE dn.mahasiswa_id = ?";
                            $sks_stmt = $conn->prepare($sks_sql);
                            $sks_stmt->bind_param("i", $mahasiswa_id);
                            $sks_stmt->execute();
                            $sks_result = $sks_stmt->get_result();
                            $sks_data = $sks_result->fetch_assoc();
                            $total_sks = $sks_data['total_sks'] ?? 0;
                            ?>
                            Total SKS: <strong><?php echo $total_sks; ?></strong>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fas fa-calculator me-2"></i>IP Semester</h5>
                        <p class="mb-0">
                            <?php 
                            $ip_sql = "SELECT AVG(CASE 
                                                WHEN dn.nilai_akhir >= 85 THEN 4.0
                                                WHEN dn.nilai_akhir >= 80 THEN 3.7
                                                WHEN dn.nilai_akhir >= 75 THEN 3.3
                                                WHEN dn.nilai_akhir >= 70 THEN 3.0
                                                WHEN dn.nilai_akhir >= 65 THEN 2.7
                                                WHEN dn.nilai_akhir >= 60 THEN 2.3
                                                WHEN dn.nilai_akhir >= 55 THEN 2.0
                                                WHEN dn.nilai_akhir >= 50 THEN 1.7
                                                WHEN dn.nilai_akhir >= 45 THEN 1.3
                                                WHEN dn.nilai_akhir >= 40 THEN 1.0
                                                ELSE 0
                                              END) as ip_semester
                                      FROM data_nilai dn
                                      WHERE dn.mahasiswa_id = ?";
                            $ip_stmt = $conn->prepare($ip_sql);
                            $ip_stmt->bind_param("i", $mahasiswa_id);
                            $ip_stmt->execute();
                            $ip_result = $ip_stmt->get_result();
                            $ip_data = $ip_result->fetch_assoc();
                            $ip_semester = number_format($ip_data['ip_semester'] ?? 0, 2);
                            ?>
                            IP Semester: <strong><?php echo $ip_semester; ?></strong>
                        </p>
                    </div>
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
            
            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Cari mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-1"></i> Cari
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                        <div class="col-auto">
                            <a href="nilai.php" class="btn btn-secondary">
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
                                    <th>Mata Kuliah</th>
                                    <th>SKS</th>
                                    <th>Dosen</th>
                                    <th>Tugas</th>
                                    <th>UTS</th>
                                    <th>UAS</th>
                                    <th>Akhir</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $row['hari']; ?> <?php echo $row['waktu_mulai']; ?>-<?php echo $row['waktu_selesai']; ?>
                                            </small>
                                        </td>
                                        <td><?php echo $row['sks']; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                                        <td><?php echo number_format($row['nilai_tugas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uts'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_akhir'], 2); ?></td>
                                        <td class="grade-<?php echo $row['grade']; ?>"><?php echo $row['grade']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2">Tidak ada data nilai ditemukan</p>
                                            <a href="nilai.php" class="btn btn-sm btn-primary mt-2">
                                                <i class="fas fa-sync me-1"></i> Reset Filter
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="nilai.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="nilai.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="nilai.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom Script -->
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