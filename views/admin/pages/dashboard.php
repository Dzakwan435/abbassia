<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/../../../include/config.php';
require_once __DIR__.'/../../../include/admin_functions.php';

// Get dashboard data
$dashboard_data = getDashboardData();
extract($dashboard_data);

// Get current date/time in WIB timezone
date_default_timezone_set('Asia/Jakarta');
$current_date = date('l, d F Y');
$current_time = date('H:i');

// Get program studi data
$prodi_json = json_encode(getProdiDistribution());
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/admin.css">
    <link rel="stylesheet" href="../../../css/dashboard-colors.css">
    
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
            <div class="container-fluid px-0">
                <!-- Welcome Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 text-primary">Selamat Datang, <?php echo $_SESSION['nama'] ?? 'Administrator'; ?></h2>
                        <p class="text-muted">Dashboard Sistem Informasi Akademik</p>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="text-end me-3">
                            <p class="mb-0 fw-bold"><?php echo $current_date; ?></p>
                            <small class="text-muted"><?php echo $current_time; ?> WIB</small>
                        </div>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-primary-light text-primary">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="mb-0"><?php echo number_format($total_mahasiswa); ?></h5>
                                        <p class="text-muted mb-0">Mahasiswa</p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="datamahasiswa.php" class="text-primary text-decoration-none">
                                        <small>Lihat Detail <i class="fas fa-arrow-right ms-1"></i></small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-success-light text-success">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="mb-0"><?php echo number_format($total_dosen); ?></h5>
                                        <p class="text-muted mb-0">Dosen</p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="datadosen.php" class="text-success text-decoration-none">
                                        <small>Lihat Detail <i class="fas fa-arrow-right ms-1"></i></small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-warning-light text-warning">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="mb-0"><?php echo number_format($total_matakuliah); ?></h5>
                                        <p class="text-muted mb-0">Mata Kuliah</p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="datamatakuliah.php" class="text-warning text-decoration-none">
                                        <small>Lihat Detail <i class="fas fa-arrow-right ms-1"></i></small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-info-light text-info">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="mb-0"><?php echo number_format($total_jadwal); ?></h5>
                                        <p class="text-muted mb-0">Jadwal Kuliah</p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <a href="jadwalkuliah.php" class="text-info text-decoration-none">
                                        <small>Lihat Detail <i class="fas fa-arrow-right ms-1"></i></small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Info Cards -->
                <div class="row mt-4">
                    <!-- Weekly Calendar dengan Algoritma Jadwal -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Jadwal Minggu Ini</h5>
                                <a href="jadwalkuliah.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                            <div class="card-body">
                                <!-- Calendar Days -->
                                <div class="d-flex justify-content-center mb-4">
                                    <?php foreach ($week_data['days_short'] as $index => $day): ?>
                                        <div class="calendar-day <?php echo ($index == $week_data['today']) ? 'active' : ''; ?>" data-day-index="<?php echo $index; ?>">
                                            <?php echo $day; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Today's Schedule -->
                                <div class="schedule-container" id="scheduleContainer">
                                    <h6 class="text-muted mb-3">
                                        <i class="fas fa-calendar me-2"></i>
                                        <span id="currentDayLabel"><?php echo $week_data['current_day_name'] . ', ' . date('d M Y'); ?></span>
                                    </h6>
                                    
                                    <div id="scheduleContent">
                                        <?php if (empty($today_schedules)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-calendar-times text-muted mb-2" style="font-size: 2rem;"></i>
                                                <p class="text-muted">Tidak ada jadwal hari ini</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($today_schedules as $schedule): ?>
                                                <div class="d-flex align-items-start mb-3 schedule-item">
                                                    <!-- Time Box -->
                                                    <div class="time-box bg-light p-3 rounded text-center me-3" style="min-width: 80px;">
                                                        <small class="text-muted d-block">Jam</small>
                                                        <strong class="d-block">
                                                            <?php echo formatTime($schedule['waktu_mulai']); ?> - 
                                                            <?php echo formatTime($schedule['waktu_selesai']); ?>
                                                        </strong>
                                                    </div>
                                                    
                                                    <!-- Schedule Details -->
                                                    <div class="schedule-details flex-grow-1">
                                                        <h6 class="mb-1 text-primary">
                                                            <?php echo htmlspecialchars($schedule['nama_matkul']); ?>
                                                        </h6>
                                                        <p class="mb-1 small text-muted">
                                                            <i class="fas fa-user-tie me-1"></i>
                                                            <?php echo htmlspecialchars($schedule['nama_dosen'] ?? 'TBA'); ?>
                                                        </p>
                                                        <small class="text-muted">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php echo htmlspecialchars($schedule['ruangan'] ?? 'Ruang TBA'); ?>
                                                        </small>
                                                        <span class="badge bg-secondary ms-2">
                                                            <?php echo $schedule['sks'] ?? 3; ?> SKS
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="../../../js/script.js"></script>
    <script>
        // Initialize dashboard-specific scripts
        $(document).ready(function() {
            initDashboard();
        });
    </script>
</body>
</html>