// Main JavaScript functionality for Alumpro.Az

// Global variables
let currentUser = null;
let notificationPermission = false;

// Initialize application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Initialize counters
    initCounters();
    
    // Initialize mobile navigation
    initMobileNav();
    
    // Initialize notifications
    initNotifications();
    
    // Initialize form validations
    initFormValidations();
    
    // Initialize auto-refresh for real-time data
    initAutoRefresh();
}

// Counter animation
function initCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    const speed = 200;

    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-counter');
            const count = +counter.innerText;
            const inc = target / speed;

            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target;
            }
        };

        updateCount();
    });
}

// Mobile navigation
function initMobileNav() {
    const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
    const currentPath = window.location.pathname;

    mobileNavItems.forEach(item => {
        const href = item.getAttribute('href');
        if (currentPath.includes(href)) {
            item.classList.add('active');
        }

        item.addEventListener('click', function(e) {
            mobileNavItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

// Notification system
function initNotifications() {
    // Check if browser supports notifications
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            notificationPermission = true;
        } else if (Notification.permission !== 'denied') {
            // Show permission modal
            const notificationModal = document.getElementById('notificationModal');
            if (notificationModal) {
                const modal = new bootstrap.Modal(notificationModal);
                modal.show();
            }
        }
    }

    // Enable notifications button
    const enableBtn = document.getElementById('enableNotifications');
    if (enableBtn) {
        enableBtn.addEventListener('click', function() {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    notificationPermission = true;
                    showNotification('Bildirişlər aktivləşdirildi!', 'Artıq yeniliklərdən xəbərdar olacaqsınız.', 'success');
                    localStorage.setItem('notifications_enabled', 'true');
                }
                bootstrap.Modal.getInstance(document.getElementById('notificationModal')).hide();
            });
        });
    }
}

// Show notification
function showNotification(title, message, type = 'info') {
    // Browser notification
    if (notificationPermission && 'Notification' in window) {
        new Notification(title, {
            body: message,
            icon: '/assets/icons/icon-192x192.png',
            badge: '/assets/icons/icon-72x72.png'
        });
    }

    // In-app notification
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong>${title}</strong>
                <p class="mb-0">${message}</p>
            </div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;

    document.body.appendChild(notification);

    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Form validations
function initFormValidations() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('994')) {
                value = '+' + value;
            } else if (value.startsWith('0')) {
                value = '+994' + value.substring(1);
            } else if (!value.startsWith('+994')) {
                value = '+994' + value;
            }
            e.target.value = value;
        });
    });

    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function(e) {
            const email = e.target.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                e.target.setCustomValidity('Düzgün email daxil edin');
            } else {
                e.target.setCustomValidity('');
            }
        });
    });
}

// Auto refresh for real-time data
function initAutoRefresh() {
    // Refresh every 30 seconds for dashboard pages
    if (window.location.pathname.includes('dashboard.php')) {
        setInterval(() => {
            updateDashboardStats();
        }, 30000);
    }

    // Refresh every 60 seconds for order pages
    if (window.location.pathname.includes('orders.php')) {
        setInterval(() => {
            updateOrderStatuses();
        }, 60000);
    }
}

// Update dashboard statistics
function updateDashboardStats() {
    const statsElements = document.querySelectorAll('[data-stat]');
    
    fetch('api/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statsElements.forEach(element => {
                    const statType = element.getAttribute('data-stat');
                    if (data.stats[statType]) {
                        element.textContent = data.stats[statType];
                    }
                });
            }
        })
        .catch(error => {
            console.error('Stats update error:', error);
        });
}

// Update order statuses
function updateOrderStatuses() {
    const orderRows = document.querySelectorAll('[data-order-id]');
    
    if (orderRows.length === 0) return;
    
    const orderIds = Array.from(orderRows).map(row => row.getAttribute('data-order-id'));
    
    fetch('api/orders.php?action=get_statuses', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ order_ids: orderIds })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.orders.forEach(order => {
                    const row = document.querySelector(`[data-order-id="${order.id}"]`);
                    if (row) {
                        const statusElement = row.querySelector('.order-status');
                        if (statusElement) {
                            statusElement.className = `badge bg-${getStatusColor(order.status)}`;
                            statusElement.textContent = getStatusText(order.status);
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Order status update error:', error);
        });
}

// Helper functions
function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'in_production': 'info',
        'completed': 'success',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'pending': 'Gözləyir',
        'in_production': 'İstehsalda',
        'completed': 'Tamamlandı',
        'cancelled': 'Ləğv edildi'
    };
    return texts[status] || 'Naməlum';
}

// Loading state management
function showLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = `
            <div class="d-flex justify-content-center align-items-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Yüklənir...</span>
                </div>
                <span class="ms-2">Yüklənir...</span>
            </div>
        `;
    }
}

function hideLoading(element, content = '') {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    
    if (element) {
        element.innerHTML = content;
    }
}

// AJAX helper functions
function makeRequest(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const finalOptions = { ...defaultOptions, ...options };

    return fetch(url, finalOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

// Export functions for global use
window.AlumproApp = {
    showNotification,
    showLoading,
    hideLoading,
    makeRequest,
    updateDashboardStats,
    updateOrderStatuses
};