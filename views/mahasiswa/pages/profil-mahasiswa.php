<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__.'/../../../include/config.php';

// Get logged in student data
$user_id = $_SESSION['user_id'];
$mahasiswa_sql = "SELECT m.*, ps.nama_prodi, u.username 
              FROM mahasiswa m 
              LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
              LEFT JOIN users u ON m.user_id = u.id
              WHERE m.user_id = ?";
$stmt = $conn->prepare($mahasiswa_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$mahasiswa_result = $stmt->get_result();

if ($mahasiswa_result->num_rows === 0) {
    $_SESSION['error_message'] = "Data mahasiswa tidak ditemukan untuk akun ini.";
    header("Location: dashboard-mahasiswa.php");
    exit();
}

$mahasiswa = $mahasiswa_result->fetch_assoc();

// Get program studi for dropdown
$prodi_sql = "SELECT kode_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi ASC";
$prodi_result = $conn->query($prodi_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    // Validate and sanitize input
    $nama = trim($_POST['nama']);
    $nim = trim($_POST['nim']);
    $prodi = $_POST['prodi'] ?: NULL;
    $nohp = trim($_POST['nohp']);
    $email = trim($_POST['email']);
    $alamat = trim($_POST['alamat']);
    $semester_aktif = intval($_POST['semester_aktif']);
    
    // Initialize foto_profil with existing value
    $foto_profil = $mahasiswa['foto_profil'];
    
    // Handle file upload
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__.'/../../../uploads/profil_mahasiswa/';
        
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
            $filename = 'mahasiswa_' . $mahasiswa['nim'] . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $targetPath)) {
                // Delete old photo if exists
                if (!empty($mahasiswa['foto_profil']) && file_exists(__DIR__.'/../../../' . $mahasiswa['foto_profil'])) {
                    unlink(__DIR__.'/../../../' . $mahasiswa['foto_profil']);
                }
                
                $foto_profil = 'uploads/profil_mahasiswa/' . $filename;
            }
        }
    }
    
    // Update mahasiswa table
    $update_mahasiswa_sql = "UPDATE mahasiswa SET 
                        nim = ?, 
                        nama = ?, 
                        prodi = ?, 
                        alamat = ?, 
                        nohp = ?, 
                        email = ?, 
                        semester_aktif = ?,
                        foto_profil = ?
                       WHERE user_id = ?";
    
    $stmt = $conn->prepare($update_mahasiswa_sql);
    $stmt->bind_param("ssssssiss", 
        $nim, 
        $nama, 
        $prodi, 
        $alamat, 
        $nohp, 
        $email,
        $semester_aktif,
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
        header("Location: profil-mahasiswa.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Gagal memperbarui profil: " . $e->getMessage();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_message'] = "Semua field password harus diisi!";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = "Password baru dan konfirmasi password tidak cocok!";
    } elseif (strlen($new_password) < 8) {
        $_SESSION['error_message'] = "Password baru harus minimal 8 karakter!";
    } else {
        // Verify current password
        $check_password_sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($check_password_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($update_password_sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Password berhasil diubah!";
                // Redirect to prevent form resubmission
                header("Location: profil-mahasiswa.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Gagal mengubah password: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Password saat ini salah!";
        }
    }
    
    // Redirect to show error message
    header("Location: profil-mahasiswa.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Mahasiswa - Portal Akademik</title>
    
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
        }
        .profile-header {
            background: linear-gradient(135deg, #1a5632 0%, rgb(14, 110, 1) 100%);
            color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
        .password-toggle {
            cursor: pointer;
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
            <!-- Profile Header -->
            <div class="profile-header d-flex align-items-center mb-4">
                <div class="me-4">
                    <img src="<?php echo !empty($mahasiswa['foto_profil']) ? '../../../' . htmlspecialchars($mahasiswa['foto_profil']) : '../../../images/default-profile.jpg'; ?>" 
                         alt="Profile Picture" class="profile-picture">
                </div>
                <div>
                    <h1 class="mb-1"><?php echo htmlspecialchars($mahasiswa['nama']); ?></h1>
                    <p class="mb-1"><i class="fas fa-id-badge me-2"></i><?php echo htmlspecialchars($mahasiswa['nim']); ?></p>
                    <p class="mb-1"><i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($mahasiswa['nama_prodi'] ?? 'Belum diatur'); ?></p>
                    <p class="mb-1"><i class="fas fa-layer-group me-2"></i>Semester <?php echo htmlspecialchars($mahasiswa['semester_aktif']); ?></p>
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
                                <label class="form-label">NIM</label>
                                <input type="text" class="form-control" name="nim" value="<?php echo htmlspecialchars($mahasiswa['nim']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($mahasiswa['nama']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Program Studi</label>
                                <select class="form-select" name="prodi">
                                    <option value="">Pilih Program Studi</option>
                                    <?php 
                                    $prodi_result->data_seek(0); // Reset pointer
                                    while($prodi_row = $prodi_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $prodi_row['kode_prodi']; ?>" <?php echo ($mahasiswa['prodi'] == $prodi_row['kode_prodi']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prodi_row['nama_prodi']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Semester Aktif</label>
                                <select class="form-select" name="semester_aktif">
                                    <?php for ($i = 1; $i <= 14; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($mahasiswa['semester_aktif'] == $i) ? 'selected' : ''; ?>>
                                            Semester <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="nohp" value="<?php echo htmlspecialchars($mahasiswa['nohp']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($mahasiswa['email']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto Profil</label>
                                <input type="file" class="form-control" name="foto_profil" accept="image/*">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat Lengkap</label>
                                <textarea class="form-control" name="alamat" rows="3"><?php echo htmlspecialchars($mahasiswa['alamat']); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="dashboard-mahasiswa.php" class="btn btn-outline-secondary">
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
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($mahasiswa['username']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="Mahasiswa" readonly>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mb-3"><i class="fas fa-key me-2"></i>Ubah Password</h5>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Password Saat Ini</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="current_password" id="current_password" required>
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimal 8 karakter</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="8">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Password Baru
                            </button>
                        </div>
                    </form>
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

        // Add password visibility toggle functionality
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.querySelector(`[data-target="${inputId}"] i`);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Add event listeners for password toggles
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                togglePasswordVisibility(target);
            });
        });
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>