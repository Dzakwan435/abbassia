<?php
// Input sanitization function
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Add student process
if (isset($_POST['tambah_mahasiswa'])) {
    $nim = clean_input($_POST['nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: datamahasiswa.php");
        exit();
    }

    // Check if NIM already exists
    $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $nim);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
        $check_stmt->close();
        header("Location: datamahasiswa.php");
        exit();
    }
    $check_stmt->close();

    // Check if user_id already exists if provided
    if ($user_id !== null) {
        $check_user_sql = "SELECT user_id FROM mahasiswa WHERE user_id = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("i", $user_id);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan mahasiswa lain";
            $check_user_stmt->close();
            header("Location: datamahasiswa.php");
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
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_valid_user_stmt->close();
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
        header("Location: datamahasiswa.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        if ($user_id === null) {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $nim, $nama, $prodi, $alamat, $nohp, $email);
        } else {
            $sql = "INSERT INTO mahasiswa (nim, nama, prodi, alamat, nohp, email, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa baru berhasil ditambahkan";
        } else {
            throw new Exception("Gagal menambahkan mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamahasiswa.php");
    exit();
}

// Edit student process
if (isset($_POST['edit_mahasiswa'])) {
    $id = (int)clean_input($_POST['id']);
    $nim = clean_input($_POST['nim']);
    $old_nim = clean_input($_POST['old_nim']);
    $nama = clean_input($_POST['nama']);
    $prodi = clean_input($_POST['prodi']);
    $alamat = clean_input($_POST['alamat']);
    $nohp = clean_input($_POST['nohp']);
    $email = clean_input($_POST['email']);
    $user_id = !empty($_POST['user_id']) ? (int)clean_input($_POST['user_id']) : null;

    // Validasi input
    if (empty($nim) || empty($nama) || empty($prodi)) {
        $_SESSION['error_message'] = "NIM, Nama dan Program Studi harus diisi";
        header("Location: datamahasiswa.php");
        exit();
    }

    // Check if student exists
    $check_id_sql = "SELECT id FROM mahasiswa WHERE id = ?";
    $check_id_stmt = $conn->prepare($check_id_sql);
    $check_id_stmt->bind_param("i", $id);
    $check_id_stmt->execute();
    $check_id_stmt->store_result();
    
    if ($check_id_stmt->num_rows == 0) {
        $_SESSION['error_message'] = "Mahasiswa tidak ditemukan";
        $check_id_stmt->close();
        header("Location: datamahasiswa.php");
        exit();
    }
    $check_id_stmt->close();

    // Check if NIM is being changed to an existing one
    if ($old_nim != $nim) {
        $check_sql = "SELECT nim FROM mahasiswa WHERE nim = ? AND nim != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $nim, $old_nim);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "NIM sudah terdaftar dalam sistem";
            $check_stmt->close();
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_stmt->close();
    }

    // Check if user_id is being changed to an existing one
    if ($user_id !== null) {
        $check_user_sql = "SELECT id FROM mahasiswa WHERE user_id = ? AND nim != ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("is", $user_id, $old_nim);
        $check_user_stmt->execute();
        $check_user_stmt->store_result();
        
        if ($check_user_stmt->num_rows > 0) {
            $_SESSION['error_message'] = "ID User sudah terhubung dengan mahasiswa lain";
            $check_user_stmt->close();
            header("Location: datamahasiswa.php");
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
            header("Location: datamahasiswa.php");
            exit();
        }
        $check_valid_user_stmt->close();
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
        header("Location: datamahasiswa.php");
        exit();
    }
    $check_prodi_stmt->close();

    try {
        // Handle NULL user_id case separately
        if ($user_id !== null) {
            $sql = "UPDATE mahasiswa SET 
                    nim = ?, 
                    nama = ?, 
                    prodi = ?, 
                    alamat = ?, 
                    nohp = ?, 
                    email = ?,
                    user_id = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssii", $nim, $nama, $prodi, $alamat, $nohp, $email, $user_id, $id);
        } else {
            $sql = "UPDATE mahasiswa SET 
                    nim = ?, 
                    nama = ?, 
                    prodi = ?, 
                    alamat = ?, 
                    nohp = ?, 
                    email = ?,
                    user_id = NULL
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", $nim, $nama, $prodi, $alamat, $nohp, $email, $id);
        }
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Data mahasiswa berhasil diperbarui";
            } else {
                $_SESSION['warning_message'] = "Tidak ada perubahan data yang dilakukan";
            }
        } else {
            throw new Exception("Gagal memperbarui data mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = "Terjadi kesalahan saat memperbarui data mahasiswa";
    }
    header("Location: datamahasiswa.php");
    exit();
}

// Delete student process
if (isset($_GET['delete_id'])) {
    $id = (int)clean_input($_GET['delete_id']);
    
    try {
        // Check if student has any course enrollments
        $check_sql = "SELECT COUNT(*) FROM data_nilai WHERE mahasiswa_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($count > 0) {
            throw new Exception("Mahasiswa tidak dapat dihapus karena memiliki data nilai");
        }

        $sql = "DELETE FROM mahasiswa WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mahasiswa berhasil dihapus";
        } else {
            throw new Exception("Gagal menghapus mahasiswa: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: datamahasiswa.php");
    exit();
}
?>