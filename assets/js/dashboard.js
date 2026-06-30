(function() {
    'use strict';

    var data = window.DASHBOARD_DATA;
    if (!data) return;

    // Asistencia — barras agrupadas
    var ctx1 = document.getElementById('chartAsistencia');
    if (ctx1 && data.asistencia.labels.length) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: data.asistencia.labels,
                datasets: [
                    {
                        label: 'Presentes',
                        data: data.asistencia.presentes,
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Ausentes',
                        data: data.asistencia.ausentes,
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 }, boxWidth: 12, padding: 12 }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }

    // Departamentos — dona
    var ctx2 = document.getElementById('chartDeptos');
    if (ctx2 && data.deptos.labels.length) {
        var colors = ['#059669','#3b82f6','#10b981','#f59e0b','#8b5cf6','#ec4899','#6b7280','#06b6d4'];
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: data.deptos.labels,
                datasets: [{
                    data: data.deptos.data,
                    backgroundColor: colors.slice(0, data.deptos.labels.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 }, boxWidth: 12, padding: 12 }
                    }
                }
            }
        });
    }

    // Nómina — área (filled line mejorado)
    var ctx3 = document.getElementById('chartNomina');
    if (ctx3 && data.nomina.labels.length) {
        var grad = ctx3.getContext('2d').createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, 'rgba(5, 150, 105, 0.35)');
        grad.addColorStop(1, 'rgba(5, 150, 105, 0.02)');

        new Chart(ctx3, {
            type: 'line',
            data: {
                labels: data.nomina.labels,
                datasets: [{
                    label: 'Total neto',
                    data: data.nomina.data,
                    borderColor: '#059669',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#059669',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return '$' + Number(ctx.raw).toLocaleString('es-MX', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: {
                            callback: function(v) { return '$' + Number(v).toLocaleString('es-MX'); }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
    }
})();
