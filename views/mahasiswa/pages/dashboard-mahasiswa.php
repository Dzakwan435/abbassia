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

$user_id = $_SESSION['user_id'];

// Ambil data mahasiswa termasuk foto profil
$mahasiswa_sql = "SELECT m.*, ps.nama_prodi 
                  FROM mahasiswa m
                  LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi
                  WHERE m.user_id = ?";
$mahasiswa_stmt = $conn->prepare($mahasiswa_sql);
$mahasiswa_stmt->bind_param("i", $user_id);
$mahasiswa_stmt->execute();
$mahasiswa_result = $mahasiswa_stmt->get_result();
$mahasiswa_data = $mahasiswa_result->fetch_assoc();

if (!$mahasiswa_data) {
    $_SESSION['error_message'] = "ga ada jir";
    header("Location: ../../login.php");
    exit();
}

// 3. IP (dari nilai yang sudah ada)
$ip_sql = "SELECT AVG(
                CASE 
                    WHEN dn.grade = 'A' THEN 4.0
                    WHEN dn.grade = 'A-' THEN 3.7
                    WHEN dn.grade = 'B+' THEN 3.3
                    WHEN dn.grade = 'B' THEN 3.0
                    WHEN dn.grade = 'B-' THEN 2.7
                    WHEN dn.grade = 'C+' THEN 2.3
                    WHEN dn.grade = 'C' THEN 2.0
                    WHEN dn.grade = 'D' THEN 1.0
                    ELSE 0.0
                END
            ) as ip
            FROM data_nilai dn
            WHERE dn.mahasiswa_id = ?";
$ip_stmt = $conn->prepare($ip_sql);
$ip_stmt->bind_param("i", $mahasiswa_data['id']);
$ip_stmt->execute();
$ip = $ip_stmt->get_result()->fetch_assoc()['ip'] ?? 0;

// 4. Jumlah Mata Kuliah yang Sudah Dinilai
$nilai_sql = "SELECT COUNT(*) as total_nilai 
              FROM data_nilai dn
              WHERE dn.mahasiswa_id = ?";
$nilai_stmt = $conn->prepare($nilai_sql);
$nilai_stmt->bind_param("i", $mahasiswa_data['id']);
$nilai_stmt->execute();
$total_nilai = $nilai_stmt->get_result()->fetch_assoc()['total_nilai'];

// Jadwal Kuliah Hari Ini
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

$jadwal_hari_ini_sql = "SELECT j.*, mk.nama_matkul, mk.sks, d.nama as nama_dosen
                        FROM jadwal_kuliah j
                        JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                        JOIN dosen d ON j.dosen_nip = d.nip
                        JOIN krs k ON j.id = k.jadwal_id
                        WHERE k.mahasiswa_id = ? AND j.hari = ? AND k.status = 'aktif'
                        ORDER BY j.waktu_mulai ASC";
$jadwal_stmt = $conn->prepare($jadwal_hari_ini_sql);
$jadwal_stmt->bind_param("is", $mahasiswa_data['id'], $hari_indonesia[$hari_ini]);
$jadwal_stmt->execute();
$jadwal_hari_ini = $jadwal_stmt->get_result();

// Nilai Terbaru
$nilai_terbaru_sql = "SELECT dn.*, mk.nama_matkul, mk.sks, d.nama as nama_dosen
                      FROM data_nilai dn
                      JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                      JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                      JOIN dosen d ON j.dosen_nip = d.nip
                      WHERE dn.mahasiswa_id = ?
                      ORDER BY dn.created_at DESC
                      LIMIT 5";
$nilai_terbaru_stmt = $conn->prepare($nilai_terbaru_sql);
$nilai_terbaru_stmt->bind_param("i", $mahasiswa_data['id']);
$nilai_terbaru_stmt->execute();
$nilai_terbaru = $nilai_terbaru_stmt->get_result();

// Grafik Progress Nilai per Semester (contoh data)
$progress_sql = "SELECT mk.nama_matkul, dn.nilai_akhir, dn.grade
                 FROM data_nilai dn
                 JOIN jadwal_kuliah j ON dn.jadwal_id = j.id
                 JOIN mata_kuliah mk ON j.kode_matkul = mk.kode_matkul
                 WHERE dn.mahasiswa_id = ?
                 ORDER BY dn.created_at DESC";
$progress_stmt = $conn->prepare($progress_sql);
$progress_stmt->bind_param("i", $mahasiswa_data['id']);
$progress_stmt->execute();
$progress_nilai = $progress_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - Portal Akademik</title>

    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.0.1/dist/chart.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
        .profile-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stats-card-info {
            background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
            color: white;
            border-radius: 10px;
        }

        .stats-card-warning {
            background: linear-gradient(135deg, #f46b45 0%, #eea849 100%);
            color: white;
            border-radius: 10px;
        }

        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.3;
        }

        .schedule-item {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: transform 0.2s;
        }

        .schedule-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .schedule-time {
            font-weight: bold;
            color: #0d6efd;
        }

        .grade-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
        }

        .grade-A {
            background-color: #28a745;
        }

        .grade-B {
            background-color: #17a2b8;
        }

        .grade-C {
            background-color: #ffc107;
        }

        .grade-D {
            background-color: #fd7e14;
        }

        .grade-E {
            background-color: #dc3545;
        }

        .fade-in {
            opacity: 0;
            transition: opacity 0.5s ease-in;
        }

        .chart-container {
            position: relative;
            height: 300px;
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
        <!-- Welcome Section -->
        <div class="card welcome-card mb-4 fade-in">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h3 class="mb-2">Selamat Datang, <?php echo htmlspecialchars($mahasiswa_data['nama']); ?>!</h3>
                        <p class="mb-2">NIM: <?php echo htmlspecialchars($mahasiswa_data['nim']); ?></p>
                        <p class="mb-2">Program Studi: <?php echo htmlspecialchars($mahasiswa_data['nama_prodi'] ?? 'Belum diset'); ?></p>
                        <p class="mb-0">Hari ini adalah <?php echo $hari_indonesia[$hari_ini]; ?>, <?php echo date('d F Y'); ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php
                        // Path foto profil
                        $profile_path = (!empty($mahasiswa_data['foto_profil']) && file_exists('../../../' . $mahasiswa_data['foto_profil']))
                            ? '../../../' . $mahasiswa_data['foto_profil']
                            : '../../../images/default-profile.jpg';

                        echo '<img src="' . htmlspecialchars($profile_path) . '" 
                             class="profile-img"
                             alt="Foto Profil">';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards (Hanya IP dan Nilai) -->
        <div class="row mb-4">
            <div class="col-lg-6 col-md-6 mb-3">
                <div class="card stats-card-info fade-in">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">IP</h6>
                                <h2 class="mb-0"><?php echo number_format($ip, 2); ?></h2>
                                <small>Indeks Prestasi</small>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 mb-3">
                <div class="card stats-card-warning fade-in">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Nilai</h6>
                                <h2 class="mb-0"><?php echo $total_nilai; ?></h2>
                                <small>Sudah keluar</small>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-star"></i>
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
                            Jadwal Kuliah Hari Ini
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
                                            </small><br>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($jadwal['nama_dosen']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo $jadwal['sks']; ?> SKS</span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada jadwal kuliah hari ini</p>
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
                            Nilai Terbaru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($nilai_terbaru->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Mata Kuliah</th>
                                            <th>Dosen</th>
                                            <th>Nilai</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($nilai = $nilai_terbaru->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <small><?php echo htmlspecialchars($nilai['nama_matkul']); ?></small><br>
                                                    <small class="text-muted"><?php echo $nilai['sks']; ?> SKS</small>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($nilai['nama_dosen']); ?></small></td>
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
                                <p class="text-muted">Belum ada nilai yang keluar</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Akademik -->
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="card fade-in">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Progress Nilai Akademik
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($progress_nilai->num_rows > 0): ?>
                            <div class="chart-container">
                                <canvas id="progressChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada data progress nilai</p>
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
                                <a href="jadwal-kuliah.php" class="btn btn-primary w-100 py-3">
                                    <i class="fas fa-calendar-alt fa-2x mb-2 d-block"></i>
                                    Lihat Jadwal
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="nilai.php" class="btn btn-success w-100 py-3">
                                    <i class="fas fa-star fa-2x mb-2 d-block"></i>
                                    Cek Nilai
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="krs.php" class="btn btn-info w-100 py-3">
                                    <i class="fas fa-list-alt fa-2x mb-2 d-block"></i>
                                    KRS
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="transkrip.php" class="btn btn-warning w-100 py-3">
                                    <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>
                                    Transkrip
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
        // Progress Chart
        <?php if ($progress_nilai->num_rows > 0): ?>
            <?php
            $progress_nilai->data_seek(0); // Reset pointer
            $labels = [];
            $data = [];

            while ($progress = $progress_nilai->fetch_assoc()) {
                $labels[] = substr($progress['nama_matkul'], 0, 15) . '...'; // Potong nama yang panjang
                $data[] = $progress['nilai_akhir'];
            }
            ?>

            const ctx = document.getElementById('progressChart').getContext('2d');
            const progressChart = new Chart(ctx, {
                type: 'line', // Changed from 'bar' to 'line'
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Nilai Akhir',
                        data: <?php echo json_encode($data); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)', // Added transparency
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4, // Adds smooth curves
                        fill: true, // Fill area under the line
                        pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    let value = context.raw;
                                    let grade = '';
                                    if (value >= 85) grade = 'A';
                                    else if (value >= 80) grade = 'A-';
                                    else if (value >= 75) grade = 'B+';
                                    else if (value >= 70) grade = 'B';
                                    else if (value >= 65) grade = 'B-';
                                    else if (value >= 60) grade = 'C+';
                                    else if (value >= 55) grade = 'C';
                                    else if (value >= 40) grade = 'D';
                                    else grade = 'E';
                                    return `Grade: ${grade}`;
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        <?php endif; ?>

        // Animasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = 1;
                }, index * 200);
            });
        });
    </script>
</body>

</html>