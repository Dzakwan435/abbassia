<?php

// Fungsi untuk membersihkan input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Proses Tambah User
if (isset($_POST['tambah_user'])) {
    $username = clean_input($_POST['username']);
    $password = password_hash(clean_input($_POST['password']), PASSWORD_DEFAULT);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']);
    $prodi = clean_input($_POST['prodi']);

    $sql = "INSERT INTO users (username, password, nama, role, prodi) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $username, $password, $nama, $role, $prodi);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User baru berhasil ditambahkan";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Proses Edit User
if (isset($_POST['edit_user'])) {
    $id = clean_input($_POST['id']);
    $username = clean_input($_POST['username']);
    $nama = clean_input($_POST['nama']);
    $role = clean_input($_POST['role']);
    $prodi = clean_input($_POST['prodi']);

    // Cek jika password diupdate
    if (!empty($_POST['password'])) {
        $password = password_hash(clean_input($_POST['password']), PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username=?, password=?, nama=?, role=?, prodi=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $username, $password, $nama, $role, $prodi, $id);
    } else {
        $sql = "UPDATE users SET username=?, nama=?, role=?, prodi=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $username, $nama, $role, $prodi, $id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Data user berhasil diperbarui";
    } else {
        $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Proses Hapus User
if (isset($_GET['delete_id'])) {
    $id = clean_input($_GET['delete_id']);
    
    // Mencegah penghapusan user yang sedang login
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "Anda tidak dapat menghapus akun yang sedang digunakan";
    } else {
        $sql = "DELETE FROM users WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User berhasil dihapus";
        } else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

?>