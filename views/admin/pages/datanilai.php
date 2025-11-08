<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/../../../include/config.php';
require_once __DIR__.'/../../../include/datanilai_functions.php';

// Ambil data untuk dropdown
$mahasiswa_sql = "SELECT m.id, m.nim, m.nama FROM mahasiswa m ORDER BY m.nama ASC";
$mahasiswa_result = $conn->query($mahasiswa_sql);

$jadwal_sql = "SELECT j.id, mk.nama_matkul, d.nama AS nama_dosen, j.hari, j.waktu_mulai, j.waktu_selesai 
               FROM jadwal_kuliah j
               JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
               JOIN dosen d ON j.dosen_nip = d.nip
               ORDER BY mk.nama_matkul ASC";
$jadwal_result = $conn->query($jadwal_sql);

// Pagination data nilai
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Hitung total record
$count_sql = "SELECT COUNT(*) FROM data_nilai";
$total_rows = $conn->query($count_sql)->fetch_row()[0];
$total_pages = ceil($total_rows / $per_page);

// Fungsi pencarian
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$search_condition = '';
$search_param = '';

if (!empty($search)) {
    $search_condition = " WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ? ";
    $search_param = "%$search%";
}

// Ambil data dengan join dan pagination
if (empty($search)) {
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
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error saat menyiapkan statement: " . $conn->error);
    }
    $stmt->bind_param("ii", $offset, $per_page);
} else {
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
            WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?
            ORDER BY m.nama ASC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error saat menyiapkan statement: " . $conn->error);
    }
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $offset, $per_page);
    
    // Update hitungan untuk pagination
    $count_sql = "SELECT COUNT(*) 
                  FROM data_nilai dn
                  JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                  JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                  JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                  WHERE m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?";
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt === false) {
        die("Error saat menyiapkan statement: " . $conn->error);
    }
    $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Idata nilai - Portal Akademik</title>
    
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
                <h2 class="mb-0 text-primary">data Nilai Mahasiswa</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus-circle me-2"></i>data Nilai Baru
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
                                <input type="text" class="form-control" name="search" placeholder="Cari NIM, nama mahasiswa, atau nama matkul..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search me-1"></i> Cari
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                        <div class="col-auto">
                            <a href="datanilai.php" class="btn btn-secondary">
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
                                    <th>NIM</th>
                                    <th>Mahasiswa</th>
                                    <th>Mata Kuliah</th>
                                    <th>Dosen</th>
                                    <th>Tugas</th>
                                    <th>UTS</th>
                                    <th>UAS</th>
                                    <th>Akhir</th>
                                    <th>Grade</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nim']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_mahasiswa']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_matkul']); ?> (<?php echo $row['sks']; ?> SKS)</td>
                                        <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                                        <td><?php echo number_format($row['nilai_tugas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uts'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_uas'], 2); ?></td>
                                        <td><?php echo number_format($row['nilai_akhir'], 2); ?></td>
                                        <td class="grade-<?php echo $row['grade']; ?>"><?php echo $row['grade']; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="datanilai.php?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus nilai ini?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Modal for each row -->
                                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">Edit Nilai</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST" action="datanilai.php">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Mahasiswa</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($row['nim']); ?>)" readonly>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Mata Kuliah</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['nama_matkul']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-4 mb-3">
                                                                <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                                                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_tugas" name="nilai_tugas" value="<?php echo $row['nilai_tugas']; ?>" required>
                                                            </div>
                                                            
                                                            <div class="col-md-4 mb-3">
                                                                <label for="nilai_uts" class="form-label">Nilai UTS</label>
                                                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_uts" name="nilai_uts" value="<?php echo $row['nilai_uts']; ?>" required>
                                                            </div>
                                                            
                                                            <div class="col-md-4 mb-3">
                                                                <label for="nilai_uas" class="form-label">Nilai UAS</label>
                                                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_uas" name="nilai_uas" value="<?php echo $row['nilai_uas']; ?>" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <i class="fas fa-info-circle me-2"></i>
                                                            Nilai akhir dan grade akan dihitung otomatis berdasarkan formula:
                                                            <ul class="mb-0 mt-2">
                                                                <li>Nilai Akhir = (25% Tugas + 35% UTS + 40% UAS)</li>
                                                                <li>Grade sesuai dengan standar penilaian universitas</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_nilai" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">Tidak ada data nilai</td>
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
                                <a class="page-link" href="datanilai.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><</a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="datanilai.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="datanilai.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">></a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

<!-- Tambah Modal -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">data Nilai Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="datanilai.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mahasiswa_id" class="form-label">Mahasiswa</label>
                        <select class="form-select" id="mahasiswa_id" name="mahasiswa_id" required>
                            <option value="" selected disabled>Pilih Mahasiswa</option>
                            <?php while ($mahasiswa = $mahasiswa_result->fetch_assoc()): ?>
                                <option value="<?php echo $mahasiswa['id']; ?>">
                                    <?php echo htmlspecialchars($mahasiswa['nama']); ?> (<?php echo htmlspecialchars($mahasiswa['nim']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jadwal_id" class="form-label">Mata Kuliah</label>
                        <select class="form-select" id="jadwal_id" name="jadwal_id" required>
                            <option value="" selected disabled>Pilih Mata Kuliah</option>
                            <?php while ($jadwal = $jadwal_result->fetch_assoc()): ?>
                                <option value="<?php echo $jadwal['id']; ?>">
                                    <?php echo htmlspecialchars($jadwal['nama_matkul']); ?> - 
                                    <?php echo htmlspecialchars($jadwal['nama_dosen']); ?> 
                                    (<?php echo $jadwal['hari']; ?> <?php echo $jadwal['waktu_mulai']; ?>-<?php echo $jadwal['waktu_selesai']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_tugas" name="nilai_tugas" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="nilai_uts" class="form-label">Nilai UTS</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_uts" name="nilai_uts" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="nilai_uas" class="form-label">Nilai UAS</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="nilai_uas" name="nilai_uas" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nilai akhir dan grade akan dihitung otomatis berdasarkan formula:
                        <ul class="mb-0 mt-2">
                            <li>Nilai Akhir = (25% Tugas + 35% UTS + 40% UAS)</li>
                            <li>Grade sesuai dengan standar penilaian universitas</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_nilai" class="btn btn-primary">Simpan Nilai</button>
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