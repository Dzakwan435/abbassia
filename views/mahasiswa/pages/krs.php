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

// Mendapatkan tahun ajaran saat ini dan yang akan datang
function getTahunAjaran() {
    $year = date('Y');
    $month = date('n');
    
    // Jika bulan > 6, maka semester ganjil tahun ajaran baru
    if ($month > 6) {
        $tahunAjaran = $year . '/' . ($year + 1);
        $semester = 'Ganjil';
    } 
    // Jika bulan <= 6, maka semester genap tahun ajaran sebelumnya
    else {
        $tahunAjaran = ($year - 1) . '/' . $year;
        $semester = 'Genap';
    }
    
    return ['tahun_ajaran' => $tahunAjaran, 'semester' => $semester];
}

$periodeAktif = getTahunAjaran();
$tahunAjaranAktif = $periodeAktif['tahun_ajaran'];
$semesterAktif = $periodeAktif['semester'];

// Ambil data mahasiswa yang login
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

// Operasi CRUD

// 1. Create - Tambah KRS Baru
if (isset($_POST['tambah_krs'])) {
    $jadwal_id = intval($_POST['jadwal_id']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);
    $semester = clean_input($_POST['semester']);
    
    // Cek apakah data sudah ada
    $check_sql = "SELECT id FROM krs WHERE mahasiswa_id = ? AND jadwal_id = ? AND tahun_ajaran = ? AND semester = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        $_SESSION['error_message'] = "Error database: " . $conn->error;
        header("Location: krs.php");
        exit();
    }
    
    $check_stmt->bind_param("iiss", $mahasiswa_id, $jadwal_id, $tahun_ajaran, $semester);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Mata kuliah ini sudah ada dalam KRS Anda untuk periode yang sama!";
    } else {
        // Cek jadwal bentrok
        $jadwal_check_sql = "SELECT jk1.hari, jk1.waktu_mulai, jk1.waktu_selesai 
                            FROM jadwal_kuliah jk1 
                            WHERE jk1.id = ?";
        $jadwal_check_stmt = $conn->prepare($jadwal_check_sql);
        
        if (!$jadwal_check_stmt) {
            $_SESSION['error_message'] = "Error database: " . $conn->error;
            header("Location: krs.php");
            exit();
        }
        
        $jadwal_check_stmt->bind_param("i", $jadwal_id);
        $jadwal_check_stmt->execute();
        $jadwal_result = $jadwal_check_stmt->get_result();
        $jadwal_data = $jadwal_result->fetch_assoc();
        
        $bentrok_sql = "SELECT jk2.id, mk.nama_matkul, jk2.hari, jk2.waktu_mulai, jk2.waktu_selesai
                        FROM krs k
                        JOIN jadwal_kuliah jk2 ON k.jadwal_id = jk2.id
                        JOIN mata_kuliah mk ON jk2.kode_matkul = mk.kode_matkul
                        WHERE k.mahasiswa_id = ? 
                        AND k.tahun_ajaran = ? 
                        AND k.semester = ?
                        AND k.status = 'aktif'
                        AND jk2.hari = ?
                        AND ((jk2.waktu_mulai <= ? AND jk2.waktu_selesai > ?) OR 
                             (jk2.waktu_mulai < ? AND jk2.waktu_selesai >= ?))";
        
        $bentrok_stmt = $conn->prepare($bentrok_sql);
        
        if (!$bentrok_stmt) {
            $_SESSION['error_message'] = "Error database: " . $conn->error;
            header("Location: krs.php");
            exit();
        }
        
        $bentrok_stmt->bind_param("isssssss", 
                                $mahasiswa_id, 
                                $tahun_ajaran, 
                                $semester,
                                $jadwal_data['hari'],
                                $jadwal_data['waktu_selesai'],
                                $jadwal_data['waktu_mulai'],
                                $jadwal_data['waktu_selesai'],
                                $jadwal_data['waktu_mulai']);
        $bentrok_stmt->execute();
        $bentrok_result = $bentrok_stmt->get_result();
        
        if ($bentrok_result->num_rows > 0) {
            $bentrok_data = $bentrok_result->fetch_assoc();
            $_SESSION['error_message'] = "Jadwal bentrok dengan mata kuliah " . $bentrok_data['nama_matkul'] . 
                                      " (" . $bentrok_data['hari'] . ", " . $bentrok_data['waktu_mulai'] . 
                                      "-" . $bentrok_data['waktu_selesai'] . ")";
        } else {
            // Hitung jumlah SKS yang sudah diambil
            $sks_sql = "SELECT SUM(mk.sks) as total_sks
                        FROM krs k
                        JOIN jadwal_kuliah j ON k.jadwal_id = j.id
                        JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                        WHERE k.mahasiswa_id = ? 
                        AND k.tahun_ajaran = ? 
                        AND k.semester = ?
                        AND k.status = 'aktif'";
            $sks_stmt = $conn->prepare($sks_sql);
            
            if (!$sks_stmt) {
                $_SESSION['error_message'] = "Error database: " . $conn->error;
                header("Location: krs.php");
                exit();
            }
            
            $sks_stmt->bind_param("iss", $mahasiswa_id, $tahun_ajaran, $semester);
            $sks_stmt->execute();
            $sks_result = $sks_stmt->get_result();
            $sks_data = $sks_result->fetch_assoc();
            $current_sks = $sks_data['total_sks'] ?? 0;
            
            // Ambil SKS mata kuliah yang akan ditambahkan
            $new_sks_sql = "SELECT mk.sks 
                           FROM jadwal_kuliah j
                           JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                           WHERE j.id = ?";
            $new_sks_stmt = $conn->prepare($new_sks_sql);
            
            if (!$new_sks_stmt) {
                $_SESSION['error_message'] = "Error database: " . $conn->error;
                header("Location: krs.php");
                exit();
            }
            
            $new_sks_stmt->bind_param("i", $jadwal_id);
            $new_sks_stmt->execute();
            $new_sks_result = $new_sks_stmt->get_result();
            $new_sks_data = $new_sks_result->fetch_assoc();
            $new_sks = $new_sks_data['sks'];
            
            // Batas maksimum SKS (bisa disesuaikan)
            $max_sks = 24;
            
            if (($current_sks + $new_sks) > $max_sks) {
                $_SESSION['error_message'] = "Total SKS (" . ($current_sks + $new_sks) . ") melebihi batas maksimum ($max_sks SKS)";
            } else {
                // Insert data KRS
                $sql = "INSERT INTO krs (mahasiswa_id, jadwal_id, tahun_ajaran, semester) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    $_SESSION['error_message'] = "Error database: " . $conn->error;
                    header("Location: krs.php");
                    exit();
                }
                
                $stmt->bind_param("iiss", $mahasiswa_id, $jadwal_id, $tahun_ajaran, $semester);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Mata kuliah berhasil ditambahkan ke KRS!";
                } else {
                    $_SESSION['error_message'] = "Gagal menambahkan mata kuliah ke KRS: " . $stmt->error;
                }
                $stmt->close();
            }
            $new_sks_stmt->close();
            $sks_stmt->close();
        }
        $bentrok_stmt->close();
        $jadwal_check_stmt->close();
    }
    $check_stmt->close();
    header("Location: krs.php");
    exit();
}

// 2. Update - Ubah Status KRS (Batal/Aktif)
if (isset($_POST['update_status'])) {
    $id = intval($_POST['id']);
    $status = clean_input($_POST['status']);
    
    $sql = "UPDATE krs SET status = ? WHERE id = ? AND mahasiswa_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error_message'] = "Error database: " . $conn->error;
        header("Location: krs.php");
        exit();
    }
    
    $stmt->bind_param("sii", $status, $id, $mahasiswa_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Status KRS berhasil diperbarui!";
    } else {
        $_SESSION['error_message'] = "Gagal memperbarui status KRS: " . $stmt->error;
    }
    $stmt->close();
    header("Location: krs.php");
    exit();
}

// 3. Delete - Hapus Data KRS
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    $sql = "DELETE FROM krs WHERE id = ? AND mahasiswa_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error_message'] = "Error database: " . $conn->error;
        header("Location: krs.php");
        exit();
    }
    
    $stmt->bind_param("ii", $id, $mahasiswa_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Mata kuliah berhasil dihapus dari KRS!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus mata kuliah dari KRS: " . $stmt->error;
    }
    $stmt->close();
    header("Location: krs.php");
    exit();
}

// Ambil data untuk dropdown mata kuliah
$jadwal_sql = "SELECT j.id, mk.nama_matkul, d.nama AS nama_dosen, j.hari, j.waktu_mulai, j.waktu_selesai, mk.sks 
               FROM jadwal_kuliah j
               JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
               JOIN dosen d ON j.dosen_nip = d.nip
               ORDER BY j.hari, j.waktu_mulai";
$jadwal_result = $conn->query($jadwal_sql);

if (!$jadwal_result) {
    die("Error query jadwal: " . $conn->error);
}

// Ambil tahun ajaran untuk dropdown
$tahun_ajaran_list = [];
$current_year = date('Y');
for ($i = -1; $i <= 1; $i++) {
    $year = $current_year + $i;
    $tahun_ajaran_list[] = $year . '/' . ($year + 1);
}

// Pagination data KRS
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter pencarian dan tahun ajaran/semester
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$filter_tahun_ajaran = isset($_GET['tahun_ajaran']) ? clean_input($_GET['tahun_ajaran']) : $tahunAjaranAktif;
$filter_semester = isset($_GET['semester']) ? clean_input($_GET['semester']) : $semesterAktif;
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';

// Membangun query dengan filter
$where_conditions = ["k.mahasiswa_id = $mahasiswa_id"]; // Hanya tampilkan KRS mahasiswa yang login
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(mk.nama_matkul LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $param_types .= "s";
}

if (!empty($filter_tahun_ajaran)) {
    $where_conditions[] = "k.tahun_ajaran = ?";
    $params[] = $filter_tahun_ajaran;
    $param_types .= "s";
}

if (!empty($filter_semester)) {
    $where_conditions[] = "k.semester = ?";
    $params[] = $filter_semester;
    $param_types .= "s";
}

if (!empty($status_filter)) {
    $where_conditions[] = "k.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Hitung total record dengan filter
$count_sql = "SELECT COUNT(*) FROM krs k
              JOIN mahasiswa m ON k.mahasiswa_id = m.id
              JOIN jadwal_kuliah j ON k.jadwal_id = j.id
              JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
              WHERE " . implode(" AND ", $where_conditions);

$count_stmt = $conn->prepare($count_sql);
if (!$count_stmt) {
    die("Error prepare count: " . $conn->error);
}

if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_rows / $per_page);

// Tambahkan parameter untuk pagination
$params_pagination = $params;
$params_pagination[] = $offset;
$params_pagination[] = $per_page;
$param_types_pagination = $param_types . "ii";

// Query untuk mengambil data KRS
$sql = "SELECT k.id, k.mahasiswa_id, k.jadwal_id, k.tahun_ajaran, k.semester, k.status, k.created_at,
               m.nim, m.nama AS nama_mahasiswa,
               mk.nama_matkul, mk.sks,
               d.nama AS nama_dosen,
               j.hari, j.waktu_mulai, j.waktu_selesai
        FROM krs k
        JOIN mahasiswa m ON k.mahasiswa_id = m.id
        JOIN jadwal_kuliah j ON k.jadwal_id = j.id
        JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
        JOIN dosen d ON j.dosen_nip = d.nip
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY k.tahun_ajaran DESC, k.semester ASC, j.hari ASC, j.waktu_mulai ASC
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error prepare stmt: " . $conn->error);
}

if (!empty($params_pagination)) {
    $stmt->bind_param($param_types_pagination, ...$params_pagination);
}
$stmt->execute();
$result = $stmt->get_result();

// Hitung total SKS yang sudah diambil untuk semester aktif
$total_sks_query = "SELECT SUM(mk.sks) as total_sks
                   FROM krs k
                   JOIN jadwal_kuliah j ON k.jadwal_id = j.id
                   JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                   WHERE k.mahasiswa_id = ? AND k.tahun_ajaran = ? AND k.semester = ? AND k.status = 'aktif'";
$total_sks_stmt = $conn->prepare($total_sks_query);
if (!$total_sks_stmt) {
    die("Error prepare total_sks: " . $conn->error);
}

$total_sks_stmt->bind_param("iss", $mahasiswa_id, $filter_tahun_ajaran, $filter_semester);
$total_sks_stmt->execute();
$total_sks_result = $total_sks_stmt->get_result();
$total_sks_data = $total_sks_result->fetch_assoc();
$total_sks = $total_sks_data['total_sks'] ?? 0;
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
    
    <!--custom css-->
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
            <h2 class="mb-0 text-primary">Kartu Rencana Studi (KRS)</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus-circle me-2"></i>Tambah Mata Kuliah
            </button>
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
                        <h5><i class="fas fa-calendar-check me-2"></i>Periode Aktif</h5>
                        <p class="mb-0">
                            <?php 
                            $periode_sql = "SELECT DISTINCT semester, tahun_ajaran 
                                          FROM krs 
                                          WHERE mahasiswa_id = ? AND status = 'aktif'
                                          ORDER BY tahun_ajaran DESC, semester DESC
                                          LIMIT 1";
                            $periode_stmt = $conn->prepare($periode_sql);
                            $periode_stmt->bind_param("i", $mahasiswa_id);
                            $periode_stmt->execute();
                            $periode_result = $periode_stmt->get_result();
                            $periode_data = $periode_result->fetch_assoc();
                            
                            if ($periode_data) {
                                echo "Semester: <strong>" . htmlspecialchars($periode_data['semester']) . "</strong><br>";
                                echo "Tahun Ajaran: <strong>" . htmlspecialchars($periode_data['tahun_ajaran']) . "</strong>";
                            } else {
                                echo "Belum ada jadwal aktif";
                            }
                            ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <h5><i class="fas fa-book me-2"></i>Total SKS</h5>
                        <p class="mb-0">
                            <?php 
                            $sks_sql = "SELECT SUM(mk.sks) as total_sks
                                       FROM krs k
                                       JOIN jadwal_kuliah jk ON k.jadwal_id = jk.id
                                       JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
                                       WHERE k.mahasiswa_id = ? AND k.status = 'aktif'";
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
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                        <select class="form-select" id="tahun_ajaran" name="tahun_ajaran">
                            <?php foreach ($tahun_ajaran_list as $tahun): ?>
                                <option value="<?php echo $tahun; ?>" <?php echo ($filter_tahun_ajaran == $tahun) ? 'selected' : ''; ?>>
                                    <?php echo $tahun; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="semester" class="form-label">Semester</label>
                        <select class="form-select" id="semester" name="semester">
                            <option value="Ganjil" <?php echo ($filter_semester == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                            <option value="Genap" <?php echo ($filter_semester == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="" <?php echo ($status_filter == '') ? 'selected' : ''; ?>>Semua</option>
                            <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="batal" <?php echo ($status_filter == 'batal') ? 'selected' : ''; ?>>Batal</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Pencarian</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Cari mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                    <div class="col-auto">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="krs.php?tahun_ajaran=<?php echo urlencode($filter_tahun_ajaran); ?>&semester=<?php echo urlencode($filter_semester); ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- KRS Table -->
        <div class="card mb-4 fade-in">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mata Kuliah</th>
                                <th>SKS</th>
                                <th>Dosen</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php 
                                $no = $offset + 1;
                                while ($row = $result->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo $row['tahun_ajaran']; ?> - <?php echo $row['semester']; ?>
                                            </small>
                                        </td>
                                        <td><?php echo $row['sks']; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_dosen']); ?></td>
                                        <td>
                                            <?php echo $row['hari']; ?><br>
                                            <?php echo date('H:i', strtotime($row['waktu_mulai'])); ?> - 
                                            <?php echo date('H:i', strtotime($row['waktu_selesai'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == 'aktif'): ?>
                                                <span class="status-aktif">Aktif</span>
                                            <?php else: ?>
                                                <span class="status-batal">Batal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($row['status'] == 'aktif'): ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="updateStatus(<?php echo $row['id']; ?>, 'batal')"
                                                            title="Batalkan">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="updateStatus(<?php echo $row['id']; ?>, 'aktif')"
                                                            title="Aktifkan">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $row['id']; ?>)"
                                                        title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-exclamation-circle text-muted" style="font-size: 2rem;"></i>
                                        <p class="text-muted mt-2">Tidak ada data KRS ditemukan</p>
                                        <a href="krs.php" class="btn btn-sm btn-primary mt-2">
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
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun_ajaran); ?>&semester=<?php echo urlencode($filter_semester); ?>&status=<?php echo urlencode($status_filter); ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun_ajaran); ?>&semester=<?php echo urlencode($filter_semester); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&tahun_ajaran=<?php echo urlencode($filter_tahun_ajaran); ?>&semester=<?php echo urlencode($filter_semester); ?>&status=<?php echo urlencode($status_filter); ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Tambah KRS Modal -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="tambahModalLabel">Tambah Mata Kuliah ke KRS</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mahasiswa</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa_nim . ' - ' . $mahasiswa_nama); ?>" readonly>
                                <input type="hidden" name="mahasiswa_id" value="<?php echo $mahasiswa_id; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="tahun_ajaran" class="form-label">Tahun Ajaran</label>
                                <select class="form-select" id="tahun_ajaran" name="tahun_ajaran" required>
                                    <?php foreach ($tahun_ajaran_list as $tahun): ?>
                                        <option value="<?php echo $tahun; ?>" <?php echo ($tahun == $tahunAjaranAktif) ? 'selected' : ''; ?>>
                                            <?php echo $tahun; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="semester" class="form-label">Semester</label>
                                <select class="form-select" id="semester" name="semester" required>
                                    <option value="Ganjil" <?php echo ($semesterAktif == 'Ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                                    <option value="Genap" <?php echo ($semesterAktif == 'Genap') ? 'selected' : ''; ?>>Genap</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="jadwal_id" class="form-label">Mata Kuliah</label>
                                <select class="form-select" id="jadwal_id" name="jadwal_id" required>
                                    <option value="">Pilih Mata Kuliah</option>
                                    <?php while ($jadwal = $jadwal_result->fetch_assoc()): ?>
                                        <option value="<?php echo $jadwal['id']; ?>">
                                            <?php echo htmlspecialchars($jadwal['nama_matkul'] . ' - ' . $jadwal['nama_dosen'] . ' (' . $jadwal['hari'] . ' ' . date('H:i', strtotime($jadwal['waktu_mulai'])) . '-' . date('H:i', strtotime($jadwal['waktu_selesai'])) . ') - ' . $jadwal['sks'] . ' SKS'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_krs" class="btn btn-primary">Simpan</button>
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
        // Function to update KRS status
        function updateStatus(id, status) {
            if (confirm(`Apakah Anda yakin ingin ${status === 'aktif' ? 'mengaktifkan' : 'membatalkan'} mata kuliah ini?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                form.appendChild(idInput);
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = status;
                form.appendChild(statusInput);
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_status';
                submitInput.value = '1';
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Function to confirm KRS deletion
        function confirmDelete(id) {
            if (confirm('Apakah Anda yakin ingin menghapus mata kuliah ini dari KRS?')) {
                window.location.href = `krs.php?delete_id=${id}`;
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>