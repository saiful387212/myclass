/**
 * ClassMate - Student Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const tomorrowCard = document.getElementById('tomorrow-routine-card');
    const downloadButton = document.getElementById('download-tomorrow-routine');

    if (downloadButton && tomorrowCard) {
        downloadButton.addEventListener('click', function() {
            downloadRoutineCard(tomorrowCard);
        });
    }

    updateRoutineStatuses();
    window.setInterval(updateRoutineStatuses, 30000);

    if (tomorrowCard) {
        window.setInterval(function() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const expectedDate = [
                tomorrow.getFullYear(),
                String(tomorrow.getMonth() + 1).padStart(2, '0'),
                String(tomorrow.getDate()).padStart(2, '0')
            ].join('-');
            if (tomorrowCard.dataset.routineDate !== expectedDate) {
                window.location.reload();
            }
        }, 60000);
    }
    
    // ===== Initialize Monthly Chart =====
    if (document.getElementById('monthlyChart')) {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        
        const data = window.monthlyData || {
            scheduled: 0,
            completed: 0,
            cancelled: 0,
            retaked: 0
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Scheduled', 'Completed', 'Cancelled', 'Retaked'],
                datasets: [{
                    data: [
                        data.scheduled || 0,
                        data.completed || 0,
                        data.cancelled || 0,
                        data.retaked || 0
                    ],
                    backgroundColor: [
                        '#4F46E5',  // Primary - Scheduled
                        '#10B981',  // Success - Completed
                        '#EF4444',  // Danger - Cancelled
                        '#F59E0B'   // Warning - Retaked
                    ],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 12,
                                family: 'Inter'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    function updateRoutineStatuses() {
        const now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.routine-class-item[data-auto-status="true"]').forEach(function(item) {
            const start = Number(item.dataset.startAt);
            const end = Number(item.dataset.endAt);
            const status = item.querySelector('[data-routine-status]');
            if (!status || !start || !end) return;

            let label = 'Scheduled';
            let statusClass = 'routine-status-scheduled';
            if (now >= end) {
                label = 'Completed';
                statusClass = 'routine-status-completed';
            } else if (now >= start) {
                label = 'In Progress';
                statusClass = 'routine-status-live';
            }

            status.textContent = label;
            status.classList.remove('routine-status-scheduled', 'routine-status-live', 'routine-status-completed', 'routine-status-cancelled');
            status.classList.add(statusClass);
        });

        const completedCount = document.querySelector('[data-today-completed-count]');
        const todayCard = document.getElementById('today-routine-card');
        if (completedCount && todayCard) {
            const completed = Array.from(todayCard.querySelectorAll('[data-routine-status]'))
                .filter(function(status) { return status.textContent.trim() === 'Completed'; }).length;
            completedCount.textContent = completed + ' Completed';
        }
    }

    function downloadRoutineCard(card) {
        const items = Array.from(card.querySelectorAll('.routine-download-item'));
        const width = 760;
        const padding = 30;
        const rowHeight = 116;
        const headerHeight = 196;
        const height = headerHeight + Math.max(items.length, 1) * rowHeight + padding;
        const scale = 2;
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const context = canvas.getContext('2d');
        if (!context) return;

        context.scale(scale, scale);
        context.fillStyle = '#f3f6fb';
        context.fillRect(0, 0, width, height);

        context.fillStyle = '#ffffff';
        drawRoundedRect(context, 18, 18, width - 36, height - 36, 18);
        context.fill();

        const date = new Date(card.dataset.routineDate + 'T00:00:00');
        context.save();
        drawRoundedRect(context, 18, 18, width - 36, 116, 18);
        context.clip();
        const headerGradient = context.createLinearGradient(18, 18, width - 18, 134);
        headerGradient.addColorStop(0, '#312e81');
        headerGradient.addColorStop(1, '#4f46e5');
        context.fillStyle = headerGradient;
        context.fillRect(18, 18, width - 36, 116);
        context.restore();

        context.fillStyle = '#c7d2fe';
        context.font = '600 13px Arial, sans-serif';
        context.fillText('CLASSMATE  /  WEEKLY ROUTINE', padding, 53);
        context.fillStyle = '#ffffff';
        context.font = '700 28px Arial, sans-serif';
        context.fillText("Tomorrow's Classes", padding, 88);
        context.fillStyle = '#e0e7ff';
        context.font = '16px Arial, sans-serif';
        context.fillText(date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }), padding, 114, width - padding * 2);

        context.fillStyle = '#64748b';
        context.font = '600 13px Arial, sans-serif';
        context.fillText(items.length + (items.length === 1 ? ' CLASS' : ' CLASSES'), padding, 164);
        context.fillStyle = '#e2e8f0';
        context.fillRect(padding, 177, width - padding * 2, 1);

        if (items.length === 0) {
            context.fillStyle = '#475569';
            context.font = '18px Arial, sans-serif';
            context.fillText('No classes scheduled for tomorrow.', padding, 220);
        }

        items.forEach(function(item, index) {
            const top = headerHeight + index * rowHeight;
            context.fillStyle = '#f8fafc';
            drawRoundedRect(context, padding, top, width - padding * 2, rowHeight - 12, 12);
            context.fill();

            context.fillStyle = '#4f46e5';
            drawRoundedRect(context, padding, top, 4, rowHeight - 12, 2);
            context.fill();

            context.fillStyle = '#94a3b8';
            context.font = '600 12px Arial, sans-serif';
            context.fillText(String(index + 1).padStart(2, '0'), padding + 18, top + 25);

            context.fillStyle = '#1e293b';
            context.font = '700 18px Arial, sans-serif';
            context.fillText(item.dataset.title || 'Class', padding + 52, top + 27, width - 245);
            context.fillStyle = '#64748b';
            context.font = '13px Arial, sans-serif';
            context.fillText((item.dataset.code || '') + '  |  ' + (item.dataset.time || ''), padding + 52, top + 51, width - 105);
            context.fillText((item.dataset.instructor || '') + '  |  ' + (item.dataset.room || 'TBA'), padding + 52, top + 76, width - 105);

            const status = item.querySelector('[data-routine-status]');
            const statusText = status ? status.textContent.trim() : 'Scheduled';
            const statusStyles = {
                Scheduled: { background: '#e0e7ff', foreground: '#3730a3' },
                'In Progress': { background: '#d1fae5', foreground: '#065f46' },
                Completed: { background: '#e5e7eb', foreground: '#374151' },
                Cancelled: { background: '#fee2e2', foreground: '#991b1b' }
            };
            const style = statusStyles[statusText] || statusStyles.Scheduled;
            context.font = '600 12px Arial, sans-serif';
            const badgeWidth = Math.min(context.measureText(statusText).width + 20, 112);
            const badgeX = width - padding - badgeWidth - 14;
            context.fillStyle = style.background;
            drawRoundedRect(context, badgeX, top + 15, badgeWidth, 25, 12);
            context.fill();
            context.fillStyle = style.foreground;
            context.textAlign = 'center';
            context.fillText(statusText, badgeX + badgeWidth / 2, top + 32, badgeWidth - 10);
            context.textAlign = 'start';
        });

        context.fillStyle = '#94a3b8';
        context.font = '12px Arial, sans-serif';
        context.fillText('ClassMate - Your class schedule', padding, height - 27);

        const link = document.createElement('a');
        link.download = 'tomorrow-classes-' + card.dataset.routineDate + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function drawRoundedRect(context, x, y, width, height, radius) {
        context.beginPath();
        context.moveTo(x + radius, y);
        context.lineTo(x + width - radius, y);
        context.quadraticCurveTo(x + width, y, x + width, y + radius);
        context.lineTo(x + width, y + height - radius);
        context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        context.lineTo(x + radius, y + height);
        context.quadraticCurveTo(x, y + height, x, y + height - radius);
        context.lineTo(x, y + radius);
        context.quadraticCurveTo(x, y, x + radius, y);
        context.closePath();
    }
    
    // ===== Auto-refresh notifications =====
    setInterval(function() {
        // Check for new notifications
        fetch('/api/notifications.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    const badge = document.querySelector('.notification-btn .badge');
                    if (badge) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'inline';
                    }
                }
            })
            .catch(() => {});
    }, 30000); // Every 30 seconds
    
    // ===== Class status auto-update =====
    const currentTime = new Date();
    document.querySelectorAll('.class-item').forEach(item => {
        const timeElement = item.querySelector('.class-time');
        if (timeElement) {
            // Update time remaining every minute
            setInterval(() => {
                // Refresh page to get updated times
                // Or implement real-time countdown
            }, 60000);
        }
    });
});

// ===== Utility: Format time remaining =====
function formatTimeRemaining(timestamp) {
    const now = new Date().getTime();
    const diff = timestamp - now;
    
    if (diff <= 0) return 'Now';
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (days > 0) {
        return `${days}d ${hours}h`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}