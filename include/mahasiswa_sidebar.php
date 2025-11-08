 <div class="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                    <span class="fw-bold">U</span>
                </div>
                <span class="sidebar-brand">PORTALSIA</span>
            </div>
            
            <div class="menu-category">MENU MAHASISWA</div>
            <div class="menu">
                <a href="dashboard-mahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="profil-mahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>Profil Mahasiswa</span>
                </a>
                <a href="jadwal-kuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Jadwal Kuliah</span>
                </a>
                <a href="krs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-edit"></i>
                    <span>KRS</span>
                </a>
                <a href="nilai.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>KHS</span>
                </a>
                <a href="materi.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Materi Kuliah</span>
                </a>
            </div>
            
            <div class="menu-category">PENGATURAN</div>
            <div class="menu">
                <a href="pengaturan-akun.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Pengaturan Akun</span>
                </a>
                <a href="bantuan.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i>
                    <span>Bantuan</span>
                </a>
                <a href="logout.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
