<?php
session_start();
require_once 'include/config.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';

    // Enhanced validation
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['error_message'] = "Semua field harus diisi!";
        header("Location: login.php");
        exit();
    }

    // Validasi role
    if (!in_array($role, ['admin', 'dosen', 'mahasiswa'])) {
        $_SESSION['error_message'] = "Role tidak valid!";
        header("Location: login.php");
        exit();
    }

    try {
        // Enhanced prepared statement
        $query = "SELECT id, username, password, role, nama, prodi FROM users WHERE username = ? AND role = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception("Error database: " . $conn->error);
        }

        $stmt->bind_param("ss", $username, $role);
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $login_success = false;

            // Password verification dengan error handling
            if (password_verify($password, $user['password'])) {
                $login_success = true;
            }
            // Fallback untuk password plain text (hanya untuk migrasi)
            else if ($password === $user['password']) {
                $login_success = true;
                // Update ke hashed password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $hashed_password, $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            }

            if ($login_success) {
                // Regenerate session ID untuk security
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['prodi'] = $user['prodi'];
                $_SESSION['login_time'] = time();

                // Update last login
                $update_time_sql = "UPDATE users SET updated_at = NOW() WHERE id = ?";
                $update_time_stmt = $conn->prepare($update_time_sql);
                if ($update_time_stmt) {
                    $update_time_stmt->bind_param("i", $user['id']);
                    $update_time_stmt->execute();
                    $update_time_stmt->close();
                }

                // Redirect berdasarkan role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: views/admin/pages/dashboard.php");
                        break;
                    case 'dosen':
                        header("Location: views/dosen/pages/dashboard-dosen.php");
                        break;
                    case 'mahasiswa':
                        header("Location: views/mahasiswa/pages/dashboard-mahasiswa.php");
                        break;
                    default:
                        header("Location: login.php");
                        break;
                }
                exit();
            } else {
                $_SESSION['error_message'] = "Username atau password salah!";
            }
        } else {
            $_SESSION['error_message'] = "Username tidak ditemukan atau role tidak sesuai!";
        }

        $stmt->close();
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error_message'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
    }

    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem Akademik</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background-color: #fff;
            width: 380px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #024820, #013b15);
            padding: 30px 0;
            text-align: center;
            color: white;
        }

        .header h2 {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-container {
            padding: 25px 30px;
        }

        .role-group {
            margin-bottom: 20px;
        }

        .role-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }

        .role-options {
            display: flex;
            gap: 10px;
        }

        .role-option {
            flex: 1;
            padding: 12px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-option.selected {
            border-color: #3a7bd5;
            background-color: #f0f7ff;
            color: #3a7bd5;
        }

        .role-option img {
            width: 30px;
            height: 30px;
            margin-bottom: 5px;
        }

        .role-option span {
            display: block;
            font-size: 12px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border 0.3s ease;
        }

        .form-group input:focus {
            border-color: #3a7bd5;
            outline: none;
        }

        button.login-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #034a15, #023923);
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button.login-btn:hover {
            box-shadow: 0 5px 15px rgba(58, 123, 213, 0.4);
        }

        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #777;
        }

        .form-footer a {
            color: #3a7bd5;
            text-decoration: none;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .icon {
            display: block;
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon i {
            font-size: 28px;
        }

        .error-message {
            color: #dc3545;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            display: none;
        }

        .success-message {
            color: #155724;
            font-size: 14px;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8.211 2.047a.5.5 0 0 0-.422 0l-7.5 3.5a.5.5 0 0 0 .025.917l7.5 3a.5.5 0 0 0 .372 0L14 7.14V13a1 1 0 0 0-1 1v2h3v-2a1 1 0 0 0-1-1V6.739l.686-.275a.5.5 0 0 0 .025-.917l-7.5-3.5Z" />
                    <path d="M4.176 9.032a.5.5 0 0 0-.656.327l-.5 1.7a.5.5 0 0 0 .294.605l4.5 1.8a.5.5 0 0 0 .372 0l4.5-1.8a.5.5 0 0 0 .294-.605l-.5-1.7a.5.5 0 0 0-.656-.327L8 10.466 4.176 9.032Z" />
                </svg>
            </div>
            <h2>Sistem Akademik</h2>
            <p>Silakan login untuk melanjutkan</p>
        </div>

        <div class="form-container">
            <!-- Menampilkan pesan error -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="error-message" style="display: block;">
                    <?php
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="success-message" style="display: block;">
                    <?php
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="role-group">
                <label>Pilih Role</label>
                <div class="role-options">
                    <div class="role-option" onclick="selectRole(this, 'mahasiswa')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                            <path d="M8 9a3.5 3.5 0 0 0-3.5 3.5V14h7v-1.5A3.5 3.5 0 0 0 8 9z" />
                        </svg>
                        <span>Mahasiswa</span>
                    </div>
                    <div class="role-option" onclick="selectRole(this, 'dosen')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M14 9.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm-6 5.7c0 .8.8.8.8.8h6.4s.8 0 .8-.8-.8-3.2-4-3.2-4 2.4-4 3.2Z" />
                            <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2Zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4Z" />
                        </svg>
                        <span>Dosen</span>
                    </div>
                    <div class="role-option" onclick="selectRole(this, 'admin')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-9 8c0 1 1 1 1 1h5v-1a2 2 0 0 1 2-2h.5a.5.5 0 0 1 0 1H10a1 1 0 0 0-1 1v1h6s1 0 1-1v-1a5 5 0 0 0-2.5-4.3C12.2 7.8 11 7.1 11 6c0-1.7-1.3-3-3-3S5 4.3 5 6c0 1.1-1.2 1.8-2.5 2.7A5 5 0 0 0 0 13v1h2v-1Z" />
                        </svg>
                        <span>Admin</span>
                    </div>
                </div>
            </div>

            <form action="login.php" method="POST" onsubmit="return validateForm()">
                <input type="hidden" id="selectedRole" name="role" value="">

                <div class="form-group">
                    <label for="username">Username / NIM / NIP</label>
                    <input type="text" id="username" name="username" placeholder="Masukkan username Anda" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan password Anda" required>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="form-footer">
                <p>Lupa password? <a href="reset_password.php">Klik disini</a></p>
            </div>
        </div>
    </div>

    <script>
        function selectRole(element, role) {
            // Hapus class 'selected' dari semua role options
            const roleOptions = document.querySelectorAll('.role-option');
            roleOptions.forEach(option => {
                option.classList.remove('selected');
            });

            // Tambahkan class 'selected' ke role yang dipilih
            element.classList.add('selected');

            // Set nilai hidden input
            document.getElementById('selectedRole').value = role;

            // Update placeholder pada input username berdasarkan role
            const usernameInput = document.getElementById('username');

            if (role === 'mahasiswa') {
                usernameInput.placeholder = 'Masukkan NIM Anda';
            } else if (role === 'dosen') {
                usernameInput.placeholder = 'Masukkan NIP Anda';
            } else if (role === 'admin') {
                usernameInput.placeholder = 'Masukkan Username Admin';
            }
        }

        function validateForm() {
            const selectedRole = document.getElementById('selectedRole').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!selectedRole) {
                alert('Silakan pilih role terlebih dahulu');
                return false;
            }

            if (!username || !password) {
                alert('Username dan password tidak boleh kosong');
                return false;
            }

            return true;
        }

        // Set role default ke 'mahasiswa' saat halaman dimuat
        window.onload = function() {
            const defaultRole = document.querySelector('.role-option');
            selectRole(defaultRole, 'mahasiswa');
        };
    </script>
</body>

</html>