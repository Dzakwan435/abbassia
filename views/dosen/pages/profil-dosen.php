<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/../../../include/config.php';

// Get logged in lecturer data
$user_id = $_SESSION['user_id'];
$dosen_sql = "SELECT d.*, ps.nama_prodi, u.username 
              FROM dosen d 
              LEFT JOIN program_studi ps ON d.prodi = ps.kode_prodi 
              LEFT JOIN users u ON d.user_id = u.id
              WHERE d.user_id = ?";
$stmt = $conn->prepare($dosen_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dosen_result = $stmt->get_result();

if ($dosen_result->num_rows === 0) {
    $_SESSION['error_message'] = "Data dosen tidak ditemukan untuk akun ini.";
    header("Location: dashboard-dosen.php");
    exit();
}

$dosen = $dosen_result->fetch_assoc();

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    // Validate and sanitize input
    $nama = trim($_POST['nama']);
    $bidang_keahlian = trim($_POST['bidang_keahlian']);
    $prodi = $_POST['prodi'] ?: NULL;
    $pangkat = trim($_POST['pangkat']);
    $nohp = trim($_POST['nohp']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    
    // Initialize foto_profil with existing value
    $foto_profil = $dosen['foto_profil'];
    
    // Handle file upload
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__.'/../../../uploads/profil_dosen/';
        
        // Create directory if not exists
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $_FILES['foto_profil']['tmp_name']);
        finfo_close($fileInfo);
        
        if (in_array($fileType, $allowedTypes)) {
            // Generate unique filename
            $ext = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $filename = 'dosen_' . $dosen['nip'] . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $targetPath)) {
                // Delete old photo if exists
                if (!empty($dosen['foto_profil']) && file_exists(__DIR__.'/../../../' . $dosen['foto_profil'])) {
                    unlink(__DIR__.'/../../../' . $dosen['foto_profil']);
                }
                
                $foto_profil = 'uploads/profil_dosen/' . $filename;
            }
        }
    }
    
    // Update dosen table
    $update_dosen_sql = "UPDATE dosen SET 
                        nama = ?, 
                        bidang_keahlian = ?, 
                        prodi = ?, 
                        pangkat = ?, 
                        nohp = ?, 
                        email = ?, 
                        alamat = ?,
                        foto_profil = ?
                       WHERE user_id = ?";
    
    $stmt = $conn->prepare($update_dosen_sql);
    $stmt->bind_param("ssssssssi", 
        $nama, 
        $bidang_keahlian, 
        $prodi, 
        $pangkat, 
        $nohp, 
        $email, 
        $alamat,
        $foto_profil,
        $user_id);
    
    // Also update users table for consistency
    $update_user_sql = "UPDATE users SET nama = ? WHERE id = ?";
    $stmt_user = $conn->prepare($update_user_sql);
    $stmt_user->bind_param("si", $nama, $user_id);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $stmt->execute();
        $stmt_user->execute();
        $conn->commit();
        
        $_SESSION['success_message'] = "Profil berhasil diperbarui!";
        // Update session nama if changed
        $_SESSION['nama'] = $nama;
        // Refresh data
        header("Location: profil-dosen.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal memperbarui profil: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Dosen - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../css/styles.css">
    
    <style>
        .profile-picture {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }.profile-header {
            background: linear-gradient(135deg,#1a5632 0%,rgb(14, 110, 1) 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
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
            <!-- Profile Header -->
            <div class="profile-header d-flex align-items-center mb-4">
                <div class="me-4">
                    <img src="<?php echo !empty($dosen['foto_profil']) ? '../../../' . htmlspecialchars($dosen['foto_profil']) : '../../../images/default-profile.jpg'; ?>" 
                         alt="Profile Picture" class="profile-picture">
                </div>
                <div>
                    <h1 class="mb-1"><?php echo htmlspecialchars($dosen['nama']); ?></h1>
                    <p class="mb-1"><i class="fas fa-id-badge me-2"></i><?php echo htmlspecialchars($dosen['nip']); ?></p>
                    <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($dosen['nama_prodi'] ?? 'Belum diatur'); ?></p>
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
            
            <!-- Profile Form Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Profil
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($dosen['nip']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($dosen['nama']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bidang Keahlian</label>
                                <input type="text" class="form-control" name="bidang_keahlian" value="<?php echo htmlspecialchars($dosen['bidang_keahlian']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi</label>
                                <select class="form-select" name="prodi">
                                    <option value="">Pilih Program Studi</option>
                                    <?php 
                                    $prodi_result->data_seek(0); // Reset pointer
                                    while($prodi_row = $prodi_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $prodi_row['kode_prodi']; ?>" <?php echo ($dosen['prodi'] == $prodi_row['kode_prodi']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pangkat / Jabatan</label>
                                <input type="text" class="form-control" name="pangkat" value="<?php echo htmlspecialchars($dosen['pangkat']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="nohp" value="<?php echo htmlspecialchars($dosen['nohp']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($dosen['email']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto Profil</label>
                                <input type="file" class="form-control" name="foto_profil" accept="image/*">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat Lengkap</label>
                                <textarea class="form-control" name="alamat" rows="3"><?php echo htmlspecialchars($dosen['alamat']); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="dashboard-dosen.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" name="update_profil" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Security Card -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Keamanan Akun
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($dosen['username']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Dosen" readonly>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Ubah Password</h6>
                                    <p class="small text-muted mb-0">Gunakan password yang kuat dan unik</p>
                                </div>
                                <a href="ubah-password.php" class="btn btn-outline-info">
                                    <i class="fas fa-key me-2"></i>Ubah Password
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../js/script.js"></script>
    
    <script>
        // Preview image before upload
        document.querySelector('input[name="foto_profil"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.querySelector('.profile-picture').src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>