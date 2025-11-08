<div class="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
            <span class="fw-bold">U</span>
        </div>
        <span class="sidebar-brand">PORTALSIA</span>
    </div>
    
    <div class="menu-category">MENU UTAMA</div>
    <div class="menu">
        <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="datadosen.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datadosen.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Data Dosen</span>
        </a>
        <a href="datamahasiswa.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datamahasiswa.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Data Mahasiswa</span>
        </a>
        <a href="datamatakuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datamatakuliah.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Data Mata Kuliah</span>
        </a>
        <a href="jadwalkuliah.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'jadwalkuliah.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Jadwal Kuliah</span>
        </a>
        <a href="datanilai.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'datanilai.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Data Nilai</span>
        </a>
    </div>
    
    <div class="menu-category">PENGATURAN</div>
    <div class="menu">
        <a href="manajemenuser.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'manajemenuser.php' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i>
            <span>Manajemen User</span>
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>