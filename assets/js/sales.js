// Sales specific JavaScript functionality

class SalesManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupCustomerSearch();
        this.setupOrderForm();
        this.initializeCharts();
        this.setupRealTimeUpdates();
    }

    setupCustomerSearch() {
        const searchInput = document.getElementById('customer_search');
        if (!searchInput) return;

        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    salesManager.searchCustomers(query);
                }, 300);
            } else {
                document.getElementById('customerResults').innerHTML = '';
            }
        });
    }

    async searchCustomers(query) {
        try {
            const response = await fetch(`../api/customers.php?action=search&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderCustomerResults(data.customers);
            } else {
                this.showError('Müştəri axtarışında xəta');
            }
        } catch (error) {
            console.error('Customer search error:', error);
            this.showError('Axtarış xətası');
        }
    }

    renderCustomerResults(customers) {
        const container = document.getElementById('customerResults');
        if (!container) return;

        if (customers.length === 0) {
            container.innerHTML = '<div class="alert alert-info">Müştəri tapılmadı</div>';
            return;
        }

        let html = '<div class="list-group">';
        customers.forEach(customer => {
            html += `
                <a href="#" class="list-group-item list-group-item-action" 
                   onclick="salesManager.selectCustomer(${customer.id}, '${customer.contact_person}', '${customer.phone}', ${customer.total_orders}, ${customer.total_spent})">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${customer.contact_person}</h6>
                        <small>${customer.total_orders} sifariş</small>
                    </div>
                    <p class="mb-1">
                        <small class="text-muted">${customer.phone}</small>
                        ${customer.company_name ? ' - ' + customer.company_name : ''}
                    </p>
                    <small class="text-success">${customer.total_spent} AZN</small>
                </a>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    }

    selectCustomer(id, name, phone, orders, spent) {
        document.getElementById('selected_customer_id').value = id;
        document.getElementById('customer_name').textContent = name;
        document.getElementById('customer_phone').textContent = phone;
        document.getElementById('customer_orders').textContent = orders;
        document.getElementById('customer_spent').textContent = spent + ' AZN';
        
        document.getElementById('selectedCustomerInfo').classList.remove('d-none');
        document.getElementById('customerResults').innerHTML = '';
        document.getElementById('customer_search').value = name;
    }

    setupOrderForm() {
        // Order item calculations
        this.setupItemCalculations();
        
        // Form validations
        this.setupFormValidations();
        
        // Dynamic item management
        this.setupDynamicItems();
    }

    setupItemCalculations() {
        // Listen for quantity and price changes
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[name*="[quantity]"], input[name*="[unit_price]"]')) {
                this.calculateItemTotal(e.target);
            }
            
            if (e.target.matches('#discount, #transport_cost, #assembly_cost, #accessories_cost')) {
                this.calculateOrderTotal();
            }
        });

        // Listen for dimension changes to calculate glass size
        document.addEventListener('change', (e) => {
            if (e.target.matches('input[name*="[height]"], input[name*="[width]"]')) {
                this.calculateGlassSize(e.target);
            }
        });
    }

    calculateItemTotal(input) {
        const itemContainer = input.closest('.order-item');
        if (!itemContainer) return;

        const quantityInput = itemContainer.querySelector('input[name*="[quantity]"]');
        const priceInput = itemContainer.querySelector('input[name*="[unit_price]"]');
        const totalSpan = itemContainer.querySelector('[id^="itemTotal"]');
        const totalHidden = itemContainer.querySelector('input[name*="[total_price]"]');

        if (!quantityInput || !priceInput || !totalSpan || !totalHidden) return;

        const quantity = parseInt(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;

        totalSpan.textContent = total.toFixed(2) + ' AZN';
        totalHidden.value = total.toFixed(2);

        this.calculateOrderTotal();
    }

    calculateOrderTotal() {
        let subtotal = 0;
        
        // Sum all item totals
        document.querySelectorAll('input[name*="[total_price]"]').forEach(input => {
            subtotal += parseFloat(input.value) || 0;
        });

        const discount = parseFloat(document.getElementById('discount')?.value) || 0;
        const transport = parseFloat(document.getElementById('transport_cost')?.value) || 0;
        const assembly = parseFloat(document.getElementById('assembly_cost')?.value) || 0;
        const accessories = parseFloat(document.getElementById('accessories_cost')?.value) || 0;

        const total = subtotal - discount + transport + assembly + accessories;

        // Update display
        const subtotalEl = document.getElementById('subtotal');
        const discountEl = document.getElementById('discount_display');
        const transportEl = document.getElementById('transport_display');
        const assemblyEl = document.getElementById('assembly_display');
        const accessoriesEl = document.getElementById('accessories_display');
        const totalEl = document.getElementById('total');

        if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2) + ' AZN';
        if (discountEl) discountEl.textContent = discount.toFixed(2);
        if (transportEl) transportEl.textContent = transport.toFixed(2);
        if (assemblyEl) assemblyEl.textContent = assembly.toFixed(2);
        if (accessoriesEl) accessoriesEl.textContent = accessories.toFixed(2);
        if (totalEl) totalEl.textContent = total.toFixed(2) + ' AZN';
    }

    calculateGlassSize(input) {
        const itemContainer = input.closest('.order-item');
        if (!itemContainer) return;

        const heightInput = itemContainer.querySelector('input[name*="[height]"]');
        const widthInput = itemContainer.querySelector('input[name*="[width]"]');
        const glassCalc = itemContainer.querySelector('[id^="glassCalc"]');
        const glassSizeSpan = itemContainer.querySelector('[id^="glassSize"]');

        if (!heightInput || !widthInput || !glassCalc || !glassSizeSpan) return;

        const height = parseFloat(heightInput.value) || 0;
        const width = parseFloat(widthInput.value) || 0;
        const glassReduction = window.glassReduction || 4;

        if (height > 0 && width > 0) {
            const glassHeight = Math.max(0, height - glassReduction);
            const glassWidth = Math.max(0, width - glassReduction);

            glassSizeSpan.textContent = `${glassHeight} x ${glassWidth} sm`;
            glassCalc.style.display = 'block';
        } else {
            glassCalc.style.display = 'none';
        }
    }

    setupFormValidations() {
        const orderForm = document.getElementById('orderForm');
        if (!orderForm) return;

        orderForm.addEventListener('submit', (e) => {
            if (!this.validateOrderForm()) {
                e.preventDefault();
            }
        });
    }

    validateOrderForm() {
        const customerId = document.getElementById('selected_customer_id')?.value;
        const orderItems = document.querySelectorAll('.order-item');

        if (!customerId) {
            this.showError('Zəhmət olmasa müştəri seçin!');
            return false;
        }

        if (orderItems.length === 0) {
            this.showError('Zəhmət olmasa ən azı bir məhsul əlavə edin!');
            return false;
        }

        // Validate each order item
        let isValid = true;
        orderItems.forEach(item => {
            const requiredInputs = item.querySelectorAll('input[required], select[required]');
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
        });

        if (!isValid) {
            this.showError('Zəhmət olmasa bütün məcburi sahələri doldurun!');
            return false;
        }

        return true;
    }

    setupDynamicItems() {
        // Add new item functionality is handled in the main HTML
        // This method can be extended for additional dynamic features
    }

    initializeCharts() {
        // Initialize sales charts if Chart.js is available
        if (typeof Chart !== 'undefined') {
            this.initSalesChart();
            this.initProductChart();
        }
    }

    initSalesChart() {
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;

        // Fetch sales data and create chart
        this.fetchSalesData().then(data => {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Satışlar',
                        data: data.values,
                        borderColor: 'rgb(32, 178, 170)',
                        backgroundColor: 'rgba(32, 178, 170, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    }

    async fetchSalesData() {
        try {
            const response = await fetch('../api/stats.php?type=sales_chart');
            const data = await response.json();
            return data.success ? data.chart_data : { labels: [], values: [] };
        } catch (error) {
            console.error('Sales data fetch error:', error);
            return { labels: [], values: [] };
        }
    }

    setupRealTimeUpdates() {
        // Update order statuses every minute
        setInterval(() => {
            this.updateOrderStatuses();
        }, 60000);

        // Update dashboard stats every 5 minutes
        setInterval(() => {
            this.updateDashboardStats();
        }, 300000);
    }

    async updateOrderStatuses() {
        const orderRows = document.querySelectorAll('[data-order-id]');
        if (orderRows.length === 0) return;

        try {
            const orderIds = Array.from(orderRows).map(row => row.getAttribute('data-order-id'));
            
            const response = await fetch('../api/orders.php?action=get_statuses', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_ids: orderIds })
            });

            const data = await response.json();
            if (data.success) {
                this.updateOrderStatusElements(data.orders);
            }
        } catch (error) {
            console.error('Order status update error:', error);
        }
    }

    updateOrderStatusElements(orders) {
        orders.forEach(order => {
            const row = document.querySelector(`[data-order-id="${order.id}"]`);
            if (row) {
                const statusElement = row.querySelector('.order-status');
                if (statusElement) {
                    statusElement.className = `badge bg-${this.getStatusColor(order.status)}`;
                    statusElement.textContent = this.getStatusText(order.status);
                }
            }
        });
    }

    getStatusColor(status) {
        const colors = {
            'pending': 'warning',
            'in_production': 'info',
            'completed': 'success',
            'cancelled': 'danger'
        };
        return colors[status] || 'secondary';
    }

    getStatusText(status) {
        const texts = {
            'pending': 'Gözləyir',
            'in_production': 'İstehsalda',
            'completed': 'Tamamlandı',
            'cancelled': 'Ləğv edildi'
        };
        return texts[status] || 'Naməlum';
    }

    // Notification methods
    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        if (window.AlumproApp && window.AlumproApp.showNotification) {
            window.AlumproApp.showNotification('Sistem', message, type);
        } else {
            alert(message);
        }
    }

    // Utility methods
    formatCurrency(amount) {
        return new Intl.NumberFormat('az-AZ', {
            style: 'currency',
            currency: 'AZN'
        }).format(amount);
    }

    formatDate(date) {
        return new Intl.DateTimeFormat('az-AZ').format(new Date(date));
    }
}

// Initialize sales manager
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('sales-page') || 
        window.location.pathname.includes('/sales/')) {
        window.salesManager = new SalesManager();
    }
});

// Global functions for backward compatibility
function selectCustomer(id, name, phone, orders, spent) {
    if (window.salesManager) {
        window.salesManager.selectCustomer(id, name, phone, orders, spent);
    }
}

function calculateItemTotal(id) {
    if (window.salesManager) {
        const input = document.querySelector(`input[name="order_items[${id}][quantity]"]`);
        if (input) {
            window.salesManager.calculateItemTotal(input);
        }
    }
}

function calculateGlassSize(id) {
    if (window.salesManager) {
        const input = document.querySelector(`input[name="order_items[${id}][height]"]`);
        if (input) {
            window.salesManager.calculateGlassSize(input);
        }
    }
}

function calculateTotal() {
    if (window.salesManager) {
        window.salesManager.calculateOrderTotal();
    }
}