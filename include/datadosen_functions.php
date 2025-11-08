<?php
// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add lecturer process
if (isset($_POST['tambah_dosen'])) {
    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $bidang_keahlian = clean_input($_POST['bidang_keahlian']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: datadosen.php");
        exit();
    }

    // Check if NIP already exists
    $check_sql = "SELECT nip FROM dosen WHERE nip = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nip);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: datadosen.php");
        exit();
    }
    $check_stmt->close();

    // Check if user_id already exists if provided
    if ($user_id !== null) {
        $check_user_sql = "SELECT user_id FROM dosen WHERE user_id = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
            $check_user_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Verify if user_id exists in users table
        $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
        $check_valid_user_stmt->bind_param("i", $user_id);
        $check_valid_user_stmt->execute();
        $check_valid_user_stmt->store_result();
        
        if ($check_valid_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak ditemukan";
            $check_valid_user_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_valid_user_stmt->close();
    }

    // Verify if prodi exists in program_studi table if provided
    if (!empty($prodi)) {
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_prodi_stmt->close();
    }

    try {
        if ($user_id === null) {
            $sql = "INSERT INTO dosen (nip, nama, bidang_keahlian, pangkat, alamat, nohp, email, prodi) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi);
        } else {
            $sql = "INSERT INTO dosen (nip, nama, bidang_keahlian, pangkat, alamat, nohp, email, prodi, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}

// Edit lecturer process
if (isset($_POST['edit_dosen'])) {
    $old_nip = clean_input($_POST['old_nip']);
    $nip = clean_input($_POST['nip']);
    $nama = clean_input($_POST['nama']);
    $bidang_keahlian = clean_input($_POST['bidang_keahlian']);
    $pangkat = clean_input($_POST['pangkat']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $prodi = clean_input($_POST['prodi']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nip) || empty($nama)) {
        $_SESSION['error_message'] = "NIP dan Nama harus diisi";
        header("Location: datadosen.php");
        exit();
    }

    // Check if NIP is being changed to an existing one
    if ($old_nip != $nip) {
        $check_sql = "SELECT nip FROM dosen WHERE nip = ? AND nip != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $nip, $old_nip);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIP sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_stmt->close();
    }

    // Check if user_id is being changed to an existing one
    if ($user_id !== null) {
        $check_user_sql = "SELECT nip FROM dosen WHERE user_id = ? AND nip != ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("is", $user_id, $old_nip);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan dosen lain";
            $check_user_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_user_stmt->close();
        
        // Verify if user_id exists in users table
        $check_valid_user_sql = "SELECT id FROM users WHERE id = ?";
        $check_valid_user_stmt = $conn->prepare($check_valid_user_sql);
        $check_valid_user_stmt->bind_param("i", $user_id);
        $check_valid_user_stmt->execute();
        $check_valid_user_stmt->store_result();
        
        if ($check_valid_user_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "ID User tidak ditemukan";
            $check_valid_user_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_valid_user_stmt->close();
    }

    // Verify if prodi exists in program_studi table if provided
    if (!empty($prodi)) {
        $check_prodi_sql = "SELECT kode_prodi FROM program_studi WHERE kode_prodi = ?";
        $check_prodi_stmt = $conn->prepare($check_prodi_sql);
        $check_prodi_stmt->bind_param("s", $prodi);
        $check_prodi_stmt->execute();
        $check_prodi_stmt->store_result();
        
        if ($check_prodi_stmt->num_rows == 0) {
            $_SESSION['error_message'] = "Program Studi tidak ditemukan";
            $check_prodi_stmt->close();
            header("Location: datadosen.php");
            exit();
        }
        $check_prodi_stmt->close();
    }

    try {
        $sql = "UPDATE dosen SET 
                nip = ?, 
                nama = ?, 
                bidang_keahlian = ?, 
                pangkat = ?, 
                alamat = ?, 
                nohp = ?, 
                email = ?,
                prodi = ?,
                user_id = ?
                WHERE nip = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssis", $nip, $nama, $bidang_keahlian, $pangkat, $alamat, $nohp, $email, $prodi, $user_id, $old_nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Data dosen berhasil diperbarui";
        } else {
            throw new Exception("Gagal memperbarui data dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}

// Delete lecturer process
if (isset($_GET['delete_nip'])) {
    $nip = clean_input($_GET['delete_nip']);
    
    try {
        // Check if lecturer is assigned to any courses
        $check_sql = "SELECT COUNT(*) FROM jadwal_kuliah WHERE dosen_nip = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $nip);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            throw new Exception("Dosen tidak dapat dihapus karena masih mengampu mata kuliah");
        }

        $sql = "DELETE FROM dosen WHERE nip = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nip);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Dosen berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus dosen: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datadosen.php");
    exit();
}
?>