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

// Ambil data mahasiswa
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
    $_SESSION['error_message'] = "Data mahasiswa tidak ditemukan";
    header("Location: dashboard-mahasiswa.php");
    exit();
}

$mahasiswa_id = $mahasiswa_data['id'];

// Fungsi untuk generate kode pembayaran
function generateKodePembayaran($mahasiswa_id)
{
    return "UKT" . date('Ymd') . str_pad($mahasiswa_id, 4, '0', STR_PAD_LEFT) . rand(100, 999);
}

// Fungsi untuk generate virtual account
function generateVirtualAccount($nim)
{
    $prefix = "88"; // Kode bank
    $middle = "800"; // Kode institusi
    $unique_id = substr($nim, -6); // 6 digit terakhir NIM
    return $prefix . $middle . $unique_id;
}

// Cek atau buat virtual account
$nomor_va = generateVirtualAccount($mahasiswa_data['nim']);

// Ambil data UKT untuk semester ini
$current_year = date('Y');
$tahun_ajaran = ($current_year) . '/' . ($current_year + 1);
$semester = (date('n') > 6) ? 'Ganjil' : 'Genap';

// Ambil tarif UKT berdasarkan golongan mahasiswa
$tarif_ukt_sql = "SELECT ut.nominal 
                  FROM ukt_tarif ut 
                  WHERE ut.prodi = ? AND ut.golongan = ?";
$tarif_stmt = $conn->prepare($tarif_ukt_sql);
$tarif_stmt->bind_param("ss", $mahasiswa_data['prodi'], $mahasiswa_data['golongan_ukt']);
$tarif_stmt->execute();
$tarif_result = $tarif_stmt->get_result();
$tarif_data = $tarif_result->fetch_assoc();

if (!$tarif_data) {
    $_SESSION['error_message'] = "Tarif UKT untuk golongan Anda belum ditetapkan";
    header("Location: dashboard-mahasiswa.php");
    exit();
}

$nominal_ukt = $tarif_data['nominal'];

// Cek apakah sudah ada pembayaran UKT untuk semester ini
$pembayaran_sql = "SELECT * FROM pembayaran_ukt 
                   WHERE mahasiswa_id = ? AND tahun_ajaran = ? AND semester = ?";
$pembayaran_stmt = $conn->prepare($pembayaran_sql);
$pembayaran_stmt->bind_param("iss", $mahasiswa_id, $tahun_ajaran, $semester);
$pembayaran_stmt->execute();
$pembayaran_result = $pembayaran_stmt->get_result();
$pembayaran_data = $pembayaran_result->fetch_assoc();

// Jika belum ada pembayaran, buat data pembayaran baru dengan status pending
if (!$pembayaran_data) {
    $kode_pembayaran = generateKodePembayaran($mahasiswa_id);
    $batas_pembayaran = date('Y-m-d H:i:s', strtotime('+7 days'));

    $insert_pembayaran_sql = "INSERT INTO pembayaran_ukt 
                             (mahasiswa_id, tahun_ajaran, semester, golongan_ukt, nominal, 
                              kode_pembayaran, batas_pembayaran, status, metode_pembayaran) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'virtual_account')";
    $insert_stmt = $conn->prepare($insert_pembayaran_sql);
    $insert_stmt->bind_param(
        "isssdss",
        $mahasiswa_id,
        $tahun_ajaran,
        $semester,
        $mahasiswa_data['golongan_ukt'],
        $nominal_ukt,
        $kode_pembayaran,
        $batas_pembayaran
    );

    if ($insert_stmt->execute()) {
        $pembayaran_id = $conn->insert_id;
        $pembayaran_data = [
            'id' => $pembayaran_id,
            'kode_pembayaran' => $kode_pembayaran,
            'nominal' => $nominal_ukt,
            'status' => 'pending',
            'batas_pembayaran' => $batas_pembayaran,
            'golongan_ukt' => $mahasiswa_data['golongan_ukt'],
            'metode_pembayaran' => 'virtual_account'
        ];

        // Set session success message
        $_SESSION['success_message'] = "Tagihan UKT untuk semester {$semester} {$tahun_ajaran} telah dibuat. Silakan lakukan pembayaran melalui Virtual Account.";
    } else {
        $_SESSION['error_message'] = "Gagal membuat data pembayaran: " . $conn->error;
    }
}

// Simulasi pembayaran otomatis (dalam implementasi real, ini akan dipanggil oleh payment gateway)
if (isset($_GET['simulate_payment']) && $_GET['simulate_payment'] == 'success' && $pembayaran_data) {
    $update_sql = "UPDATE pembayaran_ukt SET status = 'paid', tanggal_bayar = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $pembayaran_data['id']);

    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Pembayaran UKT berhasil! Status telah diperbarui menjadi LUNAS.";
        header("Location: ukt.php");
        exit();
    }
}

// Ambil riwayat pembayaran
$riwayat_sql = "SELECT * FROM pembayaran_ukt 
                WHERE mahasiswa_id = ? 
                ORDER BY created_at DESC";
$riwayat_stmt = $conn->prepare($riwayat_sql);
$riwayat_stmt->bind_param("i", $mahasiswa_id);
$riwayat_stmt->execute();
$riwayat_result = $riwayat_stmt->get_result();

// Hitung statistik untuk dashboard
$stats_sql = "SELECT 
              COUNT(*) as total_tagihan,
              SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as sudah_bayar,
              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
              SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as gagal,
              SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
              SUM(nominal) as total_tagihan_amount,
              SUM(CASE WHEN status = 'paid' THEN nominal ELSE 0 END) as total_terbayar
              FROM pembayaran_ukt 
              WHERE mahasiswa_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $mahasiswa_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Hitung belum bayar (total - sudah bayar - pending)
$stats['belum_bayar'] = $stats['total_tagihan'] - $stats['sudah_bayar'] - $stats['pending'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran UKT - Portal Akademik</title>

    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
    <style>
        /* Educational Theme Colors */
        :root {
            --edu-primary: #1a5632;
            --edu-secondary: #2e8b57;
            --edu-accent: #f8f9fa;
            --success-gradient: linear-gradient(135deg, #28a745, #218838);
            --warning-gradient: linear-gradient(135deg, #ffc107, #d39e00);
            --danger-gradient: linear-gradient(135deg, #dc3545, #bd2130);
            --info-gradient: linear-gradient(135deg, #17a2b8, #138496);
        }

        /* Enhanced Info Box */
        .info-box {
            background: linear-gradient(to right, #ffffff, var(--edu-accent));
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
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

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            margin-bottom: 1rem;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stats-icon.total {
            background: var(--info-gradient);
        }

        .stats-icon.paid {
            background: var(--success-gradient);
        }

        .stats-icon.unpaid {
            background: var(--danger-gradient);
        }

        .stats-icon.pending {
            background: var(--warning-gradient);
        }

        /* Status Badges */
        .badge-lunas {
            background: var(--success-gradient);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
        }

        .badge-belum-bayar {
            background: var(--danger-gradient);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
        }

        .badge-pending {
            background: var(--warning-gradient);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
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
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Virtual Account Box */
        .virtual-account-box {
            background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .va-number {
            font-size: 1.8rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 1rem 0;
            font-family: 'Courier New', monospace;
        }

        /* Payment Instructions */
        .payment-instructions {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .instruction-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .step-number {
            background: var(--edu-primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
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

        /* Card Styling */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--edu-primary), var(--edu-secondary));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.25rem;
            border-bottom: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .info-box {
                padding: 1rem;
            }

            .stats-card {
                margin-bottom: 1rem;
            }

            .virtual-account-box {
                padding: 1.5rem;
            }

            .va-number {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header d-flex align-items-center">
            <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                <span class="fw-bold">U</span>
            </div>
            <span class="sidebar-brand">PORTALSIA</span>
        </div>

        <div class="menu-category">MENU MAHASISWA</div>
        <div class="menu">
            <a href="dashboard-mahasiswa.php" class="menu-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="profil-mahasiswa.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>Profil Mahasiswa</span>
            </a>
            <a href="jadwal-kuliah.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Kuliah</span>
            </a>
            <a href="krs.php" class="menu-item">
                <i class="fas fa-edit"></i>
                <span>KRS</span>
            </a>
            <a href="nilai.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>KHS</span>
            </a>
            <a href="materi.php" class="menu-item">
                <i class="fas fa-book"></i>
                <span>Materi Kuliah</span>
            </a>
            <a href="ukt.php" class="menu-item active">
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
            <h2 class="mb-0 text-primary">Pembayaran UKT</h2>
            <div class="text-muted">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-user-graduate me-2"></i>Informasi Mahasiswa</h5>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></strong><br>
                        NIM: <?php echo htmlspecialchars($mahasiswa_data['nim']); ?><br>
                        Prodi: <?php echo strtoupper(htmlspecialchars($mahasiswa_data['nama_prodi'])); ?><br>
                        Semester: <?php echo $mahasiswa_data['semester_aktif']; ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <h5><i class="fas fa-money-bill-wave me-2"></i>Total Tagihan</h5>
                    <p class="mb-0">
                        <strong class="text-danger fs-4">Rp <?php echo number_format($stats['total_tagihan_amount'], 0, ',', '.'); ?></strong>
                    </p>
                </div>
                <div class="col-md-4">
                    <h5><i class="fas fa-check-circle me-2"></i>Total Terbayar</h5>
                    <p class="mb-0">
                        <strong class="text-success fs-4">Rp <?php echo number_format($stats['total_terbayar'], 0, ',', '.'); ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card fade-in">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon total me-3">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Tagihan</h6>
                            <h3 class="mb-0"><?php echo $stats['total_tagihan']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in" style="animation-delay: 0.1s;">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon paid me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Sudah Bayar</h6>
                            <h3 class="mb-0"><?php echo $stats['sudah_bayar']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in" style="animation-delay: 0.2s;">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon unpaid me-3">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Belum Bayar</h6>
                            <h3 class="mb-0"><?php echo $stats['belum_bayar']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card fade-in" style="animation-delay: 0.3s;">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon pending me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                        </div>
                    </div>
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
        <?php unset($_SESSION['success_message']);
        endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php unset($_SESSION['error_message']);
        endif; ?>

        <!-- Virtual Account Information -->
        <div class="virtual-account-box fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4><i class="fas fa-university me-2"></i>Virtual Account</h4>
                    <p class="mb-2">Gunakan Virtual Account berikut untuk pembayaran UKT</p>
                    <div class="va-number"><?php echo chunk_split($nomor_va, 4, ' '); ?></div>
                    <p class="mb-0">
                        <strong>Bank:</strong> BANK EXAMPLE |
                        <strong>Nama:</strong> <?php echo htmlspecialchars($mahasiswa_data['nama']); ?> |
                        <strong>Nominal:</strong> Rp <?php echo number_format($nominal_ukt, 0, ',', '.'); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-credit-card fa-5x opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="payment-instructions fade-in">
            <h4><i class="fas fa-info-circle me-2"></i>Cara Pembayaran</h4>
            <div class="instruction-step">
                <div class="step-number">1</div>
                <div>
                    <strong>Transfer melalui ATM/Internet Banking/Mobile Banking</strong>
                    <p class="mb-0 text-muted">Pilih menu Transfer > Ke Rekening Bank Lain > Masukkan kode bank 888 (BANK EXAMPLE) > Masukkan Virtual Account <?php echo $nomor_va; ?> > Konfirmasi pembayaran</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">2</div>
                <div>
                    <strong>Pembayaran Otomatis</strong>
                    <p class="mb-0 text-muted">Setelah transfer dilakukan, sistem akan secara otomatis memverifikasi pembayaran Anda dalam 1-5 menit</p>
                </div>
            </div>
            <div class="instruction-step">
                <div class="step-number">3</div>
                <div>
                    <strong>Konfirmasi</strong>
                    <p class="mb-0 text-muted">Status pembayaran akan berubah otomatis menjadi "LUNAS" setelah pembayaran terverifikasi</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Informasi UKT -->
            <div class="col-lg-6">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi UKT</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>NIM</strong><br>
                                <?php echo htmlspecialchars($mahasiswa_data['nim']); ?>
                            </div>
                            <div class="col-6">
                                <strong>Program Studi</strong><br>
                                <?php echo htmlspecialchars($mahasiswa_data['nama_prodi']); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Golongan UKT</strong><br>
                                <?php echo htmlspecialchars($mahasiswa_data['golongan_ukt']); ?>
                            </div>
                            <div class="col-6">
                                <strong>Status</strong><br>
                                <?php
                                $status_class = '';
                                $status_text = '';
                                if ($pembayaran_data) {
                                    switch ($pembayaran_data['status']) {
                                        case 'paid':
                                            $status_class = 'badge-lunas';
                                            $status_text = 'LUNAS';
                                            break;
                                        case 'pending':
                                            $status_class = 'badge-pending';
                                            $status_text = 'MENUNGGU PEMBAYARAN';
                                            break;
                                        case 'failed':
                                            $status_class = 'badge-belum-bayar';
                                            $status_text = 'GAGAL';
                                            break;
                                        case 'expired':
                                            $status_class = 'badge-belum-bayar';
                                            $status_text = 'KADALUARSA';
                                            break;
                                    }
                                } else {
                                    $status_class = 'badge-belum-bayar';
                                    $status_text = 'BELUM BAYAR';
                                }
                                ?>
                                <span class="<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Nominal UKT</strong><br>
                                <h3 class="text-primary">Rp <?php echo number_format($nominal_ukt, 0, ',', '.'); ?></h3>
                            </div>
                        </div>

                        <!-- Informasi tambahan untuk pembayaran pending -->
                        <?php if ($pembayaran_data && $pembayaran_data['status'] == 'pending'): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Pembayaran Otomatis:</strong> Lakukan transfer ke Virtual Account di atas. Sistem akan otomatis memverifikasi pembayaran Anda.
                            </div>
                        <?php endif; ?>

                        <!-- Demo button untuk simulasi pembayaran (Hanya untuk development) -->
                        <?php if ($pembayaran_data && $pembayaran_data['status'] == 'pending'): ?>
                            <div class="mt-3">
                                <small class="text-muted">Demo: </small>
                                <a href="ukt.php?simulate_payment=success" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-bolt me-1"></i>Simulasi Pembayaran Berhasil
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Detail Pembayaran dan Riwayat -->
            <div class="col-lg-6">
                <?php if ($pembayaran_data): ?>
                    <div class="card fade-in">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Detail Pembayaran</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6"><strong>Kode Pembayaran:</strong></div>
                                <div class="col-6"><?php echo $pembayaran_data['kode_pembayaran']; ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>Batas Pembayaran:</strong></div>
                                <div class="col-6"><?php echo date('d M Y H:i', strtotime($pembayaran_data['batas_pembayaran'])); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>Metode:</strong></div>
                                <div class="col-6">VIRTUAL ACCOUNT (OTOMATIS)</div>
                            </div>
                            <?php if ($pembayaran_data['tanggal_bayar']): ?>
                                <div class="row mb-2">
                                    <div class="col-6"><strong>Tanggal Bayar:</strong></div>
                                    <div class="col-6"><?php echo date('d M Y H:i', strtotime($pembayaran_data['tanggal_bayar'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Riwayat Transaksi -->
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Periode</th>
                                        <th>Nominal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($riwayat_result->num_rows > 0): ?>
                                        <?php while ($transaksi = $riwayat_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $transaksi['kode_pembayaran']; ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo $transaksi['tahun_ajaran']; ?> - <?php echo $transaksi['semester']; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-primary">
                                                        Rp <?php echo number_format($transaksi['nominal'], 0, ',', '.'); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_transaksi = '';
                                                    switch ($transaksi['status']) {
                                                        case 'paid':
                                                            $status_transaksi = 'badge-lunas';
                                                            break;
                                                        case 'pending':
                                                            $status_transaksi = 'badge-pending';
                                                            break;
                                                        case 'failed':
                                                            $status_transaksi = 'badge-belum-bayar';
                                                            break;
                                                        case 'expired':
                                                            $status_transaksi = 'badge-belum-bayar';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="<?php echo $status_transaksi; ?>">
                                                        <?php echo strtoupper($transaksi['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">
                                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Belum ada riwayat pembayaran</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);

            // Auto-refresh halaman setiap 30 detik untuk update status pembayaran
            setInterval(function() {
                $.get('ukt.php', function(data) {
                    // Cek jika status berubah
                    if ($(data).find('.badge-lunas').length > 0 && $('.badge-lunas').length === 0) {
                        location.reload();
                    }
                });
            }, 30000); // Refresh setiap 30 detik
        });
    </script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>