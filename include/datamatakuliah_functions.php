<?php
// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add mata kuliah process
if (isset($_POST['tambah_matkul'])) {
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $nama_matkul = clean_input($_POST['nama_matkul']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode_matkul) || empty($nama_matkul) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Semua field harus diisi";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Additional validation
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: datamatakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Check if kode_matkul already exists
    $check_sql = "SELECT kode_matkul FROM mata_kuliah WHERE kode_matkul = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $kode_matkul);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "Kode mata kuliah sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_stmt->close();

    // Verify if prodi exists in program_studi table
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction
        $conn->begin_transaction();
        
        $sql = "INSERT INTO mata_kuliah (kode_matkul, nama_matkul, sks, semester, prodi) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiis", $kode_matkul, $nama_matkul, $sks, $semester, $prodi);
        
        if ($stmt->execute()) {
            // Log the action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'ADD', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Added new mata kuliah: {$nama_matkul}";
                $log_stmt->bind_param("iss", $user_id, $kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Mata kuliah baru berhasil ditambahkan";
            $conn->commit();
        } else {
            throw new Exception("Gagal menambahkan mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}

// Edit mata kuliah process
if (isset($_POST['edit_matkul'])) {
    $old_kode_matkul = clean_input($_POST['old_kode_matkul']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $nama_matkul = clean_input($_POST['nama_matkul']);
    $sks = (int)clean_input($_POST['sks']);
    $semester = (int)clean_input($_POST['semester']);
    $prodi = clean_input($_POST['prodi']);

    // Validasi input
    if (empty($kode_matkul) || empty($nama_matkul) || empty($sks) || empty($semester) || empty($prodi)) {
        $_SESSION['error_message'] = "Semua field harus diisi";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Additional validation for input values
    if ($sks < 1 || $sks > 10) {
        $_SESSION['error_message'] = "Nilai SKS harus antara 1 sampai 10";
        header("Location: datamatakuliah.php");
        exit();
    }

    if ($semester < 1 || $semester > 14) {
        $_SESSION['error_message'] = "Semester harus antara 1 sampai 14";
        header("Location: datamatakuliah.php");
        exit();
    }

    // Check if kode_matkul is being changed to an existing one
    if ($old_kode_matkul != $kode_matkul) {
        $check_sql = "SELECT kode_matkul FROM mata_kuliah WHERE kode_matkul = ? AND kode_matkul != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $kode_matkul, $old_kode_matkul);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "Kode mata kuliah sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datamatakuliah.php");
            exit();
        }
        $check_stmt->close();
    }

    // Verify if prodi exists in program_studi table
    $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
    $check_prodi_stmt = $conn->prepare($check_prodi_sql);
    $check_prodi_stmt->bind_param("s", $prodi);
    $check_prodi_stmt->execute();
    $check_prodi_stmt->store_result();
    
    if ($check_prodi_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Program Studi tidak ditemukan";
        $check_prodi_stmt->close();
        header("Location: datamatakuliah.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Begin transaction for data integrity
        $conn->begin_transaction();
        
        // Check if this course has associated data in other tables
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE kode_matkul = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("s", $old_kode_matkul);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        $check_nilai_sql = "SELECT COUNT(*) FROM nilai WHERE kode_matkul = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        $check_nilai_stmt->bind_param("s", $old_kode_matkul);  // Fixed: changed from $kode_matkul to $old_kode_matkul
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($jadwal_count > 0 || $nilai_count > 0) {
            // If there are associated records, we cannot change the primary key
            if ($old_kode_matkul != $kode_matkul) {
                throw new Exception("Kode mata kuliah tidak dapat diubah karena sudah digunakan dalam jadwal atau nilai");
            }
            
            // But we can update other fields
            $sql = "UPDATE mata_kuliah SET 
                    nama_matkul = ?, 
                    sks = ?, 
                    semester = ?, 
                    prodi = ? 
                    WHERE kode_matkul = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiss", $nama_matkul, $sks, $semester, $prodi, $old_kode_matkul);
        } else {
            // If no associated records, can change everything including primary key
            $sql = "UPDATE mata_kuliah SET 
                    kode_matkul = ?,
                    nama_matkul = ?, 
                    sks = ?, 
                    semester = ?, 
                    prodi = ? 
                    WHERE kode_matkul = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiiss", $kode_matkul, $nama_matkul, $sks, $semester, $prodi, $old_kode_matkul);
        }
        
        if ($stmt->execute()) {
            // Log the edit action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'EDIT', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Updated mata kuliah from {$old_kode_matkul} to {$kode_matkul}";
                $log_stmt->bind_param("iss", $user_id, $old_kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Data mata kuliah berhasil diperbarui";
            $conn->commit();
        } else {
            throw new Exception("Gagal memperbarui data mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}

// Delete mata kuliah process
if (isset($_GET['delete_kode'])) {
    $kode_matkul = clean_input($_GET['delete_kode']);
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Check if mata kuliah has any related records
        $check_jadwal_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE kode_matkul = ?";
        $check_jadwal_stmt = $conn->prepare($check_jadwal_sql);
        $check_jadwal_stmt->bind_param("s", $kode_matkul);
        $check_jadwal_stmt->execute();
        $check_jadwal_stmt->bind_result($jadwal_count);
        $check_jadwal_stmt->fetch();
        $check_jadwal_stmt->close();
        
        $check_nilai_sql = "SELECT COUNT(*) FROM data_nilai WHERE kode_matkul = ?";
        $check_nilai_stmt = $conn->prepare($check_nilai_sql);
        $check_nilai_stmt->bind_param("s", $kode_matkul);
        $check_nilai_stmt->execute();
        $check_nilai_stmt->bind_result($nilai_count);
        $check_nilai_stmt->fetch();
        $check_nilai_stmt->close();
        
        if ($jadwal_count > 0) {
            throw new Exception("Mata kuliah tidak dapat dihapus karena digunakan dalam jadwal kuliah");
        }
        
        if ($nilai_count > 0) {
            throw new Exception("Mata kuliah tidak dapat dihapus karena memiliki data nilai");
        }
        
        // Get mata kuliah details for logging before deletion
        $get_matkul_sql = "SELECT nama_matkul FROM mata_kuliah WHERE kode_matkul = ?";
        $get_matkul_stmt = $conn->prepare($get_matkul_sql);
        $get_matkul_stmt->bind_param("s", $kode_matkul);
        $get_matkul_stmt->execute();
        $get_matkul_stmt->bind_result($nama_matkul);
        $get_matkul_stmt->fetch();
        $get_matkul_stmt->close();

        $sql = "DELETE FROM mata_kuliah WHERE kode_matkul = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kode_matkul);
        
        if ($stmt->execute()) {
            // Log the delete action if you have an activity log table
            if (isset($conn->activity_log)) {
                $user_id = $_SESSION['user_id'];
                $log_sql = "INSERT INTO activity_log (user_id, activity_type, table_name, record_id, details, timestamp) 
                           VALUES (?, 'DELETE', 'mata_kuliah', ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                $details = "Deleted mata kuliah '{$nama_matkul}' with code {$kode_matkul}";
                $log_stmt->bind_param("iss", $user_id, $kode_matkul, $details);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            $_SESSION['success_message'] = "Mata kuliah berhasil dihapus";
            $conn->commit();
        } else {
            throw new Exception("Gagal menghapus mata kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamatakuliah.php");
    exit();
}
?>