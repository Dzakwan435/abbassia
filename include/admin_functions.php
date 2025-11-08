<?php
function getDashboardData() {
    global $conn;
    
    // Initialize data array
    $data = [
        'total_mahasiswa' => 0,
        'total_dosen' => 0,
        'total_matakuliah' => 0,
        'total_jadwal' => 0,
        'week_data' => [],
        'today_schedules' => [],
        'all_week_schedules' => []
    ];
    
    // Get counts from database
    $data['total_mahasiswa'] = getCountFromDB("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa'");
    $data['total_dosen'] = getCountFromDB("SELECT COUNT(*) FROM users WHERE role = 'dosen'");
    $data['total_matakuliah'] = getCountFromDB("SELECT COUNT(*) FROM mata_kuliah");
    $data['total_jadwal'] = getCountFromDB("SELECT COUNT(*) FROM jadwal_kuliah");
    
    // Get schedule data
    $data['week_data'] = getWeeklySchedule($conn);
    $data['today_schedules'] = getTodaySchedule($conn, $data['week_data']['current_day_name']);
    $data['all_week_schedules'] = getAllWeekSchedule($conn);
    
    return $data;
}

function getCountFromDB($query) {
    global $conn;
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_row();
        return $row[0] ?? 0;
    }
    return 0;
}

function getWeeklySchedule($conn) {
    $days_short = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    $days_full = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $days_indonesia = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    
    $today = date('w');
    $current_day_name = $days_indonesia[$today];
    
    return [
        'days_short' => $days_short,
        'days_full' => $days_full,
        'days_indonesia' => $days_indonesia,
        'today' => $today,
        'current_day_name' => $current_day_name
    ];
}

function getTodaySchedule($conn, $day_name) {
    $sql = "SELECT jk.id, jk.hari, jk.waktu_mulai, jk.waktu_selesai, jk.ruangan,
                   mk.nama_matkul, mk.sks,
                   d.nama AS nama_dosen
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
            LEFT JOIN dosen d ON jk.dosen_nip = d.nip
            WHERE jk.hari = ?
            ORDER BY jk.waktu_mulai ASC";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $day_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $schedules[] = $row;
        }
        
        $stmt->close();
        return $schedules;
    }
    
    return [];
}

function getAllWeekSchedule($conn) {
    $sql = "SELECT jk.id, jk.hari, jk.waktu_mulai, jk.waktu_selesai, jk.ruangan,
                   mk.nama_matkul, mk.sks,
                   d.nama AS nama_dosen
            FROM jadwal_kuliah jk
            JOIN mata_kuliah mk ON jk.kode_matkul = mk.kode_matkul
            LEFT JOIN dosen d ON jk.dosen_nip = d.nip
            ORDER BY 
                CASE jk.hari 
                    WHEN 'Senin' THEN 1
                    WHEN 'Selasa' THEN 2
                    WHEN 'Rabu' THEN 3
                    WHEN 'Kamis' THEN 4
                    WHEN 'Jumat' THEN 5
                    WHEN 'Sabtu' THEN 6
                    WHEN 'Minggu' THEN 7
                END,
                jk.waktu_mulai ASC";
    
    $result = $conn->query($sql);
    $schedules = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $schedules[$row['hari']][] = $row;
        }
    }
    
    return $schedules;
}

function getProdiDistribution() {
    global $conn;
    
    $query = "SELECT prodi, COUNT(*) as jumlah FROM mahasiswa GROUP BY prodi";
    $result = $conn->query($query);
    
    $prodi_data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $prodi_data[] = [
                'prodi' => $row['prodi'],
                'jumlah' => (int)$row['jumlah']
            ];
        }
    }
    
    // Default data jika tidak ada hasil
    if (empty($prodi_data)) {
        $prodi_data = [
            ['prodi' => 'Sistem Informasi', 'jumlah' => 125],
            ['prodi' => 'Ilmu Komputer', 'jumlah' => 98],
            ['prodi' => 'Teknik Informatika', 'jumlah' => 85]
        ];
    }
    
    return $prodi_data;
}

function formatTime($time) {
    return date('H:i', strtotime($time));
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}
?>