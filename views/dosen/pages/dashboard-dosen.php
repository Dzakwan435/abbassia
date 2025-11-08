<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../include/config.php';

// Set timezone ke Waktu Indonesia Barat (Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Verifikasi koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Ambil data dosen dengan foto profil
$dosen_sql = "SELECT d.*, ps.nama_prodi, u.username, d.foto_profil 
              FROM dosen d 
              LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.user_id = ?";
$dosen_stmt = $conn->prepare($dosen_sql);
$dosen_stmt->bind_param("i", $user_id);
$dosen_stmt->execute();
$dosen_result = $dosen_stmt->get_result();
$dosen_data = $dosen_result->fetch_assoc();

if (!$dosen_data) {
    $_SESSION['error_message'] = "Data dosen tidak ditemukan!";
    header("Location: ../../login.php");
    exit();
}

// Statistik Dashboard
// 1. Jumlah Mata Kuliah yang Diajar
$matkul_sql = "SELECT COUNT(DISTINCT j.kode_matkul) as total_matkul 
               FROM jadwal_kuliah j 
               WHERE j.dosen_nip = ?";
$matkul_stmt = $conn->prepare($matkul_sql);
$matkul_stmt->bind_param("s", $dosen_data['nip']);
$matkul_stmt->execute();
$total_matkul = $matkul_stmt->get_result()->fetch_assoc()['total_matkul'];

// 2. Jumlah Kelas yang Diajar
$kelas_sql = "SELECT COUNT(*) as total_kelas
              FROM jadwal_kuliah j 
              WHERE j.dosen_nip = ?";
$kelas_stmt = $conn->prepare($kelas_sql);
$kelas_stmt->bind_param("s", $dosen_data['nip']);
$kelas_stmt->execute();
$total_kelas = $kelas_stmt->get_result()->fetch_assoc()['total_kelas'];

// 3. Jumlah Mahasiswa yang Dinilai
$mahasiswa_sql = "SELECT COUNT(DISTINCT dn.mahasiswa_id) as total_mahasiswa
                  FROM data_nilai dn
                  JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                  WHERE j.dosen_nip = ?";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);
$mahasiswa_stmt->bind_param("s", $dosen_data['nip']);
$mahasiswa_stmt->execute();
$total_mahasiswa = $mahasiswa_stmt->get_result()->fetch_assoc()['total_mahasiswa'];

// Jadwal Mengajar Hari Ini
$hari_ini = date('l'); // Nama hari dalam bahasa Inggris
$hari_indonesia = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];

$jadwal_hari_ini_sql = "SELECT j.*, mk.nama_matkul, mk.sks
                        FROM jadwal_kuliah j
                        JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                        WHERE j.dosen_nip = ? AND j.hari = ?
                        ORDER BY j.waktu_mulai ASC";
$jadwal_stmt = $conn->prepare($jadwal_hari_ini_sql);
$jadwal_stmt->bind_param("ss", $dosen_data['nip'], $hari_indonesia[$hari_ini]);
$jadwal_stmt->execute();
$jadwal_hari_ini = $jadwal_stmt->get_result();

// Jadwal Mengajar Lengkap (Semua Hari)
$jadwal_lengkap_sql = "SELECT j.*, mk.nama_matkul, mk.sks
                       FROM jadwal_kuliah j
                       JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                       WHERE j.dosen_nip = ?
                       ORDER BY 
                       CASE j.hari 
                           WHEN 'Senin' THEN 1
                           WHEN 'Selasa' THEN 2
                           WHEN 'Rabu' THEN 3
                           WHEN 'Kamis' THEN 4
                           WHEN 'Jumat' THEN 5
                           WHEN 'Sabtu' THEN 6
                           WHEN 'Minggu' THEN 7
                       END,
                       j.waktu_mulai ASC";
$jadwal_lengkap_stmt = $conn->prepare($jadwal_lengkap_sql);
$jadwal_lengkap_stmt->bind_param("s", $dosen_data['nip']);
$jadwal_lengkap_stmt->execute();
$jadwal_lengkap = $jadwal_lengkap_stmt->get_result();

// Nilai Terbaru yang Diinput
$nilai_terbaru_sql = "SELECT dn.*, m.nim, m.nama as nama_mahasiswa, mk.nama_matkul
                      FROM data_nilai dn
                      JOIN mahasiswa m ON dn.mahasiswa_id = m.id
                      JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                      JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                      WHERE j.dosen_nip = ?
                      ORDER BY dn.created_at DESC
                      LIMIT 5";
$nilai_stmt = $conn->prepare($nilai_terbaru_sql);
$nilai_stmt->bind_param("s", $dosen_data['nip']);
$nilai_stmt->execute();
$nilai_terbaru = $nilai_stmt->get_result();

// Distribusi Grade
$grade_sql = "SELECT dn.grade, COUNT(*) as jumlah
              FROM data_nilai dn
              JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
              WHERE j.dosen_nip = ?
              GROUP BY dn.grade
              ORDER BY dn.grade ASC";
$grade_stmt = $conn->prepare($grade_sql);
$grade_stmt->bind_param("s", $dosen_data['nip']);
$grade_stmt->execute();
$grade_distribution = $grade_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">

    <style>

   /* Profile Picture Styles */
.profile-picture-container {
    position: relative;
    display: inline-block;
    margin-left: 20px;
}

.profile-picture {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.profile-picture:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
}

.profile-status {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid #fff;
    background-color: #28a745; /* Online status */
}

.welcome-card {
    background: linear-gradient(135deg, #1a5632 0%, #0e6e01 100%);
    color: white;
    border: none;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

    .welcome-card h3 {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .welcome-card p {
        margin-bottom: 0.3rem;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    .welcome-card .text-warning {
        color: #ffc107 !important;
    }

    .welcome-card i {
        opacity: 0.8;
        margin-right: 5px;
    }
</style>
    
    <style>
        .schedule-table {
            font-size: 0.9rem;
        }
        .schedule-day {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        .current-day {
            background-color: #e3f2fd !important;
            color: #1976d2 !important;
        }
        .time-slot {
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }
        .subject-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .sks-badge {
            background-color: #17a2b8;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .room-info {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .current-time {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
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
      <!-- Welcome Section -->
<div class="card welcome-card mb-4 fade-in">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h3 class="mb-2">Selamat Datang, <span class="text-warning"><?php echo htmlspecialchars($dosen_data['nama']); ?></span>!</h3>
                        <p class="mb-2"><i class="fas fa-id-badge me-2"></i>NIP: <?php echo htmlspecialchars($dosen_data['nip']); ?></p>
                        <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($dosen_data['nama_prodi'] ?? 'Belum memiliki program studi'); ?></p>
                        <p class="mb-0"><i class="fas fa-clock me-2"></i><?php echo $hari_indonesia[$hari_ini]; ?>, <?php echo date('d F Y'); ?></p>
                    </div>
                    <div class="ms-4">
                        <div class="profile-picture-container">
                            <img src="<?php 
                                // Cek apakah foto profil ada di tabel users atau dosen
                                $foto_profil = '';
                                if (!empty($dosen_data['foto_profil'])) {
                                    $foto_profil = '../../../' . htmlspecialchars($dosen_data['foto_profil']);
                                } elseif (!empty($dosen_data['foto'])) { // Jika ada kolom foto di tabel dosen
                                    $foto_profil = '../../../' . htmlspecialchars($dosen_data['foto']);
                                } else {
                                    $foto_profil = '../../../images/default-profile.jpg';
                                }
                                echo $foto_profil; 
                            ?>" 
                            alt="Foto Profil" class="profile-picture rounded-circle shadow">
                            <div class="profile-status bg-success"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex flex-column align-items-end">
                    <div class="mb-2">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-user-shield me-1"></i>
                            Dosen
                        </span>
                    </div>
                    <i class="fas fa-chalkboard-teacher fa-4x opacity-25"></i>
                </div>
            </div>
        </div>
    </div>
</div>
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Mata Kuliah</h6>
                                    <h2 class="mb-0"><?php echo $total_matkul; ?></h2>
                                    <small>Total yang diajar</small>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card-success fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Kelas</h6>
                                    <h2 class="mb-0"><?php echo $total_kelas; ?></h2>
                                    <small>Total kelas aktif</small>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card-info fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Mahasiswa</h6>
                                    <h2 class="mb-0"><?php echo $total_mahasiswa; ?></h2>
                                    <small>Yang sudah dinilai</small>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card-warning fade-in">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Waktu</h6>
                                    <h2 class="mb-0" id="currentTime"><?php echo date('H:i'); ?></h2>  <small>WIB </small>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Jadwal Hari Ini -->
                <div class="col-lg-6 mb-4">
                    <div class="card fade-in">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-day me-2"></i>
                                Jadwal Mengajar Hari Ini
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($jadwal_hari_ini->num_rows > 0): ?>
                                <?php while ($jadwal = $jadwal_hari_ini->fetch_assoc()): ?>
                                <div class="schedule-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($jadwal['nama_matkul']); ?></h6>
                                            <p class="mb-1">
                                                <span class="schedule-time">
                                                    <?php echo $jadwal['waktu_mulai']; ?> - <?php echo $jadwal['waktu_selesai']; ?>
                                                </span>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                Ruang <?php echo htmlspecialchars($jadwal['ruangan']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo $jadwal['sks']; ?> SKS</span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Tidak ada jadwal mengajar hari ini</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Nilai Terbaru -->
                <div class="col-lg-6 mb-4">
                    <div class="card fade-in">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-star me-2"></i>
                                Nilai Terbaru Diinput
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($nilai_terbaru->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Mahasiswa</th>
                                                <th>Mata Kuliah</th>
                                                <th>Nilai</th>
                                                <th>Grade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($nilai = $nilai_terbaru->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo htmlspecialchars($nilai['nama_mahasiswa']); ?></small><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($nilai['nim']); ?></small>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($nilai['nama_matkul']); ?></small></td>
                                                <td><?php echo number_format($nilai['nilai_akhir'], 2); ?></td>
                                                <td><span class="grade-badge grade-<?php echo $nilai['grade']; ?>"><?php echo $nilai['grade']; ?></span></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada nilai yang diinput</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jadwal Mengajar Lengkap -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="card fade-in">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-week me-2"></i>
                                Jadwal Mengajar Lengkap
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($jadwal_lengkap->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover schedule-table">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="10%">Hari</th>
                                                <th width="15%">Waktu</th>
                                                <th width="30%">Mata Kuliah</th>
                                                <th width="10%">SKS</th>
                                                <th width="15%">Ruangan</th>
                                                <th width="20%">Kelas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $current_day = '';
                                            while ($jadwal = $jadwal_lengkap->fetch_assoc()): 
                                                $is_today = ($jadwal['hari'] == $hari_indonesia[$hari_ini]);
                                            ?>
                                            <tr class="<?php echo $is_today ? 'current-day' : ''; ?>">
                                                <td class="schedule-day <?php echo $is_today ? 'current-day' : ''; ?>">
                                                    <?php 
                                                    if ($current_day != $jadwal['hari']) {
                                                        echo htmlspecialchars($jadwal['hari']);
                                                        $current_day = $jadwal['hari'];
                                                    }
                                                    ?>
                                                    <?php if ($is_today): ?>
                                                        <i class="fas fa-arrow-right text-primary ms-1"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="time-slot">
                                                    <?php echo $jadwal['waktu_mulai']; ?> - <?php echo $jadwal['waktu_selesai']; ?>
                                                </td>
                                                <td class="subject-name">
                                                    <?php echo htmlspecialchars($jadwal['nama_matkul']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($jadwal['kode_matkul']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="sks-badge"><?php echo $jadwal['sks']; ?> SKS</span>
                                                </td>
                                                <td class="room-info">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($jadwal['ruangan']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($jadwal['kelas'] ?? 'Regular'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak ada jadwal mengajar</h5>
                                    <p class="text-muted">Belum ada jadwal yang ditetapkan untuk Anda</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribusi Grade -->
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <div class="card fade-in">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Distribusi Grade
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($grade_distribution->num_rows > 0): ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="chart-container">
                                            <canvas id="gradeChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Detail Distribusi:</h6>
                                        <?php 
                                        $grade_distribution->data_seek(0); // Reset pointer
                                        while ($grade = $grade_distribution->fetch_assoc()): 
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                                Grade <?php echo $grade['grade']; ?>
                                            </span>
                                            <span class="fw-bold"><?php echo $grade['jumlah']; ?> mahasiswa</span>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Belum ada data distribusi grade</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="card fade-in">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>
                                Aksi Cepat
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="input-nilai.php" class="btn btn-primary w-100 py-3">
                                        <i class="fas fa-edit fa-2x mb-2 d-block"></i>
                                        Input Nilai
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="jadwal-mengajar.php" class="btn btn-success w-100 py-3">
                                        <i class="fas fa-calendar-alt fa-2x mb-2 d-block"></i>
                                        Lihat Jadwal
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="daftar-mahasiswa.php" class="btn btn-info w-100 py-3">
                                        <i class="fas fa-user-graduate fa-2x mb-2 d-block"></i>
                                        Daftar Mahasiswa
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="materi-perkuliahan.php" class="btn btn-warning w-100 py-3">
                                        <i class="fas fa-book fa-2x mb-2 d-block"></i>
                                        Materi Kuliah
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Script -->
    <script>
        // Update waktu real-time
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }

        // Update setiap detik
        setInterval(updateClock, 1000);

        // Grade Distribution Chart
        <?php if ($grade_distribution->num_rows > 0): ?>
        <?php 
        $grade_distribution->data_seek(0); // Reset pointer
        $labels = [];
        $data = [];
        $colors = [
            'A' => '#28a745',
            'A-' => '#5cb85c', 
            'B+' => '#5bc0de',
            'B' => '#5bc0de',
            'B-' => '#5bc0de',
            'C+' => '#ffc107',
            'C' => '#ffc107',
            'D' => '#fd7e14',
            'E' => '#dc3545'
        ];
        $chartColors = [];
        
        while ($grade = $grade_distribution->fetch_assoc()) {
            $labels[] = 'Grade ' . $grade['grade'];
            $data[] = $grade['jumlah'];
            $chartColors[] = $colors[$grade['grade']] ?? '#6c757d';
        }
        ?>
        
               const ctx = document.getElementById('gradeChart').getContext('2d');
        const gradeChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: <?php echo json_encode($chartColors); ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Animasi fade-in untuk semua elemen dengan class fade-in
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = 1;
                }, 100 * index);
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>