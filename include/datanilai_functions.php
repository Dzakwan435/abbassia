<?php 
// Fungsi untuk membersihkan input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Operasi CRUD

// 1. Create - Tambah Data Nilai Baru
if (isset($_POST['tambah_nilai'])) {
    $mahasiswa_id = intval($_POST['mahasiswa_id']);
    $jadwal_id = intval($_POST['jadwal_id']);
    $nilai_tugas = floatval($_POST['nilai_tugas']);
    $nilai_uts = floatval($_POST['nilai_uts']);
    $nilai_uas = floatval($_POST['nilai_uas']);
    $created_by = $_SESSION['user_id'];
    
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
    header("Location: datanilai.php");
    exit();
}

// 2. Update - Edit Data Nilai
if (isset($_POST['edit_nilai'])) {
    $id = intval($_POST['id']);
    $nilai_tugas = floatval($_POST['nilai_tugas']);
    $nilai_uts = floatval($_POST['nilai_uts']);
    $nilai_uas = floatval($_POST['nilai_uas']);
    
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
    header("Location: datanilai.php");
    exit();
}

// 3. Delete - Hapus Data Nilai
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
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
    header("Location: datanilai.php");
    exit();
}



?>