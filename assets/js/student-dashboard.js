/**
 * ClassMate - Student Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
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