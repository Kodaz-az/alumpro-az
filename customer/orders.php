<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/functions.php';

SessionManager::requireRole('customer');

$user_id = SessionManager::getUserId();
$db = new Database();

// Get customer info
$stmt = $db->query("SELECT * FROM customers WHERE user_id = ?", [$user_id]);
$customer = $stmt->fetch();

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = ["o.customer_id = ?"];
$params = [$customer['id']];

if (!empty($search)) {
    $where_conditions[] = "o.order_number LIKE ?";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "o.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "o.order_date <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_params = $params;
$stmt = $db->query("SELECT COUNT(*) as total FROM orders o $where_clause", $count_params);
$total_orders = $stmt->fetch()['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders
$params[] = $limit;
$params[] = $offset;
$stmt = $db->query("SELECT o.*, s.name as store_name, u.full_name as sales_person,
                           COUNT(oi.id) as item_count
                    FROM orders o 
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN users u ON o.sales_person_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    $where_clause
                    GROUP BY o.id
                    ORDER BY o.created_at DESC 
                    LIMIT ? OFFSET ?", $params);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sifarişlərim - Alumpro.Az</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/customer-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-box text-primary"></i> Sifarişlərim
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="tel:+994501234567" class="btn btn-success">
                                <i class="bi bi-telephone"></i> Yeni Sifariş
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Sifariş nömrəsi</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" placeholder="ALM20250101...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Hamısı</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Gözləyir</option>
                                    <option value="in_production" <?= $status_filter === 'in_production' ? 'selected' : '' ?>>İstehsalda</option>
                                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Ləğv edildi</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Başlanğıc tarix</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Son tarix</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Axtar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Sifarişlər (<?= $total_orders ?> nəticə)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Sifariş №</th>
                                        <th>Tarix</th>
                                        <th>Mağaza</th>
                                        <th>Satış Meneceri</th>
                                        <th>Məhsul Sayı</th>
                                        <th>Məbləğ</th>
                                        <th>Status</th>
                                        <th>Əməliyyat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <p class="text-muted mt-2">Sifariş tapılmadı</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($order['order_date'])) ?></td>
                                                <td><?= htmlspecialchars($order['store_name']) ?></td>
                                                <td><?= htmlspecialchars($order['sales_person']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= $order['item_count'] ?> məhsul</span>
                                                </td>
                                                <td>
                                                    <strong><?= formatCurrency($order['total_amount']) ?></strong>
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
                                                    <span class="badge bg-<?= $status_colors[$order['status']] ?>">
                                                        <?= $status_texts[$order['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="order-details.php?id=<?= $order['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Təfərrüat">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($order['status'] === 'completed'): ?>
                                                            <a href="../pdf/order-receipt.php?id=<?= $order['id'] ?>" 
                                                               class="btn btn-sm btn-outline-secondary" title="PDF" target="_blank">
                                                                <i class="bi bi-file-pdf"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Pagination">
                                <ul class="pagination mb-0 justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>