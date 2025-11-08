<?php
// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add jadwal kuliah process
if (isset($_POST['tambah_jadwal'])) {
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $ruangan = clean_input($_POST['ruangan']);
    $dosen_nip = clean_input($_POST['dosen_nip']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($kode_matkul) || 
        empty($dosen_nip) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                           (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                           (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssssssss", $dosen_nip, $hari, $semester, $tahun_ajaran, 
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Dosen sudah memiliki jadwal pada hari dan waktu yang sama";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssssssss", $ruangan, $hari, $semester, $tahun_ajaran, 
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwalkuliah.php");
            exit();
        }
    }

    try {
        $sql = "INSERT INTO jadwal_kuliah (hari, waktu_mulai, waktu_selesai, kode_matkul, ruangan, dosen_nip, semester, tahun_ajaran) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $hari, $waktu_mulai, $waktu_selesai, $kode_matkul, $ruangan, $dosen_nip, $semester, $tahun_ajaran);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwalkuliah.php");
    exit();
}

// Edit jadwal kuliah process
if (isset($_POST['edit_jadwal'])) {
    $id = (int)clean_input($_POST['id']);
    $hari = clean_input($_POST['hari']);
    $waktu_mulai = clean_input($_POST['waktu_mulai']);
    $waktu_selesai = clean_input($_POST['waktu_selesai']);
    $kode_matkul = clean_input($_POST['kode_matkul']);
    $ruangan = clean_input($_POST['ruangan']);
    $dosen_nip = clean_input($_POST['dosen_nip']);
    $semester = clean_input($_POST['semester']);
    $tahun_ajaran = clean_input($_POST['tahun_ajaran']);

    // Validasi input
    if (empty($hari) || empty($waktu_mulai) || empty($waktu_selesai) || empty($kode_matkul) || 
        empty($dosen_nip) || empty($semester) || empty($tahun_ajaran)) {
        $_SESSION['error_message'] = "Semua field wajib harus diisi";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Validasi waktu
    if ($waktu_mulai >= $waktu_selesai) {
        $_SESSION['error_message'] = "Waktu mulai harus lebih awal dari waktu selesai";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Periksa konflik jadwal (dosen mengajar di waktu yang sama)
    $check_dosen_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                      WHERE dosen_nip = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                      AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                    (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                    (waktu_mulai >= ? AND waktu_selesai <= ?))";
    
    $check_dosen_stmt = $conn->prepare($check_dosen_sql);
    $check_dosen_stmt->bind_param("ssssissssss", $dosen_nip, $hari, $semester, $tahun_ajaran, $id,
                                $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                $waktu_mulai, $waktu_selesai);
    $check_dosen_stmt->execute();
    $check_dosen_stmt->bind_result($dosen_conflict);
    $check_dosen_stmt->fetch();
    $check_dosen_stmt->close();
    
    if ($dosen_conflict > 0) {
        $_SESSION['error_message'] = "Dosen sudah memiliki jadwal pada hari dan waktu yang sama";
        header("Location: jadwalkuliah.php");
        exit();
    }

    // Periksa konflik ruangan
    if (!empty($ruangan)) {
        $check_ruangan_sql = "SELECT COUNT(*) FROM jadwal_kuliah 
                            WHERE ruangan = ? AND hari = ? AND semester = ? AND tahun_ajaran = ? 
                            AND id != ? AND ((waktu_mulai <= ? AND waktu_selesai > ?) OR 
                                         (waktu_mulai < ? AND waktu_selesai >= ?) OR 
                                         (waktu_mulai >= ? AND waktu_selesai <= ?))";
        
        $check_ruangan_stmt = $conn->prepare($check_ruangan_sql);
        $check_ruangan_stmt->bind_param("ssssissssss", $ruangan, $hari, $semester, $tahun_ajaran, $id,
                                      $waktu_selesai, $waktu_mulai, $waktu_selesai, $waktu_mulai, 
                                      $waktu_mulai, $waktu_selesai);
        $check_ruangan_stmt->execute();
        $check_ruangan_stmt->bind_result($ruangan_conflict);
        $check_ruangan_stmt->fetch();
        $check_ruangan_stmt->close();
        
        if ($ruangan_conflict > 0) {
            $_SESSION['error_message'] = "Ruangan sudah digunakan pada hari dan waktu yang sama";
            header("Location: jadwalkuliah.php");
            exit();
        }
    }

    try {
        $sql = "UPDATE jadwal_kuliah SET 
                hari = ?, 
                waktu_mulai = ?, 
                waktu_selesai = ?, 
                kode_matkul = ?, 
                ruangan = ?, 
                dosen_nip = ?, 
                semester = ?, 
                tahun_ajaran = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $hari, $waktu_mulai, $waktu_selesai, $kode_matkul, $ruangan, $dosen_nip, $semester, $tahun_ajaran, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwalkuliah.php");
    exit();
}

// Delete jadwal kuliah process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    try {
        $sql = "DELETE FROM jadwal_kuliah WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Jadwal kuliah berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus jadwal kuliah: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: jadwalkuliah.php");
    exit();
}

?>