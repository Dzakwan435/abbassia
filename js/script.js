// Dashboard Initialization
function initDashboard() {
    // Program Studi Chart
    initProdiChart();
    
    // Calendar Day Click Event
    initCalendarDays();
    
    // Other dashboard initializations
}

function initProdiChart() {
    const prodiData = JSON.parse(document.getElementById('prodiData').textContent);
    const prodiLabels = prodiData.map(item => item.prodi);
    const prodiCounts = prodiData.map(item => item.jumlah);
    
    const prodiCtx = document.getElementById('prodiChart').getContext('2d');
    new Chart(prodiCtx, {
        type: 'bar',
        data: {
            labels: prodiLabels,
            datasets: [{
                label: 'Jumlah Mahasiswa',
                data: prodiCounts,
                backgroundColor: [
                    'rgba(26, 86, 50, 0.7)',
                    'rgba(46, 139, 87, 0.7)',
                    'rgba(13, 40, 24, 0.7)'
                ],
                borderColor: [
                    'rgba(26, 86, 50, 1)',
                    'rgba(46, 139, 87, 1)',
                    'rgba(13, 40, 24, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 50
                    }
                }
            }
        }
    });
}

function initCalendarDays() {
    $('.calendar-day').click(function() {
        $('.calendar-day').removeClass('active');
        $(this).addClass('active');
        
        const dayIndex = $(this).data('day-index');
        const daysIndonesia = JSON.parse(document.getElementById('daysIndonesia').textContent);
        const dayName = daysIndonesia[dayIndex];
        
        // Update day label
        const today = new Date();
        const currentDate = today.getDate();
        const currentDay = today.getDay();
        
        // Calculate the date difference
        let dateDiff = dayIndex - currentDay;
        let targetDate = new Date();
        targetDate.setDate(currentDate + dateDiff);
        
        // Format date string
        const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const formattedDate = targetDate.toLocaleDateString('id-ID', options);
        
        // Update schedule date display
        $('#currentDayLabel').text(formattedDate);
        
        // AJAX request to get schedule for selected day
        $.ajax({
            url: '../../api/get_schedule.php',
            type: 'GET',
            data: { 
                day: dayName,
                tahun_ajaran: document.getElementById('currentYear').textContent,
                semester: document.getElementById('currentSemester').textContent
            },
            success: function(response) {
                $('#scheduleContent').html(response);
            },
            error: function() {
                $('#scheduleContent').html(`
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-danger mb-2" style="font-size: 2rem;"></i>
                        <p class="text-muted">Gagal memuat jadwal</p>
                    </div>
                `);
            }
        });
    });
}

// Function to format time ago (for recent activities)
function time_elapsed_string(datetime) {
    var now = new Date();
    var date = new Date(datetime);
    var seconds = Math.floor((now - date) / 1000);
    
    var intervals = {
        'tahun': 31536000,
        'bulan': 2592000,
        'minggu': 604800,
        'hari': 86400,
        'jam': 3600,
        'menit': 60,
        'detik': 1
    };
    
    for (var unit in intervals) {
        var interval = intervals[unit];
        var value = Math.floor(seconds / interval);
        if (value >= 1) {
            return value + ' ' + unit + ' yang lalu';
        }
    }
    
    return 'baru saja';
}

// Initialize when DOM is ready
$(document).ready(function() {
    initDashboard();
});