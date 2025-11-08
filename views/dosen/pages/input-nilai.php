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
        
        // Debug: tampilkan data user (hapus setelah testing)
        // echo "<pre>User Data: " . print_r($current_user_data, true) . "</pre>";
        
        // Verifikasi role dosen
        if ($current_user_data['role'] !== 'dosen') {
            $_SESSION['error_message'] = "Hanya dosen yang bisa mengakses halaman ini. Role Anda: " . $current_user_data['role'];
            header("Location: ../../login.php");
            exit();
        }
        
        // 2. PERBAIKAN: Cari data dosen berdasarkan user_id (bukan username)
        // Karena relasi antara tabel users dan dosen melalui user_id
        $dosen_sql = "SELECT nip, nama FROM dosen WHERE user_id = ?";
        $dosen_stmt = $conn->prepare($dosen_sql);
        
        if ($dosen_stmt !== false) {
            $dosen_stmt->bind_param("i", $current_user_id);
            $dosen_stmt->execute();
            $dosen_result = $dosen_stmt->get_result();
            
            if ($dosen_result->num_rows > 0) {
                $dosen_data = $dosen_result->fetch_assoc();
                $current_dosen_nip = $dosen_data['nip'];
                
                // Debug: tampilkan data dosen (hapus setelah testing)
                // echo "<pre>Dosen Data: " . print_r($dosen_data, true) . "</pre>";
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
    // Tidak redirect untuk menghindari loop
}

// Fungsi untuk memverifikasi apakah dosen mengampu jadwal tertentu
function verifyDosenJadwal($conn, $nip, $jadwal_id) {
    $sql = "SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_nip = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt !== false) {
        $stmt->bind_param("is", $jadwal_id, $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

// Fungsi untuk mendapatkan mahasiswa yang mengambil mata kuliah yang diampu oleh dosen
function getMahasiswaByDosen($conn, $dosen_nip) {
    $sql = "SELECT DISTINCT m.id, m.nim, m.nama 
            FROM mahasiswa m
            JOIN krs ON m.id = krs.mahasiswa_id
            JOIN jadwal_kuliah j ON krs.jadwal_id = j.id
            WHERE j.dosen_nip = ?
            ORDER BY m.nama ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dosen_nip);
    $stmt->execute();
    $result = $stmt->get_result();
    $mahasiswa = [];
    
    while ($row = $result->fetch_assoc()) {
        $mahasiswa[] = $row;
    }
    
    $stmt->close();
    return $mahasiswa;
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Operasi CRUD - hanya jalankan jika NIP ditemukan
if (!empty($current_dosen_nip)) {

    // 1. Create - Tambah Data Nilai Baru
if (isset($_POST['tambah_nilai'])) {
    $mahasiswa_id = intval($_POST['mahasiswa_id']);
    $jadwal_id = intval($_POST['jadwal_id']);
    $nilai_tugas = floatval($_POST['nilai_tugas']);
    $nilai_uts = floatval($_POST['nilai_uts']);
    $nilai_uas = floatval($_POST['nilai_uas']);
    $created_by = $_SESSION['user_id'];
    
    // Verifikasi apakah dosen mengampu jadwal ini
    if (!verifyDosenJadwal($conn, $current_dosen_nip, $jadwal_id)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menambahkan nilai pada mata kuliah ini!";
        header("Location: input-nilai.php");
        exit();
    }
    
    // Verifikasi apakah mahasiswa mengambil jadwal ini
    $verify_krs_sql = "SELECT id FROM krs WHERE mahasiswa_id = ? AND jadwal_id = ?";
    $verify_krs_stmt = $conn->prepare($verify_krs_sql);
    $verify_krs_stmt->bind_param("ii", $mahasiswa_id, $jadwal_id);
    $verify_krs_stmt->execute();
    $verify_krs_result = $verify_krs_stmt->get_result();
    
    if ($verify_krs_result->num_rows == 0) {
        $_SESSION['error_message'] = "Mahasiswa tidak terdaftar pada mata kuliah ini!";
        $verify_krs_stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
    $verify_krs_stmt->close();
    
    // Cek apakah data sudah ada
    $check_sql = "SELECT id FROM data_nilai WHERE mahasiswa_id = ? AND jadwal_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $mahasiswa_id, $jadwal_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = "Data nilai untuk mahasiswa dan mata kuliah ini sudah ada!";
    } else {
        // Validasi nilai
        if ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
            $_SESSION['error_message'] = "Nilai harus berada di antara 0 dan 100";
        } else {
            // Insert data
            $sql = "INSERT INTO data_nilai (mahasiswa_id, jadwal_id, nilai_tugas, nilai_uts, nilai_uas, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidddi", $mahasiswa_id, $jadwal_id, $nilai_tugas, $nilai_uts, $nilai_uas, $created_by);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Data nilai berhasil ditambahkan!";
            } else {
                $_SESSION['error_message'] = "Gagal menambahkan data nilai: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
    header("Location: input-nilai.php");
    exit();
}

    // 2. Update - Edit Data Nilai
    if (isset($_POST['edit_nilai'])) {
        $id = intval($_POST['id']);
        $nilai_tugas = floatval($_POST['nilai_tugas']);
        $nilai_uts = floatval($_POST['nilai_uts']);
        $nilai_uas = floatval($_POST['nilai_uas']);
        
        // Verifikasi apakah nilai ini milik jadwal yang diampu oleh dosen ini
        $verify_sql = "SELECT j.id FROM data_nilai dn 
                       JOIN jadwal_kuliah j ON dn.jadwal_id = j.id 
                       WHERE dn.id = ? AND j.dosen_nip = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("is", $id, $current_dosen_nip);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows == 0) {
            $_SESSION['error_message'] = "Anda tidak memiliki akses untuk mengubah nilai ini!";
            $verify_stmt->close();
            header("Location: input-nilai.php");
            exit();
        }
        $verify_stmt->close();
        
        // Validasi nilai
        if ($nilai_tugas < 0 || $nilai_tugas > 100 || $nilai_uts < 0 || $nilai_uts > 100 || $nilai_uas < 0 || $nilai_uas > 100) {
            $_SESSION['error_message'] = "Nilai harus berada di antara 0 dan 100";
        } else {
            // Update data
            $sql = "UPDATE data_nilai SET nilai_tugas = ?, nilai_uts = ?, nilai_uas = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dddi", $nilai_tugas, $nilai_uts, $nilai_uas, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Data nilai berhasil diperbarui!";
            } else {
                $_SESSION['error_message'] = "Gagal memperbarui data nilai: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: input-nilai.php");
        exit();
    }

    // 3. Delete - Hapus Data Nilai
    if (isset($_GET['delete_id'])) {
        $id = intval($_GET['delete_id']);
        
        // Verifikasi apakah nilai ini milik jadwal yang diampu oleh dosen ini
        $verify_sql = "SELECT j.id FROM data_nilai dn 
                       JOIN jadwal_kuliah j ON dn.jadwal_id = j.id 
                       WHERE dn.id = ? AND j.dosen_nip = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("is", $id, $current_dosen_nip);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows == 0) {
            $_SESSION['error_message'] = "Anda tidak memiliki akses untuk menghapus nilai ini!";
            $verify_stmt->close();
            header("Location: input-nilai.php");
            exit();
        }
        $verify_stmt->close();
        
        // Delete data
        $sql = "DELETE FROM data_nilai WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data nilai berhasil dihapus!";
        } else {
            $_SESSION['error_message'] = "Gagal menghapus data nilai: " . $stmt->error;
        }
        $stmt->close();
        header("Location: input-nilai.php");
        exit();
    }
}

// Ambil data untuk dropdown - semua mahasiswa
$mahasiswa_list = [];
if (!empty($current_dosen_nip)) {
    $mahasiswa_list = getMahasiswaByDosen($conn, $current_dosen_nip);
}

// Inisialisasi variabel untuk mencegah error
$jadwal_result = null;
$result = null;
$total_pages = 0;
$total_rows = 0;

// Hanya ambil jadwal dan data nilai jika NIP ditemukan
if (!empty($current_dosen_nip)) {
    // Ambil jadwal yang diampu oleh dosen yang login
$jadwal_result = null;
if (!empty($current_dosen_nip)) {
    $jadwal_sql = "SELECT j.id, mk.nama_matkul, d.nama AS nama_dosen, j.hari, j.waktu_mulai, j.waktu_selesai 
                   FROM jadwal_kuliah j
                   JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                   JOIN dosen d ON j.dosen_nip = d.nip
                   WHERE j.dosen_nip = ?
                   ORDER BY mk.nama_matkul ASC";
    $jadwal_stmt = $conn->prepare($jadwal_sql);
    $jadwal_stmt->bind_param("s", $current_dosen_nip);
    $jadwal_stmt->execute();
    $jadwal_result = $jadwal_stmt->get_result();
}

    // Pagination data nilai - HANYA menampilkan nilai dari mata kuliah yang diampu oleh dosen yang login
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Hitung total record - hanya dari mata kuliah yang diampu
    $count_sql = "SELECT COUNT(*) FROM data_nilai dn
                  JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                  WHERE j.dosen_nip = ?";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("s", $current_dosen_nip);
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();
    $total_pages = ceil($total_rows / $per_page);

    // Fungsi pencarian
    $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
    $search_condition = '';
    $search_param = '';

    if (!empty($search)) {
        $search_condition = " AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?) ";
        $search_param = "%$search%";
    }

    // Ambil data dengan join dan pagination - HANYA dari mata kuliah yang diampu
    if (empty($search)) {
        $sql = "SELECT dn.*, 
                       m.nim, m.nama AS nama_mahasiswa, 
                       mk.nama_matkul, mk.sks, 
                       d.nama AS nama_dosen, 
                       j.hari, j.waktu_mulai, j.waktu_selesai,
                       ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) AS nilai_akhir,
                       CASE 
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 85 THEN 'A'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 80 THEN 'A-'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 75 THEN 'B+'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 70 THEN 'B'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 65 THEN 'B-'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 60 THEN 'C+'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 55 THEN 'C'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 40 THEN 'D'
                           ELSE 'E'
                       END AS grade
                FROM data_nilai dn
                JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                JOIN dosen d ON j.dosen_nip = d.nip
                WHERE j.dosen_nip = ?
                ORDER BY m.nama ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if ($stmt !== false) {
            $stmt->bind_param("sii", $current_dosen_nip, $offset, $per_page);
        }
    } else {
        $sql = "SELECT dn.*, 
                       m.nim, m.nama AS nama_mahasiswa, 
                       mk.nama_matkul, mk.sks, 
                       d.nama AS nama_dosen, 
                       j.hari, j.waktu_mulai, j.waktu_selesai,
                       ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) AS nilai_akhir,
                       CASE 
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 85 THEN 'A'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 80 THEN 'A-'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 75 THEN 'B+'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 70 THEN 'B'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 65 THEN 'B-'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 60 THEN 'C+'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 55 THEN 'C'
                           WHEN ROUND((dn.nilai_tugas * 0.3) + (dn.nilai_uts * 0.3) + (dn.nilai_uas * 0.4), 2) >= 50 THEN 'D'
                           ELSE 'E'
                       END AS grade
                FROM data_nilai dn
                JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                JOIN dosen d ON j.dosen_nip = d.nip
                WHERE j.dosen_nip = ? AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?)
                ORDER BY m.nama ASC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if ($stmt !== false) {
            $stmt->bind_param("ssssii", $current_dosen_nip, $search_param, $search_param, $search_param, $offset, $per_page);
        }
        
        // Update hitungan untuk pagination dengan search
        $count_sql = "SELECT COUNT(*) 
                      FROM data_nilai dn
                      JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                      JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                      JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                      WHERE j.dosen_nip = ? AND (m.nim LIKE ? OR m.nama LIKE ? OR mk.nama_matkul LIKE ?)";
        $count_stmt = $conn->prepare($count_sql);
        if ($count_stmt !== false) {
            $count_stmt->bind_param("ssss", $current_dosen_nip, $search_param, $search_param, $search_param);
            $count_stmt->execute();
            $count_stmt->bind_result($total_rows);
            $count_stmt->fetch();
            $count_stmt->close();
            $total_pages = ceil($total_rows / $per_page);
        }
    }

    if ($stmt !== false) {
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
    <title>Input Nilai - Portal Akademik</title>
    
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
                <h2 class="mb-0 text-primary">Input Nilai Mahasiswa</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Nilai
                </button>
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

                <!-- Search Form -->
                <div class="card mb-4 fade-in">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-10">
                                <input type="text" class="form-control" name="search" placeholder="Cari mahasiswa atau mata kuliah..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Cari
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Nilai Table -->
                <div class="card mb-4 fade-in">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Mahasiswa</th>
                                        <th>Mata Kuliah</th>
                                        <th>Nilai Tugas</th>
                                        <th>Nilai UTS</th>
                                        <th>Nilai UAS</th>
                                        <th>Nilai Akhir</th>
                                        <th>Grade</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php $no = $offset + 1; ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="fade-in">
                                                <td><?php echo $no++; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['nama_mahasiswa']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['nim']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['nama_matkul']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($row['hari']); ?>, 
                                                        <?php echo date('H:i', strtotime($row['waktu_mulai'])); ?>-<?php echo date('H:i', strtotime($row['waktu_selesai'])); ?>
                                                    </small>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['nilai_tugas']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_uts']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_uas']); ?></td>
                                                <td><?php echo htmlspecialchars($row['nilai_akhir']); ?></td>
                                                <td>
                                                    <span class="grade-<?php echo str_replace('+', '\\+', $row['grade']); ?>">
                                                        <?php echo htmlspecialchars($row['grade']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus nilai ini?')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal for each row -->
                                            <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-labelledby="editModalLabel<?php echo $row['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="editModalLabel<?php echo $row['id']; ?>">Edit Nilai</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mahasiswa</label>
                                                                  <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['nama_mahasiswa'] . ' (' . $row['nim'] . ')'); ?>" readonly>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mata Kuliah</label>
                                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['nama_matkul']); ?>" readonly>
                                                                </div>
                                                                <div class="row">
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_tugas" name="nilai_tugas" value="<?php echo htmlspecialchars($row['nilai_tugas']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_uts" class="form-label">Nilai UTS</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uts" name="nilai_uts" value="<?php echo htmlspecialchars($row['nilai_uts']); ?>" required>
                                                                    </div>
                                                                    <div class="col-md-4 mb-3">
                                                                        <label for="nilai_uas" class="form-label">Nilai UAS</label>
                                                                        <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uas" name="nilai_uas" value="<?php echo htmlspecialchars($row['nilai_uas']); ?>" required>
                                                                    </div>
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
                                        <tr class="fade-in">
                                            <td colspan="9" class="text-center text-muted py-4">Tidak ada data nilai yang ditemukan</td>
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
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Tambah Nilai Modal -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahModalLabel">Tambah Nilai Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mahasiswa_id" class="form-label">Mahasiswa</label>
                        <select class="form-select" id="mahasiswa_id" name="mahasiswa_id" required>
                            <option value="">Pilih Mahasiswa</option>
                            <?php foreach ($mahasiswa_list as $mahasiswa): ?>
                                <option value="<?php echo $mahasiswa['id']; ?>">
                                    <?php echo htmlspecialchars($mahasiswa['nama'] . ' (' . $mahasiswa['nim'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="jadwal_id" class="form-label">Mata Kuliah</label>
                        <select class="form-select" id="jadwal_id" name="jadwal_id" required>
                            <option value="">Pilih Mata Kuliah</option>
                            <?php if ($jadwal_result): ?>
                                <?php while ($jadwal = $jadwal_result->fetch_assoc()): ?>
                                    <option value="<?php echo $jadwal['id']; ?>">
                                       <?php echo htmlspecialchars($jadwal['nama_matkul'] . ' - ' . $jadwal['hari'] . ' ' . date('H:i', strtotime($jadwal['waktu_mulai'])) . '-' . date('H:i', strtotime($jadwal['waktu_selesai']))); ?>
                                    </option>
                                <?php endwhile; ?>
                                <?php $jadwal_result->data_seek(0); // Reset pointer untuk penggunaan berikutnya ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nilai_tugas" class="form-label">Nilai Tugas</label>
                            <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_tugas" name="nilai_tugas" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nilai_uts" class="form-label">Nilai UTS</label>
                            <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uts" name="nilai_uts" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nilai_uas" class="form-label">Nilai UAS</label>
                            <input type="number" min="0" max="100" step="0.01" class="form-control" id="nilai_uas" name="nilai_uas" required>
                        </div>
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

    <!-- Custom Script -->
    <script>
        $(document).ready(function() {
            // Auto close alert after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
            
            // Reset modal when closed
            $('#tambahModal').on('hidden.bs.modal', function () {
                $(this).find('form').trigger('reset');
            });
            
            // Validate input values
            $('form').on('submit', function() {
                const tugas = parseFloat($('#nilai_tugas').val());
                const uts = parseFloat($('#nilai_uts').val());
                const uas = parseFloat($('#nilai_uas').val());
                
                if (isNaN(tugas) || isNaN(uts) || isNaN(uas)) {
                    alert('Semua nilai harus diisi dengan angka');
                    return false;
                }
                
                if (tugas < 0 || tugas > 100 || uts < 0 || uts > 100 || uas < 0 || uas > 100) {
                    alert('Nilai harus berada di antara 0 dan 100');
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