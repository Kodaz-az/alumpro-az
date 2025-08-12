<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireSales();

$user_id = SessionManager::getUserId();
$user_role = SessionManager::getUserRole();
$store_id = SessionManager::getStoreId();
$db = new Database();

$message = '';
$error = '';

// Handle bulk status updates
if ($_POST && $_POST['action'] === 'bulk_update_status') {
    try {
        $order_ids = $_POST['order_ids'] ?? [];
        $new_status = $_POST['bulk_status'];
        
        if (empty($order_ids) || empty($new_status)) {
            throw new Exception('Sifarişlər və status seçilməlidir');
        }
        
        $db->getConnection()->beginTransaction();
        
        $updated_count = 0;
        foreach ($order_ids as $order_id) {
            $stmt = $db->query("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
            if ($stmt->rowCount() > 0) {
                $updated_count++;
                
                // Send WhatsApp notification for status change
                require_once '../includes/whatsapp_helper.php';
                WhatsAppHelper::notifyOrderStatusChanged($order_id, $new_status);
            }
        }
        
        $db->getConnection()->commit();
        Utils::logActivity($user_id, 'bulk_order_update', "Toplu status yeniləməsi: $updated_count sifariş");
        
        $message = "$updated_count sifarişin statusu yeniləndi";
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $error = $e->getMessage();
    }
}

// Pagination and filtering
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer_filter = $_GET['customer'] ?? '';

// Build where conditions
$where_conditions = ['1=1'];
$params = [];

// Store filter for sales users
if ($store_id && $user_role === 'sales') {
    $where_conditions[] = "o.store_id = ?";
    $params[] = $store_id;
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR c.contact_person LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "o.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "o.order_date <= ?";
    $params[] = $date_to;
}

// Customer filter
if (!empty($customer_filter)) {
    $where_conditions[] = "o.customer_id = ?";
    $params[] = $customer_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total 
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id 
                    $where_clause", $count_params);
$total_orders = $stmt->fetch()['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT o.*, 
                           c.contact_person, c.phone as customer_phone, c.company_name,
                           u.full_name as sales_person,
                           s.name as store_name,
                           COUNT(oi.id) as item_count,
                           SUM(oi.quantity) as total_quantity
                    FROM orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    $where_clause
                    GROUP BY o.id
                    ORDER BY o.created_at DESC 
                    LIMIT ? OFFSET ?", $params);
$orders = $stmt->fetchAll();

// Get customers for filter dropdown
$customer_params = [];
$customer_where = "";
if ($store_id && $user_role === 'sales') {
    $customer_where = "WHERE EXISTS (SELECT 1 FROM orders WHERE customer_id = c.id AND store_id = ?)";
    $customer_params[] = $store_id;
}

$stmt = $db->query("SELECT DISTINCT c.id, c.contact_person 
                    FROM customers c 
                    $customer_where
                    ORDER BY c.contact_person", $customer_params);
$customers = $stmt->fetchAll();

// Get order statistics
$stats_params = [];
$stats_where = "";
if ($store_id && $user_role === 'sales') {
    $stats_where = "WHERE store_id = ?";
    $stats_params[] = $store_id;
}

$stmt = $db->query("SELECT 
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status = 'in_production' THEN 1 END) as production_orders,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN DATE(order_date) = CURDATE() THEN 1 END) as today_orders,
                    SUM(total_amount) as total_revenue,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed_revenue
                    FROM orders $stats_where", $stats_params);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sifarişlər - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .order-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .order-card.status-pending { border-left-color: #ffc107; }
        .order-card.status-in_production { border-left-color: #0dcaf0; }
        .order-card.status-completed { border-left-color: #198754; }
        .order-card.status-cancelled { border-left-color: #dc3545; }
        
        .bulk-actions {
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
            min-width: 300px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .filter-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php if ($user_role === 'admin'): ?>
                <?php include '../includes/admin-sidebar.php'; ?>
            <?php else: ?>
                <?php include '../includes/sales-sidebar.php'; ?>
            <?php endif; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-box text-primary"></i> Sifarişlər
                        <?php if ($store_id): ?>
                            <small class="text-muted">- <?= htmlspecialchars($orders[0]['store_name'] ?? '') ?></small>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="new-order.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Yeni Sifariş
                            </a>
                            <button class="btn btn-outline-primary" onclick="exportOrders()">
                                <i class="bi bi-download"></i> Excel Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-box text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="text-muted small">Ümumi Sifarişlər</div>
                                    <div class="h4 mb-0"><?= $stats['total_orders'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="text-muted small">Gözləyən</div>
                                    <div class="h4 mb-0"><?= $stats['pending_orders'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-gear text-info" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="text-muted small">İstehsalda</div>
                                    <div class="h4 mb-0"><?= $stats['production_orders'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="text-muted small">Tamamlanan</div>
                                    <div class="h4 mb-0"><?= $stats['completed_orders'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Axtarış</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Sifariş №, müştəri adı, telefon...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">Bütün statuslar</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Gözləyir</option>
                                    <option value="in_production" <?= $status_filter === 'in_production' ? 'selected' : '' ?>>İstehsalda</option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Ləğv edildi</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Başlanğıc</label>
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Son</label>
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="customer" class="form-label">Müştəri</label>
                                <select class="form-select" name="customer">
                                    <option value="">Bütün müştərilər</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>" <?= $customer_filter == $customer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['contact_person']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                Sifarişlər (<?= $total_orders ?> nəticə)
                            </h5>
                        </div>
                        <div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">
                                    Hamısını seç
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h3 class="text-muted mt-3">Sifariş tapılmadı</h3>
                                <p class="text-muted">Filtreləri dəyişin və ya yeni sifariş yaradın</p>
                                <a href="new-order.php" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> İlk Sifarişi Yarat
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="masterCheckbox">
                                            </th>
                                            <th>Sifariş №</th>
                                            <th>Müştəri</th>
                                            <th>Tarix</th>
                                            <th>Məhsul Sayı</th>
                                            <th>Məbləğ</th>
                                            <th>Status</th>
                                            <th>Satış Meneceri</th>
                                            <th>Əməliyyat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr class="order-row" data-order-id="<?= $order['id'] ?>">
                                                <td>
                                                    <input type="checkbox" class="order-checkbox" value="<?= $order['id'] ?>">
                                                </td>
                                                <td>
                                                    <a href="order-details.php?id=<?= $order['id'] ?>" class="text-decoration-none fw-bold">
                                                        <?= htmlspecialchars($order['order_number']) ?>
                                                    </a>
                                                    <?php if ($order['barcode']): ?>
                                                        <br>
                                                        <small class="text-muted font-monospace"><?= htmlspecialchars($order['barcode']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($order['contact_person']) ?></strong>
                                                        <?php if ($order['company_name']): ?>
                                                            <br>
                                                            <small class="text-muted"><?= htmlspecialchars($order['company_name']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-1">
                                                        <a href="tel:<?= $order['customer_phone'] ?>" class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-telephone"></i>
                                                        </a>
                                                        <a href="https://wa.me/<?= str_replace(['+', ' '], '', $order['customer_phone']) ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-whatsapp"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= date('d.m.Y', strtotime($order['order_date'])) ?>
                                                    <br>
                                                    <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?= $order['item_count'] ?> növ
                                                    </span>
                                                    <br>
                                                    <small class="text-muted"><?= $order['total_quantity'] ?> ədəd</small>
                                                </td>
                                                <td>
                                                    <strong class="fs-6"><?= formatCurrency($order['total_amount']) ?></strong>
                                                    <?php if ($order['discount'] > 0): ?>
                                                        <br>
                                                        <small class="text-success">-<?= formatCurrency($order['discount']) ?> endirim</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'in_production' => 'info', 
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger'
                                                    ];
                                                    $status_texts = [
                                                        'pending' => 'Gözləyir',
                                                        'in_production' => 'İstehsalda',
                                                        'completed' => 'Tamamlandı',
                                                        'cancelled' => 'Ləğv edildi'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $status_colors[$order['status']] ?> order-status">
                                                        <?= $status_texts[$order['status']] ?>
                                                    </span>
                                                    <?php if ($order['delivery_date']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            Təhvil: <?= date('d.m.Y', strtotime($order['delivery_date'])) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= htmlspecialchars($order['sales_person']) ?></small>
                                                    <?php if ($order['store_name']): ?>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($order['store_name']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="order-details.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Təfərrüat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="../pdf/order-receipt.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline-secondary" title="PDF" target="_blank">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </a>
                                                        <?php if ($order['status'] !== 'cancelled'): ?>
                                                            <a href="../pdf/production-order.php?id=<?= $order['id'] ?>" 
                                                               class="btn btn-sm btn-outline-info" title="İstehsalat" target="_blank">
                                                                <i class="bi bi-gear"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Pagination">
                                <ul class="pagination mb-0 justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $total_pages ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>"><?= $total_pages ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bulk Actions Panel -->
    <div class="bulk-actions" id="bulkActions">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><span class="selected-count">0</span> sifariş seçildi</strong>
            <button type="button" class="btn-close btn-close-white" onclick="clearSelection()"></button>
        </div>
        <form method="POST" id="bulkForm">
            <input type="hidden" name="action" value="bulk_update_status">
            <div class="mb-3">
                <label for="bulk_status" class="form-label">Yeni Status</label>
                <select class="form-select form-select-sm" name="bulk_status" required>
                    <option value="">Seçin...</option>
                    <option value="pending">Gözləyir</option>
                    <option value="in_production">İstehsalda</option>
                    <option value="completed">Tamamlandı</option>
                    <option value="cancelled">Ləğv edildi</option>
                </select>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-light btn-sm">
                    <i class="bi bi-check-circle"></i> Status Yenilə
                </button>
                <button type="button" class="btn btn-outline-light btn-sm" onclick="exportSelected()">
                    <i class="bi bi-download"></i> Seçilənləri Export Et
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/sales.js"></script>
    <script>
        // Master checkbox functionality
        document.getElementById('masterCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });

        // Individual checkbox functionality
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateMasterCheckbox();
                updateBulkActions();
            });
        });

        function updateMasterCheckbox() {
            const masterCheckbox = document.getElementById('masterCheckbox');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');

            masterCheckbox.checked = checkboxes.length === checkedBoxes.length;
            masterCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
        }

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.querySelector('.selected-count');

            if (checkedBoxes.length > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = checkedBoxes.length;
                
                // Add selected order IDs to form
                const existingInputs = document.querySelectorAll('input[name="order_ids[]"]');
                existingInputs.forEach(input => input.remove());
                
                checkedBoxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'order_ids[]';
                    input.value = checkbox.value;
                    document.getElementById('bulkForm').appendChild(input);
                });
            } else {
                bulkActions.style.display = 'none';
            }
        }

        function clearSelection() {
            document.querySelectorAll('.order-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('masterCheckbox').checked = false;
            document.getElementById('masterCheckbox').indeterminate = false;
            updateBulkActions();
        }

        function exportOrders() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('?' + params.toString(), '_blank');
        }

        function exportSelected() {
            const checkedBoxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('Zəhmət olmasa ən azı bir sifariş seçin');
                return;
            }
            
            const orderIds = Array.from(checkedBoxes).map(cb => cb.value);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_orders.php';
            form.target = '_blank';
            
            orderIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Auto-refresh order statuses every 2 minutes
        setInterval(function() {
            if (window.salesManager && typeof window.salesManager.updateOrderStatuses === 'function') {
                window.salesManager.updateOrderStatuses();
            }
        }, 120000);

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + A to select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                document.getElementById('masterCheckbox').checked = true;
                document.getElementById('masterCheckbox').dispatchEvent(new Event('change'));
            }
            
            // Escape to clear selection
            if (e.key === 'Escape') {
                clearSelection();
            }
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>