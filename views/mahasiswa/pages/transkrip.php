<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once '../../../include/config.php';

// Verify database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Input sanitization function
function clean_input($data) {
    if (empty($data)) return $data;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to convert grade to numeric value
function grade_to_numeric($grade) {
    if (empty($grade) || $grade == '') {
        return 0.0;
    }
    
    $grade_map = [
        'A' => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B' => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C' => 2.0,
        'D' => 1.0,
        'E' => 0.0,
        'F' => 0.0
    ];
    return isset($grade_map[$grade]) ? $grade_map[$grade] : 0.0;
}

// Function to get grade description
function get_grade_description($grade) {
    $descriptions = [
        'A' => 'Sangat Baik',
        'A-' => 'Baik Sekali',
        'B+' => 'Baik Plus',
        'B' => 'Baik',
        'B-' => 'Cukup Baik',
        'C+' => 'Cukup Plus',
        'C' => 'Cukup',
        'D' => 'Kurang',
        'E' => 'Gagal',
        'F' => 'Gagal'
    ];
    return isset($descriptions[$grade]) ? $descriptions[$grade] : '-';
}

// Determine which student's transcript to show
$mahasiswa_id = null;
$selected_mahasiswa = null;

// Determine back URL based on role
$back_url = '';
if ($_SESSION['role'] === 'mahasiswa') {
    $back_url = 'dashboard-mahasiswa.php';
    // Mahasiswa can only view their own transcript
    $mahasiswa_id = $_SESSION['user_id'];
    
    // Get mahasiswa data from users table
    $sql = "SELECT m.*, ps.nama_prodi 
            FROM mahasiswa m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            WHERE m.user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $mahasiswa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_mahasiswa = $result->fetch_assoc();
        $stmt->close();
        
        if (!$selected_mahasiswa) {
            $_SESSION['error_message'] = "Data mahasiswa tidak ditemukan";
            header("Location: dashboard-mahasiswa.php");
            exit();
        }
        $mahasiswa_id = $selected_mahasiswa['id'];
    }
} elseif ($_SESSION['role'] === 'admin') {
    $back_url = 'dashboard.php';
    // Admin can select student
    if (isset($_GET['mahasiswa_id']) && !empty($_GET['mahasiswa_id'])) {
        $mahasiswa_id = (int)clean_input($_GET['mahasiswa_id']);
    } elseif (isset($_POST['mahasiswa_id']) && !empty($_POST['mahasiswa_id'])) {
        $mahasiswa_id = (int)clean_input($_POST['mahasiswa_id']);
    }
    
    if ($mahasiswa_id) {
        // Get selected mahasiswa data
        $sql = "SELECT m.*, ps.nama_prodi 
                FROM mahasiswa m 
                LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
                WHERE m.id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $mahasiswa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $selected_mahasiswa = $result->fetch_assoc();
            $stmt->close();
        }
    }
} elseif ($_SESSION['role'] === 'dosen') {
    $back_url = 'dashboard-dosen.php';
    // Dosen can select student
    if (isset($_GET['mahasiswa_id']) && !empty($_GET['mahasiswa_id'])) {
        $mahasiswa_id = (int)clean_input($_GET['mahasiswa_id']);
    } elseif (isset($_POST['mahasiswa_id']) && !empty($_POST['mahasiswa_id'])) {
        $mahasiswa_id = (int)clean_input($_POST['mahasiswa_id']);
    }
    
    if ($mahasiswa_id) {
        // Get selected mahasiswa data
        $sql = "SELECT m.*, ps.nama_prodi 
                FROM mahasiswa m 
                LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
                WHERE m.id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $mahasiswa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $selected_mahasiswa = $result->fetch_assoc();
            $stmt->close();
        }
    }
}

// Get list of students for dropdown (for admin/dosen)
$mahasiswa_list = [];
if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'dosen') {
    $sql = "SELECT m.id, m.nim, m.nama, m.prodi, ps.nama_prodi 
            FROM mahasiswa m 
            LEFT JOIN program_studi ps ON m.prodi = ps.kode_prodi 
            ORDER BY m.nama ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mahasiswa_list[] = $row;
        }
    }
}

// Get transcript data if mahasiswa is selected
$transkrip_data = [];
$total_sks = 0;
$total_nilai = 0;
$ipk = 0;

if ($mahasiswa_id && $selected_mahasiswa) {
    // Get all grades for the student with proper joins
    $sql = "SELECT 
                COALESCE(dn.kode_matkul, jk.kode_matkul) as kode_matkul,
                COALESCE(mk.nama_matkul, 'Mata Kuliah Tidak Ditemukan') as nama_matkul,
                COALESCE(mk.sks, 0) as sks,
                COALESCE(mk.semester, 1) as semester,
                jk.semester as semester_kuliah,
                jk.tahun_ajaran,
                dn.nilai_tugas,
                dn.nilai_uts,
                dn.nilai_uas,
                dn.nilai_akhir,
                dn.grade
            FROM data_nilai dn
            INNER JOIN jadwal_kuliah jk ON dn.jadwal_id = jk.id
            LEFT JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
            WHERE dn.mahasiswa_id = ?
            ORDER BY jk.tahun_ajaran, jk.semester, mk.semester ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $mahasiswa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $transkrip_data[] = $row;
            
            // Calculate totals for IPK only if grade exists and SKS > 0
            if (!empty($row['grade']) && $row['grade'] != '' && $row['sks'] > 0) {
                $total_sks += $row['sks'];
                $total_nilai += (grade_to_numeric($row['grade']) * $row['sks']);
            }
        }
        $stmt->close();
        
        // Calculate IPK
        if ($total_sks > 0) {
            $ipk = $total_nilai / $total_sks;
        }
    }
    
    // Debug: Check if data is retrieved
    error_log("Transkrip data count: " . count($transkrip_data));
    error_log("Mahasiswa ID: " . $mahasiswa_id);
}

// Group data by semester and calculate IPS for each semester
$grouped_data = [];
$semester_ips = []; // To store IPS for each semester

foreach ($transkrip_data as $item) {
    $key = $item['tahun_ajaran'] . '|' . $item['semester_kuliah'];
    if (!isset($grouped_data[$key])) {
        $grouped_data[$key] = [
            'tahun_ajaran' => $item['tahun_ajaran'],
            'semester' => $item['semester_kuliah'],
            'mata_kuliah' => [],
            'total_sks' => 0,
            'total_nilai' => 0
        ];
    }
    
    $grouped_data[$key]['mata_kuliah'][] = $item;
    
    // Calculate semester totals
    if (!empty($item['grade']) && $item['grade'] != '' && $item['sks'] > 0) {
        $grouped_data[$key]['total_sks'] += $item['sks'];
        $grouped_data[$key]['total_nilai'] += (grade_to_numeric($item['grade']) * $item['sks']);
    }
}

// Calculate IPS for each semester
foreach ($grouped_data as $key => $semester) {
    if ($semester['total_sks'] > 0) {
        $grouped_data[$key]['ips'] = $semester['total_nilai'] / $semester['total_sks'];
    } else {
        $grouped_data[$key]['ips'] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transkrip Nilai - Portal Akademik</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: white;
            color: black;
            line-height: 1.4;
        }
        
        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        
        .header h2 {
            font-size: 14pt;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .student-info {
            margin-bottom: 20px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table td {
            padding: 3px 5px;
            vertical-align: top;
        }
        
        .info-table .label {
            width: 30%;
            font-weight: bold;
        }
        
        .semester-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .semester-header {
            background: #f0f0f0;
            padding: 5px 10px;
            border: 1px solid #000;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .grade-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        
        .grade-table th,
        .grade-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
        }
        
        .grade-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .grade-table .left {
            text-align: left;
        }
        
        .summary-row {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .transcript-summary {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding-top: 15px;
        }
        
        .summary-box {
            display: inline-block;
            margin: 0 20px;
            text-align: center;
        }
        
        .summary-value {
            font-size: 14pt;
            font-weight: bold;
            display: block;
        }
        
        .summary-label {
            font-size: 10pt;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            font-style: italic;
            color: #666;
        }
        
        .controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        @media screen {
            body {
                background: #f8f9fa;
                padding: 20px 0;
            }
            
            .container {
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                border-radius: 5px;
            }
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                border-radius: 0;
                padding: 15mm;
                max-width: 100%;
            }
            
            .no-print {
                display: none !important;
            }
            
            .grade-table {
                font-size: 9pt;
            }
        }
        
        .grade-A { background-color: #e8f5e8; }
        .grade-B { background-color: #e8f0f8; }
        .grade-C { background-color: #fff9e6; }
        .grade-D { background-color: #ffe8e6; }
        .grade-E, .grade-F { background-color: #fce8e6; }
        .no-grade { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Controls (Not printed) -->
        <div class="controls no-print">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <a href="<?php echo $back_url; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                </div>
                <div class="col-md-6 text-end">
                    <button onclick="window.print()" class="btn btn-sm btn-primary">
                        <i class="bi bi-printer me-1"></i> Cetak
                    </button>
                </div>
            </div>
            
            <!-- Student Selection Form (for admin/dosen) -->
            <?php if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'dosen') && !empty($mahasiswa_list)): ?>
            <div class="mt-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-8">
                        <select name="mahasiswa_id" class="form-select form-select-sm" required>
                            <option value="">Pilih Mahasiswa...</option>
                            <?php foreach ($mahasiswa_list as $mahasiswa): ?>
                            <option value="<?php echo $mahasiswa['id']; ?>" 
                                <?php echo ($mahasiswa_id == $mahasiswa['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mahasiswa['nim'] . ' - ' . $mahasiswa['nama'] . ' (' . ($mahasiswa['nama_prodi'] ?? $mahasiswa['prodi']) . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            Tampilkan Transkrip
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show no-print">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show no-print">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <?php if ($selected_mahasiswa): ?>
        <!-- Transcript Header -->
        <div class="header">
            <h1>TRANSKRIP NILAI</h1>
            <h2>UNIVERSITAS EXAMPLE</h2>
            <div>Fakultas Sains dan Teknologi</div>
        </div>

        <!-- Student Information -->
        <div class="student-info">
            <table class="info-table">
                <tr>
                    <td class="label">NIM</td>
                    <td>: <?php echo htmlspecialchars($selected_mahasiswa['nim']); ?></td>
                    <td class="label">Program Studi</td>
                    <td>: <?php echo htmlspecialchars($selected_mahasiswa['nama_prodi'] ?? $selected_mahasiswa['prodi']); ?></td>
                </tr>
                <tr>
                    <td class="label">Nama</td>
                    <td>: <?php echo htmlspecialchars($selected_mahasiswa['nama']); ?></td>
                    <td class="label">Tanggal Cetak</td>
                    <td>: <?php echo date('d/m/Y'); ?></td>
                </tr>
            </table>
        </div>

        <!-- Transcript Content -->
        <?php if (!empty($grouped_data)): ?>
            <?php foreach ($grouped_data as $semester_key => $semester_data): ?>
            <div class="semester-section">
                <div class="semester-header">
                    SEMESTER <?php echo $semester_data['semester']; ?> - TAHUN AJARAN <?php echo $semester_data['tahun_ajaran']; ?>
                </div>
                
                <table class="grade-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">Kode</th>
                            <th>Mata Kuliah</th>
                            <th width="8%">SKS</th>
                            <th width="12%">Nilai Akhir</th>
                            <th width="10%">Grade</th>
                            <th width="15%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($semester_data['mata_kuliah'] as $mk): ?>
                        <?php
                        $grade_class = 'no-grade';
                        if (!empty($mk['grade'])) {
                            $first_char = substr($mk['grade'], 0, 1);
                            if (in_array($first_char, ['A', 'B', 'C', 'D', 'E', 'F'])) {
                                $grade_class = 'grade-' . $first_char;
                            }
                        }
                        ?>
                        <tr class="<?php echo $grade_class; ?>">
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($mk['kode_matkul']); ?></td>
                            <td class="left"><?php echo htmlspecialchars($mk['nama_matkul']); ?></td>
                            <td><?php echo $mk['sks']; ?></td>
                            <td><?php echo !empty($mk['nilai_akhir']) ? number_format($mk['nilai_akhir'], 2) : '-'; ?></td>
                            <td><strong><?php echo !empty($mk['grade']) ? $mk['grade'] : '-'; ?></strong></td>
                            <td><?php echo get_grade_description($mk['grade']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Semester Summary -->
                        <tr class="summary-row">
                            <td colspan="3" class="left">JUMLAH SEMESTER INI</td>
                            <td><?php echo $semester_data['total_sks']; ?></td>
                            <td colspan="2">INDEKS PRESTASI SEMESTER (IPS)</td>
                            <td><strong><?php echo number_format($semester_data['ips'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <!-- Transcript Summary -->
            <div class="transcript-summary">
                <div style="text-align: center;">
                    <div class="summary-box">
                        <span class="summary-value"><?php echo $total_sks; ?></span>
                        <span class="summary-label">TOTAL SKS</span>
                    </div>
                    <div class="summary-box">
                        <span class="summary-value"><?php echo number_format($ipk, 2); ?></span>
                        <span class="summary-label">INDEKS PRESTASI KUMULATIF (IPK)</span>
                    </div>
                    <div class="summary-box">
                        <span class="summary-value"><?php echo count($transkrip_data); ?></span>
                        <span class="summary-label">TOTAL MATA KULIAH</span>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="no-data">
                <p>BELUM ADA DATA NILAI UNTUK MAHASISWA INI</p>
                <p>NIM: <?php echo htmlspecialchars($selected_mahasiswa['nim']); ?></p>
                <p>Nama: <?php echo htmlspecialchars($selected_mahasiswa['nama']); ?></p>
                <p>Transkrip nilai akan muncul setelah ada nilai yang tercatat.</p>
            </div>
        <?php endif; ?>

        <?php elseif (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'dosen') && empty($mahasiswa_id)): ?>
            <!-- No student selected message -->
            <div class="no-data no-print">
                <p>SILAKAN PILIH MAHASISWA</p>
                <p>Gunakan dropdown di atas untuk memilih mahasiswa dan menampilkan transkrip nilai.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Auto-print when parameter is set
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            window.print();
        }
    </script>
</body>
</html>

<?php
// Clean up
$conn->close();
?>