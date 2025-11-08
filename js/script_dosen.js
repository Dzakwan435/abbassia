// js/script-dosen.js
document.addEventListener('DOMContentLoaded', function() {
    // Grade Distribution Chart
    const gradeChartElement = document.getElementById('gradeChart');
    
    if (gradeChartElement) {
        const ctx = gradeChartElement.getContext('2d');
        const gradeChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: gradeChartElement.dataset.labels ? JSON.parse(gradeChartElement.dataset.labels) : [],
                datasets: [{
                    data: gradeChartElement.dataset.values ? JSON.parse(gradeChartElement.dataset.values) : [],
                    backgroundColor: gradeChartElement.dataset.colors ? JSON.parse(gradeChartElement.dataset.colors) : [],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%',
                animation: {
                    animateScale: true,
                    animateRotate: true
                }
            }
        });
    }

    // Animasi saat halaman dimuat
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.opacity = 1;
        }, index * 200);
    });
});