<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$user_id = SessionManager::getUserId();
$store_id = SessionManager::getStoreId();
$db = new Database();

// Get settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('glass_size_reduction', 'order_number_prefix')");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$glass_reduction = $settings['glass_size_reduction'] ?? 4;
$order_prefix = $settings['order_number_prefix'] ?? 'ALM';

$error = '';
$success = '';

if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_order') {
        try {
            $db->getConnection()->beginTransaction();
            
            // Validate input
            $customer_id = $_POST['customer_id'] ?? '';
            $order_items = $_POST['order_items'] ?? [];
            $discount = floatval($_POST['discount'] ?? 0);
            $transport_cost = floatval($_POST['transport_cost'] ?? 0);
            $assembly_cost = floatval($_POST['assembly_cost'] ?? 0);
            $accessories_cost = floatval($_POST['accessories_cost'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($customer_id) || empty($order_items)) {
                throw new Exception('Müştəri və məhsul məlumatları mütləqdir');
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($order_items as $item) {
                $subtotal += floatval($item['total_price']);
            }
            
            $total_amount = $subtotal - $discount + $transport_cost + $assembly_cost + $accessories_cost;
            
            // Generate order number
            $order_number = Utils::generateOrderNumber($order_prefix);
            
            // Create order
            $stmt = $db->query("INSERT INTO orders (order_number, customer_id, sales_person_id, store_id, order_date, subtotal, discount, transport_cost, assembly_cost, accessories_cost, total_amount, notes, barcode) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)", 
                [$order_number, $customer_id, $user_id, $store_id, $subtotal, $discount, $transport_cost, $assembly_cost, $accessories_cost, $total_amount, $notes, 'BC' . time()]);
            
            $order_id = $db->lastInsertId();
            
            // Add order items
            foreach ($order_items as $item) {
                $db->query("INSERT INTO order_items (order_id, item_type, profile_type, glass_type, height, width, quantity, unit_price, total_price, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", 
                    [$order_id, $item['item_type'], $item['profile_type'], $item['glass_type'], $item['height'], $item['width'], $item['quantity'], $item['unit_price'], $item['total_price'], $item['notes']]);
            }
            
            // Update customer statistics
            $db->query("UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?", 
                [$total_amount, $customer_id]);
            
            $db->getConnection()->commit();
            
            // Send WhatsApp notification (placeholder)
            $stmt = $db->query("SELECT c.phone, c.contact_person FROM customers c WHERE c.id = ?", [$customer_id]);
            $customer = $stmt->fetch();
            if ($customer) {
                $message = "Salam {$customer['contact_person']}! Sifarişiniz qəbul edildi. Sifariş nömrəsi: {$order_number}. Alumpro.Az";
                // Utils::sendWhatsAppMessage($customer['phone'], $message);
            }
            
            $success = "Sifariş uğurla yaradıldı! Nömrə: $order_number";
            
            // Redirect to order details
            header("Location: order-details.php?id=$order_id&success=1");
            exit;
            
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sifariş - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .customer-info {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }
        
        .order-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        
        .order-item .item-header {
            background: var(--gradient-primary);
            color: white;
            padding: 0.5rem 1rem;
            margin: -1rem -1rem 1rem -1rem;
            border-radius: 7px 7px 0 0;
            font-weight: 600;
        }
        
        .glass-calc {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .total-section {
            background: #f8f9fa;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            padding: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sales-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-plus-circle text-success"></i> Yeni Sifariş
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Geri
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="orderForm">
                    <input type="hidden" name="action" value="save_order">
                    
                    <!-- Customer Selection -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person"></i> Müştəri Seçimi
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <label for="customer_search" class="form-label">Müştəri axtarın</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="customer_search" 
                                               placeholder="Ad, telefon və ya şirkət adı ilə axtarın...">
                                        <button type="button" class="btn btn-outline-secondary" id="customerSearchBtn">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <div id="customerResults" class="mt-2"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-grid">
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                            <i class="bi bi-person-plus"></i> Yeni Müştəri
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Selected Customer Info -->
                            <div id="selectedCustomerInfo" class="customer-info d-none">
                                <input type="hidden" name="customer_id" id="selected_customer_id">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Müştəri:</strong> <span id="customer_name"></span><br>
                                        <strong>Telefon:</strong> <span id="customer_phone"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Keçmiş sifarişlər:</strong> <span id="customer_orders"></span><br>
                                        <strong>Ümumi xərc:</strong> <span id="customer_spent"></span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="clearCustomer()">
                                    <i class="bi bi-x"></i> Dəyiş
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul"></i> Sifariş Məhsulları
                            </h5>
                            <button type="button" class="btn btn-success btn-sm" onclick="addOrderItem()">
                                <i class="bi bi-plus"></i> Qapaq Əlavə Et
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="orderItems">
                                <!-- Order items will be added here dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-calculator"></i> Sifariş Xülasəsi
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="total-section">
                                        <table class="table table-borderless mb-0">
                                            <tr>
                                                <td>Ara məbləğ:</td>
                                                <td class="text-end"><span id="subtotal">0.00 AZN</span></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label for="discount">Endirim:</label>
                                                    <input type="number" class="form-control form-control-sm d-inline w-auto" 
                                                           name="discount" id="discount" value="0" step="0.01" min="0" 
                                                           onchange="calculateTotal()">
                                                </td>
                                                <td class="text-end text-success">-<span id="discount_display">0.00</span> AZN</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label for="transport_cost">Nəqliyyat:</label>
                                                    <input type="number" class="form-control form-control-sm d-inline w-auto" 
                                                           name="transport_cost" id="transport_cost" value="0" step="0.01" min="0" 
                                                           onchange="calculateTotal()">
                                                </td>
                                                <td class="text-end">+<span id="transport_display">0.00</span> AZN</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label for="assembly_cost">Yığma:</label>
                                                    <input type="number" class="form-control form-control-sm d-inline w-auto" 
                                                           name="assembly_cost" id="assembly_cost" value="0" step="0.01" min="0" 
                                                           onchange="calculateTotal()">
                                                </td>
                                                <td class="text-end">+<span id="assembly_display">0.00</span> AZN</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label for="accessories_cost">Aksesuarlar:</label>
                                                    <input type="number" class="form-control form-control-sm d-inline w-auto" 
                                                           name="accessories_cost" id="accessories_cost" value="0" step="0.01" min="0" 
                                                           onchange="calculateTotal()">
                                                </td>
                                                <td class="text-end">+<span id="accessories_display">0.00</span> AZN</td>
                                            </tr>
                                            <tr class="border-top">
                                                <td><strong>Ümumi məbləğ:</strong></td>
                                                <td class="text-end"><strong><span id="total">0.00 AZN</span></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Qeydlər</label>
                                        <textarea class="form-control" name="notes" id="notes" rows="5" 
                                                  placeholder="Sifariş haqqında əlavə qeydlər..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                        <button type="button" class="btn btn-outline-secondary me-md-2" onclick="window.history.back()">
                            <i class="bi bi-x-circle"></i> Ləğv et
                        </button>
                        <button type="submit" class="btn btn-success btn-lg" id="saveOrderBtn">
                            <i class="bi bi-check-circle"></i> Sifarişi Yadda Saxla
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Yeni Müştəri Əlavə Et
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="newCustomerForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_name" class="form-label">Ad və Soyad *</label>
                                <input type="text" class="form-control" id="new_customer_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_phone" class="form-label">Telefon *</label>
                                <input type="tel" class="form-control" id="new_customer_phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="new_customer_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_customer_company" class="form-label">Şirkət Adı</label>
                                <input type="text" class="form-control" id="new_customer_company">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_customer_address" class="form-label">Ünvan</label>
                            <textarea class="form-control" id="new_customer_address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check"></i> Müştəri Əlavə Et
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        let orderItemCounter = 0;
        const glassReduction = <?= $glass_reduction ?>;
        
        // Add new order item
        function addOrderItem() {
            orderItemCounter++;
            const itemHtml = `
                <div class="order-item" id="orderItem${orderItemCounter}">
                    <div class="item-header">
                        Qapaq #${orderItemCounter}
                        <button type="button" class="btn btn-sm btn-light float-end" onclick="removeOrderItem(${orderItemCounter})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Profil Tipi *</label>
                            <select class="form-select" name="order_items[${orderItemCounter}][profile_type]" required>
                                <option value="">Seçin...</option>
                                <option value="B.O">B.O</option>
                                <option value="Fumo">Fumo</option>
                                <option value="Qapalı Profil">Qapalı Profil</option>
                                <option value="Açıq Profil">Açıq Profil</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Şüşə Növü *</label>
                            <select class="form-select" name="order_items[${orderItemCounter}][glass_type]" required>
                                <option value="">Seçin...</option>
                                <option value="Şəffaf">Şəffaf</option>
                                <option value="Mat">Mat</option>
                                <option value="Rəngli">Rəngli</option>
                                <option value="Naxışlı">Naxışlı</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Hündürlük (sm) *</label>
                            <input type="number" class="form-control" name="order_items[${orderItemCounter}][height]" 
                                   step="0.1" min="0" required onchange="calculateGlassSize(${orderItemCounter})">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">En (sm) *</label>
                            <input type="number" class="form-control" name="order_items[${orderItemCounter}][width]" 
                                   step="0.1" min="0" required onchange="calculateGlassSize(${orderItemCounter})">
                        </div>
                        <div class="col-md-1 mb-3">
                            <label class="form-label">Say *</label>
                            <input type="number" class="form-control" name="order_items[${orderItemCounter}][quantity]" 
                                   min="1" value="1" required onchange="calculateItemTotal(${orderItemCounter})">
                        </div>
                        <div class="col-md-1 mb-3">
                            <label class="form-label">Qiymət *</label>
                            <input type="number" class="form-control" name="order_items[${orderItemCounter}][unit_price]" 
                                   step="0.01" min="0" required onchange="calculateItemTotal(${orderItemCounter})">
                        </div>
                    </div>
                    
                    <div class="glass-calc" id="glassCalc${orderItemCounter}" style="display: none;">
                        <strong>Şüşə ölçüləri:</strong>
                        <span id="glassSize${orderItemCounter}"></span>
                        <br>
                        <small class="text-muted">Qapaq ölçülərindən ${glassReduction}mm çıxılır</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Qeydlər</label>
                        <textarea class="form-control" name="order_items[${orderItemCounter}][notes]" 
                                  rows="2" placeholder="Bu qapaq üçün əlavə qeydlər..."></textarea>
                    </div>
                    
                    <div class="text-end">
                        <strong>Məbləğ: <span id="itemTotal${orderItemCounter}">0.00 AZN</span></strong>
                        <input type="hidden" name="order_items[${orderItemCounter}][item_type]" value="door">
                        <input type="hidden" name="order_items[${orderItemCounter}][total_price]" id="itemTotalHidden${orderItemCounter}" value="0">
                    </div>
                </div>
            `;
            
            document.getElementById('orderItems').insertAdjacentHTML('beforeend', itemHtml);
        }
        
        // Remove order item
        function removeOrderItem(id) {
            const item = document.getElementById('orderItem' + id);
            if (item) {
                item.remove();
                calculateTotal();
            }
        }
        
        // Calculate glass size based on door dimensions
        function calculateGlassSize(id) {
            const heightInput = document.querySelector(`input[name="order_items[${id}][height]"]`);
            const widthInput = document.querySelector(`input[name="order_items[${id}][width]"]`);
            const glassCalc = document.getElementById('glassCalc' + id);
            const glassSizeSpan = document.getElementById('glassSize' + id);
            
            const height = parseFloat(heightInput.value) || 0;
            const width = parseFloat(widthInput.value) || 0;
            
            if (height > 0 && width > 0) {
                const glassHeight = Math.max(0, height - glassReduction);
                const glassWidth = Math.max(0, width - glassReduction);
                
                glassSizeSpan.textContent = `${glassHeight} x ${glassWidth} sm`;
                glassCalc.style.display = 'block';
            } else {
                glassCalc.style.display = 'none';
            }
        }
        
        // Calculate item total
        function calculateItemTotal(id) {
            const quantityInput = document.querySelector(`input[name="order_items[${id}][quantity]"]`);
            const priceInput = document.querySelector(`input[name="order_items[${id}][unit_price]"]`);
            const totalSpan = document.getElementById('itemTotal' + id);
            const totalHidden = document.getElementById('itemTotalHidden' + id);
            
            const quantity = parseInt(quantityInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const total = quantity * price;
            
            totalSpan.textContent = total.toFixed(2) + ' AZN';
            totalHidden.value = total.toFixed(2);
            
            calculateTotal();
        }
        
        // Calculate order total
        function calculateTotal() {
            let subtotal = 0;
            
            // Sum all item totals
            document.querySelectorAll('[id^="itemTotalHidden"]').forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const transport = parseFloat(document.getElementById('transport_cost').value) || 0;
            const assembly = parseFloat(document.getElementById('assembly_cost').value) || 0;
            const accessories = parseFloat(document.getElementById('accessories_cost').value) || 0;
            
            const total = subtotal - discount + transport + assembly + accessories;
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' AZN';
            document.getElementById('discount_display').textContent = discount.toFixed(2);
            document.getElementById('transport_display').textContent = transport.toFixed(2);
            document.getElementById('assembly_display').textContent = assembly.toFixed(2);
            document.getElementById('accessories_display').textContent = accessories.toFixed(2);
            document.getElementById('total').textContent = total.toFixed(2) + ' AZN';
        }
        
        // Customer search functionality
        document.getElementById('customer_search').addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                searchCustomers(query);
            } else {
                document.getElementById('customerResults').innerHTML = '';
            }
        });
        
        function searchCustomers(query) {
            fetch('../api/customers.php?action=search&q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    if (data.success && data.customers.length > 0) {
                        html = '<div class="list-group">';
                        data.customers.forEach(customer => {
                            html += `
                                <a href="#" class="list-group-item list-group-item-action" onclick="selectCustomer(${customer.id}, '${customer.contact_person}', '${customer.phone}', ${customer.total_orders}, ${customer.total_spent})">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${customer.contact_person}</h6>
                                        <small>${customer.total_orders} sifariş</small>
                                    </div>
                                    <p class="mb-