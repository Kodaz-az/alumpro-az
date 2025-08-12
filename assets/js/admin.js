// Admin panel specific JavaScript functionality

class AdminManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupDataTables();
        this.setupCharts();
        this.setupFileUploads();
        this.setupBulkActions();
        this.initializeRealTimeUpdates();
    }

    setupDataTables() {
        // Enhanced table functionality
        const tables = document.querySelectorAll('.admin-table');
        tables.forEach(table => {
            this.enhanceTable(table);
        });
    }

    enhanceTable(table) {
        // Add sorting capability
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                this.sortTable(table, header);
            });
        });

        // Add search functionality
        this.addTableSearch(table);
    }

    sortTable(table, header) {
        const column = Array.from(header.parentNode.children).indexOf(header);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        const currentSort = header.getAttribute('data-sort') || 'asc';
        const newSort = currentSort === 'asc' ? 'desc' : 'asc';
        
        // Clear all sort indicators
        table.querySelectorAll('th').forEach(th => {
            th.removeAttribute('data-sort');
            th.querySelector('.sort-indicator')?.remove();
        });
        
        // Set new sort
        header.setAttribute('data-sort', newSort);
        const indicator = document.createElement('i');
        indicator.className = `bi bi-arrow-${newSort === 'asc' ? 'up' : 'down'} sort-indicator ms-1`;
        header.appendChild(indicator);
        
        // Sort rows
        rows.sort((a, b) => {
            const aVal = a.cells[column]?.textContent.trim() || '';
            const bVal = b.cells[column]?.textContent.trim() || '';
            
            // Try to parse as numbers
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return newSort === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // Sort as strings
            return newSort === 'asc' ? 
                aVal.localeCompare(bVal) : 
                bVal.localeCompare(aVal);
        });
        
        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
    }

    addTableSearch(table) {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search mb-3';
        searchContainer.innerHTML = `
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" placeholder="Cədvəldə axtar...">
            </div>
        `;
        
        table.parentNode.insertBefore(searchContainer, table);
        
        const searchInput = searchContainer.querySelector('input');
        searchInput.addEventListener('input', (e) => {
            this.filterTable(table, e.target.value);
        });
    }

    filterTable(table, searchTerm) {
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }

    setupCharts() {
        if (typeof Chart !== 'undefined') {
            this.initDashboardCharts();
        }
    }

    initDashboardCharts() {
        // Revenue chart
        this.initRevenueChart();
        
        // Orders chart
        this.initOrdersChart();
        
        // Users chart
        this.initUsersChart();
    }

    async initRevenueChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        try {
            const data = await this.fetchChartData('revenue');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Gəlir (AZN)',
                        data: data.values,
                        borderColor: 'rgb(32, 178, 170)',
                        backgroundColor: 'rgba(32, 178, 170, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' AZN';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Gəlir: ' + context.parsed.y.toLocaleString() + ' AZN';
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Revenue chart error:', error);
        }
    }

    async initOrdersChart() {
        const ctx = document.getElementById('ordersChart');
        if (!ctx) return;

        try {
            const data = await this.fetchChartData('orders');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Sifarişlər',
                        data: data.values,
                        backgroundColor: [
                            'rgba(70, 130, 180, 0.8)',
                            'rgba(32, 178, 170, 0.8)',
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)'
                        ],
                        borderColor: [
                            'rgb(70, 130, 180)',
                            'rgb(32, 178, 170)',
                            'rgb(40, 167, 69)',
                            'rgb(255, 193, 7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Orders chart error:', error);
        }
    }

    async fetchChartData(type) {
        const response = await fetch(`../api/stats.php?chart=${type}`);
        const data = await response.json();
        return data.success ? data.chart_data : { labels: [], values: [] };
    }

    setupFileUploads() {
        // Enhanced file upload with drag & drop
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            this.enhanceFileInput(input);
        });
    }

    enhanceFileInput(input) {
        const container = input.parentElement;
        container.style.position = 'relative';
        
        // Add drag & drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            container.addEventListener(eventName, () => {
                container.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, () => {
                container.classList.remove('drag-over');
            }, false);
        });

        container.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                input.files = files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, false);
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    setupBulkActions() {
        // Bulk selection and actions
        const masterCheckbox = document.getElementById('selectAll');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        const bulkActions = document.getElementById('bulkActions');

        if (masterCheckbox) {
            masterCheckbox.addEventListener('change', () => {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = masterCheckbox.checked;
                });
                this.toggleBulkActions();
            });
        }

        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateMasterCheckbox();
                this.toggleBulkActions();
            });
        });
    }

    updateMasterCheckbox() {
        const masterCheckbox = document.getElementById('selectAll');
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        
        if (!masterCheckbox) return;

        const checkedCount = Array.from(itemCheckboxes).filter(cb => cb.checked).length;
        const totalCount = itemCheckboxes.length;

        masterCheckbox.checked = checkedCount === totalCount;
        masterCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
    }

    toggleBulkActions() {
        const itemCheckboxes = document.querySelectorAll('.item-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        
        if (!bulkActions) return;

        const checkedCount = Array.from(itemCheckboxes).filter(cb => cb.checked).length;
        
        if (checkedCount > 0) {
            bulkActions.style.display = 'block';
            bulkActions.querySelector('.selected-count').textContent = checkedCount;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    initializeRealTimeUpdates() {
        // Real-time updates for admin dashboard
        this.setupStatsUpdates();
        this.setupNotifications();
    }

    setupStatsUpdates() {
        // Update stats every 30 seconds
        setInterval(() => {
            this.updateDashboardStats();
        }, 30000);
    }

    async updateDashboardStats() {
        try {
            const response = await fetch('../api/stats.php?action=dashboard_stats');
            const data = await response.json();
            
            if (data.success) {
                this.updateStatElements(data.stats);
            }
        } catch (error) {
            console.error('Stats update error:', error);
        }
    }

    updateStatElements(stats) {
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                this.animateNumber(element, stats[key]);
            }
        });
    }

    animateNumber(element, targetValue) {
        const currentValue = parseInt(element.textContent) || 0;
        const increment = (targetValue - currentValue) / 10;
        let current = currentValue;

        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= targetValue) || 
                (increment < 0 && current <= targetValue)) {
                current = targetValue;
                clearInterval(timer);
            }
            element.textContent = Math.round(current);
        }, 50);
    }

    setupNotifications() {
        // Setup admin notifications
        this.checkForAlerts();
        
        // Check every 5 minutes
        setInterval(() => {
            this.checkForAlerts();
        }, 300000);
    }

    async checkForAlerts() {
        try {
            const response = await fetch('../api/alerts.php');
            const data = await response.json();
            
            if (data.success && data.alerts.length > 0) {
                this.showAdminAlerts(data.alerts);
            }
        } catch (error) {
            console.error('Alerts check error:', error);
        }
    }

    showAdminAlerts(alerts) {
        alerts.forEach(alert => {
            this.showNotification(alert.title, alert.message, alert.type);
        });
    }

    // Utility methods
    showNotification(title, message, type = 'info') {
        if (window.AlumproApp && window.AlumproApp.showNotification) {
            window.AlumproApp.showNotification(title, message, type);
        }
    }

    // Export/Import functionality
    exportTable(tableId, filename = 'export') {
        const table = document.getElementById(tableId);
        if (!table) return;

        const csv = this.tableToCSV(table);
        this.downloadCSV(csv, filename + '.csv');
    }

    tableToCSV(table) {
        const rows = table.querySelectorAll('tr');
        const csv = [];

        rows.forEach(row => {
            const cells = row.querySelectorAll('td, th');
            const rowData = Array.from(cells).map(cell => {
                return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
            });
            csv.push(rowData.join(','));
        });

        return csv.join('\n');
    }

    downloadCSV(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}

// Initialize admin manager
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('admin-page') || 
        window.location.pathname.includes('/admin/')) {
        window.adminManager = new AdminManager();
    }
});

// Global admin functions
function deleteItem(id, type, name = '') {
    const confirmMessage = name ? 
        `"${name}" adlı ${type}i silmək istədiyinizdən əminsiniz?` : 
        `Bu ${type}i silmək istədiyinizdən əminsiniz?`;
        
    if (confirm(confirmMessage)) {
        // Create and submit delete form
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="type" value="${type}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id, type, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'aktivləşdirmək' : 'deaktivləşdirmək';
    
    if (confirm(`Bu elementi ${action} istəyirsiniz?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="type" value="${type}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// CSS for admin enhancements
const adminCSS = `
.drag-over {
    border: 2px dashed var(--primary-color) !important;
    background-color: rgba(32, 178, 170, 0.1) !important;
}

.sort-indicator {
    opacity: 0.7;
}

.table-search input {
    border-radius: 8px;
}

#bulkActions {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: var(--gradient-primary);
    color: white;
    padding: 1rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    display: none;
}

.admin-stat-card {
    transition: all 0.3s ease;
}

.admin-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
`;

// Inject admin CSS
const adminStyle = document.createElement('style');
adminStyle.textContent = adminCSS;
document.head.appendChild(adminStyle);